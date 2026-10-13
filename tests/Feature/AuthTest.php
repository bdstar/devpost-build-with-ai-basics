<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/performance')->assertRedirect('/login');
        $this->post('/performance/load')->assertRedirect('/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_admin_seeded_from_config_can_log_in_and_out(): void
    {
        config(['app.admin' => ['name' => 'Seeded Admin', 'email' => 'seed@example.test', 'password' => 'secret-pass']]);
        $this->seed(AdminSeeder::class);

        $this->post('/login', ['email' => 'seed@example.test', 'password' => 'secret-pass'])
            ->assertRedirect('/');
        $this->assertAuthenticated();

        $this->get('/')->assertOk()->assertSee('Demo Fixtures');

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'admin@example.test', 'password' => 'right-pass']);

        $this->from('/login')->post('/login', ['email' => 'admin@example.test', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_seeder_refuses_to_run_without_credentials(): void
    {
        config(['app.admin' => ['name' => 'x', 'email' => null, 'password' => null]]);

        $this->expectException(\RuntimeException::class);
        $this->seed(AdminSeeder::class);
    }
}
