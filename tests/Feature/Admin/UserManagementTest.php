<?php

namespace Tests\Feature\Admin;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_purchaser_accounts_and_open_the_creation_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $purchaser = User::factory()->purchaser()->create();
        $regular = User::factory()->create();
        $this->actingAs($admin, 'web')->get(route('admin.home'))->assertOk()->assertSee('User management');
        $this->get(route('admin.users.index'))->assertOk()->assertSee($purchaser->name)->assertSee($purchaser->email)
            ->assertDontSee($admin->email)->assertDontSee($regular->email)->assertDontSee($purchaser->password);
        $this->get(route('admin.users.create'))->assertOk()->assertSee('Confirm password')->assertSee('Create account')
            ->assertSee('name="role"', false)->assertSee('value="purchaser"', false)->assertDontSee('value="admin"', false);
    }

    public function test_admin_can_create_a_purchaser_without_changing_their_own_login(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin, 'web')->post(route('admin.users.store'), [
            ...$this->accountDetails(),
            'is_admin' => true,
            'is_purchaser' => false,
            'email_verified_at' => now()->toDateTimeString(),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'))->assertSessionHas('status');

        $purchaser = User::where('email', 'newbuyer@example.com')->sole();
        $this->assertSame('Market Buyer', $purchaser->name);
        $this->assertFalse($purchaser->is_admin);
        $this->assertTrue($purchaser->is_purchaser);
        $this->assertNull($purchaser->email_verified_at);
        $this->assertTrue(Hash::check('purchase12345', $purchaser->password));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get(route('admin.purchase-orders.create'))->assertOk()->assertSee($purchaser->email);

        $this->post(route('purchaser.login.store'), ['email' => $purchaser->email, 'password' => 'purchase12345'])
            ->assertRedirect(route('purchaser.home'));
        $this->assertAuthenticatedAs($purchaser, 'purchaser');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get(route('purchaser.home'))->assertOk()->assertDontSee('User management');
    }

    public function test_guests_and_purchasers_cannot_manage_users(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.users.create'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.users.store'), $this->accountDetails())->assertRedirect(route('admin.login'));

        $purchaser = User::factory()->purchaser()->create();
        $this->actingAs($purchaser, 'purchaser')->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.users.store'), $this->accountDetails())->assertRedirect(route('admin.login'));
        $this->actingAs($purchaser, 'web')->get(route('admin.users.create'))->assertForbidden();
        $this->post(route('admin.users.store'), $this->accountDetails())->assertForbidden();
        $this->actingAs(User::factory()->create(), 'web')->get(route('admin.users.index'))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'newbuyer@example.com']);
    }

    public function test_email_is_normalized_and_existing_accounts_cannot_be_overwritten(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'ExistingAdmin@example.com']);
        $purchaser = User::factory()->purchaser()->create(['email' => 'ExistingBuyer@example.com']);
        $this->actingAs($admin, 'web');
        foreach (['EXISTINGADMIN@example.com', ' existingbuyer@EXAMPLE.com '] as $email) {
            $this->post(route('admin.users.store'), [...$this->accountDetails(), 'email' => $email])
                ->assertSessionHasErrors('email');
        }
        $this->assertDatabaseCount('users', 2);
        $this->assertTrue(Hash::check('password', $purchaser->fresh()->password));
        $this->assertTrue($admin->fresh()->is_admin);

        $this->post(route('admin.users.store'), [...$this->accountDetails(), 'email' => ' NewBuyer@EXAMPLE.com '])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'newbuyer@example.com']);
    }

    #[DataProvider('invalidDetails')]
    public function test_invalid_account_details_are_rejected(string $field, string $value, string $error): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin, 'web')->post(route('admin.users.store'), [...$this->accountDetails(), $field => $value])
            ->assertSessionHasErrors($error);
        $this->assertDatabaseCount('users', 1);
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidDetails(): array
    {
        return [
            'missing name' => ['name', '', 'name'],
            'missing role' => ['role', '', 'role'],
            'unsupported role' => ['role', 'admin', 'role'],
            'long name' => ['name', str_repeat('a', 256), 'name'],
            'invalid email' => ['email', 'not-an-email', 'email'],
            'missing password' => ['password', '', 'password'],
            'short password' => ['password', 'short', 'password'],
            'confirmation mismatch' => ['password_confirmation', 'another-password', 'password'],
            'missing confirmation' => ['password_confirmation', '', 'password'],
        ];
    }

    public function test_passwords_that_exceed_the_hashing_limit_are_rejected_cleanly(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        foreach ([str_repeat('a', 73), str_repeat("\u{754C}", 25)] as $password) {
            $this->post(route('admin.users.store'), [
                ...$this->accountDetails(), 'password' => $password, 'password_confirmation' => $password,
            ])->assertSessionHasErrors('password');
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_validation_preserves_name_and_email_without_exposing_passwords(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [...$this->accountDetails(), 'email' => 'invalid'])
            ->assertRedirect(route('admin.users.create'))->assertSessionHasErrors('email');
        $this->assertSame('Market Buyer', session()->getOldInput('name'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->get(route('admin.users.create'))->assertOk()->assertSee('Market Buyer')->assertDontSee('purchase12345');
    }

    public function test_purchaser_accounts_are_paginated(): void
    {
        User::factory()->purchaser()->count(16)->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')
            ->get(route('admin.users.index', ['page' => 2]))->assertOk()
            ->assertViewHas('purchasers', fn ($purchasers): bool => $purchasers->total() === 16 && $purchasers->count() === 1);
    }

    public function test_empty_account_lists_link_to_account_creation_from_purchase_orders(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')
            ->get(route('admin.users.index'))->assertOk()->assertSee('No purchaser accounts yet');
        $this->get(route('admin.purchase-orders.create'))->assertOk()
            ->assertSee('href="'.e(route('admin.users.create')).'"', false);
    }

    public function test_each_account_has_an_edit_page_with_a_delete_option(): void
    {
        $purchaser = User::factory()->purchaser()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web')
            ->get(route('admin.users.index'))->assertOk()
            ->assertSee('href="'.e(route('admin.users.edit', $purchaser)).'"', false);
        $this->get(route('admin.users.edit', $purchaser))->assertOk()
            ->assertSee($purchaser->name)->assertSee($purchaser->email)->assertSee('New password (optional)')
            ->assertSee('Delete account')->assertDontSee($purchaser->password)
            ->assertSee('action="'.e(route('admin.users.destroy', $purchaser)).'"', false);
    }

    public function test_editing_details_keeps_the_password_when_left_blank_and_cannot_grant_admin_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $purchaser = User::factory()->purchaser()->create();
        $passwordHash = $purchaser->password;
        $this->actingAs($admin, 'web')->put(route('admin.users.update', $purchaser), [
            'name' => 'Updated Buyer', 'email' => ' UPDATED@EXAMPLE.COM ', 'password' => '', 'password_confirmation' => '', 'role' => 'purchaser',
            'is_admin' => true, 'is_purchaser' => false,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.edit', $purchaser));
        $purchaser->refresh();
        $this->assertSame('Updated Buyer', $purchaser->name);
        $this->assertSame('updated@example.com', $purchaser->email);
        $this->assertSame($passwordHash, $purchaser->password);
        $this->assertTrue($purchaser->is_purchaser);
        $this->assertFalse($purchaser->is_admin);
        $this->assertAuthenticatedAs($admin, 'web');
        $this->put(route('admin.users.update', $purchaser), ['name' => $purchaser->name, 'email' => $purchaser->email, 'role' => 'purchaser'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->put(route('admin.users.update', $purchaser), ['name' => $purchaser->name, 'email' => $purchaser->email, 'role' => 'admin'])
            ->assertSessionHasErrors('role');
        $this->assertFalse($purchaser->fresh()->is_admin);
    }

    public function test_admin_can_set_a_new_password_for_the_purchaser(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $purchaser = User::factory()->purchaser()->create(['remember_token' => 'old-remember-token']);
        $this->actingAs($admin, 'web')->put(route('admin.users.update', $purchaser), [
            'name' => $purchaser->name, 'email' => $purchaser->email, 'role' => 'purchaser',
            'password' => 'updated-purchaser-password', 'password_confirmation' => 'updated-purchaser-password',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(Hash::check('updated-purchaser-password', $purchaser->fresh()->password));
        $this->assertNull($purchaser->fresh()->remember_token);
        $this->post(route('purchaser.login.store'), ['email' => $purchaser->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->post(route('purchaser.login.store'), ['email' => $purchaser->email, 'password' => 'updated-purchaser-password'])
            ->assertSessionHasNoErrors()->assertRedirect(route('purchaser.home'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertAuthenticatedAs($purchaser, 'purchaser');
    }

    public function test_update_rejects_another_accounts_email_and_invalid_passwords(): void
    {
        $purchaser = User::factory()->purchaser()->create();
        $other = User::factory()->purchaser()->create(['email' => 'AnotherBuyer@example.com']);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $details = ['name' => $purchaser->name, 'email' => $purchaser->email, 'role' => 'purchaser'];
        $this->put(route('admin.users.update', $purchaser), [...$details, 'email' => strtolower($other->email)])
            ->assertSessionHasErrors('email');
        foreach ([['short', 'short'], ['new-password', 'different'], [str_repeat('a', 73), str_repeat('a', 73)]] as [$password, $confirmation]) {
            $this->put(route('admin.users.update', $purchaser), [
                ...$details, 'password' => $password, 'password_confirmation' => $confirmation,
            ])->assertSessionHasErrors('password');
        }
        $this->assertTrue(Hash::check('password', $purchaser->fresh()->password));
        $this->assertSame($details['email'], $purchaser->fresh()->email);
    }

    public function test_purchasers_and_guests_cannot_edit_or_delete_accounts(): void
    {
        $target = User::factory()->purchaser()->create();
        $this->get(route('admin.users.edit', $target))->assertRedirect(route('admin.login'));
        $this->put(route('admin.users.update', $target), $this->accountDetails())->assertRedirect(route('admin.login'));
        $this->delete(route('admin.users.destroy', $target))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->purchaser()->create(), 'purchaser');
        $this->put(route('admin.users.update', $target), $this->accountDetails())->assertRedirect(route('admin.login'));
        $this->delete(route('admin.users.destroy', $target))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create(), 'web');
        $this->get(route('admin.users.edit', $target))->assertForbidden();
        $this->put(route('admin.users.update', $target), $this->accountDetails())->assertForbidden();
        $this->delete(route('admin.users.destroy', $target))->assertForbidden();
        $this->assertFalse($target->fresh()->trashed());
    }

    public function test_user_management_cannot_edit_or_delete_admins_or_regular_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regular = User::factory()->create();
        $this->actingAs($admin, 'web');
        foreach ([$admin, $regular] as $target) {
            $this->get(route('admin.users.edit', $target))->assertNotFound();
            $this->put(route('admin.users.update', $target), $this->accountDetails())->assertForbidden();
            $this->delete(route('admin.users.destroy', $target))->assertNotFound();
            $this->assertFalse($target->fresh()->trashed());
        }
    }

    public function test_deletion_removes_access_and_new_assignments_while_keeping_purchase_order_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $purchaser = User::factory()->purchaser()->create();
        $order = PurchaseOrder::factory()->hasItems(1)->create(['created_by' => $admin->id, 'purchaser_id' => $purchaser->id]);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.home'));
        $this->post(route('purchaser.login.store'), ['email' => $purchaser->email, 'password' => 'password'])
            ->assertRedirect(route('purchaser.home'));
        $this->delete(route('admin.users.destroy', $purchaser))->assertRedirect(route('admin.users.index'))->assertSessionHas('status');
        $this->assertSoftDeleted($purchaser);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'purchaser_id' => $purchaser->id]);
        $this->assertDatabaseHas('purchase_order_items', ['purchase_order_id' => $order->id]);
        Auth::forgetGuards();
        $this->get(route('purchaser.home'))->assertRedirect(route('purchaser.login'));
        $this->get(route('admin.users.index'))->assertOk()->assertDontSee($purchaser->email);
        $this->get(route('admin.purchase-orders.create'))->assertOk()->assertDontSee($purchaser->email);
        $this->get(route('admin.purchase-orders.show', $order))->assertOk()->assertSee($purchaser->name);
        $this->assertAuthenticatedAs($admin, 'web');
        $this->post(route('purchaser.login.store'), ['email' => $purchaser->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('purchaser');
        $this->post(route('admin.purchase-orders.store'), [
            'purchaser_id' => $purchaser->id, 'purchase_date' => '2026-11-02',
            'items' => [['particulars' => 'Carrots', 'quantity' => '1', 'unit' => 'kg', 'estimated_unit_price' => '100']],
        ])->assertSessionHasErrors('purchaser_id');
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_deleted_accounts_cannot_be_reused_or_edited_through_their_old_urls(): void
    {
        $purchaser = User::factory()->purchaser()->create();
        $purchaser->delete();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $this->post(route('admin.users.store'), [...$this->accountDetails(), 'email' => $purchaser->email])
            ->assertSessionHasErrors('email');
        $this->get(route('admin.users.edit', $purchaser))->assertNotFound();
        $this->put(route('admin.users.update', $purchaser), $this->accountDetails())->assertNotFound();
        $this->delete(route('admin.users.destroy', $purchaser))->assertNotFound();
        $this->artisan('admin:create', ['email' => $purchaser->email])->assertFailed();
        $this->assertSoftDeleted($purchaser);
    }

    /** @return array{name: string, email: string, password: string, password_confirmation: string, role: string} */
    private function accountDetails(): array
    {
        return ['name' => 'Market Buyer', 'email' => 'newbuyer@example.com', 'password' => 'purchase12345', 'password_confirmation' => 'purchase12345', 'role' => 'purchaser'];
    }
}
