<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Database\WriteConflict;
use App\Support\ProductionSecurity;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_password_reset_link_uses_configured_origin_even_for_a_foreign_request_host(): void
    {
        config(['app.url' => 'https://clbk.example.test']);
        Notification::fake();
        $user = User::factory()->create();

        $this->post('https://attacker.invalid/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
            $this->assertSame('clbk.example.test', parse_url($url, PHP_URL_HOST));
            $this->assertSame('/reset-password/'.$notification->token, parse_url($url, PHP_URL_PATH));
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email']);

            return true;
        });
    }

    public function test_guest_authentication_endpoints_share_the_ip_request_limit(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/forgot-password', ['email' => 'invalid'])->assertUnprocessable();
        }

        $this->postJson('/reset-password')->assertTooManyRequests();
        $this->postJson('/login')->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.22'])
            ->postJson('/forgot-password', ['email' => 'invalid'])->assertUnprocessable();
    }

    public function test_password_checks_are_limited_per_account_across_routes_and_ip_addresses(): void
    {
        $this->actingAs(User::factory()->create());
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/confirm-password', ['password' => 'incorrect'])->assertUnprocessable();
        }

        $this->putJson('/password')->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.23'])
            ->patchJson('/profile')->assertTooManyRequests();
    }

    public function test_upload_request_limit_is_shared_by_finance_and_sale_proofs(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Cashier]));
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/payments/expense/999999/proofs')->assertNotFound();
        }

        $this->postJson('/factory-sales/999999/proofs')->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.24'])
            ->postJson('/payments/expense/999999/proofs')->assertTooManyRequests();
    }

    public function test_security_headers_are_present_on_regular_and_error_responses(): void
    {
        foreach (['/up' => 200, '/route-that-does-not-exist' => 404] as $path => $status) {
            $this->get($path)->assertStatus($status)
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; object-src 'none'; base-uri 'self'")
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_production_rejects_foreign_hosts_and_http_and_adds_hsts_on_https(): void
    {
        $this->productionConfig();

        $this->get('https://clbk.example.test/up')->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('https://attacker.invalid/up')->assertStatus(400)
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->get('http://clbk.example.test/up')->assertStatus(400)
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_forwarded_https_is_accepted_only_from_an_explicit_trusted_proxy(): void
    {
        $this->productionConfig();
        config(['security.trusted_proxies' => ['192.0.2.10']]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.22'])
            ->get('http://clbk.example.test/up')->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->get('http://clbk.example.test/up')->assertStatus(400);
    }

    #[DataProvider('unsafeProductionSettings')]
    public function test_unsafe_production_configuration_is_rejected(array $settings): void
    {
        $this->productionConfig();
        config($settings);

        $this->expectException(RuntimeException::class);
        app(ProductionSecurity::class)->validate();
    }

    public static function unsafeProductionSettings(): array
    {
        return [
            'debug' => [['app.debug' => true]],
            'http URL' => [['app.url' => 'http://clbk.example.test']],
            'URL credentials' => [['app.url' => 'https://name:password@clbk.example.test']],
            'URL path' => [['app.url' => 'https://clbk.example.test/path']],
            'URL query' => [['app.url' => 'https://clbk.example.test?value=1']],
            'URL fragment' => [['app.url' => 'https://clbk.example.test#value']],
            'insecure cookie' => [['session.secure' => false]],
            'script-readable cookie' => [['session.http_only' => false]],
            'cross-site cookie' => [['session.same_site' => 'none']],
            'log mailer' => [['mail.default' => 'log']],
            'array mailer' => [['mail.default' => 'array']],
            'log fallback' => [['mail.default' => 'failover']],
            'all proxies' => [['security.trusted_proxies' => ['*']]],
            'entire IPv4 space' => [['security.trusted_proxies' => ['0.0.0.0/0']]],
            'entire IPv6 space' => [['security.trusted_proxies' => ['::/0']]],
        ];
    }

    public function test_production_boot_failure_does_not_expose_debug_details(): void
    {
        $this->productionConfig();
        config(['app.debug' => true, 'app.url' => 'http://clbk.example.test']);

        try {
            app(ProductionSecurity::class)->validate();
            $this->fail('Invalid production configuration was accepted.');
        } catch (RuntimeException $exception) {
            $request = Request::create('https://clbk.example.test/up');
            $request->headers->set('Accept', 'application/json');
            $response = app(ExceptionHandler::class)->render($request, $exception);
            $this->assertSame(500, $response->getStatusCode());
            $this->assertSame(['message' => 'Server Error'], json_decode($response->getContent(), true));
            $this->assertFalse(config('app.debug'));
        }
    }

    public function test_production_accepts_safe_mail_delivery_and_cookie_configuration(): void
    {
        $this->productionConfig();
        app(ProductionSecurity::class)->validate();

        config(['mail.default' => 'failover', 'mail.mailers.failover.mailers' => ['smtp', 'ses']]);
        app(ProductionSecurity::class)->validate();
        $this->assertFalse(config('app.debug'));
    }

    public function test_production_database_reports_omit_sql_bindings_and_exception_chains(): void
    {
        $this->productionConfig();
        Log::spy();
        $query = new QueryException('mysql', 'insert into users (email) values (?)',
            ['private@example.test'], new PDOException('driver message with private@example.test'));

        report(new WriteConflict('Server busy.', previous: $query));

        Log::shouldHaveReceived('error')->once()->with('Database operation failed.', [
            'exception_type' => WriteConflict::class,
            'sql_state' => null,
            'driver_code' => null,
        ]);
        Log::shouldNotHaveReceived('log');
    }

    private function productionConfig(): void
    {
        $this->app->instance('env', 'production');
        config([
            'app.url' => 'https://clbk.example.test', 'app.debug' => false,
            'session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax',
            'mail.default' => 'smtp',
            'security.trusted_proxies' => [],
        ]);
    }
}
