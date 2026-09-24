@extends('layouts.app')

@section('title', 'BagsakanVeggies — Fresh vegetables by the bag or by the kilo')

@section('body-class', 'has-cart-bar')

@section('head')
    @include('partials.cart-state', ['lines' => $cartLines, 'summary' => $cartSummary])
@endsection

@section('header')
    @include('partials.site-header', ['cartCount' => $cartSummary['total_qty']])
@endsection

@section('content')
    <section class="hero" aria-labelledby="hero-title">
        <div class="container hero-inner">
            <div class="hero-copy">
                <p class="eyebrow">Metro Manila delivery</p>
                <h1 id="hero-title" class="hero-title">Fresh vegetables, by the bag or by the kilo.</h1>
                <p class="hero-lede">
                    Stock up with 10&nbsp;kg bags for your store or kitchen, or order just a few kilos for home.
                    Order before 12:00&nbsp;NN and we deliver the same day.
                </p>

                <div class="hero-actions">
                    <a href="#products" class="btn btn-primary btn-lg">
                        Shop vegetables
                        <x-icon name="arrow-right" size="18" />
                    </a>
                    <a href="#how-it-works" class="btn btn-secondary btn-lg">How ordering works</a>
                </div>

                <ul class="hero-facts">
                    <li>
                        <span class="hero-fact-icon"><x-icon name="truck" /></span>
                        <span><strong>Same-day delivery</strong> For orders before 12:00 NN</span>
                    </li>
                    <li>
                        <span class="hero-fact-icon"><x-icon name="banknote" /></span>
                        <span><strong>Cash on delivery</strong> Pay when it arrives</span>
                    </li>
                    <li>
                        <span class="hero-fact-icon"><x-icon name="weight" /></span>
                        <span><strong>Bulk or retail</strong> 10 kg bags or per kilo</span>
                    </li>
                </ul>
            </div>

            @if ($products->isNotEmpty())
                <aside class="price-board" aria-labelledby="price-board-title">
                    <div class="price-board-head">
                        <h2 id="price-board-title" class="price-board-title">Price list</h2>
                        <span class="price-board-meta">{{ $products->count() }} vegetables</span>
                    </div>

                    <table class="price-table">
                        <thead>
                            <tr>
                                <th scope="col">Vegetable</th>
                                @foreach ($products->first()->variants as $variant)
                                    <th scope="col" class="num">{{ $variant['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products->take(6) as $product)
                                <tr>
                                    <th scope="row">
                                        <span class="price-dot" style="--swatch: {{ $product->swatchColor() }}" aria-hidden="true"></span>
                                        {{ $product->name }}
                                    </th>
                                    @foreach ($product->variants as $variant)
                                        <td class="num">₱{{ number_format($variant['price']) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <a href="#products" class="price-board-link">
                        See all {{ $products->count() }} vegetables
                        <x-icon name="arrow-right" size="16" />
                    </a>
                </aside>
            @endif
        </div>
    </section>

    <section id="products" class="section" aria-labelledby="products-title">
        <div class="container">
            <div class="section-head">
                <h2 id="products-title" class="section-title">Vegetables</h2>
                <p class="section-lede">Set a quantity for the bag, the kilo, or both, then add it to your cart.</p>
            </div>

            <div class="product-grid">
                @foreach ($products as $product)
                    <article
                        class="product-card"
                        style="--swatch: {{ $product->swatchColor() }}"
                        x-data="productCard({{ $product->id }}, @js(array_column($product->variants, 'price')))"
                    >
                        @if ($imageUrl = $product->imageUrl())
                            <img class="product-photo" src="{{ $imageUrl }}" alt="{{ $product->name }}" width="640" height="480" loading="lazy" decoding="async">
                        @endif

                        <header class="product-head">
                            <div>
                                <h3 class="product-name">{{ $product->name }}</h3>
                                @if ($product->note)
                                    <p class="product-note">{{ trim($product->note, '() ') }}</p>
                                @endif
                            </div>
                            <span class="product-swatch" aria-hidden="true"></span>
                        </header>

                        <ul class="variant-list">
                            @foreach ($product->variants as $i => $variant)
                                <li class="variant">
                                    <div class="variant-info">
                                        <span class="variant-label">{{ $variant['label'] }}</span>
                                        <span class="variant-price">₱{{ number_format($variant['price']) }}</span>
                                        @if ($perKilo = $product->pricePerKilo($i))
                                            <span class="variant-unit">₱{{ number_format($perKilo) }}/kg</span>
                                        @endif
                                    </div>

                                    <div class="stepper" role="group" aria-label="{{ $product->name }}, {{ $variant['label'] }}">
                                        <button
                                            type="button"
                                            class="stepper-btn"
                                            @click="dec({{ $i }})"
                                            :disabled="!qtys[{{ $i }}]"
                                            aria-label="Decrease quantity"
                                        ><x-icon name="minus" size="16" /></button>
                                        <input
                                            type="number"
                                            class="qty-input"
                                            min="0"
                                            max="999"
                                            inputmode="numeric"
                                            value="0"
                                            x-model.number="qtys[{{ $i }}]"
                                            @blur="normalize({{ $i }})"
                                            aria-label="Quantity"
                                        >
                                        <button
                                            type="button"
                                            class="stepper-btn"
                                            @click="inc({{ $i }})"
                                            aria-label="Increase quantity"
                                        ><x-icon name="plus" size="16" /></button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <div class="product-foot">
                            <button
                                type="button"
                                class="btn btn-block btn-add"
                                :class="{ 'is-ready': hasQty }"
                                :disabled="!hasQty || loading"
                                @click="addToCart()"
                                disabled
                            >
                                <x-icon name="basket" size="18" />
                                <span x-text="buttonLabel">Add to cart</span>
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="how-it-works" class="section section-muted" aria-labelledby="how-title">
        <div class="container">
            <div class="section-head">
                <h2 id="how-title" class="section-title">How ordering works</h2>
            </div>

            <ol class="steps">
                <li class="step">
                    <span class="step-num">1</span>
                    <h3 class="step-title">Fill your cart</h3>
                    <p>Pick 10 kg bags, per-kilo amounts, or a mix of both for each vegetable.</p>
                </li>
                <li class="step">
                    <span class="step-num">2</span>
                    <h3 class="step-title">Set your delivery</h3>
                    <p>Enter your address and preferred date and time. Orders placed before 12:00 NN can arrive the same day.</p>
                </li>
                <li class="step">
                    <span class="step-num">3</span>
                    @if ($messengerPageUrl)
                        <h3 class="step-title">Confirm on Messenger</h3>
                        <p>Send us your order summary on Messenger and we'll confirm it. Pay cash when it's delivered.</p>
                    @else
                        <h3 class="step-title">Get a confirmation</h3>
                        <p>We'll confirm your order by SMS. Pay cash when it's delivered.</p>
                    @endif
                </li>
            </ol>
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
        x-show="$store.cart.summary.total_qty > 0"
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
