<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(CartService $cart): View
    {
        return $this->shelf(Product::CATEGORY_VEGETABLE, $cart);
    }

    public function fruits(CartService $cart): View
    {
        return $this->shelf(Product::CATEGORY_FRUIT, $cart);
    }

    /**
     * The shop page for one category: its products, plus the cart shared by every page.
     */
    protected function shelf(string $category, CartService $cart): View
    {
        $products = Product::where('category', $category)->orderBy('sort_order')->get();

        return view('products.index', [
            'category' => $category,
            'products' => $products,
            'cartLines' => $cart->lines(),
            'cartSummary' => $cart->summary(),
        ]);
    }
}
