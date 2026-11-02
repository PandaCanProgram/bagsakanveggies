<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportPurchaseOrdersRequest;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrderIndexRequest;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DailyOrderSummary;
use App\Services\PurchaseOrderWorkbook;
use App\Support\SimpleXlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderController extends Controller
{
    public function index(PurchaseOrderIndexRequest $request): View
    {
        $day = $request->day();
        $search = $request->validated('search') ?? '';
        $query = PurchaseOrder::with(['purchaser', 'items'])
            ->when($day, fn (Builder $query): Builder => $query->whereDate('purchase_date', $day->toDateString()))
            ->matchingSearch($search);

        return view('admin.purchase-orders.index', [
            'purchaseOrders' => (clone $query)->latest('id')->paginate(15)->withQueryString(),
            'perPurchaser' => $request->query('view') === 'purchasers',
            'exportGroups' => PurchaseOrderWorkbook::selectionGroups((clone $query)->setEagerLoads([])->with('purchaser')->get(['id', 'name', 'purchaser_id', 'purchase_date'])),
            'isPurchaser' => false,
            'search' => $search,
            'day' => $day,
            'today' => DailyOrderSummary::today(),
        ]);
    }

    public function export(ExportPurchaseOrdersRequest $request): StreamedResponse
    {
        $day = $request->day();
        $validated = $request->validated();
        $orders = PurchaseOrder::with(['purchaser', 'items'])
            ->when($day, fn (Builder $query): Builder => $query->whereDate('purchase_date', $day->toDateString()))
            ->matchingSearch($validated['search'] ?? null)
            ->when(isset($validated['purchases']), fn (Builder $query): Builder => $query->whereIn('id', $validated['purchases']))
            ->orderBy('purchase_date')->orderBy('id')->get();
        abort_if($orders->isEmpty(), 422, 'There are no purchases to download in this date filter.');
        $perPurchaser = $validated['mode'] === 'purchasers';
        $sheet = PurchaseOrderWorkbook::build($orders, $perPurchaser, $day?->format('F j, Y') ?? 'All purchase dates');
        $filename = ($perPurchaser ? 'purchases-per-purchaser-' : 'all-purchases-').($day?->toDateString() ?? 'all-dates').'.xlsx';

        return response()->streamDownload(function () use ($sheet): void {
            echo $sheet->toString();
        }, $filename, ['Content-Type' => SimpleXlsx::MIME_TYPE]);
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
                'name' => $validated['name'],
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
