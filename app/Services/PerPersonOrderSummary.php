<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything ordered on one day, person by person: what to pack for each customer, where it goes and how much to collect.
 */
class PerPersonOrderSummary
{
    /**
     * @param  list<array{name: string, contact_number: string, addresses: list<string>, orders: list<array{id: int, time: string}>, lines: list<array{product: string, emoji: string, size: string, qty: float, amount: float}>, delivery_fee: float, total: float}>  $people  In the order they first ordered.
     */
    public function __construct(
        public readonly CarbonImmutable $day,
        public readonly int $orderCount,
        public readonly array $people,
        public readonly float $total,
    ) {}

    /**
     * Group the orders placed on the given day, from midnight to midnight store time, by the person who placed them.
     */
    public static function for(CarbonImmutable $day): self
    {
        $start = $day->setTimezone(DailyOrderSummary::TIMEZONE)->startOfDay();

        $orders = Order::query()
            ->placedOn($start)
            ->with('items.product:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // A person is a CP/Viber number, so someone who checks out twice gets one packing list.
        $people = $orders
            ->groupBy(fn (Order $order) => preg_replace('/\D/', '', $order->contact_number))
            ->map(fn (Collection $personOrders) => [
                'name' => self::distinct($personOrders->pluck('full_name'))->implode(' / '),
                'contact_number' => $personOrders->last()->contact_number,
                'addresses' => self::distinct($personOrders->pluck('delivery_address'))->all(),
                'orders' => $personOrders->map(fn (Order $order) => [
                    'id' => $order->id,
                    'time' => $order->created_at->setTimezone(DailyOrderSummary::TIMEZONE)->format('g:i A'),
                ])->values()->all(),
                'lines' => self::lines($personOrders->flatMap->items),
                'delivery_fee' => (float) $personOrders->sum('delivery_fee'),
                'total' => (float) $personOrders->sum('total'),
            ])
            ->values()
            ->all();

        return new self($start, $orders->count(), $people, (float) $orders->sum('total'));
    }

    /**
     * One line per product and size, A to Z; the same item ordered twice is added up.
     *
     * @param  Collection<int, OrderItem>  $items
     * @return list<array{product: string, emoji: string, size: string, qty: float, amount: float}>
     */
    protected static function lines(Collection $items): array
    {
        return $items
            ->groupBy(fn (OrderItem $item) => ($item->product_id ?? 'deleted: '.$item->product_name).' | '.$item->variant_label)
            ->map(function (Collection $sameItems) {
                $name = $sameItems->first()->product?->name ?? $sameItems->first()->product_name;

                return [
                    'product' => $name,
                    'emoji' => Product::emojiFor($name),
                    'size' => $sameItems->first()->variant_label,
                    'qty' => (float) $sameItems->sum('qty'),
                    'amount' => (float) $sameItems->sum('line_total'),
                ];
            })
            ->sortBy('product', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Names or addresses typed more than once, kept once even if spacing or capitals differ.
     *
     * @param  Collection<int, string>  $texts
     * @return Collection<int, string>
     */
    protected static function distinct(Collection $texts): Collection
    {
        return $texts->map(fn (string $text) => Str::squish($text))
            ->unique(fn (string $text) => Str::lower($text))
            ->values();
    }
}
