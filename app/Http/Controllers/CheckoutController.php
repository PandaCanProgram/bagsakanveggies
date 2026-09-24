<?php

namespace App\Http\Controllers;

use App\Services\CartService;

class CheckoutController extends Controller
{
    public function index(CartService $cart)
    {
        if ($cart->itemCount() === 0) {
            return redirect()->route('products.index')
                ->with('notice', 'Your cart is empty. Add some items first.');
        }

        return view('checkout.index', [
            'lines' => $cart->lines(),
            'summary' => $cart->summary(),
        ]);
    }
}
