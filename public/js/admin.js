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

// Libraries that turn the price sheet into a PDF. Loaded the first time "Download PDF" is pressed,
// so the other admin pages never download them.
const PDF_LIBRARIES = [
    ['https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js', 'sha384-ZZ1pncU3bQe8y31yfZdMFdSpttDoPmOZg2wguVK9almUodir1PghgT0eY7Mrty8H'],
    ['https://cdn.jsdelivr.net/npm/jspdf@4.2.1/dist/jspdf.umd.min.js', 'sha384-qovJwSBbRDPP5cEjCp8S0UP66wrvnjaa60XMOGzTNanrThcrGfXfnZkvgY8N1KT3'],
];

// Drag and drop for putting products in order, loaded only on the Veggies and Fruits lists.
const SORTABLE_LIBRARY = ['https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js', 'sha384-DgmC6Xe2bSN2WjTDXzWYbUbxyhNP+NNkGDR/g78pCXV7E7rcVTGxVg0uIVCUUcBc'];

// Letter ("short bond") paper in points, with the same 12 mm margins as printing.
const PDF_PAPER = { width: 612, height: 792, margin: 34 };

const loadingScripts = new Map();

// Load a [src, integrity] script once, however many times it's asked for.
function loadScript([src, integrity]) {
    if (!loadingScripts.has(src)) {
        loadingScripts.set(src, new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.integrity = integrity;
            script.crossOrigin = 'anonymous';
            script.onload = resolve;
            script.onerror = () => {
                // Let the next try load it again, e.g. once the internet is back.
                loadingScripts.delete(src);
                script.remove();
                reject(new Error(`Couldn't load ${src}`));
            };
            document.head.append(script);
        }));
    }

    return loadingScripts.get(src);
}

// A copy of the price sheet laid out like the printed one (.is-pdf in admin.css), without ids or Alpine attributes.
function copyPriceSheet(sheet) {
    const copy = sheet.cloneNode(true);

    copy.classList.add('is-pdf');
    copy.removeAttribute('x-ref');
    copy.querySelectorAll('[id]').forEach((element) => element.removeAttribute('id'));

    return copy;
}

// html2canvas stretches photos to fill their box (it ignores object-fit: contain), so size each copied photo
// to fit its box the way the page shows it. Also pass the placeholder sprout its colour, which html2canvas loses.
function fitPhotosForPdf(copy, sheet) {
    const originals = sheet.querySelectorAll('img.a-price-sheet-photo');

    copy.querySelectorAll('img.a-price-sheet-photo').forEach((photo, index) => {
        const { naturalWidth, naturalHeight } = originals[index] ?? {};

        if (!naturalWidth || !naturalHeight) return;

        const boxWidth = photo.clientWidth;
        const boxHeight = photo.clientHeight;
        const scale = Math.min(boxWidth / naturalWidth, boxHeight / naturalHeight);
        const width = naturalWidth * scale;
        const height = naturalHeight * scale;

        Object.assign(photo.style, {
            width: `${width}px`,
            height: `${height}px`,
            margin: `${(boxHeight - height) / 2}px ${(boxWidth - width) / 2}px`,
        });
    });

    copy.querySelectorAll('svg').forEach((icon) => {
        icon.style.color = getComputedStyle(icon).color;
    });
}

// Split the sheet into pages that fit the paper: the header on page 1, then whole rows of products,
// so no product is ever cut in two. Returns one laid-out copy of the sheet per page, inside `stage`.
function paginatePriceSheet(sheet, stage) {
    const full = copyPriceSheet(sheet);
    stage.append(full);
    fitPhotosForPdf(full, sheet);

    const grid = full.querySelector('.a-price-sheet-grid');
    const sheetTop = full.getBoundingClientRect().top;
    const gridTop = grid.getBoundingClientRect().top;
    const rowGap = parseFloat(getComputedStyle(grid).rowGap) || 0;
    // CSS pixels per point: the copy is as wide as the paper minus its margins.
    const pixelsPerPoint = full.offsetWidth / (PDF_PAPER.width - 2 * PDF_PAPER.margin);
    const pageHeight = (PDF_PAPER.height - 2 * PDF_PAPER.margin) * pixelsPerPoint;

    // Products that start at the same height share a row.
    const rows = [];

    [...grid.children].forEach((item, index) => {
        const { top, bottom } = item.getBoundingClientRect();
        const row = rows.at(-1);

        if (row && Math.abs(row.top - top) < 1) {
            row.indexes.push(index);
            row.bottom = Math.max(row.bottom, bottom);
        } else {
            rows.push({ indexes: [index], top, bottom });
        }
    });

    const pages = [];

    for (const row of rows) {
        const rowHeight = row.bottom - row.top;
        const page = pages.at(-1);

        if (page && page.height + rowGap + rowHeight <= pageHeight) {
            page.indexes.push(...row.indexes);
            page.height += rowGap + rowHeight;
        } else {
            pages.push({ indexes: [...row.indexes], height: (pages.length === 0 ? gridTop - sheetTop : 0) + rowHeight });
        }
    }

    const pageSheets = pages.map((page, pageIndex) => {
        const copy = full.cloneNode(true);
        const copyGrid = copy.querySelector('.a-price-sheet-grid');

        [...copyGrid.children].forEach((item, index) => page.indexes.includes(index) || item.remove());

        if (pageIndex > 0) {
            copy.querySelector('.a-price-sheet-head').remove();
            copyGrid.classList.add('is-continued');
        }

        return copy;
    });

    full.remove();
    stage.append(...pageSheets);

    return pageSheets;
}

