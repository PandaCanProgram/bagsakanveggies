<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function add(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_index' => ['required', 'integer', 'min:0'],
            'items.*.qty' => ['required', 'numeric', 'min:0', 'max:999'],
        ]);

        foreach ($data['items'] as $item) {
            $cart->add((int) $data['product_id'], (int) $item['variant_index'], (float) $item['qty']);
        }

        return $this->response($cart);
    }

    public function update(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_index' => ['required', 'integer', 'min:0'],
            'qty' => ['required', 'numeric', 'min:0', 'max:999'],
        ]);

        $cart->updateQty((int) $data['product_id'], (int) $data['variant_index'], (float) $data['qty']);

        return $this->response($cart);
    }

    public function remove(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_index' => ['required', 'integer', 'min:0'],
        ]);

        $cart->remove((int) $data['product_id'], (int) $data['variant_index']);

        return $this->response($cart);
    }

    protected function response(CartService $cart)
    {
        return response()->json([
            'summary' => $cart->summary(),
            'lines' => $cart->lines(),
        ]);
    }
}
