function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function cartRequest(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!res.ok) {
        throw new Error('Cart request failed');
    }

    return res.json();
}

document.addEventListener('alpine:init', () => {
    Alpine.store('cart', window.__CART_SUMMARY__ || {
        item_count: 0,
        total_qty: 0,
        subtotal: 0,
        delivery_fee: 0,
        total: 0,
    });
});
