<div
    class="drawer-backdrop"
    x-data
    x-cloak
    x-show="$store.cart.open"
    x-transition.opacity.duration.200ms
    @click="$store.cart.hide()"
></div>

<aside
    id="cart-drawer"
    class="drawer"
    x-data
    x-cloak
    x-show="$store.cart.open"
    x-trap.inert.noscroll="$store.cart.open"
    @keydown.escape.window="$store.cart.hide()"
    x-transition:enter="drawer-enter"
    x-transition:enter-start="drawer-closed"
    x-transition:enter-end="drawer-opened"
    x-transition:leave="drawer-leave"
    x-transition:leave-start="drawer-opened"
    x-transition:leave-end="drawer-closed"
    role="dialog"
    aria-modal="true"
    aria-labelledby="cart-drawer-title"
>
    <div class="drawer-head">
        <h2 id="cart-drawer-title" class="drawer-title">
            Your cart
            <span class="drawer-count" x-show="$store.cart.lines.length" x-text="$store.cart.countLabel"></span>
        </h2>
        <button type="button" class="icon-button" @click="$store.cart.hide()" aria-label="Close cart">
            <x-icon name="x" />
        </button>
    </div>

    <div class="drawer-body">
        <div class="empty-state" x-show="$store.cart.lines.length === 0">
            <span class="empty-state-icon"><x-icon name="basket" size="28" /></span>
            <p class="empty-state-title">Your cart is empty</p>
            <p class="empty-state-text">Choose a 10 kg bag or a few kilos from the list to get started.</p>
            <a href="#products" class="btn btn-secondary" @click="$store.cart.hide()">Browse vegetables</a>
        </div>

        @include('partials.cart-lines')
    </div>

    <div class="drawer-foot" x-show="$store.cart.lines.length > 0">
        @include('partials.cart-totals')

        <a href="{{ route('checkout.index') }}" class="btn btn-primary btn-lg btn-block">
            Checkout
            <x-icon name="arrow-right" size="18" />
        </a>
        <p class="fine-print">Cash on delivery. Same-day delivery for orders placed before 12:00 NN.</p>
    </div>
</aside>
