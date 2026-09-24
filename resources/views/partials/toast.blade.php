<div class="toast-region" x-data aria-live="polite" aria-atomic="true">
    <div
        class="toast"
        :class="{ 'toast-error': $store.cart.toast?.tone === 'error' }"
        x-cloak
        x-show="$store.cart.toast"
        x-transition:enter="toast-enter"
        x-transition:enter-start="toast-hidden"
        x-transition:leave="toast-leave"
        x-transition:leave-end="toast-hidden"
    >
        <span class="toast-icon">
            <x-icon name="check" size="18" x-show="$store.cart.toast?.tone !== 'error'" />
            <x-icon name="alert" size="18" x-show="$store.cart.toast?.tone === 'error'" />
        </span>
        <span class="toast-message" x-text="$store.cart.toast?.message"></span>
        <button
            type="button"
            class="toast-action"
            x-show="$store.cart.toast?.showCart"
            @click="$store.cart.show()"
        >View cart</button>
    </div>
</div>
