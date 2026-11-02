<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PurchaseOrderSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
    }

    public function test_search_matches_partial_names_and_keeps_the_selected_day(): void
    {
        $match = PurchaseOrder::factory()->create(['name' => 'Barrio Fiesta', 'purchase_date' => '2026-11-02']);
        $old = PurchaseOrder::factory()->create(['name' => 'Barrio Older', 'purchase_date' => '2026-11-01']);
        PurchaseOrder::factory()->create(['name' => 'Unrelated', 'purchase_date' => '2026-11-02']);
        $this->get(route('admin.purchase-orders.index', ['date' => '2026-11-02', 'search' => 'bARRio']))
            ->assertOk()->assertSee('Barrio Fiesta')->assertDontSee('Barrio Older')->assertDontSee('Unrelated')
            ->assertSee('<span>PURCHASE ORDERS</span>', false)
            ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 1 && $orders->first()->id === $match->id);
        $this->get(route('admin.purchase-orders.index', ['all_dates' => 1, 'search' => 'bARRio']))
            ->assertOk()->assertSee($old->name)
            ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 2);
    }

    public function test_purchaser_names_and_emails_match_without_bypassing_the_date_filter(): void
    {
        $buyer = User::factory()->purchaser()->create(['name' => 'Market Buyer', 'email' => 'unique-buyer@example.com']);
        $match = PurchaseOrder::factory()->create(['name' => 'Today purchase', 'purchaser_id' => $buyer->id, 'purchase_date' => '2026-11-02']);
        PurchaseOrder::factory()->create(['name' => 'Old purchase', 'purchaser_id' => $buyer->id, 'purchase_date' => '2026-11-01']);
        $buyer->delete();
        foreach (['market BUYER', 'UNIQUE-BUYER@'] as $search) {
            $this->get(route('admin.purchase-orders.index', ['date' => '2026-11-02', 'search' => $search]))
                ->assertOk()->assertSee($match->name)->assertDontSee('Old purchase')
                ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 1);
        }
    }

    public function test_older_unnamed_orders_can_be_found_by_their_po_reference_or_id(): void
    {
        $order = PurchaseOrder::factory()->create(['purchase_date' => '2026-11-02']);
        PurchaseOrder::factory()->create(['name' => 'Unrelated', 'purchase_date' => '2026-11-02']);
        foreach ([$order->number(), strtolower($order->number()), (string) $order->id] as $search) {
            $this->get(route('admin.purchase-orders.index', ['date' => '2026-11-02', 'search' => $search]))
                ->assertOk()->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 1 && $orders->first()->id === $order->id);
        }
    }

    public function test_search_and_view_are_kept_in_pagination_filters_and_download_checkboxes(): void
    {
        $orders = PurchaseOrder::factory()->count(16)->create(['name' => 'Market list', 'purchase_date' => '2026-11-02']);
        $excluded = PurchaseOrder::factory()->create(['name' => 'Other list', 'purchase_date' => '2026-11-02']);
        $this->get(route('admin.purchase-orders.index', ['date' => '2026-11-02', 'search' => 'Market', 'view' => 'purchasers']))
            ->assertOk()->assertSee('name="search" value="Market"', false)
            ->assertSee('name="purchases[]" value="'.$orders->last()->id.'"', false)
            ->assertDontSee('name="purchases[]" value="'.$excluded->id.'"', false)
            ->assertSee('href="'.e(route('admin.purchase-orders.index', ['all_dates' => 1, 'view' => 'purchasers', 'search' => 'Market'])).'"', false)
            ->assertSee('Clear search')
            ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 16
                && str_contains($orders->nextPageUrl(), 'search=Market')
                && str_contains($orders->nextPageUrl(), 'view=purchasers'));
        $this->get(route('admin.purchase-orders.index', ['date' => '2026-11-02', 'search' => 'Market', 'page' => 2]))
            ->assertOk()->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->count() === 1);
    }

    public function test_like_characters_and_quotes_are_treated_as_literal_search_text(): void
    {
        $order = PurchaseOrder::factory()->create(['name' => 'Discount 50%_! sale', 'purchase_date' => '2026-11-02']);
        PurchaseOrder::factory()->create(['name' => 'Discount 50abc sale', 'purchase_date' => '2026-11-02']);
        $this->get(route('admin.purchase-orders.index', ['date' => '2026-11-02', 'search' => '50%_!']))
            ->assertOk()->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 1 && $orders->first()->id === $order->id);
        $this->get(route('admin.purchase-orders.index', ['all_dates' => 1, 'search' => "' OR 1=1 --"]))
            ->assertOk()->assertSee('No matching purchase orders')
            ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 0);
    }

    public function test_exports_follow_search_results_and_reject_unmatched_selected_purchases(): void
    {
        $match = PurchaseOrder::factory()->hasItems(1)->create(['name' => 'Matching purchase', 'purchase_date' => '2026-11-02']);
        $other = PurchaseOrder::factory()->hasItems(1)->create(['name' => 'Excluded purchase', 'purchase_date' => '2026-11-02']);
        $response = $this->get(route('admin.purchase-orders.export', ['date' => '2026-11-02', 'mode' => 'all', 'search' => 'Matching']))
            ->assertDownload('all-purchases-2026-11-02.xlsx');
        $this->assertStringContainsString($match->name, $response->streamedContent());
        $this->assertStringNotContainsString($other->name, $response->streamedContent());
        $this->getJson(route('admin.purchase-orders.export', ['date' => '2026-11-02', 'mode' => 'purchasers', 'selection' => 1, 'purchases' => [$other->id], 'search' => 'Matching']))
            ->assertUnprocessable()->assertJsonValidationErrors('purchases.0');
    }

    public function test_invalid_search_values_are_rejected_for_both_list_and_export(): void
    {
        foreach ([['bad'], str_repeat('a', 121)] as $search) {
            $this->getJson(route('admin.purchase-orders.index', ['search' => $search]))
                ->assertUnprocessable()->assertJsonValidationErrors('search');
            $this->getJson(route('admin.purchase-orders.export', ['mode' => 'all', 'search' => $search]))
                ->assertUnprocessable()->assertJsonValidationErrors('search');
        }
    }

    public function test_the_purchaser_panel_still_shows_only_assigned_orders(): void
    {
        $buyer = User::factory()->purchaser()->create();
        PurchaseOrder::factory()->create(['purchaser_id' => $buyer->id, 'name' => 'My list']);
        PurchaseOrder::factory()->create(['name' => 'Another purchasers list']);
        $this->actingAs($buyer, 'purchaser')->get(route('purchaser.home'))
            ->assertOk()->assertSee('My list')->assertDontSee('Another purchasers list')
            ->assertDontSee('id="po-search"', false);
    }
}
