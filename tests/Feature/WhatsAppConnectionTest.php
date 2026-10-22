<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\AuditService;
use App\Services\WhatsApp\FonnteClient;
use App\Services\WhatsApp\WhatsAppSettings;
use Database\Seeders\BusinessDefaultsSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class WhatsAppConnectionTest extends TestCase
{
    use DatabaseMigrations;

    private User $manager;

    private const TOKEN = 'fonnte-private-device-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessDefaultsSeeder::class);
        $this->manager = User::factory()->manager()->create();
        $this->actingAs($this->manager);
        config(['whatsapp.enabled' => false, 'whatsapp.token' => '']);
        Http::preventStrayRequests();
    }

    private function saveToken(): void
    {
        $this->putJson(route('whatsapp.connection.update'), ['token' => self::TOKEN])->assertOk();
    }

    public function test_token_is_encrypted_and_never_returned_or_audited(): void
    {
        $this->saveToken();
        $connection = WhatsAppConnection::findOrFail(1);
        $raw = DB::table('whatsapp_connections')->value('token');
        $this->assertNotSame(self::TOKEN, $raw);
        $this->assertStringNotContainsString(self::TOKEN, $raw);
        $this->assertSame(self::TOKEN, $connection->token);
        $this->assertArrayNotHasKey('token', $connection->toArray());
        $this->assertArrayNotHasKey('revision', $connection->toArray());
        $this->assertStringNotContainsString(self::TOKEN, json_encode(app(AuditService::class)->snapshot($connection)));
        $this->assertStringNotContainsString(self::TOKEN, DB::table('audit_logs')->get()->toJson());
        $this->get(route('whatsapp.index'))->assertOk()->assertDontSee(self::TOKEN)->assertDontSee($raw)
            ->assertInertia(fn (Assert $page) => $page->where('connection.has_token', true)->where('connection.status', 'unchecked')->where('configured', false));
        $this->get(route('settings.business.edit'))->assertOk()->assertDontSee(self::TOKEN)->assertDontSee($raw);
        Http::assertNothingSent();
    }

    public static function deviceResults(): array
    {
        return [
            'connected' => [['status' => true, 'device_status' => 'connect', 'device' => '628123456789'], 200, 'connected', true],
            'disconnected' => [['status' => true, 'device_status' => 'disconnect'], 200, 'disconnected', false],
            'invalid token' => [['status' => false, 'reason' => 'token invalid'], 200, 'invalid', false],
            'unknown status' => [['status' => true, 'device_status' => 'unknown'], 200, 'error', false],
            'provider error' => [['error' => 'unavailable'], 503, 'error', false],
            'rate limited' => [['status' => false], 429, 'error', false],
        ];
    }

    #[DataProvider('deviceResults')]
    public function test_connection_check_uses_device_profile_without_sending_messages(array $payload, int $httpStatus, string $status, bool $configured): void
    {
        $this->saveToken();
        Http::fake(['api.fonnte.com/device' => Http::response($payload, $httpStatus)]);
        $this->postJson(route('whatsapp.connection.check'))->assertOk()->assertJsonPath('connection.status', $status)
            ->assertJsonPath('configured', $configured)->assertDontSee(self::TOKEN);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.fonnte.com/device'
            && $request->hasHeader('Authorization', self::TOKEN) && $request->method() === 'POST');
        Http::assertSentCount(1);
        $this->assertSame($status, WhatsAppConnection::findOrFail(1)->status);
        $this->assertFalse(app(WhatsAppSettings::class)->values()['enabled']);
        $activation = $this->patch(route('whatsapp.settings'), ['enabled' => true, 'weighing_enabled' => true, 'payment_enabled' => true]);
        if ($configured) {
            $activation->assertSessionHasNoErrors();
            $this->assertTrue(app(WhatsAppSettings::class)->active('weighing_posted'));
        } else {
            $activation->assertSessionHasErrors('enabled');
        }
    }

    public function test_timeout_is_shown_as_connection_failure(): void
    {
        $this->saveToken();
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->postJson(route('whatsapp.connection.check'))->assertOk()->assertJsonPath('connection.status', 'error')
            ->assertJsonPath('configured', false)->assertDontSee(self::TOKEN);
    }

    public function test_empty_or_invalid_token_is_not_flashed_back_to_session(): void
    {
        $this->putJson(route('whatsapp.connection.update'), ['token' => ''])->assertUnprocessable();
        $invalid = "private token\nwith-spaces";
        $this->put(route('whatsapp.connection.update'), ['token' => $invalid])->assertSessionHasErrors('token');
        $this->assertNull(session()->getOldInput('token'));
        $this->assertDatabaseCount('whatsapp_connections', 0);
        $this->postJson(route('whatsapp.connection.check'))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_only_managers_can_save_or_check_credentials(): void
    {
        foreach ([UserRole::Cashier, UserRole::Driver] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->putJson(route('whatsapp.connection.update'), ['token' => self::TOKEN])->assertForbidden();
            $this->postJson(route('whatsapp.connection.check'))->assertForbidden();
        }
        $this->assertDatabaseCount('whatsapp_connections', 0);
        Http::assertNothingSent();
    }

    public function test_replacing_token_invalidates_connection_and_disables_sending(): void
    {
        $this->saveToken();
        WhatsAppConnection::findOrFail(1)->update(['status' => 'connected', 'checked_at' => now()]);
        AppSetting::updateOrCreate(['key' => 'whatsapp'], ['value_json' => ['enabled' => true, 'weighing_enabled' => true, 'payment_enabled' => true]]);
        $this->assertTrue(app(WhatsAppSettings::class)->active('weighing_posted'));
        $this->putJson(route('whatsapp.connection.update'), ['token' => 'new-private-fonnte-token'])->assertOk()
            ->assertJsonPath('connection.status', 'unchecked')->assertJsonPath('configured', false);
        $this->assertFalse(app(WhatsAppSettings::class)->values()['enabled']);
        $this->assertFalse(app(WhatsAppSettings::class)->active('weighing_posted'));
        $this->assertSame('new-private-fonnte-token', app(WhatsAppSettings::class)->token());
    }

    public function test_connection_result_does_not_overwrite_a_newer_token(): void
    {
        $this->saveToken();
        Http::fake(function () {
            WhatsAppConnection::findOrFail(1)->update(['token' => 'replacement-token', 'revision' => (string) Str::uuid(), 'status' => 'unchecked']);

            return Http::response(['status' => true, 'device_status' => 'connect']);
        });
        $this->postJson(route('whatsapp.connection.check'))->assertConflict();
        $this->assertSame('replacement-token', app(WhatsAppSettings::class)->token());
        $this->assertSame('unchecked', WhatsAppConnection::findOrFail(1)->status);
    }

    public function test_message_sender_uses_the_token_saved_in_settings(): void
    {
        $this->saveToken();
        config(['whatsapp.token' => 'old-environment-token']);
        Http::fake(['api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['345678']])]);
        $result = app(FonnteClient::class)->send('628123456789', 'Tes pengiriman');
        $this->assertSame('accepted', $result['status']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', self::TOKEN));
    }

    public function test_whatsapp_page_is_under_settings_and_old_link_redirects(): void
    {
        $this->get('/settings/whatsapp')->assertOk()->assertInertia(fn (Assert $page) => $page->component('WhatsApp/Index'));
        $this->get('/whatsapp')->assertRedirect('/settings/whatsapp');
    }
}
