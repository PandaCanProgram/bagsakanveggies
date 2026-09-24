{{-- Client-rendered from Alpine.store('cart'); used by the cart drawer and the checkout summary. --}}
<ul class="cart-lines">
    <template x-for="line in $store.cart.lines" :key="line.key">
        <li class="cart-line" :style="{ '--swatch': line.swatch }">
            <span class="cart-line-tile" aria-hidden="true"></span>

            <div class="cart-line-main">
                <p class="cart-line-name" x-text="line.name"></p>
                <p class="cart-line-meta" x-text="line.variant_label + ' · ' + peso(line.unit_price) + ' each'"></p>

                <div class="stepper stepper-sm" role="group" :aria-label="'Quantity of ' + line.name + ', ' + line.variant_label">
                    <button
                        type="button"
                        class="stepper-btn"
                        @click="$store.cart.setQty(line, line.qty - 1)"
                        :disabled="$store.cart.busy"
                        aria-label="Decrease quantity"
                    ><x-icon name="minus" size="16" /></button>
                    <span class="qty-value" x-text="line.qty"></span>
                    <button
                        type="button"
                        class="stepper-btn"
                        @click="$store.cart.setQty(line, line.qty + 1)"
                        :disabled="$store.cart.busy"
                        aria-label="Increase quantity"
                    ><x-icon name="plus" size="16" /></button>
                </div>
            </div>

            <div class="cart-line-side">
                <p class="cart-line-total" x-text="peso(line.line_total)"></p>
                <button
                    type="button"
                    class="link-button link-danger"
                    @click="$store.cart.remove(line)"
                    :disabled="$store.cart.busy"
                    :aria-label="'Remove ' + line.name + ', ' + line.variant_label"
                >Remove</button>
            </div>
        </li>
    </template>
</ul>
