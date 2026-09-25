<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request, CartService $cart)
    {
        if ($cart->itemCount() === 0) {
            return redirect()->route('products.index')
                ->with('notice', 'Your cart is empty. Add some items first.');
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['required', 'date_format:H:i'],
            'order_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'contact_number.regex' => 'Enter a valid PH mobile number, e.g. 09171234567.',
        ]);

        $lines = $cart->lines();
        $summary = $cart->summary();

        $order = DB::transaction(function () use ($data, $lines, $summary) {
            $order = Order::create([
                'full_name' => $data['full_name'],
                'contact_number' => $data['contact_number'],
                'delivery_address' => $data['delivery_address'],
                'preferred_date' => $data['preferred_date'],
                'preferred_time' => $data['preferred_time'],
                'order_notes' => $data['order_notes'] ?? null,
                'payment_method' => 'Cash on Delivery',
                'subtotal' => $summary['subtotal'],
                'delivery_fee' => $summary['delivery_fee'],
                'total' => $summary['total'],
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'product_name' => $line['name'],
                    'variant_label' => $line['variant_label'],
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $order;
        });

        $cart->clear();

        // The customer already reviewed everything in the checkout dialog, so go straight to Messenger.
        if ($messengerUrl = $order->load('items')->messengerUrl()) {
            return redirect()->away($messengerUrl);
        }

        return redirect()->route('orders.confirmation', $order);
    }

    public function confirmation(Order $order)
    {
        return view('orders.confirmation', ['order' => $order->load('items')]);
    }
}
