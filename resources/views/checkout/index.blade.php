@extends('layouts.app')

@section('title', 'Checkout — Bagsakan Veggies Phils')

@section('head')
    @include('partials.cart-state', ['lines' => $lines, 'summary' => $summary])
@endsection

@section('header')
    @include('partials.site-header', ['minimal' => true])
@endsection

@section('content')
    <div
        class="container checkout"
        x-data="checkout"
        x-effect="if ($store.cart.lines.length === 0) window.location.href = @js(route('products.index'))"
    >
        <div class="page-head">
            <h1 class="page-title">Checkout</h1>
            <p class="page-lede">Check your order, then fill in your details.</p>
        </div>

        <div class="checkout-grid">
            <aside class="panel checkout-summary" aria-labelledby="summary-title">
                <div class="panel-head">
                    <h2 id="summary-title" class="panel-title">Summary</h2>
                    <span class="panel-meta" x-text="$store.cart.countLabel"></span>
                </div>

                @include('partials.cart-lines')
                @include('partials.cart-totals')

                <div class="payment-note">
                    <x-icon name="banknote" />
                    <p><strong>Cash on delivery.</strong> Pay the rider when your order arrives.</p>
                </div>
            </aside>

            <form class="checkout-form" method="POST" action="{{ route('orders.store') }}" x-ref="form" @submit="onSubmit($event)">
                @csrf

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        <x-icon name="alert" />
                        <p><strong>Please check the highlighted fields.</strong> Some details are missing or invalid.</p>
                    </div>
                @endif

                <fieldset class="panel form-section">
                    <legend class="panel-title">Details</legend>

                    <div class="field">
                        <label for="full_name">Name</label>
                        <input
                            id="full_name"
                            type="text"
                            name="full_name"
                            class="input"
                            autocomplete="name"
                            value="{{ old('full_name') }}"
                            required
                            @error('full_name') aria-invalid="true" aria-describedby="full_name-error" @enderror
                        >
                        @error('full_name')
                            <p id="full_name-error" class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="contact_number">CP or Viber number</label>
                        <input
                            id="contact_number"
                            type="tel"
                            name="contact_number"
                            class="input"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            maxlength="11"
                            pattern="09[0-9]{9}"
                            placeholder="09171234567"
                            value="{{ old('contact_number') }}"
                            required
                            aria-describedby="contact_number-hint @error('contact_number') contact_number-error @enderror"
                            @error('contact_number') aria-invalid="true" @enderror
                        >
                        <p id="contact_number-hint" class="field-hint">11 digits starting with 09, no spaces or dashes.</p>
                        @error('contact_number')
                            <p id="contact_number-error" class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="delivery_address">Delivery address</label>
                        <textarea
                            id="delivery_address"
                            name="delivery_address"
                            class="input"
                            rows="3"
                            autocomplete="street-address"
                            placeholder="House/unit no., street, barangay, city"
                            required
                            @error('delivery_address') aria-invalid="true" aria-describedby="delivery_address-error" @enderror
                        >{{ old('delivery_address') }}</textarea>
                        @error('delivery_address')
                            <p id="delivery_address-error" class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="dispatch-note">
                        <x-icon name="clock" />
                        <p>
                            <strong>Note:</strong> Orders received before <strong>10&nbsp;AM</strong> will be dispatched by <strong>12&nbsp;NN</strong>.
                            After 10&nbsp;AM, orders will be dispatched by <strong>3&nbsp;PM</strong>.
                        </p>
                    </div>
                </fieldset>

                <div class="place-order">
                    <div class="place-order-total">
                        <span>Order total</span>
                        <strong x-text="peso($store.cart.summary.total)">{{ \App\Support\Format::peso($summary['total']) }}</strong>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block" :disabled="submitting || $store.cart.busy" aria-haspopup="dialog">
                        Place order
                    </button>
                    <p class="fine-print">You'll review your order before it's sent.</p>
                </div>
            </form>
        </div>

        @include('checkout.partials.review-dialog')
    </div>
@endsection
