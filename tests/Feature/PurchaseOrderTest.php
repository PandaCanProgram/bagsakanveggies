<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Support\PurchaseMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchaser_sign_in_goes_to_the_purchaser_panel_even_after_visiting_an_admin_url(): void
    {
        $purchaser = User::factory()->purchaser()->create();

        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.store'), ['email' => $purchaser->email, 'password' => 'password'])
            ->assertRedirect(route('purchaser.home'));
        $this->assertAuthenticatedAs($purchaser, 'purchaser');
        $this->get(route('purchaser.login'))->assertRedirect(route('purchaser.home'));
        $this->get(route('purchaser.home'))->assertOk()->assertSee('Purchase orders')->assertDontSee('Print price list');
    }

    public function test_purchasers_cannot_access_admin_pages_or_create_purchase_orders(): void
    {
        $this->actingAs(User::factory()->purchaser()->create(), 'purchaser');
        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.products.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.purchase-orders.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.purchase-orders.store'), [])->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_guests_and_regular_users_cannot_access_purchase_orders(): void
    {
        $order = PurchaseOrder::factory()->create();
        $this->get(route('purchaser.home'))->assertRedirect(route('purchaser.login'));
        $this->put(route('purchaser.purchase-orders.update', $order), [])->assertRedirect(route('purchaser.login'));
        $this->get(route('admin.purchase-orders.create'))->assertRedirect(route('admin.login'));
        $regular = User::factory()->create();
        $this->actingAs($regular, 'purchaser');
        $this->get(route('purchaser.home'))->assertForbidden();
        $this->actingAs($regular, 'web');
        $this->get(route('admin.purchase-orders.show', $order))->assertForbidden();
    }

    public function test_admin_can_create_and_assign_a_purchase_order_with_fractional_quantities(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $purchaser = User::factory()->purchaser()->create();
        $this->actingAs($admin)->get(route('admin.purchase-orders.create'))->assertOk()->assertSee($purchaser->email);
        $payload = $this->newOrderPayload($purchaser);
        $payload['created_by'] = $purchaser->id;
        $payload['status'] = 'completed';
        $payload['total'] = 1;

        $this->post(route('admin.purchase-orders.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $order = PurchaseOrder::with('items')->sole();
        $this->assertSame($admin->id, $order->created_by);
        $this->assertSame($purchaser->id, $order->purchaser_id);
        $this->assertSame('assigned', $order->status);
        $this->assertSame(25025, $order->estimatedTotalCents());
        $this->assertNull($order->items->sole()->actual_unit_price);
        $this->actingAs($purchaser, 'purchaser')->get(route('purchaser.home', ['date' => $order->purchase_date->toDateString()]))->assertOk()->assertSee($order->number());
        $this->get(route('purchaser.purchase-orders.show', $order))->assertOk()->assertSee('Fresh carrots')->assertSee('Save progress');
    }

    public function test_admin_cannot_assign_an_order_to_an_admin_or_regular_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.purchase-orders.store'), $this->newOrderPayload($admin))
            ->assertSessionHasErrors('purchaser_id');
        $this->post(route('admin.purchase-orders.store'), $this->newOrderPayload(User::factory()->create()))
            ->assertSessionHasErrors('purchaser_id');
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_no_purchaser_accounts_shows_an_explanation_in_the_create_form(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.purchase-orders.create'))->assertOk()->assertSee('No purchaser account is available yet');
    }

    #[DataProvider('invalidEstimateValues')]
    public function test_invalid_estimates_and_quantities_are_rejected(string $field, string $value): void
    {
        $payload = $this->newOrderPayload(User::factory()->purchaser()->create());
        $payload['items'][0][$field] = $value;
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.purchase-orders.store'), $payload)->assertSessionHasErrors('items.0.'.$field);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidEstimateValues(): array
    {
        return [
            'zero quantity' => ['quantity', '0'],
            'too many quantity decimals' => ['quantity', '1.0001'],
            'negative price' => ['estimated_unit_price', '-1'],
            'too many price decimals' => ['estimated_unit_price', '1.999'],
            'scientific notation' => ['estimated_unit_price', '1e2'],
            'price limit' => ['estimated_unit_price', '1000000'],
        ];
    }

    public function test_purchasers_only_see_and_update_their_own_orders(): void
    {
        $mine = PurchaseOrder::factory()->hasItems(1)->create();
        $other = PurchaseOrder::factory()->hasItems(1)->create();
        $this->actingAs($mine->purchaser, 'purchaser')->get(route('purchaser.home'))
            ->assertOk()->assertSee($mine->number())->assertDontSee($other->number());
        $this->get(route('purchaser.purchase-orders.show', $other))->assertForbidden();
        $this->put(route('purchaser.purchase-orders.update', $other), $this->actualPayload($other, '95.25'))
            ->assertForbidden();
        $this->assertNull($other->items->sole()->fresh()->actual_unit_price);
    }

    public function test_saving_progress_keeps_the_estimates_and_updates_admin_figures(): void
    {
        $order = PurchaseOrder::factory()->hasItems(2)->create();
        $first = $order->items->first();
        $last = $order->items->last();
        $payload = ['action' => 'save', 'items' => [
            $first->id => ['actual_unit_price' => '110.25', 'purchaser_remarks' => 'Market price increased'],
            $last->id => ['actual_unit_price' => '', 'purchaser_remarks' => 'Not bought yet'],
        ]];

        $this->actingAs($order->purchaser, 'purchaser')->put(route('purchaser.purchase-orders.update', $order), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('purchaser.purchase-orders.show', $order));
        $order->refresh();
        $this->assertSame('assigned', $order->status);
        $this->assertSame('100.00', $first->fresh()->estimated_unit_price);
        $this->assertSame('110.25', $first->fresh()->actual_unit_price);
        $this->assertSame(27563, $order->actualTotalCents());
        $this->assertSame(1, $order->unpricedItemsCount());
        $this->actingAs($order->creator, 'web')->get(route('admin.purchase-orders.show', $order))
            ->assertOk()->assertSee('275.63')->assertSee('Market price increased')->assertSee('Pending');
    }

    public function test_completion_requires_all_actual_prices_and_a_complete_item_list(): void
    {
        $order = PurchaseOrder::factory()->hasItems(2)->create();
        $this->actingAs($order->purchaser, 'purchaser');
        $payload = $this->actualPayload($order, '', 'complete');
        $this->put(route('purchaser.purchase-orders.update', $order), $payload)
            ->assertSessionHasErrors('items.'.$order->items->first()->id.'.actual_unit_price');
        $payload = $this->actualPayload($order, '90.00', 'complete');
        unset($payload['items'][$order->items->last()->id]);
        $this->put(route('purchaser.purchase-orders.update', $order), $payload)->assertSessionHasErrors('items');
        $this->assertSame('assigned', $order->fresh()->status);
        $this->assertSame(2, $order->fresh()->unpricedItemsCount());
    }

    public function test_completion_records_final_actual_costs_and_locks_the_order(): void
    {
        $order = PurchaseOrder::factory()->hasItems(1)->create();
        $this->actingAs($order->purchaser, 'purchaser')
            ->put(route('purchaser.purchase-orders.update', $order), $this->actualPayload($order, '95.25', 'complete'))
            ->assertSessionHasNoErrors()->assertRedirect();
        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(23813, $order->actualTotalCents());
        $this->put(route('purchaser.purchase-orders.update', $order), $this->actualPayload($order, '1.00'))
            ->assertStatus(409);
        $this->assertSame('95.25', $order->items->sole()->fresh()->actual_unit_price);
        $this->get(route('purchaser.purchase-orders.show', $order))->assertOk()->assertDontSee('Save progress');
        $this->actingAs($order->creator, 'web')->get(route('admin.purchase-orders.show', $order))
            ->assertOk()->assertSee('Liquidation summary')->assertSee('Saved')->assertSee("\u{20B1}".'11.87')->assertDontSee('-'."\u{20B1}".'11.87');
    }

    public function test_zero_actual_price_is_recorded_and_is_not_treated_as_missing(): void
    {
        $order = PurchaseOrder::factory()->hasItems(1)->create();
        $this->actingAs($order->purchaser, 'purchaser')
            ->put(route('purchaser.purchase-orders.update', $order), $this->actualPayload($order, '0', 'complete'))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, $order->fresh()->unpricedItemsCount());
        $this->assertSame('0.00', $order->items->sole()->fresh()->actual_unit_price);
    }

    public function test_foreign_item_ids_and_estimate_tampering_are_rejected_without_partial_updates(): void
    {
        $order = PurchaseOrder::factory()->hasItems(1)->create();
        $other = PurchaseOrder::factory()->hasItems(1)->create();
        $payload = ['action' => 'save', 'items' => [$other->items->sole()->id => ['actual_unit_price' => '50.00']]];
        $this->actingAs($order->purchaser, 'purchaser')->put(route('purchaser.purchase-orders.update', $order), $payload)
            ->assertSessionHasErrors('items');
        $payload = $this->actualPayload($order, '50.00');
        $payload['items'][$order->items->sole()->id]['estimated_unit_price'] = '1.00';
        $this->put(route('purchaser.purchase-orders.update', $order), $payload)
            ->assertSessionHasErrors('items.'.$order->items->sole()->id);
        $this->assertSame('100.00', $order->items->sole()->fresh()->estimated_unit_price);
        $this->assertNull($order->items->sole()->fresh()->actual_unit_price);
    }

    public function test_negative_and_excess_precision_actual_prices_are_rejected(): void
    {
        $order = PurchaseOrder::factory()->hasItems(1)->create();
        $this->actingAs($order->purchaser, 'purchaser');
        foreach (['-1', '0.001', '1e2', '1000000'] as $price) {
            $this->put(route('purchaser.purchase-orders.update', $order), $this->actualPayload($order, $price))
                ->assertSessionHasErrors('items.'.$order->items->sole()->id.'.actual_unit_price');
        }
        $this->assertNull($order->items->sole()->fresh()->actual_unit_price);
    }

    public function test_purchasers_can_change_their_password_and_sign_out(): void
    {
        $purchaser = User::factory()->purchaser()->create();
        $this->actingAs($purchaser, 'purchaser')->get(route('purchaser.password.edit'))->assertOk()->assertSee('Purchase order system');
        $this->put(route('purchaser.password.update'), [
            'current_password' => 'password', 'password' => 'new-purchaser-password', 'password_confirmation' => 'new-purchaser-password',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(Hash::check('new-purchaser-password', $purchaser->fresh()->password));
        $this->post(route('purchaser.logout'))->assertRedirect(route('purchaser.login'));
        $this->assertGuest('purchaser');
    }

    public function test_purchaser_creation_command_hashes_password_without_granting_admin_access(): void
    {
        $this->artisan('purchaser:create', ['email' => 'purchaser@example.com'])
            ->expectsQuestion('Name', 'Market Purchaser')
            ->expectsQuestion('Password (at least 8 characters)', 'purchase123')
            ->expectsQuestion('Confirm password', 'purchase123')->assertSuccessful();
        $purchaser = User::where('email', 'purchaser@example.com')->sole();
        $this->assertTrue($purchaser->is_purchaser);
        $this->assertFalse($purchaser->is_admin);
        $this->assertTrue(Hash::check('purchase123', $purchaser->password));
    }

    public function test_purchaser_creation_command_does_not_overwrite_an_existing_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->artisan('purchaser:create', ['email' => $admin->email])->assertFailed();
        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertFalse($admin->fresh()->is_purchaser);
    }

    public function test_money_calculations_round_each_line_to_centavos_without_float_drift(): void
    {
        $this->assertSame(1, PurchaseMoney::lineTotal('0.001', '5.00'));
        $this->assertSame(333, PurchaseMoney::lineTotal('0.333', '10.00'));
        $this->assertSame(23813, PurchaseMoney::lineTotal('2.500', '95.25'));
        $this->assertSame(9999999800000, PurchaseMoney::lineTotal('99999.999', '999999.99'));
        $this->assertSame('-'."\u{20B1}".'11.87', PurchaseMoney::format(-1187));
    }

    /** @return array{purchaser_id: int, purchase_date: string, remarks: string, items: list<array{particulars: string, quantity: string, unit: string, estimated_unit_price: string, remarks: string}>} */
    private function newOrderPayload(User $purchaser): array
    {
        return [
            'purchaser_id' => $purchaser->id,
            'purchase_date' => '2026-10-27',
            'remarks' => 'Buy at the market',
            'items' => [['particulars' => 'Fresh carrots', 'quantity' => '2.500', 'unit' => 'kg', 'estimated_unit_price' => '100.10', 'remarks' => 'Choose fresh stock']],
        ];
    }

    /** @return array{action: string, items: array<int, array{actual_unit_price: string, purchaser_remarks: string}>} */
    private function actualPayload(PurchaseOrder $order, string $price, string $action = 'save'): array
    {
        return ['action' => $action, 'items' => $order->items->mapWithKeys(fn (PurchaseOrderItem $item): array => [
            $item->id => ['actual_unit_price' => $price, 'purchaser_remarks' => 'Purchased'],
        ])->all()];
    }
}
