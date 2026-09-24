@extends('layouts.app')

@section('title', 'BagsakanVeggies — Order Placed')

@section('header')
    <header class="site-header checkout-header">
        <a href="{{ route('products.index') }}" class="btn-back">← Back to Shop</a>
        <span class="header-divider">|</span>
        <div class="checkout-title">🧾 Order Confirmation</div>
    </header>
@endsection

@section('content')
    <div class="confirmation-box">
        <div class="confirmation-icon">✅</div>
        <div class="confirmation-title">Order Placed!</div>
        <div class="confirmation-text">
            Thanks, {{ $order->full_name }}. Your order #{{ $order->id }} has been received.
            @if ($order->messengerUrl())
                Message us on Messenger to confirm and arrange GCash payment.
            @else
                We'll confirm your order via SMS shortly.
            @endif
        </div>

        <div class="confirmation-details">
            <div><strong>Delivery Address:</strong> {{ $order->delivery_address }}</div>
            <div><strong>Preferred Date:</strong> {{ $order->preferred_date->format('F j, Y') }}</div>
            <div><strong>Preferred Time:</strong> {{ \Carbon\Carbon::parse($order->preferred_time)->format('g:i A') }}</div>
            <div><strong>Payment:</strong> {{ $order->payment_method }}</div>
            <div><strong>Total:</strong> ₱{{ number_format($order->total) }}</div>
        </div>

        @if ($order->messengerUrl())
            <a href="{{ $order->messengerUrl() }}" id="messenger-link" class="btn-facebook" style="margin-bottom:6px;">📩 Message us on Messenger</a>
            <div class="messenger-warning">⚠️ Don't edit the message — just tap Send so we get your full order details.</div>
            <div class="messenger-redirect-note">Opening Messenger automatically…</div>
            <script>
                setTimeout(function () {
                    window.location.href = document.getElementById('messenger-link').href;
                }, 1200);
            </script>
        @endif

        <a href="{{ route('products.index') }}" class="btn-primary" style="display:block;">Back to Shop</a>
    </div>
@endsection
