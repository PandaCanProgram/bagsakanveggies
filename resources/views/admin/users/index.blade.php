@extends('layouts.admin')

@section('title', 'User management')
@section('nav', 'users')

@section('content')
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">User management</h1>
            <p class="a-muted">Manage user accounts and their roles.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="a-btn a-btn-primary"><x-admin.icon name="plus" /> Create</a>
    </div>

    <div class="a-stack">
        <h2 class="a-section-title">Accounts ({{ $purchasers->total() }})</h2>
        @forelse ($purchasers as $purchaser)
            <article class="a-card a-card-pad a-user-card">
                <div class="a-user-details">
                    <h3 class="a-user-name">{{ $purchaser->name }}</h3>
                    <p class="a-muted">{{ $purchaser->email }}</p>
                </div>
                <div class="a-user-details">
                    <span class="a-brand-chip">Purchaser</span>
                    <p class="a-hint">Added {{ $purchaser->created_at->timezone(\App\Services\DailyOrderSummary::TIMEZONE)->format('M j, Y') }}</p>
                </div>
                <a href="{{ route('admin.users.edit', $purchaser) }}" class="a-btn a-btn-secondary"><x-admin.icon name="pencil" :size="18" /> Edit<span class="sr-only"> {{ $purchaser->name }}</span></a>
            </article>
        @empty
            <div class="a-card a-empty">
                <span class="a-empty-icon"><x-admin.icon name="users" :size="28" /></span>
                <h3 class="a-empty-title">No purchaser accounts yet</h3>
                <p class="a-muted">Add a purchaser so you can assign shopping lists and receive actual prices.</p>
            </div>
        @endforelse
        {{ $purchasers->links() }}
    </div>
@endsection
