<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class DriverCustomerTest extends TestCase
{
    use DatabaseMigrations;

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Konsumen Kebun Utara',
            'phone' => '08123456789',
            'address' => 'Jalan Kebun 12',
            'farm_location' => 'Kebun Utara',
            'notes' => 'Hubungi sebelum penjemputan.',
        ], $overrides);
    }

    public function test_driver_can_open_creation_without_existing_customer_details(): void
    {
        Customer::query()->create(['code' => 'PRIVAT', 'name' => 'Konsumen Lama', 'notes' => 'Catatan privat']);
        $this->actingAs(User::factory()->create());

        $this->get(route('customers.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Create')->has('fields', 5)
            ->where('fields.0.key', 'name')
            ->where('fields', fn ($fields) => ! collect($fields)->contains('key', 'code'))
            ->missing('records')->missing('record'));
    }

    public function test_driver_creates_customer_without_code_and_returns_to_weighing_with_audit(): void
    {
        $driver = User::factory()->create();
        $this->actingAs($driver);

        $response = $this->post(route('customers.store'), $this->payload(['is_active' => false, 'id' => 9876]));
        $customer = Customer::query()->sole();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('weighings.create', ['customer_id' => $customer->id]))
            ->assertSessionHas('success');
        $this->assertSame('C001', $customer->code);
        $this->assertSame('Konsumen Kebun Utara', $customer->name);
        $this->assertSame('08123456789', $customer->phone);
        $this->assertSame('Jalan Kebun 12', $customer->address);
        $this->assertSame('Kebun Utara', $customer->farm_location);
        $this->assertSame('Hubungi sebelum penjemputan.', $customer->notes);
        $this->assertTrue($customer->is_active);
        $this->assertNotSame(9876, $customer->id);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $driver->id, 'action' => 'created',
            'entity_type' => 'customers', 'entity_id' => (string) $customer->id, 'before_json' => null,
        ]);
        $audit = DB::table('audit_logs')->where('entity_type', 'customers')->sole();
        $this->assertTrue(json_decode($audit->after_json, true)['is_active']);
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Weighings/Form')
                ->has('selectedCustomer', 2)
                ->where('selectedCustomer.id', $customer->id)
                ->where('selectedCustomer.label', $customer->name)
                ->missing('selectedCustomer.phone')->missing('selectedCustomer.address')
                ->missing('selectedCustomer.farm_location')->missing('selectedCustomer.notes'));
    }

    public function test_automatic_codes_skip_existing_codes_ignore_manual_input_and_allow_shared_phone(): void
    {
        Customer::query()->create(['code' => 'C001', 'name' => 'Konsumen Lama', 'phone' => '08123456789']);
        $this->actingAs(User::factory()->create());

        $this->post(route('customers.store'), $this->payload(['code' => 'C001']))->assertSessionHasNoErrors();
        $this->post(route('customers.store'), $this->payload(['code' => ['manual']]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['code' => 'C002', 'phone' => '08123456789', 'is_active' => true]);
        $this->assertDatabaseHas('customers', ['code' => 'C003', 'phone' => '08123456789', 'is_active' => true]);
        $this->assertDatabaseHas('customers', ['code' => 'C001', 'name' => 'Konsumen Lama']);
        $this->assertDatabaseCount('customers', 3);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_invalid_and_oversized_fields_never_create_customers(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([
            ['name', ''],
            ['name', str_repeat('N', 151)], ['phone', str_repeat('1', 31)],
            ['address', str_repeat('A', 1001)], ['farm_location', str_repeat('K', 1001)],
            ['notes', str_repeat('N', 2001)], ['name', ['unexpected']],
        ] as [$field, $value]) {
            $this->post(route('customers.store'), $this->payload([$field => $value]))
                ->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cashier_cannot_open_or_post_the_driver_customer_form(): void
    {
        $this->actingAs(User::factory()->cashier()->create());
        $this->get(route('customers.create'))->assertForbidden();
        $this->post(route('customers.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guests_and_inactive_drivers_cannot_create_customers(): void
    {
        $this->get(route('customers.create'))->assertRedirect(route('login'));
        $this->post(route('customers.store'), $this->payload())->assertRedirect(route('login'));
        $driver = User::factory()->inactive()->create();
        $this->actingAs($driver)->getJson(route('customers.create'))->assertForbidden();
        $this->actingAs($driver)->postJson(route('customers.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_driver_cannot_list_or_manage_existing_or_new_customers(): void
    {
        $existing = Customer::query()->create(['code' => 'LAMA', 'name' => 'Konsumen Lama']);
        $this->actingAs(User::factory()->create());
        $this->post(route('customers.store'), $this->payload())->assertSessionHasNoErrors();
        $own = Customer::query()->where('code', 'C001')->sole();

        $this->get(route('master-data.index', 'customers'))->assertForbidden();
        $this->get(route('master-data.create', 'customers'))->assertForbidden();
        $this->post(route('master-data.store', 'customers'), $this->payload(['code' => 'C002', 'is_active' => false]))->assertForbidden();
        foreach ([$existing, $own] as $customer) {
            $this->get(route('master-data.edit', ['customers', $customer->id]))->assertForbidden();
            $this->patch(route('master-data.update', ['customers', $customer->id]), $this->payload(['is_active' => true]))->assertForbidden();
            $this->patch(route('master-data.status', ['customers', $customer->id]), ['is_active' => false])->assertForbidden();
            $this->delete(route('master-data.destroy', ['customers', $customer->id]))->assertForbidden();
            $this->assertTrue($customer->fresh()->is_active);
        }
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_manager_can_still_create_customers(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $this->get(route('customers.create'))->assertOk();
        $this->post(route('customers.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['code' => 'C001', 'is_active' => true]);
    }

    public function test_weighing_form_rejects_inactive_or_missing_selected_customers(): void
    {
        $customer = Customer::query()->create(['code' => 'NONAKTIF', 'name' => 'Konsumen Lama', 'is_active' => false]);
        $this->actingAs(User::factory()->create());
        $this->get(route('weighings.create', ['customer_id' => $customer->id]))->assertNotFound();
        $this->get(route('weighings.create', ['customer_id' => 999999]))->assertNotFound();
        $this->get(route('weighings.create'))->assertInertia(fn (Assert $page) => $page
            ->component('Weighings/Form')->where('selectedCustomer', null));
    }

    public function test_audit_failure_rolls_back_customer_creation(): void
    {
        $this->partialMock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failed')));
        $this->actingAs(User::factory()->create());
        $this->withoutExceptionHandling();
        try {
            $this->post(route('customers.store'), $this->payload());
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit failed', $exception->getMessage());
        }
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
