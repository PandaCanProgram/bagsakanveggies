@extends('layouts.app')

@section('title', 'BagsakanVeggies — Shop')

@section('head')
    <script>
        window.__CART_SUMMARY__ = @json($cartSummary);
    </script>
@endsection

@section('header')
    <header class="site-header">
        <div class="header-left">
            <div class="logo-badge">🌿</div>
            <div>
                <div class="brand-name">BagsakanVeggies</div>
                <div class="brand-tag">Farm-fresh vegetables • Metro Manila</div>
            </div>
        </div>
        <a href="{{ route('checkout.index') }}" class="cart-pill" x-data>
            🛒 Cart
            <span class="cart-badge" x-text="$store.cart.total_qty"></span>
            ₱<span x-text="$store.cart.total.toLocaleString()"></span>
        </a>
    </header>
@endsection

@section('content')
    <div class="page-container">
        <div class="product-grid">
            @foreach ($products as $product)
                <div
                    class="product-card"
                    x-data="{
                        qtys: {{ json_encode(array_fill(0, count($product->variants), 0)) }},
                        loading: false,
                        get hasQty() { return this.qtys.some(q => q > 0) },
                        inc(i) { this.qtys[i]++ },
                        dec(i) { if (this.qtys[i] > 0) this.qtys[i]-- },
                        async addToCart() {
                            if (!this.hasQty || this.loading) return;
                            this.loading = true;
                            const items = this.qtys
                                .map((qty, variant_index) => ({ variant_index, qty }))
                                .filter(item => item.qty > 0);
                            try {
                                const data = await cartRequest('{{ route('cart.add') }}', {
                                    product_id: {{ $product->id }},
                                    items,
                                });
                                Object.assign(Alpine.store('cart'), data.summary);
                                this.qtys = this.qtys.map(() => 0);
                            } finally {
                                this.loading = false;
                            }
                        },
                    }"
                >
                    <div class="product-image-placeholder">
                        <div class="icon">📷</div>
                        <div class="label">Photo coming soon</div>
                    </div>
                    <div class="product-body">
                        <div>
                            <div class="product-name">{{ $product->name }}</div>
                            @if ($product->note)
                                <div class="product-note">{{ $product->note }}</div>
                            @endif
                        </div>

                        @foreach ($product->variants as $i => $variant)
                            <div class="variant-row">
                                <div>
                                    <div class="variant-label">{{ $variant['label'] }}</div>
                                    <div class="variant-price">₱{{ number_format($variant['price']) }}</div>
                                </div>
                                <div class="stepper">
                                    <button type="button" class="btn-minus" @click="dec({{ $i }})" :disabled="qtys[{{ $i }}] === 0">−</button>
                                    <span class="qty-value" x-text="qtys[{{ $i }}]"></span>
                                    <button type="button" class="btn-plus" @click="inc({{ $i }})">+</button>
                                </div>
                            </div>
                        @endforeach

                        <button type="button" class="btn-add-to-cart" :class="{ active: hasQty }" :disabled="!hasQty" @click="addToCart()">
                            🛒 <span x-text="loading ? 'Adding…' : 'Add to Cart'"></span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="sticky-cart-bar" x-data>
        <div class="sticky-cart-info">
            <div class="sticky-cart-count"><span x-text="$store.cart.total_qty"></span> item(s) in cart</div>
            <div class="sticky-cart-total">₱<span x-text="$store.cart.total.toLocaleString()"></span></div>
        </div>
        <a href="{{ route('checkout.index') }}" class="btn-checkout">Checkout →</a>
    </div>
@endsection
