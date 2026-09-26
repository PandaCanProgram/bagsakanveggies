@extends('layouts.app')

@section('title', 'Bagsakan Veggies Phils — Fresh vegetables by the bag or by the kilo')

@section('body-class', 'has-cart-bar')

@section('head')
    @include('partials.cart-state', ['lines' => $cartLines, 'summary' => $cartSummary])
@endsection

@section('header')
    @include('partials.site-header', ['cartCount' => $cartSummary['item_count']])
@endsection

@section('content')
    <section id="products" class="section" aria-labelledby="products-title">
        <div class="container">
            <div class="section-head">
                <h2 id="products-title" class="section-title">Vegetables</h2>
                <p class="section-lede">Type how many you want in the <strong>Qty</strong> box, then press <strong>Add to cart</strong>.</p>
            </div>

            <div class="product-grid">
                @foreach ($products as $product)
                    <article
                        class="product-card"
                        style="--swatch: {{ $product->swatchColor() }}"
                        x-data="productCard({{ $product->id }}, @js(array_column($product->variants, 'price')), @js(array_map(fn ($i) => $product->minQty($i), array_keys($product->variants))))"
                    >
                        <div class="product-media">
                            @if ($imageUrl = $product->imageUrl())
                                <img class="product-photo" src="{{ $imageUrl }}" alt="{{ $product->name }}" width="640" height="480" loading="lazy" decoding="async">
                            @else
                                <span class="product-photo-empty" aria-hidden="true"><x-icon name="sprout" size="32" /></span>
                            @endif
                        </div>

                        <div class="product-body">
                            <h3 class="product-name">{{ $product->name }}</h3>
                            {{-- Always takes a line (blank when there's no note) so sizes line up across the row. --}}
                            <p class="product-note">
                                @if ($product->note)
                                    ({{ trim($product->note, '() ') }})
                                @else
                                    &nbsp;
                                @endif
                            </p>

                            <ul class="variant-list">
                                @foreach ($product->variants as $i => $variant)
                                    @php($qtyId = "qty-{$product->id}-{$i}")
                                    @php($halves = $product->allowsHalf($i))
                                    <li class="variant">
                                        <label class="variant-info" for="{{ $qtyId }}">
                                            <span class="variant-label">{{ $variant['label'] }}<span class="variant-sep"> -</span></span>
                                            <span class="variant-price">₱{{ number_format($variant['price']) }}</span>
                                            @if ($halves)
                                                <span class="variant-hint">min 0.5 kg</span>
                                            @endif
                                        </label>
                                        {{-- Own tiny form per box: phone keyboards then show "Go/✓" instead of "Next" (which jumps to the next box). --}}
                                        <form class="qty-field" @submit.prevent="$el.querySelector('input').blur()">
                                            <span class="qty-caption" aria-hidden="true">Qty</span>
                                            <input
                                                id="{{ $qtyId }}"
                                                type="number"
                                                class="qty-box"
                                                enterkeyhint="done"
                                                min="0"
                                                max="999"
                                                step="{{ $halves ? '0.1' : '1' }}"
                                                inputmode="{{ $halves ? 'decimal' : 'numeric' }}"
                                                x-model="qtys[{{ $i }}]"
                                                @blur="normalize({{ $i }})"
                                                @keydown.enter.prevent="$el.blur()"
                                                aria-label="How many: {{ $product->name }}, {{ $variant['label'] }}"
                                            >
                                        </form>
                                    </li>
                                @endforeach
                            </ul>

                            <button
                                type="button"
                                class="btn btn-block btn-add"
                                :disabled="!hasQty || loading"
                                @click="addToCart()"
                                disabled
                            >
                                <span class="btn-add-label">
                                    <x-icon name="basket" size="18" />
                                    <span x-text="buttonLabel">Add to cart</span>
                                </span>
                                <span class="btn-add-total" x-show="hasQty" x-cloak x-text="peso(selectedTotal)"></span>
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- End of the list: cart total, then ORDER goes to page 2 (summary + delivery details). --}}
            <div class="order-summary" x-data>
                <div class="order-summary-total">
                    <span class="order-summary-label">Cart total</span>
                    <strong class="order-summary-amount" x-text="peso($store.cart.summary.total)">{{ \App\Support\Format::peso($cartSummary['total']) }}</strong>
                    <span class="order-summary-count" x-text="$store.cart.lines.length ? $store.cart.countLabel : 'Your cart is empty'"></span>
                </div>

                <a
                    href="{{ route('checkout.index') }}"
                    class="btn btn-primary btn-lg order-summary-btn"
                    :class="{ 'is-disabled': !$store.cart.lines.length }"
                    :aria-disabled="(!$store.cart.lines.length).toString()"
                    @click="if (!$store.cart.lines.length) $event.preventDefault()"
                >
                    ORDER
                    <x-icon name="arrow-right" size="20" />
                </a>
                <p class="order-summary-hint" x-show="!$store.cart.lines.length">Add vegetables above, then press ORDER.</p>
            </div>
        </div>
    </section>
@endsection

@section('overlays')
    @include('partials.cart-drawer')

    <button
        type="button"
        class="cart-bar"
        x-data
        x-cloak
        x-show="$store.cart.summary.item_count > 0"
        x-transition.opacity
        @click="$store.cart.show()"
        aria-haspopup="dialog"
        aria-controls="cart-drawer"
    >
        <span class="cart-bar-info">
            <span class="cart-bar-count" x-text="$store.cart.countLabel"></span>
            <strong x-text="peso($store.cart.summary.total)"></strong>
        </span>
        <span class="cart-bar-cta">
            View cart
            <x-icon name="arrow-right" size="18" />
        </span>
    </button>
@endsection
