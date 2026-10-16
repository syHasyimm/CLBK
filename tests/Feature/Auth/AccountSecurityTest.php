<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_requires_the_current_password_and_rejects_non_string_passwords(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $originalName = $user->name;

        foreach ([null, 'incorrect-password', ['password']] as $password) {
            $this->actingAs($user)->from('/profile')->patch('/profile', [
                'name' => 'Changed Name',
                'email' => 'attacker@example.com',
                'current_password' => $password,
            ])->assertSessionHasErrors('current_password');

            $this->assertSame($originalEmail, $user->fresh()->email);
            $this->assertSame($originalName, $user->fresh()->name);
        }
    }

    public function test_name_change_needs_no_password_and_cannot_change_privileged_account_fields(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'manager',
            'is_active' => false,
            'password' => 'AttackerPassword123',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame(UserRole::Driver, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame($originalPassword, $user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_reset_link_responses_do_not_reveal_existing_unknown_or_broker_throttled_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $expectedStatus = 'Jika email terdaftar, tautan pengaturan ulang akan dikirim.';

        foreach ([$user->email, 'unknown@example.com', $user->email] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
                ->assertRedirect('/forgot-password')
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', $expectedStatus);
        }

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        Notification::assertCount(1);
    }

    public function test_own_password_update_cannot_bypass_account_creation_password_requirements(): void
    {
        $user = User::factory()->create();

        foreach (['abc12345', 'abcdefghijklm', '123456789012'] as $password) {
            $this->actingAs($user)->from('/profile')->put('/password', [
                'current_password' => 'password',
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertSessionHasErrors('password');
        }

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_reset_cannot_bypass_account_creation_password_requirements(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        foreach (['abc12345', 'abcdefghijklm', '123456789012'] as $password) {
            $this->post('/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertSessionHasErrors('password');
        }

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_confirmation_rejects_array_input_without_confirming_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/confirm-password', [
            'password' => ['password'],
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_password_update_rejects_array_credentials_without_changing_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/password', [
            'current_password' => ['password'],
            'password' => 'UpdatedPassword123',
            'password_confirmation' => 'UpdatedPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_recovery_rejects_array_inputs_without_server_errors(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $validPayload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'UpdatedPassword123',
            'password_confirmation' => 'UpdatedPassword123',
        ];

        foreach (['token', 'email', 'password'] as $field) {
            $payload = $validPayload;
            $payload[$field] = [$payload[$field]];

            $this->postJson('/reset-password', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->postJson('/forgot-password', ['email' => [$user->email]])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_invalid_reset_token_responses_do_not_reveal_whether_the_email_exists(): void
    {
        $user = User::factory()->create();
        $knownResponse = $this->postJson('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'UpdatedPassword123',
            'password_confirmation' => 'UpdatedPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $unknownResponse = $this->postJson('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'unknown@example.com',
            'password' => 'UpdatedPassword123',
            'password_confirmation' => 'UpdatedPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame($knownResponse->json('errors'), $unknownResponse->json('errors'));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
