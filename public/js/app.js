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

function peso(amount) {
    return '₱' + Number(amount || 0).toLocaleString('en-PH');
}

function wholeQty(value) {
    return Math.max(0, Math.floor(Number(value) || 0));
}

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
            const qty = this.summary.total_qty;
            return qty === 1 ? '1 item' : `${qty} items`;
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

        async send(url, body) {
            this.busy = true;

            try {
                const data = await cartRequest(url, body);
                this.lines = data.lines;
                this.summary = data.summary;
                return true;
            } catch (e) {
                this.notify("We couldn't update your cart. Please try again.", { tone: 'error' });
                return false;
            } finally {
                this.busy = false;
            }
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
                qty: wholeQty(qty),
            });
        },

        remove(line) {
            return this.send(this.urls.remove, {
                product_id: line.product_id,
                variant_index: line.variant_index,
            });
        },
    });

    Alpine.data('productCard', (productId, prices) => ({
        qtys: prices.map(() => 0),
        loading: false,

        get hasQty() {
            return this.qtys.some((qty) => wholeQty(qty) > 0);
        },

        get selectedTotal() {
            return this.qtys.reduce((sum, qty, i) => sum + wholeQty(qty) * prices[i], 0);
        },

        get buttonLabel() {
            if (this.loading) return 'Adding…';
            return this.hasQty ? `Add to cart · ${peso(this.selectedTotal)}` : 'Add to cart';
        },

        inc(i) {
            this.qtys[i] = wholeQty(this.qtys[i]) + 1;
        },

        dec(i) {
            this.qtys[i] = Math.max(0, wholeQty(this.qtys[i]) - 1);
        },

        normalize(i) {
            this.qtys[i] = wholeQty(this.qtys[i]);
        },

        async addToCart() {
            if (!this.hasQty || this.loading) return;

            this.loading = true;

            const items = this.qtys
                .map((qty, variant_index) => ({ variant_index, qty: wholeQty(qty) }))
                .filter((item) => item.qty > 0);

            if (await Alpine.store('cart').add(productId, items)) {
                this.qtys = this.qtys.map(() => 0);
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
            const date = data.get('preferred_date');
            const time = data.get('preferred_time');

            this.details = {
                full_name: data.get('full_name'),
                contact_number: data.get('contact_number'),
                delivery_address: data.get('delivery_address'),
                order_notes: (data.get('order_notes') || '').trim(),
                date: date
                    ? new Date(date + 'T00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
                    : '',
                time: time
                    ? new Date('1970-01-01T' + time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
                    : '',
            };
            this.reviewing = true;
        },

        closeReview() {
            if (!this.submitting) this.reviewing = false;
        },

        confirm() {
            this.confirmed = true;
            this.$nextTick(() => this.$refs.form.requestSubmit());
        },
    }));
});
