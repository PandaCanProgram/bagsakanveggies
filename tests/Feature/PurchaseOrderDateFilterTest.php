<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseOrderDateFilterTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('panels')]
    public function test_the_selected_day_filters_by_purchase_date_instead_of_creation_date(string $route, string $guard): void
    {
        $purchaser = $this->signIn($guard);
        $matching = PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-27', 'created_at' => '2026-10-20 12:00:00']);
        $differentDate = PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-28', 'created_at' => '2026-10-27 12:00:00']);

        $this->get(route($route, ['date' => '2026-10-27']))
            ->assertOk()->assertSee('October 27, 2026')->assertSee($matching->number())->assertDontSee($differentDate->number())
            ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 1)
            ->assertSee('href="'.e(route($route, ['date' => '2026-10-26'])).'"', false)
            ->assertSee('href="'.e(route($route, ['date' => '2026-10-28'])).'"', false);
    }

    #[DataProvider('panels')]
    public function test_the_default_day_is_today_in_manila(string $route, string $guard): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-29 16:30:00', 'UTC'));
        $purchaser = $this->signIn($guard);
        $today = PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-30']);
        $yesterday = PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-29']);

        $this->get(route($route))->assertOk()->assertSee('October 30, 2026')->assertSee($today->number())
            ->assertDontSee($yesterday->number())->assertSee('value="2026-10-30"', false)
            ->assertViewHas('day', fn (CarbonImmutable $day): bool => $day->toDateString() === '2026-10-30');
    }

    #[DataProvider('panels')]
    public function test_future_dates_can_show_planned_purchases(string $route, string $guard): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30 10:00:00', 'Asia/Manila'));
        $purchaser = $this->signIn($guard);
        $planned = PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-11-02']);

        $this->get(route($route, ['date' => '2026-11-02']))->assertOk()->assertSee($planned->number())
            ->assertSee('November 2, 2026')->assertSee('href="'.e(route($route, ['date' => '2026-11-03'])).'"', false)
            ->assertSee('href="'.e(route($route)).'"', false);
    }

    #[DataProvider('panels')]
    public function test_all_dates_retains_purchaser_access_restrictions(string $route, string $guard): void
    {
        $purchaser = $this->signIn($guard);
        $older = PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-27']);
        $newer = PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-30']);
        $other = PurchaseOrder::factory()->create(['purchase_date' => '2026-10-27']);

        $response = $this->get(route($route, ['all_dates' => 1, 'purchaser_id' => $other->purchaser_id]))
            ->assertOk()->assertSee('All purchase orders')->assertSee($older->number())->assertSee($newer->number())
            ->assertViewHas('day', null);

        if ($guard === 'purchaser') {
            $response->assertDontSee($other->number())
                ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 2);
            $this->get(route($route, ['date' => '2026-10-27']))->assertOk()->assertSee($older->number())->assertDontSee($other->number());
        } else {
            $response->assertSee($other->number())
                ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 3);
        }
    }

    #[DataProvider('panels')]
    public function test_pagination_preserves_the_chosen_date_and_all_dates_mode(string $route, string $guard): void
    {
        $purchaser = $this->signIn($guard);
        PurchaseOrder::factory()->count(16)->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-27']);
        PurchaseOrder::factory()->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-28']);

        $this->get(route($route, ['date' => '2026-10-27']))->assertOk()
            ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 16
                && str_contains($orders->nextPageUrl(), 'date=2026-10-27'));
        $this->get(route($route, ['date' => '2026-10-27', 'page' => 2]))->assertOk()
            ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 16 && $orders->count() === 1);
        $this->get(route($route, ['all_dates' => 1]))->assertOk()
            ->assertViewHas('purchaseOrders', fn (LengthAwarePaginator $orders): bool => $orders->total() === 17
                && str_contains($orders->nextPageUrl(), 'all_dates=1'));
    }

    #[DataProvider('panels')]
    public function test_invalid_dates_are_rejected_and_show_a_useful_message(string $route, string $guard): void
    {
        $this->signIn($guard);
        foreach (['2026-02-30', 'not-a-date'] as $date) {
            $this->getJson(route($route, ['date' => $date]))->assertUnprocessable()->assertJsonValidationErrors('date');
        }
        $this->from(route($route))->followingRedirects()->get(route($route, ['date' => '2026-02-30']))
            ->assertOk()->assertSee('Pick a day from the calendar.');
    }

    #[DataProvider('panels')]
    public function test_an_empty_day_and_the_back_link_use_the_selected_purchase_date(string $route, string $guard): void
    {
        $purchaser = $this->signIn($guard);
        $order = PurchaseOrder::factory()->hasItems(1)->create(['purchaser_id' => $purchaser->id, 'purchase_date' => '2026-10-27']);
        $this->get(route($route, ['date' => '2026-10-28']))->assertOk()->assertSee('No purchase orders on this day')
            ->assertDontSee($order->number());
        $showRoute = $guard === 'web' ? 'admin.purchase-orders.show' : 'purchaser.purchase-orders.show';
        $this->get(route($showRoute, $order))->assertOk()
            ->assertSee('href="'.e(route($route, ['date' => '2026-10-27'])).'"', false);
    }

    /** @return array<string, array{string, string}> */
    public static function panels(): array
    {
        return [
            'admin' => ['admin.purchase-orders.index', 'web'],
            'purchaser' => ['purchaser.home', 'purchaser'],
        ];
    }

    private function signIn(string $guard): User
    {
        $purchaser = User::factory()->purchaser()->create();
        $actor = $guard === 'web' ? User::factory()->create(['is_admin' => true]) : $purchaser;
        $this->actingAs($actor, $guard);

        return $purchaser;
    }
}
