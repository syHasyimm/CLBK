<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RoleUserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class RoleUserSeederTest extends TestCase
{
    use DatabaseMigrations;

    public function test_seeder_creates_each_active_role_with_hashed_passwords_and_safe_audit(): void
    {
        $this->seed(RoleUserSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('audit_logs', 3);

        foreach ([
            'manager@clbk.test' => UserRole::Manager,
            'supir@clbk.test' => UserRole::Driver,
            'kasir@clbk.test' => UserRole::Cashier,
        ] as $email => $role) {
            $user = User::query()->where('email', $email)->sole();
            $this->assertSame($role, $user->role);
            $this->assertTrue($user->is_active);
            $this->assertTrue(Hash::check('ClbkToke12345!', $user->password));
            $this->assertNotSame('ClbkToke12345!', $user->password);
            $audit = DB::table('audit_logs')->where('entity_id', (string) $user->id)->sole();
            $this->assertSame('seeded', $audit->action);
            $this->assertSame($user->id, $audit->actor_id);
            $snapshot = json_decode($audit->after_json, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($role->value, $snapshot['role']);
            $this->assertArrayNotHasKey('password', $snapshot);
            $this->assertArrayNotHasKey('remember_token', $snapshot);
        }
    }

    public function test_rerunning_preserves_existing_accounts_passwords_roles_and_activation(): void
    {
        $this->seed(RoleUserSeeder::class);
        $user = User::query()->where('email', 'manager@clbk.test')->sole();
        $user->update([
            'name' => 'Akun yang sudah diubah',
            'password' => 'ChangedPassword678!',
            'role' => UserRole::Driver,
            'is_active' => false,
        ]);
        $before = User::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->seed(RoleUserSeeder::class);

        $this->assertSame($before, User::query()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('audit_logs', 3);
        $this->assertTrue(Hash::check('ChangedPassword678!', $user->fresh()->password));
    }

    public function test_each_seeded_role_can_log_in_and_user_management_remains_manager_only(): void
    {
        $this->seed(RoleUserSeeder::class);

        foreach (['manager@clbk.test', 'supir@clbk.test', 'kasir@clbk.test'] as $email) {
            $user = User::query()->where('email', $email)->sole();
            $this->post('/login', ['email' => $email, 'password' => 'ClbkToke12345!'])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('dashboard', absolute: false));
            $this->assertAuthenticatedAs($user);
            $this->get(route('users.index'))->assertStatus($user->role === UserRole::Manager ? 200 : 403);
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
    }

    public function test_demo_accounts_are_rejected_in_production_without_writes(): void
    {
        $environment = app()->environment();
        app()->instance('env', 'production');

        try {
            app(RoleUserSeeder::class)->run();
            $this->fail('Seeder akun lokal tidak boleh berjalan pada produksi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('RoleUserSeeder hanya dapat dijalankan pada lingkungan local atau testing.', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('audit_logs', 0);
        } finally {
            app()->instance('env', $environment);
        }
    }
}
