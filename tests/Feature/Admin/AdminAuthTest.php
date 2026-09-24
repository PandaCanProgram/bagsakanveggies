<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_admin_sign_in_page(): void
    {
        $this->get(route('admin.products.index'))->assertRedirect(route('admin.login'));
    }

    public function test_signed_in_users_who_are_not_admins_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    public function test_admins_can_sign_in(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.products.index'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admins_cannot_sign_in_to_the_admin_page(): void
    {
        $user = User::factory()->create();

        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admins_can_sign_out(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_admin_create_command_makes_an_admin_account(): void
    {
        $this->artisan('admin:create', ['email' => 'owner@example.com'])
            ->expectsQuestion('Name', 'Store Owner')
            ->expectsQuestion('Password (at least 8 characters)', 'secret-password')
            ->expectsQuestion('Confirm password', 'secret-password')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'is_admin' => true]);
    }

    public function test_admin_create_command_can_reset_an_admin_password(): void
    {
        $admin = User::factory()->create(['email' => 'owner@example.com', 'is_admin' => true]);

        $this->artisan('admin:create', ['email' => 'owner@example.com'])
            ->expectsConfirmation('Set a new password for owner@example.com?', 'yes')
            ->expectsQuestion('Password (at least 8 characters)', 'brand-new-password')
            ->expectsQuestion('Confirm password', 'brand-new-password')
            ->assertSuccessful();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'brand-new-password'])
            ->assertRedirect(route('admin.products.index'));

        $this->assertAuthenticatedAs($admin);
    }
}
