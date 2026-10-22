<?php

namespace Tests\Feature;

use App\Actions\Finance\ReverseFinance;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Actions\Weighings\VoidWeighing;
use App\Enums\FinanceKind;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppMessage;
use App\Services\BackupService;
use App\Services\CustomerSettlement;
use App\Services\WhatsApp\NotificationOutbox;
use App\Services\WhatsApp\SendMessage;
use App\Services\WhatsApp\WhatsAppSettings;
use Carbon\Carbon;
use Database\Seeders\BusinessDefaultsSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\Concerns\LegacyCustomerPayments;
use Tests\TestCase;

class WhatsAppNotificationsTest extends TestCase
{
    use DatabaseMigrations;
    use LegacyCustomerPayments;

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
        $this->seed(BusinessDefaultsSeeder::class);
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

    public function test_notifications_are_available_but_disabled_on_a_fresh_installation(): void
    {
        $this->assertNotificationSchema();
        $this->assertFalse(app(WhatsAppSettings::class)->values()['enabled']);
        $this->assertFalse($this->customer->fresh()->whatsapp_enabled);
        $this->assertSame('08123456789', $this->customer->fresh()->phone);
        $this->assertForeignKeysAreValid();
    }

    public function test_notification_routes_enforce_roles_and_never_expose_tokens(): void
    {
        config(['whatsapp.token' => 'secret-fonnte-token']);
        $this->assertArrayHasKey('whatsapp:dispatch', Artisan::all());
        $this->actingAs($this->manager)->get(route('whatsapp.index'))->assertOk()->assertDontSee('secret-fonnte-token')
            ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
        $this->actingAs($this->cashier)->get(route('whatsapp.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        $this->patch(route('whatsapp.settings'), ['enabled' => false, 'weighing_enabled' => true, 'payment_enabled' => true])->assertForbidden();
        $this->post(route('whatsapp.test'), ['target' => '08123456789'])->assertForbidden();
        $driver = User::factory()->create(['role' => UserRole::Driver]);
        $this->actingAs($driver)->get(route('whatsapp.index'))->assertForbidden();
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
        $this->assertNotificationSchema();
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

    private function assertNotificationSchema(): void
    {
        foreach (['whatsapp_webhook_events', 'whatsapp_attempts', 'whatsapp_messages', 'whatsapp_connections'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('customers', 'whatsapp_number'));
        $this->assertTrue(Schema::hasColumn('customers', 'whatsapp_enabled'));
    }

    private function enableWhatsApp(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.token' => 'test-token', 'whatsapp.webhook_secret' => str_repeat('b', 64)]);
        AppSetting::updateOrCreate(['key' => 'whatsapp'], ['value_json' => [
            'enabled' => true, 'weighing_enabled' => true, 'payment_enabled' => true,
        ]]);
        $this->customer->update(['whatsapp_enabled' => true]);
    }

    private function successResponse(): void
    {
        Http::fake(['api.fonnte.com/send' => Http::response(['status' => true, 'process' => 'pending', 'id' => ['123456']])]);
    }

    private function sendMessage(WhatsAppMessage $message): void
    {
        app(SendMessage::class)->send($message->id, $message->uuid);
    }

    public function test_weighing_is_queued_once_without_any_prices_or_purchase_amounts(): void
    {
        $this->enableWhatsApp();
        $data = ['customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id, 'tbs_kg' => '1000',
            'sort_kg' => '100', 'worker_ids' => [$this->worker->id], 'price_schedule_id' => $this->price->id,
            'idempotency_key' => (string) Str::uuid()];
        $action = app(SaveDirectWeighing::class);
        $record = $action->handle($this->manager, $data);
        $this->assertSame($record->id, $action->handle($this->manager, $data)->id);
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertDatabaseCount('jobs', 1);
        $message = WhatsAppMessage::sole();
        $this->assertSame('628123456789', $message->target);
        foreach (['Budi Konsumen', $record->number, '09-10-2026 10:00 WIB', '1.000,000 kg', '100,000 kg'] as $text) {
            $this->assertStringContainsString($text, $message->body);
        }
        foreach (['Rp', 'harga', 'pembelian', '3.200.000', '3.000', '2.000'] as $text) {
            $this->assertStringNotContainsString($text, $message->body);
        }
        Http::assertNothingSent();
        $this->successResponse();
        $this->artisan('queue:work', ['connection' => 'whatsapp', '--queue' => 'whatsapp', '--once' => true])->assertSuccessful();
        $this->assertSame('accepted', $message->fresh()->status);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'test-token')
            && $request['target'] === '628123456789' && $request['countryCode'] === '0'
            && ! str_contains($request['message'], 'Rp'));
        $this->sendMessage($message);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('whatsapp_attempts', 1);
        $this->actingAs($this->cashier)->getJson(route('whatsapp.show', $message))->assertOk()
            ->assertJsonPath('attempts.0.created_at', '2026-10-09T03:00:00+00:00');
    }

    public function test_one_payment_notification_covers_all_settled_tickets(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $this->weigh('500', '0');
        $data = $this->payment();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('customer-payments.store'), $data)->assertSessionHasNoErrors();
        $message = WhatsAppMessage::where('event', 'payment_posted')->sole();
        $this->assertStringContainsString('Rp 4.700.000', $message->body);
        $this->assertStringContainsString('Jumlah tiket: 2', $message->body);
        $this->assertStringContainsString('Metode: Tunai', $message->body);
        $this->assertSame(1, WhatsAppMessage::where('event', 'payment_posted')->count());
        $this->assertSame(0, app(CustomerSettlement::class)->total($this->customer->id));
        Http::assertNothingSent();
    }

    public function test_rollback_discards_outbox_and_never_dispatches_a_job(): void
    {
        $record = $this->weigh();
        $this->enableWhatsApp();
        try {
            DB::transaction(function () use ($record) {
                app(NotificationOutbox::class)->weighing($record->fresh());
                throw new \RuntimeException('Rollback test');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('Rollback test', $error->getMessage());
        }
        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_numbers_and_opt_out_do_not_block_business_transactions(): void
    {
        $this->enableWhatsApp();
        $this->customer->update(['phone' => '08123']);
        $this->assertSame('posted', $this->weigh()->status);
        $this->assertSame('skipped', WhatsAppMessage::sole()->status);
        $this->customer->update(['phone' => '08123456789', 'whatsapp_enabled' => false]);
        $this->assertSame('posted', $this->weigh()->status);
        $this->assertSame(2, WhatsAppMessage::where('status', 'skipped')->count());
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }

    public function test_phone_changes_and_opt_out_are_rechecked_before_sending(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $first = WhatsAppMessage::sole();
        $this->customer->update(['phone' => '081234567890']);
        $this->sendMessage($first);
        $this->assertSame('skipped', $first->fresh()->status);
        $this->weigh();
        $second = WhatsAppMessage::latest('id')->first();
        $this->customer->update(['whatsapp_enabled' => false]);
        $this->sendMessage($second);
        $this->assertSame('skipped', $second->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_timeout_is_uncertain_and_cannot_be_retried_blindly(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $message = WhatsAppMessage::sole();
        Http::fake(fn () => throw new ConnectionException('May have been accepted'));
        $this->sendMessage($message);
        $this->assertSame('uncertain', $message->fresh()->status);
        $this->actingAs($this->manager)->post(route('whatsapp.retry', $message))->assertSessionHasErrors('operation');
        $this->sendMessage($message);
        $this->assertSame(1, $message->fresh()->attempts);
    }

    public function test_explicit_disconnect_uses_bounded_backoff_without_blocking_transactions(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $message = WhatsAppMessage::sole();
        Http::fake(['api.fonnte.com/send' => Http::response(['status' => false, 'reason' => 'device disconnected'])]);
        for ($i = 1; $i <= 4; $i++) {
            $this->sendMessage($message->fresh());
            $this->assertSame($i < 4 ? 'retrying' : 'failed', $message->fresh()->status);
            if ($i < 4) {
                $this->travel(16)->minutes();
            }
        }
        $this->assertDatabaseCount('whatsapp_attempts', 4);
        Http::assertSentCount(4);
    }

    public function test_retry_does_not_resend_messages_already_accepted_by_fonnte(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $message = WhatsAppMessage::sole();
        Http::fake(['api.fonnte.com/send' => Http::sequence()
            ->push(['status' => false, 'reason' => 'insufficient quota'])
            ->push(['status' => true, 'process' => 'pending', 'id' => ['123456']])]);
        $this->sendMessage($message);
        $this->assertSame('failed', $message->fresh()->status);
        $this->actingAs($this->manager)->post(route('whatsapp.retry', $message))->assertSessionHasNoErrors();
        $this->sendMessage($message->fresh());
        $this->assertSame('accepted', $message->fresh()->status);
        $this->post(route('whatsapp.retry', $message))->assertSessionHasErrors('operation');
    }

    public function test_cancellation_stops_pending_results_and_queues_a_correction_without_prices(): void
    {
        $this->enableWhatsApp();
        $record = $this->weigh();
        $message = WhatsAppMessage::sole();
        app(VoidWeighing::class)->handle($this->manager, $record, [
            'version' => $record->version, 'reason' => 'Koreksi berat', 'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertSame('cancelled', $message->fresh()->status);
        $correction = WhatsAppMessage::where('event', 'weighing_voided')->sole();
        $this->assertStringContainsString($record->number, $correction->body);
        $this->assertStringNotContainsString('Rp', $correction->body);
        $this->sendMessage($message);
        Http::assertNothingSent();
    }

    public function test_payment_reversal_cancels_pending_confirmation(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment())->assertSessionHasNoErrors();
        $payment = CustomerPayment::sole();
        app(ReverseFinance::class)->handle($this->manager, FinanceKind::Customer, $payment, [
            'version' => $payment->version, 'reason' => 'Koreksi pembayaran', 'return_reference' => 'Uang kembali',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertSame('cancelled', WhatsAppMessage::where('event', 'payment_posted')->sole()->status);
        $this->assertStringContainsString('PEMBATALAN PEMBAYARAN', WhatsAppMessage::where('event', 'payment_reversed')->sole()->body);
    }

    public function test_webhooks_are_authenticated_idempotent_and_do_not_regress_delivery_state(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $message = WhatsAppMessage::sole();
        $this->successResponse();
        $this->sendMessage($message);
        $url = '/integrations/fonnte/webhook/'.str_repeat('b', 64);
        $payload = ['id' => '123456', 'stateid' => 'state-1', 'status' => 'sent', 'state' => 'read'];
        $this->postJson('/integrations/fonnte/webhook/'.str_repeat('a', 64), $payload)->assertNotFound();
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
        $this->assertSame('read', $message->fresh()->status);
        $this->postJson($url, ['stateid' => 'state-1', 'state' => 'delivered'])->assertOk();
        $this->assertSame('read', $message->fresh()->status);
        $this->postJson($url, ['id' => '123456', 'status' => 'failed'])->assertOk();
        $this->assertSame('read', $message->fresh()->status);
        config(['whatsapp.device' => '628999999999']);
        $this->postJson($url, $payload + ['device' => '628111111111'])->assertForbidden();
    }

    public function test_early_webhooks_are_reconciled_after_the_send_response(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $message = WhatsAppMessage::sole();
        $this->postJson('/integrations/fonnte/webhook/'.str_repeat('b', 64), [
            'id' => '123456', 'status' => 'sent', 'state' => 'read', 'stateid' => 'early-state',
        ])->assertOk();
        $this->successResponse();
        $this->sendMessage($message);
        $this->assertSame('read', $message->fresh()->status);
        $this->assertNotNull(DB::table('whatsapp_webhook_events')->sole()->processed_at);
    }

    public function test_scheduler_recovers_unscheduled_messages_and_quarantines_stale_processing(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        DB::table('jobs')->delete();
        $message = WhatsAppMessage::sole();
        $message->update(['queued_at' => null]);
        $this->artisan('whatsapp:dispatch')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('whatsapp:dispatch')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $message->update(['status' => 'processing', 'processing_at' => now()->subMinutes(4)]);
        $this->artisan('whatsapp:dispatch')->assertSuccessful();
        $this->assertSame('uncertain', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_settings_opt_in_validation_and_test_message_are_safe(): void
    {
        $this->actingAs($this->manager)->patch(route('whatsapp.settings'), [
            'enabled' => true, 'weighing_enabled' => true, 'payment_enabled' => true,
        ])->assertSessionHasErrors('enabled');
        $this->enableWhatsApp();
        $this->patch(route('master-data.update', ['customers', $this->customer]), [
            'name' => $this->customer->name, 'phone' => '08123', 'is_active' => true, 'whatsapp_enabled' => true,
        ])->assertSessionHasErrors('phone');
        $key = (string) Str::uuid();
        $this->post(route('whatsapp.test'), ['target' => '+62 812-3456-789', 'idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->post(route('whatsapp.test'), ['target' => '+62 812-3456-789', 'idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertSame('628123456789', WhatsAppMessage::sole()->target);
        Http::assertNothingSent();
    }

    public function test_restore_quarantines_historical_outbox_and_disables_sending(): void
    {
        $this->enableWhatsApp();
        $connection = new WhatsAppConnection;
        $connection->id = 1;
        $connection->fill(['token' => 'test-token', 'revision' => (string) Str::uuid(), 'status' => 'connected', 'checked_at' => now()])->save();
        $this->weigh();
        $message = WhatsAppMessage::sole();
        $directory = sys_get_temp_dir().'/clbk-wa-restore-'.Str::uuid();
        config(['backups.path' => $directory, 'backups.minimum_free_bytes' => 0,
            'backups.require_separate_disk' => false, 'session.driver' => 'database']);
        Storage::fake('local');
        config(['filesystems.disks.local.root' => Storage::disk('local')->path('')]);
        try {
            $service = app(BackupService::class);
            $backup = $service->create($this->manager);
            $this->successResponse();
            $this->sendMessage($message);
            $this->assertSame('accepted', $message->fresh()->status);
            $this->app->maintenanceMode()->activate(['time' => time()]);
            $service->restore($service->path($backup['id']));
            $this->assertFalse(app(WhatsAppSettings::class)->values()['enabled']);
            $this->assertSame('unchecked', $connection->fresh()->status);
            $this->assertNull($connection->fresh()->checked_at);
            $this->assertSame('test-token', $connection->fresh()->token);
            $this->assertSame('held', $message->fresh()->status);
            $this->assertDatabaseCount('jobs', 0);
            $this->app->maintenanceMode()->deactivate();
            $this->sendMessage($message);
            $this->assertSame('held', $message->fresh()->status);
            Http::assertSentCount(1);
        } finally {
            $this->app->maintenanceMode()->deactivate();
            File::deleteDirectory($directory);
        }
    }

    public function test_numeric_webhook_state_is_saved_without_claiming_delivery(): void
    {
        $this->enableWhatsApp();
        $this->weigh();
        $message = WhatsAppMessage::sole();
        $this->successResponse();
        $this->sendMessage($message);
        $this->postJson('/integrations/fonnte/webhook/'.str_repeat('b', 64), [
            'id' => 123456, 'status' => 'sent', 'state' => 0, 'stateid' => 'numeric-state',
        ])->assertOk();
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertStringContainsString('belum diterima', $message->fresh()->reason);
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
