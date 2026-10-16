<?php

namespace Tests\Feature;

use App\Actions\Accounts\SaveUser;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Models\Customer;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\SortSale;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Queries\AuditChanges;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class DriverVehicleAssignmentTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
            DB::disconnect();
        });
    }

    private User $manager;

    private User $driver;

    private Vehicle $first;

    private Vehicle $second;

    private Customer $customer;

    private LoadingWorker $worker;

    private PriceSchedule $price;

    private Factory $factory;

    private FactoryPriceSchedule $factoryPrice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->manager = User::factory()->manager()->create();
        $this->driver = User::factory()->create();
        $this->first = Vehicle::create(['code' => 'M1', 'plate_number' => 'BK MOBIL A', 'is_active' => true]);
        $this->second = Vehicle::create(['code' => 'M2', 'plate_number' => 'BK MOBIL B', 'is_active' => true]);
        $this->customer = Customer::create(['code' => 'K', 'name' => 'Konsumen', 'is_active' => true]);
        $this->worker = LoadingWorker::create(['code' => 'A', 'name' => 'Anggota', 'is_active' => true]);
        $this->price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $this->factory = Factory::create(['code' => 'P', 'name' => 'Pabrik', 'is_active' => true]);
        $this->factoryPrice = FactoryPriceSchedule::create(['factory_id' => $this->factory->id, 'effective_from' => '2026-10-08 17:00:00', 'tbs_sell_price_idr' => 3500, 'published_by' => $this->manager->id]);
    }

    private function assign(?Vehicle $vehicle): User
    {
        return app(SaveUser::class)->handle($this->manager, $this->driver->id, ['assigned_vehicle_id' => $vehicle?->id]);
    }

    private function account(array $changes = []): array
    {
        return array_replace(['name' => $this->driver->name, 'email' => $this->driver->email, 'role' => 'driver',
            'is_active' => true, 'password' => '', 'password_confirmation' => '', 'assigned_vehicle_id' => $this->first->id], $changes);
    }

    private function payload(string $kind, array $changes = []): array
    {
        $user = $this->driver->fresh();

        return array_replace(['vehicle_id' => $user->assigned_vehicle_id ?? $this->first->id,
            'vehicle_assignment_version' => $user->vehicle_assignment_version, 'idempotency_key' => (string) Str::uuid()]
            + match ($kind) {
                'weighings' => ['customer_id' => $this->customer->id, 'tbs_kg' => '100', 'sort_kg' => '10', 'worker_ids' => [$this->worker->id], 'price_schedule_id' => $this->price->id],
                'factory' => ['factory_id' => $this->factory->id, 'accepted_kg' => '100', 'factory_price_schedule_id' => $this->factoryPrice->id, 'deduction_idr' => 0],
                'sort' => ['sold_kg' => '10', 'sell_price_snapshot_idr' => 2000],
            }, $changes);
    }

    private function routeName(string $kind, string $action = 'store'): string
    {
        return ($kind === 'weighings' ? 'weighings' : $kind.'-sales').'.'.$action;
    }

    private function model(string $kind): string
    {
        return match ($kind) {
            'weighings' => Weighing::class, 'factory' => FactorySale::class, 'sort' => SortSale::class
        };
    }

    public function test_manager_assigns_a_single_vehicle_and_lists_it_with_audited_changes(): void
    {
        $this->actingAs($this->manager)->patch(route('users.update', $this->driver), $this->account())->assertSessionHasNoErrors();
        $this->assertSame($this->first->id, $this->driver->fresh()->assigned_vehicle_id);
        $this->assertSame(2, $this->driver->fresh()->vehicle_assignment_version);
        $this->get(route('users.edit', $this->driver))->assertInertia(fn (Assert $p) => $p
            ->where('record.assigned_vehicle_id', $this->first->id)->has('vehicles', 2)->missing('record.password'));
        $this->get(route('users.index'))->assertInertia(fn (Assert $p) => $p
            ->where('records.data', fn ($rows) => collect($rows)->firstWhere('id', $this->driver->id)['assigned_vehicle'] === 'BK MOBIL A'));
        $audit = DB::table('audit_logs')->latest('id')->first();
        $this->assertStringContainsString('ID mobil penugasan supir', app(AuditChanges::class)->describe($audit->before_json, $audit->after_json));
        $this->assertStringNotContainsString('password', $audit->before_json.$audit->after_json);
        $this->patch(route('users.update', $this->driver), $this->account(['name' => 'Nama diperbarui']))->assertSessionHasNoErrors();
        $this->assertSame(2, $this->driver->fresh()->vehicle_assignment_version);
        $this->patch(route('users.update', $this->driver), $this->account(['assigned_vehicle_id' => $this->second->id]))->assertSessionHasNoErrors();
        $this->assertSame($this->second->id, $this->driver->fresh()->assigned_vehicle_id);
        $this->assertSame(3, $this->driver->fresh()->vehicle_assignment_version);
    }

    public function test_new_driver_can_be_created_with_assignment_and_client_cannot_set_its_version(): void
    {
        $this->actingAs($this->manager)->post(route('users.store'), $this->account([
            'email' => 'supir-baru@example.com', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123',
            'vehicle_assignment_version' => 999,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('users.index'));
        $user = User::where('email', 'supir-baru@example.com')->sole();
        $this->assertSame($this->first->id, $user->assigned_vehicle_id);
        $this->assertSame(1, $user->vehicle_assignment_version);
        $this->assign($this->first);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_assignment_is_only_managed_by_manager_and_rejects_unavailable_vehicles(): void
    {
        foreach ([$this->driver, User::factory()->cashier()->create()] as $actor) {
            $this->actingAs($actor)->patch(route('users.update', $this->driver), $this->account())->assertForbidden();
        }
        $this->actingAs($this->driver)->patch(route('profile.update'), ['name' => $this->driver->name, 'email' => $this->driver->email, 'assigned_vehicle_id' => $this->first->id])->assertSessionHasNoErrors();
        $this->assertNull($this->driver->fresh()->assigned_vehicle_id);
        $this->actingAs($this->manager)->patch(route('users.update', $this->driver), $this->account(['assigned_vehicle_id' => 999999]))->assertSessionHasErrors('assigned_vehicle_id');
        $this->second->update(['is_active' => false]);
        $this->patch(route('users.update', $this->driver), $this->account(['assigned_vehicle_id' => $this->second->id]))->assertSessionHasErrors('assigned_vehicle_id');
        $this->patch(route('users.update', $this->driver), $this->account(['role' => 'cashier']))->assertSessionHasErrors('assigned_vehicle_id');
        $this->assign($this->first);
        $this->delete(route('master-data.destroy', ['resource' => 'vehicles', 'record' => $this->first->id]))->assertSessionHasErrors('operation');
        $this->assertDatabaseHas('vehicles', ['id' => $this->first->id]);
        $this->patch(route('users.update', $this->driver), $this->account(['role' => 'cashier', 'assigned_vehicle_id' => null]))->assertSessionHasNoErrors();
        $this->assertNull($this->driver->fresh()->assigned_vehicle_id);
    }

    public function test_driver_forms_always_use_assigned_vehicle_and_picker_cannot_return_other_vehicles(): void
    {
        $this->assign($this->first);
        $this->actingAs($this->driver);
        foreach (['weighings', 'factory', 'sort'] as $kind) {
            $this->get(route($this->routeName($kind, 'create'), ['vehicle_id' => $this->second->id]))->assertInertia(fn (Assert $p) => $p
                ->where('selectedVehicle.id', $this->first->id)->where('vehicleAssignment.version', 2)->where('vehicleAssignment.error', null));
        }
        $this->getJson(route('operational-references.index', 'vehicles'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->first->id);
        $this->getJson(route('operational-references.index', ['kind' => 'vehicles', 'q' => 'MOBIL B']))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->manager)->get(route('weighings.create', ['vehicle_id' => $this->second->id]))->assertInertia(fn (Assert $p) => $p
            ->where('selectedVehicle.id', $this->second->id)->where('vehicleAssignment', null));
    }

    public function test_unassigned_driver_can_access_dashboard_but_cannot_create_any_document(): void
    {
        $this->actingAs($this->driver)->get(route('dashboard'))->assertOk();
        foreach (['weighings', 'factory', 'sort'] as $kind) {
            $this->get(route($this->routeName($kind, 'create')))->assertInertia(fn (Assert $p) => $p
                ->where('selectedVehicle', null)->where('vehicleAssignment.error', 'Mobil belum ditetapkan. Hubungi admin.'));
            $this->post(route($this->routeName($kind)), $this->payload($kind))->assertSessionHasErrors('vehicle_id');
            $this->assertDatabaseCount($this->model($kind)::make()->getTable(), 0);
        }
        $this->assertDatabaseCount('idempotency_requests', 0);
        $this->assertDatabaseCount('document_sequences', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_all_new_documents_use_assignment_and_history_access_still_follows_creator(): void
    {
        $this->assign($this->first);
        $other = User::factory()->create(['assigned_vehicle_id' => $this->first->id]);
        foreach (['weighings', 'factory', 'sort'] as $kind) {
            $this->actingAs($this->driver)->post(route($this->routeName($kind)), $this->payload($kind, ['created_by' => $this->manager->id]))->assertSessionHasNoErrors();
            $record = $this->model($kind)::latest('id')->firstOrFail();
            $this->assertSame($this->first->id, $record->vehicle_id);
            $this->assertSame($this->driver->id, $record->created_by);
            $this->actingAs($other)->get(route($this->routeName($kind, 'show'), $record->id))->assertForbidden();
        }
        $this->assign($this->second);
        $this->actingAs($this->driver)->get(route('weighings.show', Weighing::firstOrFail()))->assertOk();
        $this->assertSame($this->first->id, Weighing::firstOrFail()->vehicle_id);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->has('summary.vehicles', 1)->where('summary.vehicles.0.id', $this->first->id));
        $this->get(route('reports.index'))->assertForbidden();
    }

    public function test_server_rejects_tampered_vehicle_missing_version_and_inactive_assignment(): void
    {
        $this->assign($this->first);
        $this->actingAs($this->driver);
        foreach (['weighings', 'factory', 'sort'] as $kind) {
            $this->post(route($this->routeName($kind)), $this->payload($kind, ['vehicle_id' => $this->second->id]))->assertSessionHasErrors('vehicle_id');
            $data = $this->payload($kind);
            unset($data['vehicle_assignment_version']);
            $this->post(route($this->routeName($kind)), $data)->assertSessionHasErrors('vehicle_assignment_version');
        }
        try {
            app(SaveDirectWeighing::class)->handle($this->driver, $this->payload('weighings', ['vehicle_id' => $this->second->id]));
            $this->fail('Direct action must enforce assignment');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vehicle_id', $e->errors());
        }
        $this->first->update(['is_active' => false]);
        foreach (['weighings', 'factory', 'sort'] as $kind) {
            $this->get(route($this->routeName($kind, 'create')))->assertInertia(fn (Assert $p) => $p->where('vehicleAssignment.error', 'Mobil yang ditugaskan sudah nonaktif. Hubungi admin untuk mengganti mobil.'));
            $this->post(route($this->routeName($kind)), $this->payload($kind))->assertSessionHasErrors('vehicle_id');
            $this->assertDatabaseCount($this->model($kind)::make()->getTable(), 0);
        }
        $this->assertDatabaseCount('idempotency_requests', 0);
        $this->assertDatabaseCount('document_sequences', 0);
    }

    public function test_open_forms_are_rejected_after_reassignment_including_return_to_the_original_vehicle(): void
    {
        $this->assign($this->first);
        $old = [];
        foreach (['weighings', 'factory', 'sort'] as $kind) {
            $old[$kind] = $this->payload($kind);
        }
        $this->assign($this->second);
        $this->actingAs($this->driver);
        foreach ($old as $kind => $data) {
            $this->post(route($this->routeName($kind)), $data)->assertSessionHasErrors('vehicle_id');
            $this->post(route($this->routeName($kind)), array_replace($data, ['vehicle_id' => $this->second->id]))->assertSessionHasErrors('vehicle_id');
        }
        $this->assign($this->first);
        foreach ($old as $kind => $data) {
            $this->post(route($this->routeName($kind)), $data)->assertSessionHasErrors('vehicle_id');
            $this->post(route($this->routeName($kind)), $this->payload($kind))->assertSessionHasNoErrors();
            $this->assertDatabaseCount($this->model($kind)::make()->getTable(), 1);
        }
    }

    public function test_exact_replays_after_assignment_removal_return_original_documents_without_duplicates(): void
    {
        $this->assign($this->first);
        $data = [];
        foreach (['weighings', 'factory', 'sort'] as $kind) {
            $data[$kind] = $this->payload($kind);
            $this->actingAs($this->driver)->post(route($this->routeName($kind)), $data[$kind])->assertSessionHasNoErrors();
        }
        $this->assign(null);
        $this->first->update(['is_active' => false]);
        $auditCount = DB::table('audit_logs')->count();
        foreach ($data as $kind => $payload) {
            $this->post(route($this->routeName($kind)), $payload)->assertSessionHasNoErrors();
            $this->assertDatabaseCount($this->model($kind)::make()->getTable(), 1);
        }
        $this->assertDatabaseCount('idempotency_requests', 3);
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->assertDatabaseCount('cash_entries', 0);
    }

    public function test_database_constraints_and_migration_preserve_users_and_business_triggers(): void
    {
        foreach ([['assigned_vehicle_id' => 999999], ['vehicle_assignment_version' => 0]] as $data) {
            try {
                DB::table('users')->where('id', $this->driver->id)->update($data);
                $this->fail('Invalid assignment data must be rejected');
            } catch (QueryException) {
                $this->assertNull($this->driver->fresh()->assigned_vehicle_id);
            }
        }
        try {
            DB::table('users')->where('id', $this->manager->id)->update(['assigned_vehicle_id' => $this->first->id]);
            $this->fail('Non-driver assignment must be rejected');
        } catch (QueryException) {
            $this->assertNull($this->manager->fresh()->assigned_vehicle_id);
        }
        $triggers = DB::table('sqlite_master')->where('type', 'trigger')->orderBy('name')->pluck('sql')->all();
        $migration = require database_path('migrations/2026_10_10_000002_assign_driver_vehicle.php');
        $migration->down();
        $migration->up();
        $this->assertSame($triggers, DB::table('sqlite_master')->where('type', 'trigger')->orderBy('name')->pluck('sql')->all());
        $this->assertDatabaseCount('users', 2);
        $this->assertSame(1, $this->driver->fresh()->vehicle_assignment_version);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame('ok', DB::selectOne('PRAGMA integrity_check')->integrity_check);
    }
}
