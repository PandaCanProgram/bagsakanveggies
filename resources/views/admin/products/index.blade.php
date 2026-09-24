@extends('layouts.admin')

@section('title', 'Veggies & prices')

@section('content')
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Veggies &amp; prices</h1>
            <p class="a-muted">
                {{ trans_choice(':count veggie|:count veggies', $products->count()) }} on the store
                @if ($lastUpdatedAt)
                    · last change {{ $lastUpdatedAt->diffForHumans() }}
                @endif
            </p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="a-btn a-btn-primary">
            <x-admin.icon name="plus" :size="18" />
            Add veggie
        </a>
    </div>

    @if ($products->isEmpty())
        <div class="a-card a-empty">
            <span class="a-empty-icon"><x-admin.icon name="carrot" :size="28" /></span>
            <h2 class="a-empty-title">No veggies yet</h2>
            <p class="a-muted">Add your first veggie and it will show up on the store right away.</p>
            <a href="{{ route('admin.products.create') }}" class="a-btn a-btn-primary">
                <x-admin.icon name="plus" :size="18" />
                Add veggie
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('admin.products.prices.update') }}" x-data="priceEditor" @input="refresh()" @submit="submitting = true" novalidate>
            @csrf
            @method('PATCH')

            @if ($errors->any())
                <div class="a-alert a-alert-error" role="alert" tabindex="-1" x-init="$el.focus()">
                    <x-admin.icon name="alert-circle" :size="20" />
                    <span>Some prices need fixing before they can be saved. They’re marked in red below.</span>
                </div>
            @endif

            <div class="a-card">
                <div class="a-toolbar">
                    <div class="a-search">
                        <label for="veggie-search" class="sr-only">Search veggies</label>
                        <x-admin.icon name="search" :size="18" class="a-search-icon" />
                        <input id="veggie-search" type="search" class="a-input a-search-input" placeholder="Search veggies" x-model="query" autocomplete="off" @keydown.enter.prevent>
                    </div>
                    <p class="a-toolbar-hint a-muted">Type a new price, then save. Changes go live on the store right away.</p>
                </div>

                <div class="a-table-wrap">
                    <table class="a-table">
                        <thead>
                            <tr>
                                <th scope="col">Veggie</th>
                                <th scope="col">Sizes &amp; prices</th>
                                <th scope="col" class="a-col-updated">Last changed</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr data-name="{{ strtolower(trim($product->name.' '.$product->note)) }}" x-show="matches($el.dataset.name)">
                                    <th scope="row" class="a-cell-name">
                                        <div class="a-name-wrap">
                                            @if ($imageUrl = $product->imageUrl())
                                                <img src="{{ $imageUrl }}" alt="" class="a-thumb" width="48" height="48" loading="lazy" decoding="async">
                                            @else
                                                <span class="a-thumb a-thumb-empty" title="No photo yet"><x-admin.icon name="image" :size="18" /></span>
                                            @endif
                                            <span>
                                                <span class="a-product-name">{{ $product->name }}</span>
                                                @if ($product->note)
                                                    <span class="a-product-note">{{ $product->note }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </th>
                                    <td>
                                        <div class="a-price-list">
                                            @foreach ($product->variants as $index => $variant)
                                                @php
                                                    $field = "prices.{$product->id}.{$index}";
                                                    $inputId = "price-{$product->id}-{$index}";
                                                @endphp
                                                <div class="a-price-field @error($field) is-invalid @enderror">
                                                    <label for="{{ $inputId }}" class="a-price-label">
                                                        {{ $variant['label'] }}
                                                        <span class="sr-only">price for {{ $product->name }}</span>
                                                    </label>
                                                    <div class="a-money">
                                                        <span class="a-money-sign" aria-hidden="true">₱</span>
                                                        <input id="{{ $inputId }}" name="prices[{{ $product->id }}][{{ $index }}]"
                                                               type="number" inputmode="numeric" min="1" step="1"
                                                               class="a-input a-money-input"
                                                               value="{{ old("prices.{$product->id}.{$index}", $variant['price']) }}"
                                                               data-original="{{ $variant['price'] }}"
                                                               @error($field) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @enderror>
                                                    </div>
                                                    <span class="a-price-was">was ₱{{ number_format($variant['price']) }}</span>
                                                    @error($field)
                                                        <span id="{{ $inputId }}-error" class="a-field-error">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="a-col-updated a-muted">
                                        <time datetime="{{ $product->updated_at?->toIso8601String() }}" title="{{ $product->updated_at?->format('M j, Y g:i A') }}">
                                            {{ $product->updated_at?->diffForHumans() ?? '—' }}
                                        </time>
                                    </td>
                                    <td class="a-cell-actions">
                                        <a href="{{ route('admin.products.edit', $product) }}" class="a-btn a-btn-secondary a-btn-sm">
                                            <x-admin.icon name="pencil" :size="16" />
                                            Edit <span class="sr-only">{{ $product->name }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            <tr x-show="noMatches" x-cloak>
                                <td colspan="4" class="a-no-results">
                                    No veggies match “<span x-text="query"></span>”.
                                    <button type="button" class="a-link-btn" @click="query = ''">Clear search</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="a-savebar" x-show="dirtyCount > 0" x-cloak x-transition.opacity.duration.200ms role="region" aria-label="Unsaved price changes">
                <div class="a-savebar-inner">
                    <p class="a-savebar-text" aria-live="polite">
                        <strong x-text="dirtyCount"></strong>
                        <span x-text="dirtyCount === 1 ? 'price changed' : 'prices changed'"></span>
                        <span class="a-hide-sm">· not saved yet</span>
                    </p>
                    <div class="a-savebar-actions">
                        <button type="button" class="a-btn a-btn-ghost-inverse" @click="discard()" :disabled="submitting">
                            <x-admin.icon name="undo" :size="18" />
                            Discard
                        </button>
                        <button type="submit" class="a-btn a-btn-primary" :disabled="submitting">
                            <span x-text="submitting ? 'Saving…' : 'Save prices'">Save prices</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    @endif
@endsection
