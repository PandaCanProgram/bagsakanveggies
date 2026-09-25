{{-- Opened by "Place order" on checkout; submitting happens from here. State lives in Alpine.data('checkout'). --}}
<div
    class="drawer-backdrop"
    x-cloak
    x-show="reviewing"
    x-transition.opacity.duration.200ms
    @click="closeReview()"
></div>

<div
    class="review-dialog"
    x-cloak
    x-show="reviewing"
    x-trap.inert.noscroll="reviewing"
    @keydown.escape.window="closeReview()"
    x-transition:enter="review-enter"
    x-transition:enter-start="review-closed"
    x-transition:enter-end="review-opened"
    x-transition:leave="review-leave"
    x-transition:leave-start="review-opened"
    x-transition:leave-end="review-closed"
    role="dialog"
    aria-modal="true"
    aria-labelledby="review-title"
>
    <div class="drawer-head">
        <h2 id="review-title" class="drawer-title">Review your order</h2>
        <button type="button" class="icon-button" @click="closeReview()" :disabled="submitting" aria-label="Close and edit details">
            <x-icon name="x" />
        </button>
    </div>

    <div class="review-body">
        <section aria-labelledby="review-items-title">
            <h3 id="review-items-title" class="review-heading">
                Items
                <span class="drawer-count" x-text="$store.cart.countLabel"></span>
            </h3>
            <ul class="receipt">
                <template x-for="line in $store.cart.lines" :key="line.key">
                    <li>
                        <div>
                            <p class="receipt-name" x-text="line.name"></p>
                            <p class="receipt-meta" x-text="line.variant_label + ' · ' + line.qty + ' × ' + peso(line.unit_price)"></p>
                        </div>
                        <span class="receipt-total" x-text="peso(line.line_total)"></span>
                    </li>
                </template>
            </ul>
            @include('partials.cart-totals')
        </section>

        <section aria-labelledby="review-contact-title">
            <h3 id="review-contact-title" class="review-heading">Contact &amp; delivery</h3>
            <dl class="detail-list review-details">
                <div>
                    <dt>Name</dt>
                    <dd x-text="details.full_name"></dd>
                </div>
                <div>
                    <dt>Mobile number</dt>
                    <dd x-text="details.contact_number"></dd>
                </div>
                <div class="review-span">
                    <dt>Address</dt>
                    <dd x-text="details.delivery_address"></dd>
                </div>
                <div>
                    <dt>Preferred date</dt>
                    <dd x-text="details.date"></dd>
                </div>
                <div>
                    <dt>Preferred time</dt>
                    <dd x-text="details.time"></dd>
                </div>
                <div class="review-span" x-show="details.order_notes">
                    <dt>Notes</dt>
                    <dd x-text="details.order_notes"></dd>
                </div>
                <div class="review-span">
                    <dt>Payment</dt>
                    <dd>Cash on Delivery</dd>
                </div>
            </dl>
        </section>
    </div>

    <div class="drawer-foot">
        @if ($messengerPageUrl)
            <p class="callout">
                <x-icon name="alert" size="18" />
                <span>
                    After you confirm, <strong>Messenger will open</strong> with your order already typed.
                    Don't edit it — just tap <strong>Send</strong> so we get notified and can confirm your order.
                </span>
            </p>
        @endif

        <div class="review-actions">
            <button type="button" class="btn btn-secondary btn-lg" @click="closeReview()" :disabled="submitting">
                <x-icon name="arrow-left" size="18" />
                Edit details
            </button>
            <button
                type="button"
                class="btn btn-lg {{ $messengerPageUrl ? 'btn-messenger' : 'btn-primary' }}"
                @click="confirm()"
                :disabled="submitting || $store.cart.busy"
            >
                @if ($messengerPageUrl)
                    <x-icon name="message" />
                    <span x-text="submitting ? 'Placing order…' : 'Confirm & open Messenger'">Confirm &amp; open Messenger</span>
                @else
                    <span x-text="submitting ? 'Placing order…' : 'Confirm order'">Confirm order</span>
                @endif
            </button>
        </div>
    </div>
</div>
