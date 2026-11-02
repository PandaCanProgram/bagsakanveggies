<article class="a-card a-card-pad po-list-card">
    <div>
        <a class="po-order-link" href="{{ route($isPurchaser ? 'purchaser.purchase-orders.show' : 'admin.purchase-orders.show', $order) }}">{{ $order->displayName() }}</a>
        @if (filled($order->name))<p class="a-hint">{{ $order->number() }}</p>@endif
        <p class="a-muted">{{ $order->purchase_date->format('M j, Y') }} &middot; {{ $order->purchaser->name }}</p>
        <p class="a-hint">{{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }} &middot; {{ $order->unpricedItemsCount() }} awaiting actual price</p>
    </div>
    <div class="po-list-totals">
        <span class="po-status">{{ $order->status === \App\Models\PurchaseOrder::STATUS_COMPLETED ? 'Completed' : 'Assigned' }}</span>
        <p>Estimate <strong>{{ \App\Support\PurchaseMoney::format($order->estimatedTotalCents()) }}</strong></p>
        <p>Recorded actual <strong>{{ \App\Support\PurchaseMoney::format($order->actualTotalCents()) }}</strong></p>
    </div>
</article>
