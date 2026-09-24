@extends('layouts.app')

@section('title', 'BagsakanVeggies — Checkout')

@section('header')
    <header class="site-header checkout-header">
        <a href="{{ route('products.index') }}" class="btn-back">← Back</a>
        <span class="header-divider">|</span>
        <div class="checkout-title">🧾 Checkout</div>
    </header>
@endsection

@section('content')
    <div
        class="checkout-container"
        x-data="{
            activeTab: '{{ $errors->any() ? 'delivery' : 'summary' }}',
            lines: {{ Illuminate\Support\Js::from($lines) }},
            summary: {{ Illuminate\Support\Js::from($summary) }},
            productsUrl: '{{ route('products.index') }}',
            async changeQty(line, qty) {
                const data = await cartRequest('{{ route('cart.update') }}', {
                    product_id: line.product_id,
                    variant_index: line.variant_index,
                    qty: Math.max(0, qty),
                });
                this.applyResponse(data);
            },
            async removeLine(line) {
                const data = await cartRequest('{{ route('cart.remove') }}', {
                    product_id: line.product_id,
                    variant_index: line.variant_index,
                });
                this.applyResponse(data);
            },
            applyResponse(data) {
                this.lines = data.lines;
                this.summary = data.summary;
                if (this.lines.length === 0) {
                    window.location.href = this.productsUrl;
                }
            },
        }"
    >
        <div class="tabs">
            <button type="button" class="tab-btn" :class="{ active: activeTab === 'summary' }" @click="activeTab = 'summary'">
                🧺 Order Summary (<span x-text="lines.length"></span>)
            </button>
            <button type="button" class="tab-btn" :class="{ active: activeTab === 'delivery' }" @click="activeTab = 'delivery'">
                📋 Delivery Info
            </button>
        </div>

        <template x-if="activeTab === 'summary'">
            <div>
                <div class="card">
                    <template x-for="line in lines" :key="line.key">
                        <div class="order-line">
                            <div class="order-thumb">📷</div>
                            <div class="order-line-body">
                                <div class="order-line-name" x-text="line.name"></div>
                                <div class="order-line-meta" x-text="line.variant_label + ' · ₱' + line.unit_price.toLocaleString() + ' each'"></div>
                                <div class="stepper">
                                    <button type="button" class="btn-minus" @click="changeQty(line, line.qty - 1)">−</button>
                                    <span class="qty-value" x-text="line.qty"></span>
                                    <button type="button" class="btn-plus" @click="changeQty(line, line.qty + 1)">+</button>
                                </div>
                            </div>
                            <div>
                                <div class="order-line-price" x-text="'₱' + line.line_total.toLocaleString()"></div>
                                <button type="button" class="order-line-remove" @click="removeLine(line)">Remove</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="card">
                    <div class="summary-row">
                        <span>Subtotal (<span x-text="summary.total_qty"></span> items)</span>
                        <span>₱<span x-text="summary.subtotal.toLocaleString()"></span></span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery fee</span>
                        <span x-text="summary.delivery_fee === 0 ? 'Free' : ('₱' + summary.delivery_fee.toLocaleString())"></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span class="value">₱<span x-text="summary.total.toLocaleString()"></span></span>
                    </div>
                </div>

                <button type="button" class="btn-primary" @click="activeTab = 'delivery'">Continue to Delivery Info →</button>
            </div>
        </template>

        <template x-if="activeTab === 'delivery'">
            <form method="POST" action="{{ route('orders.store') }}">
                @csrf
                <div class="card" style="padding:0;">
                    <div class="section-title">👤 Your Information</div>
                    <div class="section-body">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control" placeholder="Juan Dela Cruz" value="{{ old('full_name') }}" required>
                            @error('full_name') <div class="error-text">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label>Contact Number (CP #) <span class="required">*</span></label>
                            <input type="tel" name="contact_number" class="form-control" placeholder="09XX-XXX-XXXX" value="{{ old('contact_number') }}" required>
                            @error('contact_number') <div class="error-text">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card" style="padding:0;">
                    <div class="section-title">📦 Delivery Details</div>
                    <div class="section-body">
                        <div class="form-group">
                            <label>Delivery Address <span class="required">*</span></label>
                            <textarea name="delivery_address" class="form-control" rows="3" placeholder="House/Unit No., Street, Barangay, City" required>{{ old('delivery_address') }}</textarea>
                            @error('delivery_address') <div class="error-text">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Preferred Date <span class="required">*</span></label>
                                <input type="date" name="preferred_date" class="form-control" value="{{ old('preferred_date') }}" min="{{ date('Y-m-d') }}" required>
                                @error('preferred_date') <div class="error-text">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label>Preferred Time <span class="required">*</span></label>
                                <input type="time" name="preferred_time" class="form-control" value="{{ old('preferred_time') }}" required>
                                @error('preferred_time') <div class="error-text">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Order Notes <span class="hint">(optional)</span></label>
                            <textarea name="order_notes" class="form-control" rows="2" placeholder="Landmarks, gate code, special instructions...">{{ old('order_notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="order-total-bar">
                        <div>
                            <div class="order-total-label">Order Total</div>
                            <div class="order-total-value">₱<span x-text="summary.total.toLocaleString()"></span></div>
                        </div>
                        <div>
                            <div class="payment-label">Payment</div>
                            <div class="payment-value">Cash on Delivery</div>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">✅ Place Order</button>
                    <div class="footnote">You'll be taken to Messenger to confirm your order</div>
                </div>
            </form>
        </template>
    </div>
@endsection
