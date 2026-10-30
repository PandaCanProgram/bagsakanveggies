@extends('layouts.admin')

@section('title', 'Purchase orders')
@section('nav', 'purchase-orders')

@section('content')
    @php
        $indexRoute = $isPurchaser ? 'purchaser.home' : 'admin.purchase-orders.index';
        $pickerDay = $day ?? $today;
        $isToday = $day?->isSameDay($today) ?? false;
    @endphp
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
        <section class="a-card" aria-labelledby="po-date-title">
            <div class="a-toolbar a-summary-toolbar">
                <form method="GET" action="{{ route($indexRoute) }}" class="a-day-picker">
                    <a href="{{ route($indexRoute, ['date' => $pickerDay->subDay()->toDateString()]) }}" class="a-icon-btn" title="Previous day" aria-label="Previous day">
                        <x-admin.icon name="chevron-left" />
                    </a>
                    <label for="po-date" class="sr-only">Purchase date to show</label>
                    <input id="po-date" type="date" name="date" value="{{ $day?->toDateString() }}" required
                           class="a-input a-day-input @error('date') is-invalid @enderror"
                           x-data="{ typing: false }" @keydown="typing = true" @change="typing || $el.form.requestSubmit()"
                           @error('date') aria-invalid="true" aria-describedby="po-date-error" @enderror>
                    <a href="{{ route($indexRoute, ['date' => $pickerDay->addDay()->toDateString()]) }}" class="a-icon-btn" title="Next day" aria-label="Next day">
                        <x-admin.icon name="chevron-right" />
                    </a>
                    <button type="submit" class="a-btn a-btn-secondary a-day-show">Show</button>
                </form>
                <div class="a-summary-actions">
                    @unless ($isToday)
                        <a href="{{ route($indexRoute) }}" class="a-btn a-btn-secondary">Today</a>
                    @endunless
                    <a href="{{ route($indexRoute, ['all_dates' => 1]) }}" class="a-btn a-btn-secondary" @if ($day === null) aria-current="page" @endif>All dates</a>
                </div>
                @error('date')<p id="po-date-error" class="a-field-error a-summary-error">{{ $message }}</p>@enderror
            </div>
            <div class="a-summary-sheet">
                <h2 id="po-date-title" class="a-summary-title">{{ $day ? 'Purchase orders for' : 'All purchase orders' }}</h2>
                <p class="a-summary-date">
                    {{ $day?->format('F j, Y') ?? 'All dates' }}
                    @if ($isToday)<span class="a-day-tag">Today</span>@endif
                </p>
                <p class="a-summary-count">Purchase orders: <strong>{{ $purchaseOrders->total() }}</strong></p>
            </div>
        </section>

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
                <h2 class="a-empty-title">{{ $day ? ($isToday ? 'No purchase orders today' : 'No purchase orders on this day') : 'No purchase orders yet' }}</h2>
                <p class="a-muted">{{ $isPurchaser ? 'Purchase orders assigned to you will appear here for the selected date.' : 'Choose another date or create a purchase order to send a shopping list to your purchaser.' }}</p>
            </div>
        @endforelse
        {{ $purchaseOrders->links() }}
    </div>
@endsection
