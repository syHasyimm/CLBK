<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Aktifkan pemeriksaan CSRF yang biasanya dilewati oleh HTTP test Laravel.
        $this->app->instance('env', 'local');
    }

    public function test_login_without_a_session_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(419);
        $this->assertGuest();
    }

    public function test_login_accepts_the_session_token_and_rotates_session_id(): void
    {
        $user = User::factory()->create();
        $this->withSession(['_token' => 'csrf-test-token']);
        $originalId = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            '_token' => 'csrf-test-token',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($originalId, session()->getId());
    }

    public function test_logout_rejects_invalid_csrf_and_keeps_the_user_authenticated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['_token' => 'valid-token']);
        $this->post('/logout', ['_token' => 'wrong-token'])->assertStatus(419);
        $this->assertAuthenticatedAs($user);
    }
}
