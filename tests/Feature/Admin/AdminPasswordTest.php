<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_see_the_change_password_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.password.edit'))
            ->assertOk()
            ->assertSee('Current password');
    }

    public function test_guests_cannot_change_a_password(): void
    {
        $this->get(route('admin.password.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.password.update'))->assertRedirect(route('admin.login'));
    }

    public function test_admins_can_change_their_password_with_the_current_one(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.password.update'), [
                'current_password' => 'password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect(route('admin.password.edit'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('brand-new-password', $admin->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.password.update'), [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }
}
