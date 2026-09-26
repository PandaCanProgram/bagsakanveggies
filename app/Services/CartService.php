<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected const SESSION_KEY = 'cart';

    protected function raw(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    protected function save(array $cart): void
    {
        Session::put(self::SESSION_KEY, $cart);
    }

    public function add(int $productId, int $variantIndex, float $qty): void
    {
        $cart = $this->raw();
        $key = "{$productId}:{$variantIndex}";
        $qty = $this->normalize($productId, $variantIndex, ($cart[$key] ?? 0) + $qty);

        if ($qty > 0) {
            $cart[$key] = $qty;
            $this->save($cart);
        }
    }

    public function updateQty(int $productId, int $variantIndex, float $qty): void
    {
        $cart = $this->raw();
        $key = "{$productId}:{$variantIndex}";
        $qty = $this->normalize($productId, $variantIndex, $qty);

        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key] = $qty;
        }

        $this->save($cart);
    }

    /**
     * Round to the size's step (half kilos for per-kilo sizes, whole for bags); 0 for unknown products.
     */
    protected function normalize(int $productId, int $variantIndex, float $qty): float
    {
        $product = Product::find($productId);

        return $product?->variant($variantIndex) ? $product->normalizeQty($variantIndex, $qty) : 0.0;
    }

    public function remove(int $productId, int $variantIndex): void
    {
        $cart = $this->raw();
        unset($cart["{$productId}:{$variantIndex}"]);
        $this->save($cart);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Resolved cart lines with product/variant data and computed totals.
     */
    public function lines(): array
    {
        $cart = $this->raw();

        if (empty($cart)) {
            return [];
        }

        $productIds = collect(array_keys($cart))
            ->map(fn ($key) => (int) explode(':', $key)[0])
            ->unique();

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lines = [];

        foreach ($cart as $key => $qty) {
            [$productId, $variantIndex] = array_map('intval', explode(':', $key));
            $product = $products->get($productId);
            $variant = $product?->variant($variantIndex);

            if (! $product || ! $variant) {
                continue;
            }

            $lines[] = [
                'key' => $key,
                'product_id' => $productId,
                'variant_index' => $variantIndex,
                'name' => $product->name,
                'swatch' => $product->swatchColor(),
                'variant_label' => $variant['label'],
                'unit_price' => $variant['price'],
                'qty' => (float) $qty,
                'min' => $product->minQty($variantIndex),
                'line_total' => round($variant['price'] * $qty, 2),
            ];
        }

        return $lines;
    }

    public function itemCount(): int
    {
        return count($this->raw());
    }

    public function totalQty(): float
    {
        return (float) array_sum($this->raw());
    }

    public function subtotal(): float
    {
        return round(collect($this->lines())->sum('line_total'), 2);
    }

    public function deliveryFee(): float
    {
        return 0;
    }

    public function total(): float
    {
        return $this->subtotal() + $this->deliveryFee();
    }

    public function summary(): array
    {
        return [
            'item_count' => $this->itemCount(),
            'total_qty' => $this->totalQty(),
            'subtotal' => $this->subtotal(),
            'delivery_fee' => $this->deliveryFee(),
            'total' => $this->total(),
        ];
    }
}
