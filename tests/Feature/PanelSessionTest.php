<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_open_purchaser_sign_in_without_signing_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.home'));
        $this->get(route('purchaser.home'))->assertRedirect(route('purchaser.login'));
        $this->get(route('purchaser.login'))->assertOk()->assertSee('Purchaser sign in')->assertDontSee('id="admin-menu"', false);
        $this->get(route('admin.purchase-orders.index'))->assertOk()->assertSee('Create purchase order');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_both_panels_keep_their_own_account_when_the_same_browser_signs_in_twice(): void
    {
        [$admin, $purchaser] = $this->signInBothPanels();
        Auth::forgetGuards();

        $this->get(route('admin.home'))->assertOk()->assertSee('Print price list')->assertDontSee('Open purchaser panel');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get(route('purchaser.home'))->assertOk()->assertDontSee('Print price list')->assertDontSee('Open admin panel')
            ->assertSee('Purchase order system')->assertSee(explode('@', $purchaser->email)[0]);
        $this->assertAuthenticatedAs($purchaser, 'purchaser');
        $this->get(route('admin.purchase-orders.create'))->assertOk()->assertSee($purchaser->email);
        $this->get(route('purchaser.password.edit'))->assertOk()->assertSee($purchaser->email)->assertDontSee($admin->email);
    }

    public function test_a_purchaser_can_open_admin_sign_in_and_keep_the_purchaser_login(): void
    {
        $purchaser = User::factory()->purchaser()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->post(route('admin.login.store'), ['email' => $purchaser->email, 'password' => 'password'])
            ->assertRedirect(route('purchaser.home'));
        $this->get(route('admin.login'))->assertOk()->assertDontSee('id="admin-menu"', false);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.home'));
        Auth::forgetGuards();
        $this->get(route('purchaser.home'))->assertOk();
        $this->get(route('admin.home'))->assertOk();
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertAuthenticatedAs($purchaser, 'purchaser');
    }

    public function test_signing_out_of_purchasing_keeps_admin_and_its_open_forms_signed_in(): void
    {
        [$admin] = $this->signInBothPanels();
        $token = session()->token();
        $this->post(route('purchaser.logout'))->assertRedirect(route('purchaser.login'));
        Auth::forgetGuards();
        $this->get(route('admin.home'))->assertOk();
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertGuest('purchaser');
        $this->assertSame($token, session()->token());
        $this->get(route('purchaser.home'))->assertRedirect(route('purchaser.login'));
    }

    public function test_signing_out_of_admin_keeps_purchasing_and_its_open_forms_signed_in(): void
    {
        [, $purchaser] = $this->signInBothPanels();
        $token = session()->token();
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        Auth::forgetGuards();
        $this->get(route('purchaser.home'))->assertOk();
        $this->assertAuthenticatedAs($purchaser, 'purchaser');
        $this->assertGuest('web');
        $this->assertSame($token, session()->token());
        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
    }

    public function test_changing_the_purchaser_password_only_changes_that_account(): void
    {
        [$admin, $purchaser] = $this->signInBothPanels();
        $token = session()->token();
        $this->put(route('purchaser.password.update'), [
            'current_password' => 'password',
            'password' => 'changed-purchaser-password',
            'password_confirmation' => 'changed-purchaser-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('purchaser.password.edit'));
        $this->assertTrue(Hash::check('changed-purchaser-password', $purchaser->fresh()->password));
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
        $this->assertSame($token, session()->token());
        Auth::forgetGuards();
        $this->get(route('admin.home'))->assertOk();
        $this->get(route('purchaser.home'))->assertOk();
    }

    public function test_invalid_purchaser_credentials_do_not_replace_an_existing_admin_login(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $purchaser = User::factory()->purchaser()->create();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->post(route('purchaser.login.store'), ['email' => $purchaser->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertGuest('purchaser');
        $this->get(route('admin.home'))->assertOk();
    }

    public function test_admin_and_purchaser_can_hand_off_a_po_without_replacing_each_others_login(): void
    {
        [$admin, $purchaser] = $this->signInBothPanels();
        $this->post(route('admin.purchase-orders.store'), [
            'purchaser_id' => $purchaser->id,
            'purchase_date' => '2026-10-27',
            'items' => [['particulars' => 'Carrots', 'quantity' => '2', 'unit' => 'kg', 'estimated_unit_price' => '100']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $order = PurchaseOrder::with('items')->sole();
        $this->assertSame($admin->id, $order->created_by);
        $this->get(route('purchaser.purchase-orders.show', $order))->assertOk()->assertSee('Carrots');
        $this->put(route('purchaser.purchase-orders.update', $order), [
            'action' => 'complete',
            'items' => [$order->items->sole()->id => ['actual_unit_price' => '120']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        Auth::forgetGuards();
        $this->get(route('admin.purchase-orders.show', $order))->assertOk()->assertSee('240.00')->assertSee('Liquidation summary');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertAuthenticatedAs($purchaser, 'purchaser');
    }

    /** @return array{User, User} */
    private function signInBothPanels(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $purchaser = User::factory()->purchaser()->create();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.home'));
        $token = session()->token();
        $sessionId = session()->getId();
        $this->post(route('purchaser.login.store'), ['email' => $purchaser->email, 'password' => 'password'])
            ->assertRedirect(route('purchaser.home'));
        $this->assertSame($token, session()->token());
        $this->assertNotSame($sessionId, session()->getId());

        return [$admin, $purchaser];
    }
}
