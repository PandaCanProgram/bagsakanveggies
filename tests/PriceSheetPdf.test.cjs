const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

const source = readFileSync(resolve(__dirname, '../public/js/admin.js'), 'utf8');

function environment() {
    const factories = {};
    const captured = [];
    const downloads = [];
    const elements = [];
    let addedPages = 0;

    const context = vm.createContext({
        console: { error() {} },
        document: {
            fonts: { ready: Promise.resolve() },
            addEventListener(name, callback) { callback(); },
            body: { append() {} },
            createElement() {
                const element = {
                    children: [],
                    removed: false,
                    setAttribute() {},
                    append(...children) { this.children.push(...children); },
                    remove() { this.removed = true; },
                };
                elements.push(element);
                return element;
            },
        },
        Alpine: { data(name, factory) { factories[name] = factory; } },
        window: {
            jspdf: {
                jsPDF: class {
                    setProperties() {}
                    addPage() { addedPages++; }
                    addImage() {}
                    save(filename) { downloads.push(filename); }
                },
            },
            async html2canvas(page) {
                captured.push(page.name);
                return { width: 720, height: 500, toDataURL: () => 'image' };
            },
        },
    });

    vm.runInContext(source, context);
    vm.runInContext('loadScript = async () => {}; paginatePriceSheet = (sheet) => sheet.pages;', context);

    const sheet = (name, pages = 1) => ({
        querySelectorAll: () => [{ decode: async () => {} }],
        pages: Array.from({ length: pages }, (_, i) => ({
            name: name + '-' + i,
            remove() {},
        })),
    });

    function component(sheets) {
        const instance = factories.priceSheetPdf({
            filename: 'vegetables.pdf',
            title: 'Vegetables',
            allUrl: '/admin/products/print?category=all',
            allFilename: 'all-categories.pdf',
            allTitle: 'All categories',
        });
        instance.$refs = { sheets: { querySelectorAll: () => sheets } };
        return instance;
    }

    return { context, captured, downloads, elements, sheet, component, pagesAdded: () => addedPages };
}

test('one PDF contains every category and continuation page without blank pages', async () => {
    const env = environment();
    const sheets = [env.sheet('vegetables', 2), env.sheet('fruits'), env.sheet('frozen'), env.sheet('miscellaneous')];
    sheets[1].querySelectorAll = () => [{ decode: async () => { throw new Error('Broken image'); } }];
    env.context.sheets = sheets;

    await vm.runInContext('makePriceSheetPdf(sheets, "All categories")', env.context);

    assert.deepEqual(env.captured, ['vegetables-0', 'vegetables-1', 'fruits-0', 'frozen-0', 'miscellaneous-0']);
    assert.equal(env.pagesAdded(), 4);
    assert.equal(env.elements[0].removed, true);
});

test('the existing category button downloads only its displayed sheets', async () => {
    const env = environment();
    const component = env.component([env.sheet('vegetables')]);
    env.context.fetch = () => { throw new Error('A single category should not fetch anything'); };

    await component.download();

    assert.deepEqual(env.downloads, ['vegetables.pdf']);
    assert.deepEqual(env.captured, ['vegetables-0']);
    assert.equal(component.failed, false);
    assert.equal(component.busy, false);
});

test('download all fetches fresh sheets, saves one file, and removes temporary elements', async () => {
    const env = environment();
    const sheets = [env.sheet('fruits'), env.sheet('frozen')];
    const component = env.component([env.sheet('vegetables')]);
    env.context.fetch = async (url, options) => {
        assert.equal(url, '/admin/products/print?category=all');
        assert.equal(options.credentials, 'same-origin');
        return { ok: true, redirected: false, text: async () => '<html></html>' };
    };
    env.context.DOMParser = class {
        parseFromString() { return { querySelectorAll: () => sheets }; }
    };

    await component.download(true);

    assert.deepEqual(env.downloads, ['all-categories.pdf']);
    assert.deepEqual(env.captured, ['fruits-0', 'frozen-0']);
    assert.equal(component.failed, false);
    assert.equal(component.busy, false);
    assert.equal(component.downloadingAll, false);
    assert.ok(env.elements.every((element) => element.removed));
});

test('session expiry and server failures show an error without saving a PDF', async () => {
    for (const response of [{ ok: true, redirected: true }, { ok: false, redirected: false }]) {
        const env = environment();
        const component = env.component([]);
        env.context.fetch = async () => response;

        await component.download(true);

        assert.equal(component.failed, true);
        assert.equal(component.busy, false);
        assert.equal(component.downloadingAll, false);
        assert.deepEqual(env.downloads, []);
    }
});

test('an empty store does not save a blank PDF', async () => {
    const env = environment();
    const component = env.component([]);
    env.context.fetch = async () => ({ ok: true, redirected: false, text: async () => '' });
    env.context.DOMParser = class {
        parseFromString() { return { querySelectorAll: () => [] }; }
    };

    await component.download(true);

    assert.equal(component.failed, true);
    assert.deepEqual(env.downloads, []);
    assert.ok(env.elements.every((element) => element.removed));
});

test('rendering failures clear the busy state and allow a retry', async () => {
    const env = environment();
    const component = env.component([env.sheet('vegetables')]);
    env.context.window.html2canvas = async () => { throw new Error('Render failed'); };

    await component.download();

    assert.equal(component.failed, true);
    assert.equal(component.busy, false);
    assert.equal(env.elements[0].removed, true);

    env.context.window.html2canvas = async () => ({ width: 720, height: 500, toDataURL: () => 'image' });
    await component.download();

    assert.equal(component.failed, false);
    assert.deepEqual(env.downloads, ['vegetables.pdf']);
});

test('a second click during an export cannot start another download', async () => {
    const env = environment();
    const component = env.component([]);
    component.busy = true;
    env.context.fetch = () => { throw new Error('A second request should not start'); };

    await component.download(true);

    assert.deepEqual(env.downloads, []);
    assert.equal(component.busy, true);
});
