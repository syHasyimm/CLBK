<?php

namespace Tests\Feature\Auth;

use App\Domain\Pricing\PriceSnapshotResolver;
use App\Models\Customer;
use App\Models\LoadingWorker;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\BusinessClock;
use Database\Seeders\WeighingDateFilterSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class WeighingSessionTest extends TestCase
{
    use DatabaseMigrations;

    private function nextRequest(TestResponse $response): void
    {
        // Use the encrypted cookies a browser received, without encrypting them a second time.
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
        // Do not let a cached guard or in-memory session hide a broken storage roundtrip.
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        app()->forgetInstance('redirect');
    }

    public function test_database_session_survives_create_cancel_filter_validation_and_save_redirects(): void
    {
        $this->seed();
        config([
            'app.url' => 'https://clbk.example.test',
            'session.driver' => 'database', 'session.encrypt' => true,
            'session.secure' => true, 'session.http_only' => true,
            'session.path' => '/', 'session.domain' => null, 'session.same_site' => 'lax',
        ]);
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $driver = User::where('email', 'supir@clbk.test')->sole();
        $response = $this->post('https://clbk.example.test/login', ['email' => $driver->email, 'password' => 'ClbkToke12345!']);
        $response->assertRedirect('/dashboard');
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $sessionId = session()->getId();
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $driver->id]);
        $this->assertStringNotContainsString($driver->email, base64_decode(DB::table('sessions')->where('id', $sessionId)->value('payload')));

        foreach (['/dashboard', '/weighings/create', '/weighings', '/weighings?from=2026-10-10&to=2026-10-12'] as $path) {
            $this->nextRequest($response);
            $response = $this->get('https://clbk.example.test'.$path);
            $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $driver->id));
            $this->assertSame($sessionId, session()->getId());
        }
        $response->assertInertia(fn (Assert $page) => $page->where('weighings.total', 3));

        $this->nextRequest($response);
        $response = $this->from('https://clbk.example.test/weighings/create')->post('https://clbk.example.test/weighings', []);
        $response->assertRedirect('https://clbk.example.test/weighings/create')->assertSessionHasErrors('customer_id');
        $this->nextRequest($response);
        $response = $this->get('https://clbk.example.test/weighings/create');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $driver->id));

        $this->nextRequest($response);
        $response = $this->withCredentials()->postJson('https://clbk.example.test/weighings/preview', ['tbs_kg' => '100', 'sort_kg' => '10', 'worker_ids' => []]);
        $response->assertOk();
        $price = app(PriceSnapshotResolver::class)->resolve(app(BusinessClock::class)->now());
        $this->nextRequest($response);
        $response = $this->post('https://clbk.example.test/weighings', [
            'customer_id' => Customer::where('code', WeighingDateFilterSeeder::CUSTOMER_CODE)->value('id'),
            'vehicle_id' => Vehicle::where('code', 'UAT-DATE-M01')->value('id'),
            'vehicle_assignment_version' => $driver->vehicle_assignment_version,
            'tbs_kg' => '100', 'sort_kg' => '10',
            'worker_ids' => [LoadingWorker::where('code', 'UAT-DATE-W01')->value('id')],
            'price_schedule_id' => $price->id, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect('https://clbk.example.test/weighings');
        $this->nextRequest($response);
        $response = $this->get('https://clbk.example.test/weighings');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $driver->id)->where('weighings.total', 6));
        $this->assertSame($sessionId, session()->getId());

        $this->nextRequest($response);
        $response = $this->post('https://clbk.example.test/logout');
        $response->assertRedirect('/');
        $this->nextRequest($response);
        $this->get('https://clbk.example.test/weighings/create')->assertRedirect('https://clbk.example.test/login');
    }
}
