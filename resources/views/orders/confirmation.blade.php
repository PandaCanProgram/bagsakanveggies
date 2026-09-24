@extends('layouts.app')

@section('title', 'Order placed — BagsakanVeggies')

@section('header')
    @include('partials.site-header', ['minimal' => true])
@endsection

@section('content')
    <div class="container confirm">
        <section class="panel confirm-hero">
            <span class="confirm-icon"><x-icon name="check" size="28" /></span>
            <p class="eyebrow">Order #{{ $order->id }}</p>
            <h1 class="page-title">Thanks, {{ $order->full_name }}. We've received your order.</h1>

            @if ($order->messengerUrl())
                <p class="page-lede">Message us on Messenger to confirm your order and arrange GCash payment.</p>

                <div class="messenger-cta">
                    <a href="{{ $order->messengerUrl() }}" id="messenger-link" class="btn btn-messenger btn-lg btn-block">
                        <x-icon name="message" />
                        Message us on Messenger
                    </a>
                    <p class="callout">
                        <x-icon name="alert" size="18" />
                        <span>Don't edit the message — just tap <strong>Send</strong> so we get your full order details.</span>
                    </p>
                    <p class="fine-print">Opening Messenger automatically…</p>
                </div>

                <script>
                    setTimeout(function () {
                        window.location.href = document.getElementById('messenger-link').href;
                    }, 1200);
                </script>
            @else
                <p class="page-lede">We'll confirm your order via SMS shortly.</p>
            @endif
        </section>

        <div class="confirm-grid">
            <section class="panel" aria-labelledby="delivery-title">
                <h2 id="delivery-title" class="panel-title">Delivery</h2>
                <dl class="detail-list">
                    <div>
                        <dt>Address</dt>
                        <dd>{{ $order->delivery_address }}</dd>
                    </div>
                    <div>
                        <dt>Preferred date</dt>
                        <dd>{{ $order->preferred_date->format('F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt>Preferred time</dt>
                        <dd>{{ \Carbon\Carbon::parse($order->preferred_time)->format('g:i A') }}</dd>
                    </div>
                    <div>
                        <dt>Payment</dt>
                        <dd>{{ $order->payment_method }}</dd>
                    </div>
                </dl>
            </section>

            <section class="panel" aria-labelledby="items-title">
                <h2 id="items-title" class="panel-title">Items</h2>
                <ul class="receipt">
                    @foreach ($order->items as $item)
                        <li>
                            <div>
                                <p class="receipt-name">{{ $item->product_name }}</p>
                                <p class="receipt-meta">{{ $item->variant_label }} · {{ $item->qty }} × ₱{{ number_format($item->unit_price) }}</p>
                            </div>
                            <span class="receipt-total">₱{{ number_format($item->line_total) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="totals">
                    <div class="totals-row">
                        <dt>Subtotal</dt>
                        <dd>₱{{ number_format($order->subtotal) }}</dd>
                    </div>
                    <div class="totals-row">
                        <dt>Delivery</dt>
                        <dd>{{ $order->delivery_fee ? '₱'.number_format($order->delivery_fee) : 'Free' }}</dd>
                    </div>
                    <div class="totals-row totals-grand">
                        <dt>Total</dt>
                        <dd>₱{{ number_format($order->total) }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <a href="{{ route('products.index') }}" class="btn btn-secondary">
            <x-icon name="arrow-left" size="18" />
            Back to shop
        </a>
    </div>
@endsection
