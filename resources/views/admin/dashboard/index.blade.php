@extends('layouts.admin')

@section('title', 'Dashboard')
@section('nav', 'dashboard')

@section('content')
    @php
        $isToday = $summary->day->isSameDay($today);
        $date = $summary->day->toDateString();
        // Moving between days keeps the chosen view; "All orders" is the default and needs no ?view=.
        $view = $perPerson ? 'people' : null;
        $peopleCount = $perPerson ? count($summary->people) : 0;
    @endphp

    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Dashboard</h1>
            <p class="a-muted">{{ $perPerson ? 'Each person’s order for the day, ready to pack and deliver.' : 'Everything ordered on the store, added up per day.' }}</p>
        </div>

        {{-- Switching views stays on the same day; on today the link leaves the date out so it still means "today" tomorrow. --}}
        <nav class="a-segmented" aria-label="Order summary">
            <a href="{{ route('admin.home', ['date' => $isToday ? null : $date]) }}" class="a-segmented-link" @unless ($perPerson) aria-current="page" @endunless>
                <x-admin.icon name="clipboard-list" :size="18" />
                All orders
            </a>
            <a href="{{ route('admin.home', ['date' => $isToday ? null : $date, 'view' => 'people']) }}" class="a-segmented-link" @if ($perPerson) aria-current="page" @endif>
                <x-admin.icon name="users" :size="18" />
                Per person
            </a>
        </nav>
    </div>

    <section class="a-card" aria-labelledby="summary-title">
        <div class="a-toolbar a-summary-toolbar">
            <form method="GET" action="{{ route('admin.home') }}" class="a-day-picker">
                @if ($view)
                    <input type="hidden" name="view" value="{{ $view }}">
                @endif
                <a href="{{ route('admin.home', ['date' => $summary->day->subDay()->toDateString(), 'view' => $view]) }}" class="a-icon-btn" title="Previous day" aria-label="Previous day">
                    <x-admin.icon name="chevron-left" />
                </a>
                <label for="summary-date" class="sr-only">Day to show</label>
                {{-- A day picked from the calendar shows right away; typed dates wait for Enter or Show so half-typed ones don't reload the page. --}}
                <input id="summary-date" type="date" name="date" value="{{ $date }}" max="{{ $today->toDateString() }}" required
                       class="a-input a-day-input @error('date') is-invalid @enderror"
                       x-data="{ typing: false }" @keydown="typing = true" @change="typing || $el.form.requestSubmit()"
                       @error('date') aria-invalid="true" aria-describedby="summary-date-error" @enderror>
                @if ($isToday)
                    <button type="button" class="a-icon-btn" aria-label="Next day" disabled>
                        <x-admin.icon name="chevron-right" />
                    </button>
                @else
                    <a href="{{ route('admin.home', ['date' => $summary->day->addDay()->toDateString(), 'view' => $view]) }}" class="a-icon-btn" title="Next day" aria-label="Next day">
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
                    <a href="{{ route($perPerson ? 'admin.orders-per-person.export' : 'admin.order-summary.export', ['date' => $date]) }}" class="a-btn a-btn-primary">
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
            <h2 id="summary-title" class="a-summary-title">{{ $perPerson ? 'Orders per person' : 'Daily order summary' }}</h2>
            <p class="a-summary-date">
                {{ $summary->day->format('F j, Y') }}
                @if ($isToday)
                    <span class="a-day-tag">Today</span>
                @endif
            </p>
            <p class="a-summary-count">
                Orders received: <strong>{{ $summary->orderCount }}</strong>
                @if ($peopleCount > 0)
                    from <strong>{{ $peopleCount }}</strong> {{ \Illuminate\Support\Str::plural('person', $peopleCount) }}
                @endif
            </p>

            @if ($summary->orderCount === 0)
                <div class="a-empty a-summary-empty">
                    <span class="a-empty-icon"><x-admin.icon name="clipboard-list" :size="28" /></span>
                    <h3 class="a-empty-title">{{ $isToday ? 'No orders yet today' : 'No orders on this day' }}</h3>
                    <p class="a-muted">Orders placed on the store show up here, {{ $perPerson ? 'one list per person' : 'added up per item' }}.</p>
                </div>
            @elseif ($perPerson)
                @include('admin.dashboard.partials.per-person')
            @else
                @include('admin.dashboard.partials.all-orders')
            @endif
        </div>
    </section>
@endsection
