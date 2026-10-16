<?php

namespace Tests\Feature;

use App\Actions\Weighings\SaveDirectWeighing;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Services\CustomerSettlement;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Concerns\DatabaseMigrations;
use Tests\Concerns\LegacyCustomerPayments;
use Tests\TestCase;

class WhatsAppRemovalTest extends TestCase
{
    use DatabaseMigrations;
    use LegacyCustomerPayments;

    private const REMOVED_TABLES = [
        'whatsapp_webhook_events', 'whatsapp_attempts', 'whatsapp_messages', 'pickup_notices', 'whatsapp_settings',
    ];

    private User $manager;

    private User $cashier;

    private Customer $customer;

    private CashAccount $cash;

    private Vehicle $vehicle;

    private LoadingWorker $worker;

    private PriceSchedule $price;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->seed();
        $this->manager = User::factory()->manager()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->cash = CashAccount::firstOrFail();
        $this->customer = Customer::create(['code' => 'K1', 'name' => 'Budi Konsumen', 'phone' => '08123456789']);
        $this->vehicle = Vehicle::create(['code' => 'M1', 'plate_number' => 'BK 1000']);
        $this->worker = LoadingWorker::create(['code' => 'A1', 'name' => 'Anggota']);
        $this->price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $this->actingAs($this->manager)->post(route('cash.store'), ['kind' => 'opening', 'amount_idr' => 20000000,
            'occurred_at' => '2026-10-09T09:00:00', 'reason' => 'Modal', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    }

    public function test_fresh_database_has_no_notification_tables_or_customer_notification_fields(): void
    {
        $this->assertNotificationSchemaRemoved();
        $this->assertSame('08123456789', $this->customer->fresh()->phone);
        $this->assertEmpty(DB::select('PRAGMA foreign_key_check'));
    }

    public function test_notification_routes_and_dispatch_command_are_removed(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('whatsapp', strtolower($route->getName() ?? ''));
            $this->assertStringNotContainsString('whatsapp', strtolower($route->uri()));
            $this->assertStringNotContainsString('fonnte', strtolower($route->uri()));
        }
        $this->assertArrayNotHasKey('whatsapp:dispatch', Artisan::all());
        $this->actingAs($this->manager)->get('/whatsapp')->assertNotFound();
        $this->get('/settings/whatsapp')->assertNotFound();
        $this->post('/integrations/fonnte/webhook/'.str_repeat('a', 64), [])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_weighing_and_full_settlement_work_without_any_external_request(): void
    {
        $first = $this->weigh();
        $this->legacyCustomerPayment($this->cashier, $this->cash, [$first->id => 1000000]);
        $second = $this->weigh('500', '0');
        $this->assertSame('posted', $second->status);
        $this->assertSame(1500000, $second->total_purchase_idr);

        foreach ([$this->cashier, $this->manager] as $actor) {
            $this->actingAs($actor)->get(route('customer-payments.payee', [$this->customer->id, 'from' => '2026-11-01']))
                ->assertInertia(fn (Assert $page) => $page->has('rows.data', 0)
                    ->where('customerSettlement.remaining_idr', 3700000)->where('customerSettlement.ticket_count', 2));
        }
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment(['amount_idr' => 1000000]))
            ->assertSessionHasErrors('amount_idr');
        $data = $this->payment(['selected_ids' => [$second->id]]);
        $this->post(route('customer-payments.store'), $data)->assertSessionHasNoErrors();
        $payment = CustomerPayment::latest('id')->firstOrFail();
        $this->assertSame(3700000, $payment->amount_idr);
        $this->assertSame(0, app(CustomerSettlement::class)->total($this->customer->id));
        $this->assertSame(2, DB::table('customer_payment_allocations')->where('customer_payment_id', $payment->id)->count());
        $this->post(route('customer-payments.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_payments', 2);
        $this->assertDatabaseHas('cash_entries', ['customer_payment_id' => $payment->id, 'direction' => 'out', 'amount_idr' => 3700000]);
        $this->assertNotificationSchemaRemoved();
        Http::assertNothingSent();
    }

    public function test_full_settlement_still_covers_more_than_two_hundred_tickets(): void
    {
        for ($i = 0; $i < 201; $i++) {
            $this->weigh('1', '0');
        }
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment())->assertSessionHasNoErrors();
        $this->assertSame(0, app(CustomerSettlement::class)->total($this->customer->id));
        $this->assertDatabaseCount('customer_payment_allocations', 201);
        $this->assertDatabaseCount('customer_payments', 1);
        Http::assertNothingSent();
    }

