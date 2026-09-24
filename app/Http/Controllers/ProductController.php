<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;

class ProductController extends Controller
{
    public function index(CartService $cart)
    {
        $products = Product::orderBy('sort_order')->get();

        return view('products.index', [
            'products' => $products,
            'cartLines' => $cart->lines(),
            'cartSummary' => $cart->summary(),
        ]);
    }
}
