@extends('layouts.admin')

@php
    $isFruits = $category === \App\Models\Product::CATEGORY_FRUIT;
    $noun = \App\Models\Product::ADMIN_NAMES[$category];
@endphp

@section('title', 'Print price list')
@section('nav', 'print')

@section('content')
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Print price list</h1>
            <p class="a-muted">The store page with just each {{ $noun }}’s photo, name and prices, ready to print.</p>
        </div>

        <nav class="a-segmented" aria-label="Price list to print">
            <a href="{{ route('admin.products.print') }}" class="a-segmented-link" @unless ($isFruits) aria-current="page" @endunless>
                <x-admin.icon name="carrot" :size="18" />
                Veggies
            </a>
            <a href="{{ route('admin.products.print', ['category' => \App\Models\Product::CATEGORY_FRUIT]) }}" class="a-segmented-link" @if ($isFruits) aria-current="page" @endif>
                <x-admin.icon name="apple" :size="18" />
                Fruits
            </a>
        </nav>
    </div>

    <section class="a-card a-price-sheet-card" aria-labelledby="price-list-title" x-data="priceSheetPdf(@js(['filename' => $pdfFilename, 'title' => $pdfTitle]))">
        @if ($products->isNotEmpty())
            <div class="a-toolbar a-price-sheet-toolbar">
                <p class="a-toolbar-hint a-muted">Same order as the store. Print it, or download it as a PDF to send.</p>
                <div class="a-summary-actions">
                    <button type="button" class="a-btn a-btn-secondary" onclick="window.print()">
                        <x-admin.icon name="printer" :size="18" />
                        Print
                    </button>
                    <button type="button" class="a-btn a-btn-primary" @click="download()" :disabled="busy">
                        <x-admin.icon name="download" :size="18" />
                        <span x-text="busy ? 'Making PDF…' : 'Download PDF'">Download PDF</span>
                    </button>
                </div>
                <p class="a-field-error a-summary-error" role="alert" x-show="failed" x-cloak>
                    The PDF couldn’t be made. Check the internet connection and try again.
                </p>
            </div>
        @endif

        {{-- Plain white like the store page, so the screen shows what comes out of the printer or the PDF. --}}
        <div class="a-price-sheet" x-ref="sheet">
            <header class="a-price-sheet-head">
                <img src="{{ asset('images/logo-mark.png') }}" alt="" class="a-price-sheet-logo" width="256" height="256">
                <div>
                    <p class="a-price-sheet-store">Bagsakan Veggies Phils</p>
                    <h2 id="price-list-title" class="a-price-sheet-title">{{ \App\Models\Product::CATEGORIES[$category] }}</h2>
                </div>
            </header>

            @if ($products->isEmpty())
                <div class="a-empty">
                    <span class="a-empty-icon"><x-admin.icon :name="$isFruits ? 'apple' : 'carrot'" :size="28" /></span>
                    <h3 class="a-empty-title">No {{ \Illuminate\Support\Str::plural($noun) }} to print yet</h3>
                    <p class="a-muted">Add {{ \Illuminate\Support\Str::plural($noun) }} and they show up here, ready to print.</p>
                </div>
            @else
                <ul class="a-price-sheet-grid">
                    @foreach ($products as $product)
                        <li class="a-price-sheet-item">
                            {{-- Loaded right away (not lazily) so every photo is there when the page is printed. --}}
                            @if ($imageUrl = $product->imageUrl())
                                <img src="{{ $imageUrl }}" alt="" class="a-price-sheet-photo" width="640" height="480" decoding="async">
                            @else
                                <span class="a-price-sheet-photo a-price-sheet-photo-empty"><x-admin.icon name="sprout" :size="32" /></span>
                            @endif

                            <h3 class="a-price-sheet-name">{{ $product->name }}</h3>
                            <ul class="a-price-sheet-prices">
                                @foreach ($product->variants as $variant)
                                    <li>{{ $variant['label'] }} - <strong>₱{{ number_format($variant['price']) }}</strong></li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endsection