// The price sheet as a PDF on Letter paper, each page drawn from a copy of the sheet with html2canvas.
async function makePriceSheetPdf(sheet, title) {
    await Promise.all(PDF_LIBRARIES.map(loadScript));

    // Wait for every photo so none is missing from the PDF (a broken one is just left blank).
    await Promise.all([...sheet.querySelectorAll('img')].map((photo) => photo.decode().catch(() => {})));

    // Copies are laid out out of sight, then shown only in html2canvas's own copy of the page.
    const stage = document.createElement('div');
    stage.className = 'a-pdf-stage';
    stage.setAttribute('aria-hidden', 'true');
    document.body.append(stage);

    try {
        const pdf = new window.jspdf.jsPDF({ unit: 'pt', format: 'letter' });
        const contentWidth = PDF_PAPER.width - 2 * PDF_PAPER.margin;

        pdf.setProperties({ title });

        for (const [index, page] of paginatePriceSheet(sheet, stage).entries()) {
            const canvas = await window.html2canvas(page, {
                scale: 2,
                backgroundColor: '#ffffff',
                logging: false,
                onclone: (pageCopy) => pageCopy.querySelector('.a-pdf-stage').classList.add('is-drawing'),
            });

            if (index > 0) pdf.addPage();

            pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', PDF_PAPER.margin, PDF_PAPER.margin, contentWidth, canvas.height * contentWidth / canvas.width);
        }

        return pdf;
    } finally {
        stage.remove();
    }
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

    // Veggies / Fruits list order: drag a row by its handle, or focus the handle and press ↑ / ↓, to change
    // where the product shows on the store. Every move is saved right away. Sits inside priceEditor (for `query`).
    Alpine.data('productOrder', ({ url, category }) => ({
        orderStatus: '',
        orderFailed: false,
        orderSaving: false,
        sending: false,
        saveAgain: false,
        saveTimer: null,

        // Hidden rows would make "above" and "below" unclear, so the order can't change while searching.
        get searching() {
            return this.query.trim() !== '';
        },

        async init() {
            try {
                await loadScript(SORTABLE_LIBRARY);
            } catch {
                return; // No dragging without the library; ↑ / ↓ on the handle still work.
            }

            const sortable = window.Sortable.create(this.$refs.rows, {
                handle: '.a-drag-handle',
                draggable: 'tr[data-product-id]',
                // Sortable's own drag works the same everywhere (Firefox can't start a native drag on a button).
                forceFallback: true,
                fallbackTolerance: 3,
                // Scroll while the row is within 100px of the screen's edge: on phones the sticky top bar covers
                // the top 60px, so this keeps the row moving past other rows as the page scrolls.
                scrollSensitivity: 100,
                animation: 150,
                ghostClass: 'is-drag-placeholder',
                fallbackClass: 'is-drag-copy',
                disabled: this.searching,
                onEnd: ({ item, oldIndex, newIndex }) => {
                    if (oldIndex !== newIndex) this.moved(item);
                },
            });

            this.$watch('searching', (searching) => sortable.option('disabled', searching));
        },

        rows() {
            return [...this.$refs.rows.querySelectorAll('tr[data-product-id]')];
        },

        // Keyboard: move the handle's row one place up (step -1) or down (step 1).
        move(handle, step) {
            if (this.searching) return;

            const row = handle.closest('tr');
            const rows = this.rows();
            const neighbour = rows[rows.indexOf(row) + step];

            if (!neighbour) return;

            if (step < 0) {
                neighbour.before(row);
            } else {
                neighbour.after(row);
            }

            handle.focus();
            this.moved(row);
        },

        moved(row) {
            const rows = this.rows();

            this.orderFailed = false;
            this.orderSaving = true;
            this.orderStatus = `${row.dataset.productName} is now number ${rows.indexOf(row) + 1} of ${rows.length}. Saving…`;

            // A quick run of ↑ / ↓ presses is saved once, at the end.
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.saveOrder(), 500);
        },

        async saveOrder() {
            // One save at a time; a move made meanwhile is saved straight after.
            if (this.sending) {
                this.saveAgain = true;
                return;
            }

            this.sending = true;
            let message;
            let failed = false;

            try {
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ category, ids: this.rows().map((row) => Number(row.dataset.productId)) }),
                });
                const result = await response.json().catch(() => ({}));

                if (!response.ok) {
                    // 422 says why (e.g. the list changed in another tab); anything else gets the general message.
                    throw new Error(response.status === 422 ? result.message : '');
                }

                message = result.message;
            } catch (error) {
                failed = true;
                message = error.message || 'The new order couldn’t be saved. Check the internet connection, then reload the page and try again.';
            }

            this.sending = false;

            if (this.saveAgain) {
                this.saveAgain = false;
                this.saveOrder();
                return;
            }

            this.orderSaving = false;
            this.orderFailed = failed;
            this.orderStatus = message;
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

    // Print price list: "Download PDF" saves the sheet as a PDF that looks like the printed one.
    Alpine.data('priceSheetPdf', ({ filename, title }) => ({
        busy: false,
        failed: false,

        async download() {
            if (this.busy) return;

            this.busy = true;
            this.failed = false;

            try {
                const pdf = await makePriceSheetPdf(this.$refs.sheet, title);
                pdf.save(filename);
            } catch (error) {
                console.error(error);
                this.failed = true;
            } finally {
                this.busy = false;
            }
        },
    }));
});
