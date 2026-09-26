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

// Pesos, with centavos only when there are any: ₱55, ₱57.50, ₱1,400.
function peso(amount) {
    const value = Math.round(Number(amount || 0) * 100) / 100;
    const decimals = Number.isInteger(value) ? 0 : 2;

    return '₱' + value.toLocaleString('en-PH', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

// Round a typed amount for its size: per-kilo sizes (min 0.5) to 0.1 kg, bags (min 1) to whole numbers.
// At least the minimum, at most 999; 0 stays 0.
function snapQty(value, min = 1) {
    const qty = Number(value) || 0;

    if (qty <= 0) return 0;

    const rounded = min < 1 ? Math.round(qty * 10) / 10 : Math.round(qty);

    return Math.min(Math.max(rounded, min), 999);
}

// Phones: some keyboards show a "Next" key that jumps straight into the next Qty box.
// On touch screens a Qty box may only be focused by tapping it; a jump without a tap just closes the keyboard.
let lastTouchAt = 0;

document.addEventListener('touchstart', () => (lastTouchAt = Date.now()), { capture: true, passive: true });

document.addEventListener('focusin', (event) => {
    const isQtyBox = event.target.classList?.contains('qty-box');
    const onTouchScreen = window.matchMedia('(pointer: coarse)').matches;

    if (isQtyBox && onTouchScreen && Date.now() - lastTouchAt > 800) {
        event.target.blur();
    }
}, true);

document.addEventListener('alpine:init', () => {
    const initial = window.__CART__ || {};

    Alpine.store('cart', {
        open: false,
        busy: false,
        toast: null,
        toastTimer: null,
        urls: initial.urls || {},
        lines: initial.lines || [],
        summary: initial.summary || {
            item_count: 0,
            total_qty: 0,
            subtotal: 0,
            delivery_fee: 0,
            total: 0,
        },

        get countLabel() {
            // Counts different items (not kilos): "2 items" for 1.5 kg carrots + 1 bag of onions.
            const count = this.summary.item_count;
            return count === 1 ? '1 item' : `${count} items`;
        },

        show() {
            this.open = true;
        },

        hide() {
            this.open = false;
        },

        notify(message, { tone = 'success', showCart = false } = {}) {
            clearTimeout(this.toastTimer);
            this.toast = { message, tone, showCart };
            this.toastTimer = setTimeout(() => (this.toast = null), 4000);
        },

        // Cart requests run one at a time: the cart lives in the session, and overlapping requests can overwrite each other.
        queue: Promise.resolve(),
        pending: 0,

        send(url, body) {
            this.pending++;
            this.busy = true;

            const run = async () => {
                try {
                    const data = await cartRequest(url, body);
                    this.lines = data.lines;
                    this.summary = data.summary;
                    return true;
                } catch (e) {
                    this.notify("We couldn't update your cart. Please try again.", { tone: 'error' });
                    return false;
                } finally {
                    this.busy = --this.pending > 0;
                }
            };

            const result = this.queue.then(run);
            this.queue = result;

            return result;
        },

        async add(productId, items) {
            const added = await this.send(this.urls.add, { product_id: productId, items });

            if (added) {
                this.notify('Added to your cart', { showCart: !this.open });
            }

            return added;
        },

        setQty(line, qty) {
            return this.send(this.urls.update, {
                product_id: line.product_id,
                variant_index: line.variant_index,
                qty: snapQty(qty, line.min || 1),
            });
        },

        remove(line) {
            return this.send(this.urls.remove, {
                product_id: line.product_id,
                variant_index: line.variant_index,
            });
        },
    });

    // Shop card: type amounts in the Qty boxes, then "Add to cart" adds them all at once.
    // mins[i] is 0.5 for per-kilo sizes (ordered to 0.1 kg) and 1 for bags (whole only).
    Alpine.data('productCard', (productId, prices, mins) => ({
        qtys: prices.map(() => ''),
        loading: false,

        qtyAt(i) {
            return snapQty(this.qtys[i], mins[i]);
        },

        get hasQty() {
            return this.qtys.some((qty, i) => this.qtyAt(i) > 0);
        },

        get selectedTotal() {
            return this.qtys.reduce((sum, qty, i) => sum + this.qtyAt(i) * prices[i], 0);
        },

        get buttonLabel() {
            return this.loading ? 'Adding…' : 'Add to cart';
        },

        normalize(i) {
            this.qtys[i] = this.qtyAt(i) || '';
        },

        async addToCart() {
            if (!this.hasQty || this.loading) return;

            this.loading = true;

            const items = this.qtys
                .map((qty, variant_index) => ({ variant_index, qty: this.qtyAt(variant_index) }))
                .filter((item) => item.qty > 0);

            if (await Alpine.store('cart').add(productId, items)) {
                this.qtys = this.qtys.map(() => '');
            }

            this.loading = false;
        },
    }));

    // Checkout: "Place order" opens a review dialog first; the form only submits from there.
    Alpine.data('checkout', () => ({
        submitting: false,
        reviewing: false,
        confirmed: false,
        details: {},

        init() {
            // Coming back with the browser's Back button can restore this page frozen mid-submit; reset it.
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    this.submitting = false;
                    this.confirmed = false;
                    this.reviewing = false;
                }
            });
        },

        onSubmit(event) {
            if (this.confirmed) {
                this.submitting = true;
                return;
            }

            event.preventDefault();
            this.openReview();
        },

        openReview() {
            const data = new FormData(this.$refs.form);

            this.details = {
                full_name: data.get('full_name'),
                contact_number: data.get('contact_number'),
                delivery_address: data.get('delivery_address'),
            };
            this.reviewing = true;
        },

        closeReview() {
            if (!this.submitting) this.reviewing = false;
        },

        async confirm() {
            if (this.submitting) return;
            this.submitting = true;

            const form = this.$refs.form;
            const isDesktop = !window.matchMedia('(pointer: coarse)').matches;

            // Desktop: open the Messenger tab now, while we still have the click; browsers block tabs opened later.
            const messengerTab = isDesktop ? window.open('', '_blank') : null;

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });

                if (!res.ok) throw new Error('Order request failed');

                const data = await res.json();

                if (messengerTab && data.messenger_url) {
                    messengerTab.opener = null;
                    messengerTab.location.href = data.messenger_url;
                } else {
                    messengerTab?.close();
                }

                // Phones: the thank-you page opens the Messenger app itself, so Back returns to that page.
                const openHere = !isDesktop && data.messenger_url ? '#open-messenger' : '';
                window.location.replace(data.confirmation_url + openHere);
            } catch (error) {
                messengerTab?.close();

                // Fall back to a normal submit so the server can show any validation errors.
                this.confirmed = true;
                this.$nextTick(() => form.requestSubmit());
            }
        },
    }));
});
