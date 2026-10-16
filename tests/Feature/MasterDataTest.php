<?php

namespace Tests\Feature;

use App\Actions\MasterData\DeleteMasterData;
use App\Actions\MasterData\SaveMasterData;
use App\Enums\MasterResource;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use DatabaseMigrations;

    public static function masters(): array
    {
        return [
            ['customers', ['code' => ' C 001 ', 'name' => 'Konsumen Pertama', 'phone' => '081234', 'farm_location' => 'Kebun Utara']],
            ['loading-workers', ['code' => ' A 001 ', 'name' => 'Anggota Pertama', 'joined_on' => '2026-10-01']],
            ['vehicles', ['code' => ' M 001 ', 'plate_number' => ' bk 1234 ab ', 'capacity_kg' => '1250,500']],
            ['factories', ['code' => ' P 001 ', 'name' => 'Pabrik Pertama', 'contact_name' => 'Kontak Pabrik']],
            ['cash-accounts', ['code' => ' K 001 ', 'name' => 'Kas Pertama', 'type' => 'cash']],
            ['expense-categories', ['code' => ' E 001 ', 'name' => 'Biaya Pertama']],
        ];
    }

    public static function manageableMasters(): array
    {
        return array_values(array_filter(self::masters(), fn (array $master) => $master[0] !== 'cash-accounts'));
    }

    #[DataProvider('manageableMasters')]
    public function test_manager_can_create_edit_deactivate_reactivate_and_delete_unused_master(string $resource, array $data): void
    {
        $this->actingAs(User::factory()->manager()->create());
        unset($data['code']);
        $this->get(route('master-data.create', $resource))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fields.0.key', $resource === 'vehicles' ? 'plate_number' : 'name')
                ->where('fields', fn ($fields) => ! collect($fields)->contains('key', 'code')));
        $this->post(route('master-data.store', $resource), $data + ['is_active' => true])
            ->assertSessionHasNoErrors()->assertRedirect(route('master-data.index', $resource));
        $record = MasterResource::from($resource)->model()::query()->sole();
        $this->assertMatchesRegularExpression('/^[A-Z][0-9]{3}$/', $record->code);
        $originalCode = $record->code;
        if ($resource === 'vehicles') {
            $this->assertSame('BK1234AB', $record->plate_number);
            $this->assertSame(1250500, $record->capacity_grams);
        }
        $this->get(route('master-data.index', $resource))->assertInertia(fn (Assert $page) => $page->missing('columns.code'));
        $this->get(route('master-data.edit', [$resource, $record->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('fields', fn ($fields) => ! collect($fields)->contains('key', 'code')));
        $changed = $data;
        $changed['code'] = 'BARU';
        $identity = $resource === 'vehicles' ? 'plate_number' : 'name';
        $changed[$identity] = $resource === 'vehicles' ? 'BK1234AC' : 'Nama Baru';
        $this->patch(route('master-data.update', [$resource, $record->id]), $changed + ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame($originalCode, $record->fresh()->code);
        $this->assertSame($changed[$identity], $record->fresh()->getAttribute($identity));
        $this->patch(route('master-data.status', [$resource, $record->id]), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($record->fresh()->is_active);
        $this->patch(route('master-data.status', [$resource, $record->id]), ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($record->fresh()->is_active);
        $this->delete(route('master-data.destroy', [$resource, $record->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing($record->getTable(), ['id' => $record->id]);
        $this->assertSame(5, DB::table('audit_logs')->where('entity_type', $record->getTable())->count());
    }

    #[DataProvider('masters')]
    public function test_driver_and_cashier_cannot_access_or_mutate_master_management(string $resource, array $data): void
    {
        $model = MasterResource::from($resource)->model();
        $payload = $data + ['is_active' => true];
        if ($resource === 'vehicles') {
            unset($payload['capacity_kg']);
            $payload['plate_number'] = 'BK1234AB';
        }
        $record = $model::query()->create($payload);
        foreach (['driver', 'cashier'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('master-data.index', $resource))->assertForbidden();
            $this->get(route('master-data.create', $resource))->assertForbidden();
            $this->get(route('master-data.edit', [$resource, $record->id]))->assertForbidden();
            $this->post(route('master-data.store', $resource), $data + ['is_active' => true])->assertForbidden();
            $this->patch(route('master-data.update', [$resource, $record->id]), $data + ['is_active' => true])->assertForbidden();
            $this->patch(route('master-data.status', [$resource, $record->id]), ['is_active' => false])->assertForbidden();
            $this->delete(route('master-data.destroy', [$resource, $record->id]))->assertForbidden();
        }
        $this->assertTrue($record->fresh()->is_active);
        $this->assertDatabaseCount($record->getTable(), 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_list_search_status_and_pagination_are_server_filtered(): void
    {
        for ($i = 1; $i <= 18; $i++) {
            Customer::query()->create(['code' => sprintf('C%03d', $i), 'name' => 'Konsumen '.$i, 'is_active' => true]);
        }
        Customer::query()->create(['code' => 'NONAKTIF', 'name' => 'Konsumen Nonaktif', 'is_active' => false]);
        $this->actingAs(User::factory()->manager()->create());
        $this->get(route('master-data.index', 'customers'))->assertInertia(fn (Assert $page) => $page
            ->component('MasterData/Index')->has('records.data', 15)->where('records.total', 19));
        $this->get(route('master-data.index', ['customers', 'q' => 'NONAKTIF', 'status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.code', 'NONAKTIF'));
        $this->get(route('master-data.index', ['customers', 'status' => 'active', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 3)->where('records.total', 18));
        $this->get(route('master-data.index', ['customers', 'status' => 'invalid']))->assertSessionHasErrors('status');
    }

    public function test_nonmanager_reference_picker_only_returns_active_identity_without_private_details(): void
    {
        Customer::query()->create(['code' => 'AKTIF', 'name' => 'Konsumen Aktif', 'phone' => '081234', 'address' => 'Alamat privat', 'notes' => 'Catatan privat', 'is_active' => true]);
        Customer::query()->create(['code' => 'NONAKTIF', 'name' => 'Konsumen Lama', 'is_active' => false]);
        $this->actingAs(User::factory()->create());
        $this->getJson(route('references.index', 'customers'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'AKTIF')->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.address')->assertJsonMissingPath('data.0.notes');
        $this->getJson(route('references.index', 'cash-accounts'))->assertForbidden();
        $this->actingAs(User::factory()->cashier()->create());
        $this->getJson(route('references.index', 'cash-accounts'))->assertOk();
    }

    public function test_manager_cannot_add_edit_deactivate_or_delete_cash_accounts_in_pages_or_actions(): void
    {
        $manager = User::factory()->manager()->create();
        $cash = CashAccount::create(['code' => 'KAS-UTAMA', 'name' => 'Kas Utama', 'type' => 'cash', 'is_active' => true]);
        $original = $cash->fresh()->getAttributes();
        $data = ['code' => 'BANK-BARU', 'name' => 'Bank Baru', 'type' => 'bank', 'is_active' => true];
        $this->actingAs($manager)->get(route('master-data.index', 'cash-accounts'))->assertRedirect(route('cash.index'));
        $this->get(route('master-data.create', 'cash-accounts'))->assertForbidden();
        $this->get(route('master-data.edit', ['cash-accounts', $cash->id]))->assertForbidden();
        $this->post(route('master-data.store', 'cash-accounts'), $data)->assertForbidden();
        $this->patch(route('master-data.update', ['cash-accounts', $cash->id]), $data)->assertForbidden();
        $this->patch(route('master-data.status', ['cash-accounts', $cash->id]), ['is_active' => false])->assertForbidden();
        $this->delete(route('master-data.destroy', ['cash-accounts', $cash->id]))->assertForbidden();
        foreach ([
            fn () => app(SaveMasterData::class)->handle($manager, MasterResource::CashAccounts, null, $data),
            fn () => app(SaveMasterData::class)->handle($manager, MasterResource::CashAccounts, $cash->id, $data),
            fn () => app(DeleteMasterData::class)->handle($manager, MasterResource::CashAccounts, $cash->id),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Cash account management must remain denied inside the action');
            } catch (AuthorizationException) {
                $this->assertSame($original, $cash->fresh()->getAttributes());
            }
        }
        $this->assertDatabaseCount('cash_accounts', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_used_master_cannot_be_deleted_but_can_be_deactivated_without_losing_history(): void
    {
        $record = Customer::query()->create(['code' => 'C001', 'name' => 'Konsumen Lama']);
        // Fixture referensi menguji foreign key master tanpa transaksi usaha.
        DB::statement('CREATE TABLE test_customer_refs (id BIGINT UNSIGNED PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL, FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT) ENGINE=InnoDB');
        DB::table('test_customer_refs')->insert(['id' => 1, 'customer_id' => $record->id]);
        try {
            $this->actingAs(User::factory()->manager()->create());
            $this->delete(route('master-data.destroy', ['customers', $record->id]))->assertSessionHasErrors('operation');
            $this->assertDatabaseHas('customers', ['id' => $record->id]);
            $this->assertDatabaseCount('audit_logs', 0);
            $this->patch(route('master-data.status', ['customers', $record->id]), ['is_active' => false])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('test_customer_refs', ['id' => 1, 'customer_id' => $record->id]);
        } finally {
            DB::statement('DROP TABLE test_customer_refs');
        }
    }

    public function test_codes_are_automatic_and_plates_are_normalized_before_uniqueness_validation(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $this->post(route('master-data.store', 'customers'), ['code' => 'MANUAL', 'name' => 'Satu', 'phone' => '081234', 'is_active' => true])->assertSessionHasNoErrors();
        $this->post(route('master-data.store', 'customers'), ['name' => 'Dua', 'phone' => '081234', 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame(['C001', 'C002'], Customer::query()->orderBy('id')->pluck('code')->all());
        $this->post(route('master-data.store', 'vehicles'), ['plate_number' => 'bk 1 aa', 'is_active' => true])->assertSessionHasNoErrors();
        $this->post(route('master-data.store', 'vehicles'), ['plate_number' => 'BK1AA', 'is_active' => true])->assertSessionHasErrors('plate_number');
        $this->assertSame('BK1AA', Vehicle::query()->sole()->plate_number);
    }

    #[DataProvider('manageableMasters')]
    public function test_automatic_codes_skip_existing_data_and_are_not_reused_after_deletion(string $resource, array $data): void
    {
        $model = MasterResource::from($resource)->model();
        $legacyCode = preg_replace('/\\s+/', '', $data['code']);
        $legacyData = $data;
        $legacyData['code'] = $legacyCode;
        if ($resource === 'vehicles') {
            unset($legacyData['capacity_kg']);
            $legacyData['plate_number'] = 'BK111AA';
        }
        $legacy = $model::query()->create($legacyData);
        unset($data['code']);
        $this->actingAs(User::factory()->manager()->create());
        $this->post(route('master-data.store', $resource), $data + ['is_active' => true])->assertSessionHasNoErrors();
        $created = $model::query()->whereKeyNot($legacy->id)->sole();
        $this->assertSame($legacyCode[0].'002', $created->code);
        $this->delete(route('master-data.destroy', [$resource, $created->id]))->assertSessionHasNoErrors();
        $this->post(route('master-data.store', $resource), $data + ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame($legacyCode[0].'003', $model::query()->whereKeyNot($legacy->id)->sole()->code);
        $this->assertSame($legacyCode, $legacy->fresh()->code);
    }

    public function test_vehicle_only_accepts_active_driver_and_exact_positive_capacity(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->create();
        $inactive = User::factory()->inactive()->create();
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($manager);
        foreach ([$manager->id, $inactive->id, $cashier->id, 99999] as $id) {
            $this->post(route('master-data.store', 'vehicles'), ['code' => 'M1', 'plate_number' => 'BK1AA', 'default_driver_id' => $id, 'is_active' => true])->assertSessionHasErrors('default_driver_id');
        }
        foreach (['0', '-1', '1.1234', '1e3', '1000000.001', ['1']] as $capacity) {
            $this->post(route('master-data.store', 'vehicles'), ['code' => 'M1', 'plate_number' => 'BK1AA', 'capacity_kg' => $capacity, 'is_active' => true])->assertSessionHasErrors('capacity_kg');
        }
        $this->post(route('master-data.store', 'vehicles'), ['code' => 'M1', 'plate_number' => 'BK1AA', 'capacity_kg' => '0.001', 'default_driver_id' => $driver->id, 'is_active' => true])->assertSessionHasNoErrors();
        $vehicle = Vehicle::query()->sole();
        $this->assertSame(1, $vehicle->capacity_grams);
        $this->assertSame($driver->id, $vehicle->default_driver_id);
        $this->get(route('master-data.edit', ['vehicles', $vehicle->id]))->assertInertia(fn (Assert $page) => $page->where('record.capacity_kg', '0.001'));
    }

    public function test_unknown_resource_and_unvalidated_fields_cannot_access_arbitrary_tables(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $this->get('/master/users')->assertNotFound();
        $this->get('/references/users')->assertNotFound();
        $this->post(route('master-data.store', 'customers'), ['code' => 'C1', 'name' => 'Konsumen', 'is_active' => true, 'id' => 1000, 'password' => 'secret'])->assertSessionHasNoErrors();
        $this->assertSame(1, Customer::query()->sole()->id);
    }

    public function test_audit_failure_rolls_back_master_write(): void
    {
        $this->partialMock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failed')));
        $this->actingAs(User::factory()->manager()->create());
        $this->withoutExceptionHandling();
        try {
            $this->post(route('master-data.store', 'customers'), ['code' => 'C1', 'name' => 'Konsumen', 'is_active' => true]);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit failed', $exception->getMessage());
        }
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseMissing('document_sequences', ['name' => 'master:customers']);
    }

    public function test_database_rejects_invalid_boolean_and_capacity_outside_the_allowed_range(): void
    {
        $record = Vehicle::query()->create(['code' => 'M1', 'plate_number' => 'BK1AA']);
        foreach ([['is_active' => 2], ['capacity_grams' => -1], ['capacity_grams' => 1000000001]] as $values) {
            try {
                DB::table('vehicles')->where('id', $record->id)->update($values);
                $this->fail('Invalid value passed database constraint');
            } catch (QueryException $exception) {
                $this->assertSame(3819, (int) $exception->errorInfo[1]);
            }
        }
    }
}
