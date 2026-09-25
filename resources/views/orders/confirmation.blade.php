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
            <h1 class="page-title">Thank you for ordering, {{ $order->full_name }}!</h1>

            @if ($messengerUrl = $order->messengerUrl())
                <p class="page-lede">
                    <strong>One last step:</strong> send us your order on Messenger so we can confirm it.
                    Your order message is already typed — just tap <strong>Send</strong>.
                </p>

                <div class="messenger-cta">
                    <a href="{{ $messengerUrl }}" id="messenger-link" class="btn btn-messenger btn-lg btn-block" target="_blank" rel="noopener">
                        <x-icon name="message" />
                        Message us on Messenger
                    </a>
                </div>

                <div class="backup-message" x-data="{ copied: false }">
                    <p class="backup-title">Didn't send it, or the message came out empty?</p>
                    <p class="backup-text">
                        Tap <strong>Copy message</strong>, open
                        @if ($messengerPageUrl)
                            <a href="{{ $messengerPageUrl }}" target="_blank" rel="noopener">our Messenger</a>,
                        @else
                            our Messenger,
                        @endif
                        then paste it and tap Send.
                    </p>
                    <textarea x-ref="text" class="input backup-textarea" rows="8" readonly aria-label="Your order message">{{ $order->messengerText() }}</textarea>
                    <button
                        type="button"
                        class="btn btn-secondary btn-block"
                        @click="
                            const text = $refs.text.value;
                            const done = () => { copied = true; setTimeout(() => copied = false, 2500); };
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text).then(done);
                            } else {
                                $refs.text.select();
                                document.execCommand('copy');
                                done();
                            }
                        "
                    >
                        <x-icon name="check" size="18" x-show="copied" x-cloak />
                        <span x-text="copied ? 'Copied!' : 'Copy message'">Copy message</span>
                    </button>
                </div>

                <script>
                    // Phones arrive with #open-messenger: open the Messenger app once, and keep this page for when they come back.
                    if (window.location.hash === '#open-messenger') {
                        history.replaceState(null, '', window.location.pathname + window.location.search);
                        setTimeout(function () {
                            window.location.href = document.getElementById('messenger-link').href;
                        }, 700);
                    }
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
