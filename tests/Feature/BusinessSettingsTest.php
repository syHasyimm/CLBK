<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CashAccount;
use App\Models\User;
use App\Services\BusinessCash;
use App\Services\CashLedger;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_initial_seed_is_idempotent_preserves_edits_and_does_not_create_demo_users_or_balance(): void
    {
        $this->seed();
        AppSetting::query()->whereKey('business_name')->first()->update(['value_json' => 'Usaha Sawit']);
        CashAccount::query()->sole()->update(['name' => 'Kas Kantor', 'is_active' => false]);
        $this->seed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('app_settings', 4);
        $this->assertDatabaseCount('cash_accounts', 1);
        $this->assertDatabaseCount('expense_categories', 6);
        $this->assertSame('Usaha Sawit', AppSetting::values()['business_name']);
        $this->assertSame('Kas Kantor', CashAccount::query()->sole()->name);
        $this->assertFalse(CashAccount::query()->sole()->is_active);
        $this->assertArrayNotHasKey('balance', CashAccount::query()->sole()->toArray());
    }

    public function test_manager_can_update_business_identity_with_audit_and_fixed_rules_remain_unchanged(): void
    {
        $this->seed();
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('settings.business.edit'))->assertInertia(fn (Assert $page) => $page->component('Settings/Business')->where('settings.business_timezone', 'Asia/Jakarta')->where('settings.weight_tolerance_basis_points', 200));
        $this->patch(route('settings.business.update'), ['business_name' => 'CLBK TOKE Baru', 'receipt_prefix' => 'CLBK26'])->assertSessionHasNoErrors();
        $settings = AppSetting::values();
        $this->assertSame('CLBK TOKE Baru', $settings['business_name']);
        $this->assertSame('CLBK26', $settings['receipt_prefix']);
        $this->assertSame('Asia/Jakarta', $settings['business_timezone']);
        $this->assertSame(200, $settings['weight_tolerance_basis_points']);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertSame($manager->id, AppSetting::query()->whereKey('business_name')->value('updated_by'));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('business.name', 'CLBK TOKE Baru'));
    }

    public function test_seeding_reuses_an_existing_cash_account_without_creating_another_or_changing_its_balance(): void
    {
        $manager = User::factory()->manager()->create();
        $cash = CashAccount::create(['code' => 'KAS-D', 'name' => 'Kas Usaha Lama', 'type' => 'cash', 'is_active' => true]);
        DB::table('cash_entries')->insert(['cash_account_id' => $cash->id, 'number' => 'KAS-AWAL-LAMA',
            'direction' => 'in', 'kind' => 'opening', 'amount_idr' => 5000, 'occurred_at' => now(),
            'business_date' => now()->toDateString(), 'reason' => 'Saldo lama', 'created_by' => $manager->id]);
        $cash->update(['is_active' => false]);
        $original = $cash->fresh()->getAttributes();

        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('cash_accounts', 1);
        $this->assertSame($original, $cash->fresh()->getAttributes());
        $this->assertSame($cash->id, app(BusinessCash::class)->account()->id);
        $this->assertSame(5000, app(CashLedger::class)->balance($cash->id));
        $this->assertDatabaseCount('cash_entries', 1);
    }

    public function test_timezone_tolerance_and_invalid_receipt_prefix_cannot_be_submitted(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->manager()->create());
        $data = ['business_name' => 'CLBK', 'receipt_prefix' => 'CLBK'];
        $this->patch(route('settings.business.update'), $data + ['business_timezone' => 'UTC'])->assertSessionHasErrors('business_timezone');
        $this->patch(route('settings.business.update'), $data + ['weight_tolerance_basis_points' => 999])->assertSessionHasErrors('weight_tolerance_basis_points');
        $this->patch(route('settings.business.update'), ['business_name' => 'CLBK', 'receipt_prefix' => '../X'])->assertSessionHasErrors('receipt_prefix');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(200, AppSetting::values()['weight_tolerance_basis_points']);
        $this->assertTrue(DB::table('app_settings')->whereRaw('json_valid(value_json)')->count() === 4);
    }
}
