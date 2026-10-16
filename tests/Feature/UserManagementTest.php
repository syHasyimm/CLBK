<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseMigrations;

    private function payload(array $override = []): array
    {
        return array_replace([
            'name' => 'Supir Baru', 'email' => 'SUPIR@example.com', 'role' => 'driver',
            'is_active' => true, 'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
        ], $override);
    }

    public function test_manager_can_create_account_with_role_and_hashed_password_without_exposing_credentials(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->post(route('users.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('users.index'));
        $user = User::query()->where('email', 'supir@example.com')->sole();
        $this->assertSame(UserRole::Driver, $user->role);
        $this->assertTrue(Hash::check('SecurePassword123', $user->password));
        $this->get(route('users.index'))->assertInertia(fn (Assert $page) => $page->component('Users/Index')->has('records.data', 2)->missing('records.data.0.password')->missing('auth.user.password'));
        $audit = DB::table('audit_logs')->sole();
        $this->assertSame($manager->id, $audit->actor_id);
        $this->assertStringNotContainsString('password', $audit->after_json);
        $this->assertStringNotContainsString($user->password, $audit->after_json);
        $this->assertStringNotContainsString('remember_token', $audit->after_json);
    }

    public function test_nonmanager_cannot_list_create_or_edit_accounts_and_settings(): void
    {
        $manager = User::factory()->manager()->create();
        foreach (['driver', 'cashier'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('users.index'))->assertForbidden();
            $this->get(route('users.create'))->assertForbidden();
            $this->get(route('users.edit', $manager))->assertForbidden();
            $this->post(route('users.store'), $this->payload())->assertForbidden();
            $this->patch(route('users.update', $manager), $this->payload())->assertForbidden();
            $this->patch(route('users.status', $manager), ['is_active' => false])->assertForbidden();
            $this->get(route('settings.business.edit'))->assertForbidden();
            $this->patch(route('settings.business.update'), ['business_name' => 'Changed', 'receipt_prefix' => 'XX'])->assertForbidden();
        }
    }

    public function test_last_active_manager_cannot_be_disabled_or_demoted_and_changes_are_rolled_back(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->manager()->inactive()->create();
        $this->actingAs($manager)->patch(route('users.status', $manager), ['is_active' => false])->assertSessionHasErrors('operation');
        $this->patch(route('users.update', $manager), $this->payload(['email' => $manager->email, 'role' => 'cashier', 'password' => '', 'password_confirmation' => '']))->assertSessionHasErrors('operation');
        $this->assertSame(UserRole::Manager, $manager->fresh()->role);
        $this->assertTrue($manager->fresh()->is_active);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_manager_can_demote_self_when_another_active_manager_remains(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->manager()->create();
        $this->actingAs($manager)->patch(route('users.update', $manager), $this->payload(['email' => $manager->email, 'password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertSame(UserRole::Driver, $manager->fresh()->role);
        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_disabled_accounts_cannot_login_and_old_authenticated_session_is_invalidated(): void
    {
        $user = User::factory()->inactive()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertSessionHasErrors('email');
        $this->assertGuest();
        $user->update(['is_active' => true]);
        $this->actingAs($user);
        $user->update(['is_active' => false]);
        $this->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_changed_role_is_enforced_on_existing_session(): void
    {
        $user = User::factory()->manager()->create();
        $this->actingAs($user);
        DB::table('users')->where('id', $user->id)->update(['role' => 'driver']);
        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_existing_session_with_old_password_hash_is_logged_out_after_reset(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash]);
        DB::table('users')->where('id', $user->id)->update(['password' => Hash::make('ChangedPassword123')]);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_disabling_or_resetting_password_revokes_sessions_and_remember_token(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->create(['remember_token' => 'old-token']);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($manager)->patch(route('users.status', $user), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
        $this->patch(route('users.status', $user), ['is_active' => true])->assertSessionHasNoErrors();
        $token = $user->fresh()->remember_token;
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->patch(route('users.update', $user), $this->payload(['email' => $user->email]))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('SecurePassword123', $user->fresh()->password));
        $this->assertNotSame($token, $user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $audit = DB::table('audit_logs')->orderByDesc('id')->first();
        $this->assertSame('updated_with_password_reset', $audit->action);
        $this->assertStringNotContainsString('SecurePassword123', $audit->before_json.$audit->after_json);
    }

    public function test_account_update_keeps_password_when_empty_and_rejects_weak_or_invalid_role(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->create();
        $hash = $user->password;
        $this->actingAs($manager)->patch(route('users.update', $user), $this->payload(['email' => $user->email, 'password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
        $this->assertSame($hash, $user->fresh()->password);
        $this->post(route('users.store'), $this->payload(['role' => 'admin']))->assertSessionHasErrors('role');
        $this->post(route('users.store'), $this->payload(['password' => 'weak', 'password_confirmation' => 'weak']))->assertSessionHasErrors('password');
    }

    public function test_profile_cannot_escalate_own_role_or_enable_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Nama Baru', 'email' => $user->email, 'role' => 'manager', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Driver, $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_audit_is_append_only_and_database_rejects_unknown_user_role(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->post(route('users.store'), $this->payload())->assertSessionHasNoErrors();
        foreach (['update', 'delete'] as $operation) {
            try {
                $query = DB::table('audit_logs');
                $operation === 'update' ? $query->update(['action' => 'edited']) : $query->delete();
                $this->fail('Audit was altered');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('append-only', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('audit_logs', 1);
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $manager->id)->update(['role' => 'admin']);
    }
}