    public function test_upgrade_removes_old_notification_data_and_preserves_business_data_and_sqlite_guards(): void
    {
        $this->weigh();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment())->assertSessionHasNoErrors();
        DB::table('audit_logs')->insert(['actor_id' => $this->manager->id, 'action' => 'whatsapp-test',
            'entity_type' => 'whatsapp_settings', 'entity_id' => '1', 'after_json' => '{"enabled":true}', 'occurred_at' => now()]);
        $beforeData = $this->businessData();
        $beforeSchema = $this->businessSchema();
        $beforeSequences = DB::table('sqlite_sequence')->whereNotIn('name', self::REMOVED_TABLES)->orderBy('name')->get()->toJson();

        $oldMigration = require database_path('migrations/2026_10_15_000001_add_whatsapp_notifications.php');
        $oldMigration->up();
        DB::table('customers')->where('id', $this->customer->id)->update(['whatsapp_number' => '628123456789', 'whatsapp_enabled' => true]);
        DB::table('whatsapp_settings')->insert(['id' => 1, 'enabled' => true, 'token' => 'old-encrypted-token']);
        $message = DB::table('whatsapp_messages')->insertGetId(['event_key' => 'weighing:1', 'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name, 'target' => '628123456789', 'kind' => 'weighing',
            'source_type' => 'weighing', 'source_id' => 1, 'body' => 'Pesan lama']);
        DB::table('whatsapp_attempts')->insert(['whatsapp_message_id' => $message, 'status' => 'submitted', 'created_at' => now()]);
        DB::table('whatsapp_webhook_events')->insert(['event_hash' => str_repeat('a', 64), 'created_at' => now()]);

        $migration = require database_path('migrations/2026_10_16_000001_remove_whatsapp_notifications.php');
        $migration->up();

        $this->assertNotificationSchemaRemoved();
        $this->assertSame($beforeData, $this->businessData());
        $this->assertSame($beforeSchema, $this->businessSchema());
        $this->assertSame($beforeSequences, DB::table('sqlite_sequence')->whereNotIn('name', self::REMOVED_TABLES)->orderBy('name')->get()->toJson());
        $this->assertEmpty(DB::select('PRAGMA foreign_key_check'));
        $this->assertSame('ok', DB::selectOne('PRAGMA integrity_check')->integrity_check);
        try {
            DB::table('customers')->insert(['code' => 'INVALID', 'name' => 'Invalid', 'is_active' => 2]);
            $this->fail('The original customer CHECK constraint must remain enforced.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('CHECK constraint failed', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_notification_removal_refuses_to_recreate_deleted_data_on_rollback(): void
    {
        $migration = require database_path('migrations/2026_10_16_000001_remove_whatsapp_notifications.php');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Penghapusan WhatsApp tidak dapat dibatalkan');
        $migration->down();
    }

    private function assertNotificationSchemaRemoved(): void
    {
        foreach (self::REMOVED_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table.' should be removed.');
        }
        $this->assertFalse(Schema::hasColumn('customers', 'whatsapp_number'));
        $this->assertFalse(Schema::hasColumn('customers', 'whatsapp_enabled'));
    }

    private function businessData(): array
    {
        $data = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name") as $table) {
            if (! in_array($table->name, [...self::REMOVED_TABLES, 'migrations', 'sqlite_sequence'], true)) {
                $data[$table->name] = DB::table($table->name)->orderByRaw('rowid')->get()->toJson();
            }
        }

        return $data;
    }

    private function businessSchema(): string
    {
        return collect(DB::select("SELECT type, name, tbl_name, sql FROM sqlite_master WHERE type IN ('index', 'trigger') ORDER BY type, name"))
            ->reject(fn ($object) => in_array($object->tbl_name, self::REMOVED_TABLES, true))->values()->toJson();
    }

    private function weigh(string $tbs = '1000', string $sort = '100'): Weighing
    {
        return app(SaveDirectWeighing::class)->handle($this->manager, ['customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id, 'tbs_kg' => $tbs, 'sort_kg' => $sort, 'worker_ids' => [$this->worker->id],
            'price_schedule_id' => $this->price->id, 'idempotency_key' => (string) Str::uuid()]);
    }

    private function payment(array $extra = []): array
    {
        $amount = app(CustomerSettlement::class)->total($this->customer->id);

        return $extra + ['recipient_id' => $this->customer->id, 'amount_idr' => $amount, 'expected_remaining_idr' => $amount,
            'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00', 'idempotency_key' => (string) Str::uuid()];
    }
}
