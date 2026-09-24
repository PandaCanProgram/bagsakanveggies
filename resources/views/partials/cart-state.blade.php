@php
    $cartState = [
        'lines' => $lines,
        'summary' => $summary,
        'urls' => [
            'add' => route('cart.add'),
            'update' => route('cart.update'),
            'remove' => route('cart.remove'),
        ],
    ];
@endphp

<script>
    window.__CART__ = @json($cartState);
</script>
