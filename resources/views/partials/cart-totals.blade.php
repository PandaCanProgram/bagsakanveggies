<dl class="totals">
    <div class="totals-row">
        <dt>Subtotal (<span x-text="$store.cart.countLabel"></span>)</dt>
        <dd x-text="peso($store.cart.summary.subtotal)"></dd>
    </div>
    <div class="totals-row">
        <dt>Delivery</dt>
        <dd x-text="$store.cart.summary.delivery_fee === 0 ? 'Free' : peso($store.cart.summary.delivery_fee)"></dd>
    </div>
    <div class="totals-row totals-grand">
        <dt>Total</dt>
        <dd x-text="peso($store.cart.summary.total)"></dd>
    </div>
</dl>
