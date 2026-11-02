@extends('layouts.admin')

@section('title', 'Purchase orders')
@section('nav', 'purchase-orders')

@section('content')
    @php
        $indexRoute = $isPurchaser ? 'purchaser.home' : 'admin.purchase-orders.index';
        $pickerDay = $day ?? $today;
        $isToday = $day?->isSameDay($today) ?? false;
        $perPurchaser = $perPurchaser ?? false;
        $view = $perPurchaser ? 'purchasers' : null;
        $filter = ['date' => $day?->toDateString(), 'all_dates' => $day === null ? 1 : null];
        $visibleOrders = $perPurchaser ? $purchaseOrders->getCollection()->sortBy('purchaser_id') : $purchaseOrders;
        $previousPurchaser = null;
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

    @unless ($isPurchaser)
        <nav class="a-segmented po-view-switch" aria-label="Purchase order view">
            <a href="{{ route($indexRoute, $filter) }}" class="a-segmented-link" @unless ($perPurchaser) aria-current="page" @endunless>All purchases</a>
            <a href="{{ route($indexRoute, [...$filter, 'view' => 'purchasers']) }}" class="a-segmented-link" @if ($perPurchaser) aria-current="page" @endif>Per purchaser</a>
        </nav>
    @endunless

    <div class="a-stack">
        <section class="a-card" aria-labelledby="po-date-title">
            <div class="a-toolbar a-summary-toolbar">
                <form method="GET" action="{{ route($indexRoute) }}" class="a-day-picker">
                    <a href="{{ route($indexRoute, ['date' => $pickerDay->subDay()->toDateString(), 'view' => $view]) }}" class="a-icon-btn" title="Previous day" aria-label="Previous day">
                        <x-admin.icon name="chevron-left" />
                    </a>
                    @if ($view)<input type="hidden" name="view" value="{{ $view }}">@endif
                    <label for="po-date" class="sr-only">Purchase date to show</label>
                    <input id="po-date" type="date" name="date" value="{{ $day?->toDateString() }}" required
                           class="a-input a-day-input @error('date') is-invalid @enderror"
                           x-data="{ typing: false }" @keydown="typing = true" @change="typing || $el.form.requestSubmit()"
                           @error('date') aria-invalid="true" aria-describedby="po-date-error" @enderror>
                    <a href="{{ route($indexRoute, ['date' => $pickerDay->addDay()->toDateString(), 'view' => $view]) }}" class="a-icon-btn" title="Next day" aria-label="Next day">
                        <x-admin.icon name="chevron-right" />
                    </a>
                    <button type="submit" class="a-btn a-btn-secondary a-day-show">Show</button>
                </form>
                <div class="a-summary-actions">
                    @if (! $isPurchaser && $purchaseOrders->total() > 0)
                        @include('admin.purchase-orders.partials.export')
                    @endif
                    @unless ($isToday)
                        <a href="{{ route($indexRoute, ['view' => $view]) }}" class="a-btn a-btn-secondary">Today</a>
                    @endunless
                    <a href="{{ route($indexRoute, ['all_dates' => 1, 'view' => $view]) }}" class="a-btn a-btn-secondary" @if ($day === null) aria-current="page" @endif>All dates</a>
                </div>
                @error('date')<p id="po-date-error" class="a-field-error a-summary-error">{{ $message }}</p>@enderror
                @if ($errors->hasAny(['purchases', 'purchases.*', 'selection', 'mode']))
                    <p class="a-field-error a-summary-error" role="alert">{{ $errors->first('purchases') ?: $errors->first('purchases.*') ?: $errors->first('selection') ?: $errors->first('mode') }}</p>
                @endif
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

        @forelse ($visibleOrders as $order)
            @if ($perPurchaser && $previousPurchaser !== $order->purchaser_id)
                <h2 class="a-section-title">{{ $order->purchaser->name }}</h2>
            @endif
            @include('admin.purchase-orders.partials.order-card')
            @php($previousPurchaser = $order->purchaser_id)
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
