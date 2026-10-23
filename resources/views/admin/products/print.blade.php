@extends('layouts.admin')

@php
    $noun = $category === 'all' ? 'product' : \App\Models\Product::ADMIN_NAMES[$category];
@endphp

@section('title', 'Print price list')
@section('nav', 'print')

@section('content')
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Print price list</h1>
            <p class="a-muted">The store page with just each {{ $noun }}’s photo, name and prices, ready to print.</p>
        </div>

        <nav class="a-segmented a-category-tabs" aria-label="Price list to print">
            @foreach (\App\Models\Product::CATEGORIES as $value => $label)
                <a href="{{ route('admin.products.print', $value === \App\Models\Product::CATEGORY_VEGETABLE ? [] : ['category' => $value]) }}" class="a-segmented-link" @if ($category === $value) aria-current="page" @endif>
                    <x-admin.icon :name="\App\Models\Product::CATEGORY_ICONS[$value]" :size="18" />
                    {{ $value === \App\Models\Product::CATEGORY_VEGETABLE ? 'Veggies' : $label }}
                </a>
            @endforeach
        </nav>
    </div>

    <section class="a-card a-price-sheet-card" aria-label="Price lists" x-data="priceSheetPdf(@js([
        'filename' => $pdfFilename,
        'title' => $pdfTitle,
        'allUrl' => route('admin.products.print', ['category' => 'all']),
        'allFilename' => $allPdfFilename,
        'allTitle' => 'Bagsakan Veggies Phils - All categories',
    ]))">
        @if ($hasProducts)
            <div class="a-toolbar a-price-sheet-toolbar">
                <p class="a-toolbar-hint a-muted">Same order as the store. Print it, or download it as a PDF to send.</p>
                <div class="a-summary-actions" :aria-busy="busy">
                    <button type="button" class="a-btn a-btn-secondary" onclick="window.print()" @disabled($products->isEmpty()) :disabled="busy || @js($products->isEmpty())">
                        <x-admin.icon name="printer" :size="18" />
                        Print
                    </button>
                    <button type="button" class="a-btn a-btn-primary" @click="download()" @disabled($products->isEmpty()) :disabled="busy || @js($products->isEmpty())">
                        <x-admin.icon name="download" :size="18" />
                        <span x-text="busy && !downloadingAll ? 'Making PDF…' : 'Download PDF'">Download PDF</span>
                    </button>
                    <button type="button" class="a-btn a-btn-secondary" @click="download(true)" :disabled="busy">
                        <x-admin.icon name="download" :size="18" />
                        <span x-text="busy && downloadingAll ? 'Making PDF…' : 'Download all categories'">Download all categories</span>
                    </button>
                </div>
                <p class="a-field-error a-summary-error" role="alert" x-show="failed" x-cloak>
                    The PDF could not be made. Reload the page, check your connection and try again.
                </p>
            </div>
        @endif

        {{-- Plain white like the store page, so the screen shows what comes out of the printer or the PDF. --}}
        <div class="a-price-sheets" x-ref="sheets">
            @foreach ($printCategories as $sheetCategory => $sheetLabel)
                @php($sheetProducts = $products->where('category', $sheetCategory))
                @if ($category !== 'all' || $sheetProducts->isNotEmpty())
                    @include('admin.products.partials.price-sheet', ['category' => $sheetCategory, 'products' => $sheetProducts])
                @endif
            @endforeach
            @if ($category === 'all' && $products->isEmpty())
                <div class="a-empty">
                    <h2 class="a-empty-title">No products to print yet</h2>
                    <p class="a-muted">Add products and they show up here, ready to print.</p>
                </div>
            @endif
        </div>
    </section>
@endsection
