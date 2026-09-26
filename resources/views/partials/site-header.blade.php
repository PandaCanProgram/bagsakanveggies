{{-- Pass ['minimal' => true] on checkout-style pages to swap the nav and cart for a back link. --}}
<header class="site-header">
    <div class="container header-inner">
        <a href="{{ route('products.index') }}" class="brand">
            <span class="brand-mark"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="256" height="256"></span>
            <span class="brand-word">Bagsakan <span>Veggies</span> Phils</span>
        </a>

        @if ($minimal ?? false)
            <a href="{{ route('products.index') }}" class="btn btn-ghost header-back">
                <x-icon name="arrow-left" size="18" />
                <span class="header-back-long">Back to shop</span>
                <span class="header-back-short">Shop</span>
            </a>
        @else
            <nav class="primary-nav" aria-label="Main">
                <a href="#products">Vegetables</a>
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
                    :class="{ 'is-empty': !$store.cart.summary.item_count }"
                    x-text="$store.cart.summary.item_count"
                >{{ $cartCount ?? 0 }}</span>
                <span class="sr-only">items</span>
            </button>
        @endif
    </div>
</header>
