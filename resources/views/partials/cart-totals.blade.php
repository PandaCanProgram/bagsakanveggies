{{-- Total only (delivery is free, so a subtotal line would just repeat it). --}}
<dl class="totals">
    <div class="totals-row totals-grand">
        <dt>Total</dt>
        <dd x-text="peso($store.cart.summary.total)"></dd>
    </div>
</dl>
