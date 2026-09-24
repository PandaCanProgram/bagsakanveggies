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

    public function add(int $productId, int $variantIndex, int $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $cart = $this->raw();
        $key = "{$productId}:{$variantIndex}";
        $cart[$key] = ($cart[$key] ?? 0) + $qty;
        $this->save($cart);
    }

    public function updateQty(int $productId, int $variantIndex, int $qty): void
    {
        $cart = $this->raw();
        $key = "{$productId}:{$variantIndex}";

        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key] = $qty;
        }

        $this->save($cart);
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
                'qty' => $qty,
                'line_total' => $variant['price'] * $qty,
            ];
        }

        return $lines;
    }

    public function itemCount(): int
    {
        return count($this->raw());
    }

    public function totalQty(): int
    {
        return array_sum($this->raw());
    }

    public function subtotal(): int
    {
        return collect($this->lines())->sum('line_total');
    }

    public function deliveryFee(): int
    {
        return 0;
    }

    public function total(): int
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
