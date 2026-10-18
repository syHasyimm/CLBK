<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_email_hides_password_recovery_without_disabling_login(): void
    {
        config(['mail.enabled' => false]);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('canResetPassword', false));

        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_disabled_email_blocks_recovery_and_verification_without_creating_tokens_or_notifications(): void
    {
        config(['mail.enabled' => false]);
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => $user->email])->assertNotFound();
        $this->get('/reset-password/unused-token')->assertNotFound();
        $this->post('/reset-password', [])->assertNotFound();
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->actingAs($user)->get('/verify-email')->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
        Notification::assertNothingSent();
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'UpdatedPassword123',
                'password_confirmation' => 'UpdatedPassword123',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
