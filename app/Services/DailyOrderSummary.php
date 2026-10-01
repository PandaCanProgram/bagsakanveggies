<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything ordered on one day, added up per product: how much the store needs to buy and pack.
 */
class DailyOrderSummary
{
    /**
     * The store's timezone. Orders are saved in UTC, so this decides which day an order belongs to.
     */
    public const TIMEZONE = 'Asia/Manila';

    /**
     * @param  list<array{product: string, emoji: string, quantity: string}>  $lines  One line per product, A to Z.
     */
    public function __construct(
        public readonly CarbonImmutable $day,
        public readonly int $orderCount,
        public readonly array $lines,
        public readonly string $total,
    ) {}

    /**
     * Midnight today, store time.
     */
    public static function today(): CarbonImmutable
    {
        return now(self::TIMEZONE)->toImmutable()->startOfDay();
    }

    /**
     * Add up the orders placed on the given day, from midnight to midnight store time.
     */
    public static function for(CarbonImmutable $day): self
    {
        $start = $day->setTimezone(self::TIMEZONE)->startOfDay();
        $appTimezone = config('app.timezone');

        $orders = Order::query()
            ->where('created_at', '>=', $start->setTimezone($appTimezone))
            ->where('created_at', '<', $start->addDay()->setTimezone($appTimezone))
            ->with('items.product:id,name')
            ->get();

        $items = $orders->flatMap->items;

        // One line per product even if it was renamed during the day; deleted products keep the name they were ordered under.
        $lines = $items
            ->groupBy(fn (OrderItem $item) => $item->product_id ?? 'deleted: '.$item->product_name)
            ->map(function (Collection $productItems) {
                $name = $productItems->first()->product?->name ?? $productItems->first()->product_name;

                return [
                    'product' => $name,
                    'emoji' => Product::emojiFor($name),
                    'quantity' => self::describe(self::amounts($productItems)),
                ];
            })
            ->sortBy('product', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return new self($start, $orders->count(), $lines, self::describe(self::amounts($items)) ?: '0 kg');
    }

    /**
     * Add up order lines by unit: kilos for sizes sold by weight ("10 kg bag", "200 grams"),
     * pieces for sizes like "1 piece", and a plain count for any other size.
     *
     * @param  Collection<int, OrderItem>  $items
     * @return array<string, float> Amount by unit: "kg", "pcs", or the size itself.
     */
    protected static function amounts(Collection $items): array
    {
        $amounts = ['kg' => 0.0, 'pcs' => 0.0];

        foreach ($items as $item) {
            $qty = (float) $item->qty;
            $kilos = Product::kilosPerUnit($item->variant_label);

            if ($kilos !== null) {
                $amounts['kg'] += $qty * $kilos;
            } elseif (preg_match('/(?:(\d+)\s*)?(?<![a-z])(?:pcs?|pieces?)\b/i', $item->variant_label, $matches)) {
                $amounts['pcs'] += $qty * max(1, (int) ($matches[1] ?? 1));
            } else {
                $amounts[$item->variant_label] = ($amounts[$item->variant_label] ?? 0.0) + $qty;
            }
        }

        return array_filter($amounts);
    }

    /**
     * Amounts as text: "61.5 kg", "3 pcs", "2 kg + 1 pc", "2 × 1 bundle".
     *
     * @param  array<string, float>  $amounts
     */
    protected static function describe(array $amounts): string
    {
        return collect($amounts)
            ->map(fn (float $amount, int|string $unit) => match ($unit) {
                'kg' => Format::qty($amount, 3).' kg',
                'pcs' => Format::qty($amount).' '.($amount == 1 ? 'pc' : 'pcs'),
                default => Format::qty($amount).' × '.$unit,
            })
            ->implode(' + ');
    }
}
