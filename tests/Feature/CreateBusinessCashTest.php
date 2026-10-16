<?php

namespace Tests\Feature;

use App\Actions\Finance\CreateBusinessCash;
use App\Models\CashAccount;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CashLedger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class CreateBusinessCashTest extends TestCase
{
    use DatabaseMigrations;

    public function test_manager_can_initialize_one_cash_without_code_and_immediately_post_capital(): void
    {
        $manager = User::factory()->manager()->create();
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
            ->where('account', null)->where('canManage', false)->where('canCreateAccount', false));
        $this->actingAs($manager)->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
            ->where('account', null)->where('canManage', true)->where('canCreateAccount', true));

        $this->post(route('cash.account.store'), [
            'code' => 'PALSU', 'name' => 'Kas lain', 'type' => 'bank', 'is_active' => false, 'balance_idr' => 999999,
        ])->assertSessionHasNoErrors()->assertRedirect(route('cash.index'));
        $account = CashAccount::query()->sole();
        $this->assertSame('KAS-UTAMA', $account->code);
        $this->assertSame('Kas Usaha', $account->name);
        $this->assertSame('cash', $account->type);
        $this->assertTrue($account->is_active);
        $this->assertSame(0, app(CashLedger::class)->balance($account->id));
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('audit_logs', 1);
        $audit = DB::table('audit_logs')->sole();
        $this->assertSame($manager->id, $audit->actor_id);
        $this->assertSame('created', $audit->action);
        $this->assertSame('cash_accounts', $audit->entity_type);
        $this->assertNull($audit->before_json);
        $this->assertSame($account->id, json_decode($audit->after_json, true)['id']);
        foreach ([$manager, $cashier] as $actor) {
            $this->actingAs($actor)->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
                ->where('account.id', $account->id)->where('account.balance_idr', 0)->where('canCreateAccount', false)
                ->where('cash.id', $account->id));
        }

        $this->actingAs($manager)->post(route('cash.store'), [
            'kind' => 'capital', 'amount_idr' => 5000, 'occurred_at' => now()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'reason' => 'Modal pertama', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(5000, app(CashLedger::class)->balance($account->id));
        $this->assertDatabaseHas('cash_entries', ['cash_account_id' => $account->id, 'amount_idr' => 5000]);

        $this->post(route('cash.account.store'))->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('cash_accounts', 1);
        $this->assertDatabaseCount('cash_entries', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public static function existingAccounts(): array
    {
        return [
            'active cash' => ['cash', true],
            'inactive cash' => ['cash', false],
            'active legacy bank' => ['bank', true],
            'inactive legacy bank' => ['bank', false],
        ];
    }

    #[DataProvider('existingAccounts')]
    public function test_existing_account_disables_creation_and_http_and_action_cannot_add_another(string $type, bool $active): void
    {
        $manager = User::factory()->manager()->create();
        $account = CashAccount::query()->create(['code' => 'LAMA', 'name' => 'Kas Lama', 'type' => $type, 'is_active' => $active]);
        $before = $account->fresh()->getAttributes();
        $this->actingAs($manager)->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)->where('canCreateAccount', false));
        $this->post(route('cash.account.store'))->assertSessionHasErrors('operation');
        try {
            app(CreateBusinessCash::class)->handle($manager);
            $this->fail('The action must not create a second account');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation', $exception->errors());
        }
        $this->assertSame($before, $account->fresh()->getAttributes());
        $this->assertDatabaseCount('cash_accounts', 1);
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guests_cashiers_drivers_and_inactive_managers_cannot_initialize_cash(): void
    {
        $this->post(route('cash.account.store'))->assertRedirect(route('login'));
        foreach ([User::factory()->cashier()->create(), User::factory()->create(), User::factory()->manager()->inactive()->create()] as $actor) {
            $this->actingAs($actor)->postJson(route('cash.account.store'))->assertForbidden();
            try {
                app(CreateBusinessCash::class)->handle($actor);
                $this->fail('Only an active manager may initialize cash');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('cash_accounts', 0);
            }
        }
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_action_rechecks_manager_role_inside_the_write_transaction(): void
    {
        $manager = User::factory()->manager()->create();
        User::query()->whereKey($manager->id)->update(['role' => 'cashier']);
        try {
            app(CreateBusinessCash::class)->handle($manager);
            $this->fail('A stale manager must not initialize cash');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('cash_accounts', 0);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_audit_failure_rolls_back_initial_cash_creation(): void
    {
        $manager = User::factory()->manager()->create();
        $this->partialMock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failed')));
        try {
            app(CreateBusinessCash::class)->handle($manager);
            $this->fail('Expected the audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit failed', $exception->getMessage());
        }
        $this->assertDatabaseCount('cash_accounts', 0);
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
