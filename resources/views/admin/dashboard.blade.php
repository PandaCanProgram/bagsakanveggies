@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    @php
        $isToday = $summary->day->isSameDay($today);
    @endphp

    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Dashboard</h1>
            <p class="a-muted">Everything ordered on the store, added up per day.</p>
        </div>
    </div>

    <section class="a-card" aria-labelledby="summary-title">
        <div class="a-toolbar a-summary-toolbar">
            <form method="GET" action="{{ route('admin.home') }}" class="a-day-picker">
                <a href="{{ route('admin.home', ['date' => $summary->day->subDay()->toDateString()]) }}" class="a-icon-btn" title="Previous day" aria-label="Previous day">
                    <x-admin.icon name="chevron-left" />
                </a>
                <label for="summary-date" class="sr-only">Day to show</label>
                {{-- A day picked from the calendar shows right away; typed dates wait for Enter or Show so half-typed ones don't reload the page. --}}
                <input id="summary-date" type="date" name="date" value="{{ $summary->day->toDateString() }}" max="{{ $today->toDateString() }}" required
                       class="a-input a-day-input @error('date') is-invalid @enderror"
                       x-data="{ typing: false }" @keydown="typing = true" @change="typing || $el.form.requestSubmit()"
                       @error('date') aria-invalid="true" aria-describedby="summary-date-error" @enderror>
                @if ($isToday)
                    <button type="button" class="a-icon-btn" aria-label="Next day" disabled>
                        <x-admin.icon name="chevron-right" />
                    </button>
                @else
                    <a href="{{ route('admin.home', ['date' => $summary->day->addDay()->toDateString()]) }}" class="a-icon-btn" title="Next day" aria-label="Next day">
                        <x-admin.icon name="chevron-right" />
                    </a>
                @endif
                <button type="submit" class="a-btn a-btn-secondary a-day-show">Show</button>
            </form>

            @if ($summary->orderCount > 0)
                <div class="a-summary-actions">
                    <button type="button" class="a-btn a-btn-secondary" onclick="window.print()">
                        <x-admin.icon name="printer" :size="18" />
                        Print
                    </button>
                    <a href="{{ route('admin.order-summary.export', ['date' => $summary->day->toDateString()]) }}" class="a-btn a-btn-primary">
                        <x-admin.icon name="download" :size="18" />
                        Download Excel
                    </a>
                </div>
            @endif

            @error('date')
                <p id="summary-date-error" class="a-field-error a-summary-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="a-summary-sheet">
            <h2 id="summary-title" class="a-summary-title">Daily order summary</h2>
            <p class="a-summary-date">
                {{ $summary->day->format('F j, Y') }}
                @if ($isToday)
                    <span class="a-day-tag">Today</span>
                @endif
            </p>
            <p class="a-summary-count">Orders received: <strong>{{ $summary->orderCount }}</strong></p>

            @if ($summary->orderCount === 0)
                <div class="a-empty a-summary-empty">
                    <span class="a-empty-icon"><x-admin.icon name="clipboard-list" :size="28" /></span>
                    <h3 class="a-empty-title">{{ $isToday ? 'No orders yet today' : 'No orders on this day' }}</h3>
                    <p class="a-muted">Orders placed on the store show up here, added up per item.</p>
                </div>
            @else
                <table class="a-summary-table">
                    <thead>
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col" class="a-num">Total Qty Ordered</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary->lines as $line)
                            <tr>
                                <th scope="row"><span class="a-emoji" aria-hidden="true">{{ $line['emoji'] }}</span> {{ $line['product'] }}</th>
                                <td class="a-num">{{ $line['quantity'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">TOTAL</th>
                            <td class="a-num">{{ $summary->total }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </section>
@endsection
