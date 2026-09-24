// Admin page behaviour (Alpine components). Loaded before Alpine so the components exist when it starts.

function warnBeforeLeaving(component) {
    window.addEventListener('beforeunload', (event) => {
        if (component.hasUnsavedChanges() && !component.submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

// Scale big photos down to at most `maxSide` pixels and re-encode as JPEG, so phone photos fit the upload limit.
// Returns the original file when it can't be decoded or shrinking wouldn't make it smaller.
async function shrinkImage(file, maxSide = 1600, quality = 0.85) {
    let bitmap;

    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return file;
    }

    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));

    if (scale === 1 && file.size <= 500 * 1024) {
        bitmap.close?.();
        return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);

    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));

    if (!blob || blob.size >= file.size) {
        return file;
    }

    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
}

document.addEventListener('alpine:init', () => {
    // Price list: tracks which prices differ from what's saved and lets the admin save or discard them together.
    Alpine.data('priceEditor', () => ({
        query: '',
        dirtyCount: 0,
        submitting: false,

        init() {
            this.refresh();
            warnBeforeLeaving(this);
        },

        priceInputs() {
            return this.$root.querySelectorAll('input[data-original]');
        },

        refresh() {
            let count = 0;

            this.priceInputs().forEach((input) => {
                const changed = input.value.trim() !== input.dataset.original;
                input.closest('.a-price-field').classList.toggle('is-changed', changed);
                if (changed) count++;
            });

            this.dirtyCount = count;
        },

        discard() {
            this.priceInputs().forEach((input) => {
                input.value = input.dataset.original;
                const field = input.closest('.a-price-field');
                field.classList.remove('is-invalid');
                field.querySelector('.a-field-error')?.remove();
                input.removeAttribute('aria-invalid');
            });

            this.refresh();
        },

        hasUnsavedChanges() {
            return this.dirtyCount > 0;
        },

        matches(name) {
            const query = this.query.trim().toLowerCase();

            return query === '' || name.includes(query);
        },

        get noMatches() {
            return this.query.trim() !== ''
                && [...this.$root.querySelectorAll('tr[data-name]')].every((row) => !this.matches(row.dataset.name));
        },
    }));

    // Add / edit veggie form with repeatable size + price rows and a live store preview.
    Alpine.data('productForm', (state) => {
        let nextId = 0;
        const toRow = (variant = {}) => ({
            id: nextId++,
            label: variant.label ?? '',
            price: variant.price ?? '',
        });

        return {
            name: state.name ?? '',
            note: state.note ?? '',
            variants: (state.variants.length ? state.variants : [{}]).map(toRow),
            errors: state.errors ?? {},
            max: state.max,
            imageUrl: state.imageUrl ?? null,
            newImageUrl: null,
            removeImage: state.removeImage ?? false,
            maxImageBytes: state.maxImageBytes,
            imageError: null,
            processing: false,
            dragging: false,
            dirty: false,
            submitting: false,

            init() {
                warnBeforeLeaving(this);
            },

            hasUnsavedChanges() {
                return this.dirty;
            },

            error(index, field) {
                return this.errors[`variants.${index}.${field}`]?.[0] ?? null;
            },

            addVariant() {
                if (this.variants.length >= this.max) return;

                this.variants.push(toRow());
                this.dirty = true;
                this.$nextTick(() => document.getElementById(`variants-${this.variants.length - 1}-label`)?.focus());
            },

            removeVariant(index) {
                if (this.variants.length === 1) return;

                this.variants.splice(index, 1);
                // Server errors are tied to row positions, so they no longer line up once a row is removed.
                this.errors = {};
                this.dirty = true;
                this.$nextTick(() => document.getElementById(`variants-${Math.min(index, this.variants.length - 1)}-label`)?.focus());
            },

            get previewImage() {
                return this.newImageUrl || (this.removeImage ? null : this.imageUrl);
            },

            async pickImage(file) {
                this.imageError = null;

                if (!file) return;

                if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
                    this.imageError = 'Use a JPG, PNG or WebP photo.';
                    this.$refs.imageInput.value = '';
                    return;
                }

                this.processing = true;

                try {
                    const prepared = await shrinkImage(file);

                    if (prepared.size > this.maxImageBytes) {
                        throw new Error('This photo is too big. Try one under 2 MB.');
                    }

                    // Put the prepared file into the real input so it's sent with the form.
                    const transfer = new DataTransfer();
                    transfer.items.add(prepared);
                    this.$refs.imageInput.files = transfer.files;

                    if (this.newImageUrl) URL.revokeObjectURL(this.newImageUrl);
                    this.newImageUrl = URL.createObjectURL(prepared);
                    this.removeImage = false;
                    this.dirty = true;
                } catch (error) {
                    this.imageError = error?.message?.startsWith('This photo')
                        ? error.message
                        : 'That photo couldn’t be prepared. Try a different one.';
                    this.$refs.imageInput.value = '';
                } finally {
                    this.processing = false;
                }
            },

            removePhoto() {
                if (this.newImageUrl) {
                    URL.revokeObjectURL(this.newImageUrl);
                    this.newImageUrl = null;
                    this.$refs.imageInput.value = '';
                }

                if (this.imageUrl) {
                    this.removeImage = true;
                }

                this.imageError = null;
                this.dirty = true;
                this.$nextTick(() => this.$refs.imageInput.focus());
            },

            undoRemove() {
                this.removeImage = false;
            },

            formatPrice(price) {
                const pesos = parseInt(price, 10);

                return Number.isFinite(pesos) && pesos > 0 ? `₱${pesos.toLocaleString()}` : '₱—';
            },
        };
    });
});
