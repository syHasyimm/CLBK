<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_access_the_workspace(): void
    {
        $this->actingAs(User::factory()->unverified()->create());
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }
}
