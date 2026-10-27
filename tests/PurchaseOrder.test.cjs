const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

function setup() {
    const factories = {};
    const context = vm.createContext({
        document: { addEventListener(name, callback) { callback(); } },
        Alpine: { data(name, factory) { factories[name] = factory; } },
    });
    vm.runInContext(readFileSync(resolve(__dirname, '../public/js/admin.js'), 'utf8'), context);
    return { context, factories };
}

test('price previews round fractional quantities to centavos, including the maximum allowed values', () => {
    const { context } = setup();
    assert.equal(context.purchaseLineCents('2.500', '95.25'), 23813);
    assert.equal(context.purchaseLineCents('0.001', '5.00'), 1);
    assert.equal(context.purchaseLineCents('99999.999', '999999.99'), 9999999800000);
    assert.equal(context.purchaseMoney(-1187), '-\u20b111.87');
});

test('blank, invalid and negative prices stay pending while zero is a recorded price', () => {
    const { factories } = setup();
    const actuals = factories.purchaseActuals({ 1: { quantity: '2.5', actual_unit_price: '' } }, 25000);
    for (const price of ['', '-1', '1e2', '1.999', '1000000']) {
        actuals.entries[1].actual_unit_price = price;
        assert.equal(actuals.pendingCount(), 1);
        assert.equal(actuals.lineTotal('1'), 'Pending');
    }
    actuals.entries[1].actual_unit_price = '0';
    assert.equal(actuals.pendingCount(), 0);
    assert.equal(actuals.totalCents(), 0);
});

test('actual totals update while unpriced lines remain pending', () => {
    const { factories } = setup();
    const actuals = factories.purchaseActuals({ 1: { quantity: '2.5', actual_unit_price: '110.25' }, 2: { quantity: '1', actual_unit_price: '' } }, 35000);
    assert.equal(actuals.totalCents(), 27563);
    assert.equal(actuals.pendingCount(), 1);
    actuals.entries[2].actual_unit_price = '20.50';
    assert.equal(actuals.totalCents(), 29613);
    assert.equal(actuals.pendingCount(), 0);
});

test('completing the form preserves the submit action before disabling buttons', () => {
    const { factories } = setup();
    const actuals = factories.purchaseActuals({}, 0);
    const action = { value: 'save' };
    actuals.startSubmit({ target: { elements: { namedItem: () => action } }, submitter: { value: 'complete' } });
    assert.equal(action.value, 'complete');
    assert.equal(actuals.submitting, true);
});
