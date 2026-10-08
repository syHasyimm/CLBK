<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapUserTest extends TestCase
{
    use DatabaseMigrations;

    public function test_initial_account_can_be_created_with_a_hashed_password(): void
    {
        $this->artisan('clbk:bootstrap-user')
            ->expectsQuestion('Nama pengguna', 'Pengguna Awal')
            ->expectsQuestion('Email', 'awal@example.com')
            ->expectsQuestion('Kata sandi (minimal 12 karakter, huruf dan angka)', 'InitialAccount123')
            ->expectsQuestion('Ulangi kata sandi', 'InitialAccount123')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertTrue(Hash::check('InitialAccount123', $user->password));
    }

    public function test_bootstrap_cannot_add_an_account_when_users_already_exist(): void
    {
        User::factory()->create();
        $this->artisan('clbk:bootstrap-user')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_credentials_do_not_create_an_initial_account(): void
    {
        $this->artisan('clbk:bootstrap-user')
            ->expectsQuestion('Nama pengguna', 'Pengguna Awal')
            ->expectsQuestion('Email', 'bukan-email')
            ->expectsQuestion('Kata sandi (minimal 12 karakter, huruf dan angka)', 'weak')
            ->expectsQuestion('Ulangi kata sandi', 'different')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
