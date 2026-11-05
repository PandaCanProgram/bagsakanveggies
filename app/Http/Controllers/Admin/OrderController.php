<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DailyOrderSummary;
use Illuminate\Http\RedirectResponse;

class OrderController extends Controller
{
    public function destroy(Order $order): RedirectResponse
    {
        $date = $order->created_at->setTimezone(DailyOrderSummary::TIMEZONE)->toDateString();
        $orderId = $order->id;

        $order->delete();

        return redirect()->route('admin.home', ['date' => $date, 'view' => 'people'])
            ->with('status', "Order #{$orderId} has been cancelled and permanently deleted.");
    }
}
