{{-- Pass ['minimal' => true] on checkout-style pages to swap the nav and cart for a back link. --}}
<header class="site-header">
    <div class="container header-inner">
        <a href="{{ route('products.index') }}" class="brand">
            <span class="brand-mark"><x-icon name="sprout" size="20" /></span>
            <span class="brand-word">Bagsakan<span>Veggies</span></span>
        </a>

        @if ($minimal ?? false)
            <a href="{{ route('products.index') }}" class="btn btn-ghost header-back">
                <x-icon name="arrow-left" size="18" />
                Back to shop
            </a>
        @else
            <nav class="primary-nav" aria-label="Main">
                <a href="#products">Vegetables</a>
                <a href="#how-it-works">How to order</a>
                @if ($messengerPageUrl)
                    <a href="{{ $messengerPageUrl }}" target="_blank" rel="noopener">Message us</a>
                @endif
            </nav>

            <button
                type="button"
                class="cart-button"
                x-data
                @click="$store.cart.show()"
                aria-haspopup="dialog"
                aria-controls="cart-drawer"
            >
                <x-icon name="basket" />
                <span class="cart-button-label">Cart</span>
                <span
                    class="cart-count"
                    :class="{ 'is-empty': !$store.cart.summary.total_qty }"
                    x-text="$store.cart.summary.total_qty"
                >{{ $cartCount ?? 0 }}</span>
                <span class="sr-only">items</span>
            </button>
        @endif
    </div>
</header>
