const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

function selector(ids) {
    const factories = {};
    const context = vm.createContext({
        document: { addEventListener(name, callback) { callback(); } },
        Alpine: { data(name, factory) { factories[name] = factory; } },
    });
    vm.runInContext(readFileSync(resolve(__dirname, '../public/js/admin.js'), 'utf8'), context);
    return factories.personExport(ids);
}

test('the customer checklist starts with everyone selected', () => {
    const customers = selector(['11', '25', '40']);
    assert.deepEqual(Array.from(customers.selected), ['11', '25', '40']);
    assert.equal(customers.allSelected, true);
});

test('select all clears every customer and can select them again', () => {
    const customers = selector(['11', '25', '40']);
    customers.toggleAll();
    assert.deepEqual(Array.from(customers.selected), []);
    assert.equal(customers.allSelected, false);
    customers.toggleAll();
    assert.deepEqual(Array.from(customers.selected), ['11', '25', '40']);
});

test('individual choices do not change the customer list used by select all', () => {
    const customers = selector(['11', '25', '40']);
    customers.selected.splice(1, 1);
    assert.equal(customers.allSelected, false);
    assert.deepEqual(customers.ids, ['11', '25', '40']);
    customers.toggleAll();
    assert.equal(customers.allSelected, true);
});
