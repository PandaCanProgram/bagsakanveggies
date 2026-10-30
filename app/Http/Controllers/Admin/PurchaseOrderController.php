<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrderIndexRequest;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DailyOrderSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(PurchaseOrderIndexRequest $request): View
    {
        $day = $request->day();

        return view('admin.purchase-orders.index', [
            'purchaseOrders' => PurchaseOrder::with(['purchaser', 'items'])
                ->when($day, fn (Builder $query): Builder => $query->whereDate('purchase_date', $day->toDateString()))
                ->latest('id')->paginate(15)->withQueryString(),
            'isPurchaser' => false,
            'day' => $day,
            'today' => DailyOrderSummary::today(),
        ]);
    }

    public function create(): View
    {
        return view('admin.purchase-orders.create', [
            'purchasers' => User::where('is_purchaser', true)->where('is_admin', false)->orderBy('name')->get(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $order = DB::transaction(function () use ($request, $validated): PurchaseOrder {
            $order = PurchaseOrder::create([
                'created_by' => $request->user()->id,
                'purchaser_id' => $validated['purchaser_id'],
                'purchase_date' => $validated['purchase_date'],
                'remarks' => $validated['remarks'] ?? null,
            ]);
            $order->items()->createMany($validated['items']);

            return $order;
        });

        return redirect()->route('admin.purchase-orders.show', $order)
            ->with('status', $order->number().' has been sent to the purchaser.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('admin.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder->load(['purchaser', 'creator', 'items']),
            'isPurchaser' => false,
        ]);
    }
}
