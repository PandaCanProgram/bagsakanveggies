@extends('layouts.admin')

@section('title', 'Purchase orders')
@section('nav', 'purchase-orders')

@section('content')
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Purchase orders</h1>
            <p class="a-muted">{{ $isPurchaser ? 'Your assigned shopping lists. Record the actual prices as you buy.' : 'Assign a shopping list and compare estimated prices with actual costs.' }}</p>
        </div>
        @unless ($isPurchaser)
            <a href="{{ route('admin.purchase-orders.create') }}" class="a-btn a-btn-primary"><x-admin.icon name="plus" /> Create purchase order</a>
        @endunless
    </div>

    <div class="a-stack">
        @forelse ($purchaseOrders as $order)
            <article class="a-card a-card-pad po-list-card">
                <div>
                    <a class="po-order-link" href="{{ route($isPurchaser ? 'purchaser.purchase-orders.show' : 'admin.purchase-orders.show', $order) }}">{{ $order->number() }}</a>
                    <p class="a-muted">{{ $order->purchase_date->format('M j, Y') }} &middot; {{ $order->purchaser->name }}</p>
                    <p class="a-hint">{{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }} &middot; {{ $order->unpricedItemsCount() }} awaiting actual price</p>
                </div>
                <div class="po-list-totals">
                    <span class="po-status">{{ $order->status === \App\Models\PurchaseOrder::STATUS_COMPLETED ? 'Completed' : 'Assigned' }}</span>
                    <p>Estimate <strong>{{ \App\Support\PurchaseMoney::format($order->estimatedTotalCents()) }}</strong></p>
                    <p>Recorded actual <strong>{{ \App\Support\PurchaseMoney::format($order->actualTotalCents()) }}</strong></p>
                </div>
            </article>
        @empty
            <div class="a-card a-empty">
                <span class="a-empty-icon"><x-admin.icon name="clipboard-list" :size="28" /></span>
                <h2 class="a-empty-title">No purchase orders yet</h2>
                <p class="a-muted">{{ $isPurchaser ? 'Orders assigned to you by admin will appear here.' : 'Create a purchase order to send a shopping list to your purchaser.' }}</p>
            </div>
        @endforelse
        {{ $purchaseOrders->links() }}
    </div>
@endsection
