@php($noun = \App\Models\Product::ADMIN_NAMES[$category])

<div class="a-price-sheet" data-price-sheet data-category="{{ $category }}">
    <header class="a-price-sheet-head">
        <img src="{{ asset('images/logo-mark.png') }}" alt="" class="a-price-sheet-logo" width="256" height="256">
        <div>
            <p class="a-price-sheet-store">Bagsakan Veggies Phils</p>
            <h2 id="price-list-title-{{ $category }}" class="a-price-sheet-title">{{ \App\Models\Product::CATEGORIES[$category] }}</h2>
        </div>
    </header>

    @if ($products->isEmpty())
        <div class="a-empty">
            <span class="a-empty-icon"><x-admin.icon :name="\App\Models\Product::CATEGORY_ICONS[$category]" :size="28" /></span>
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
                        <span class="a-price-sheet-photo a-price-sheet-photo-empty"><x-admin.icon :name="\App\Models\Product::CATEGORY_ICONS[$category]" :size="32" /></span>
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
