<?php

namespace App\Http\Controllers\Purchaser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchaser\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.purchase-orders.index', [
            'purchaseOrders' => PurchaseOrder::with(['purchaser', 'items'])
                ->where('purchaser_id', $request->user()->id)->latest('id')->paginate(15),
            'isPurchaser' => true,
        ]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        abort_unless($purchaseOrder->purchaser_id === $request->user()->id, 403);

        return view('admin.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder->load(['purchaser', 'creator', 'items']),
            'isPurchaser' => true,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $purchaseOrder, $validated): void {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_unless($order->purchaser_id === $request->user()->id, 403);
            abort_if($order->status === PurchaseOrder::STATUS_COMPLETED, 409, 'This purchase order is already completed.');

            foreach ($order->items as $item) {
                $entry = $validated['items'][$item->id];
                $item->forceFill([
                    'actual_unit_price' => $entry['actual_unit_price'] ?? null,
                    'purchaser_remarks' => $entry['purchaser_remarks'] ?? null,
                ])->save();
            }

            if ($validated['action'] === 'complete') {
                $order->forceFill(['status' => PurchaseOrder::STATUS_COMPLETED, 'completed_at' => now()])->save();
            }
        });

        return redirect()->route('purchaser.purchase-orders.show', $purchaseOrder)
            ->with('status', $validated['action'] === 'complete'
                ? 'Purchase order completed. The final actual costs are available to admin.'
                : 'Actual prices saved. Admin can now see your updates.');
    }
}
