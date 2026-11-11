const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const template = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/finance/voucher/typed_posting_fields.blade.php'), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];
const window = {};
const document = { readyState: 'loading' };
// Minimal deterministic jQuery facade for the application's first-party
// jQuery posting script. This simulates the selectors used by the tested
// form without loading a browser or switching production code to native DOM.
class JQuerySelection {
    constructor(elements = []) { this.elements = elements; this.length = elements.length; }
    find(selector) {
        if (!this.length) return new JQuerySelection();
        if (selector === '[data-posting-allocation]') return new JQuerySelection(inputs);
        const item = this.elements[0].querySelector?.(selector);
        return new JQuerySelection(item ? [item] : []);
    }
    first() { return new JQuerySelection(this.elements.slice(0, 1)); }
    val(next) {
        if (arguments.length) {
            this.elements.forEach(item => { item.value = next; });
            return this;
        }
        return this.elements[0]?.value;
    }
    attr(key, next) {
        const item = this.elements[0];
        if (!item) return undefined;
        const name = key.replace(/^data-/, '').replace(/-([a-z])/g, (_, c) => c.toUpperCase());
        if (arguments.length > 1) {
            item.dataset ??= {};
            item.dataset[name] = next;
            return this;
        }
        return item.dataset?.[name];
    }
    prop(key, next) {
        this.elements.forEach(item => { item[key] = next; });
        return this;
    }
    each(callback) { this.elements.forEach((item, i) => callback.call(item, i, item)); return this; }
    on() { return this; }
}
const $ = value => {
    if (typeof value === 'function') {
        value();
        return new JQuerySelection();
    }
    if (value === '[data-original-posting-fields]') return new JQuerySelection();
    return new JQuerySelection(value === null || value === undefined ? [] : [value]);
};
vm.runInNewContext(script, { window, document, $, queueMicrotask });
assert.equal(window.originalPostingTotal(['0.10', '0.20']), '0.30');
assert.equal(window.originalPostingTotal(['1,234.56', '0.44']), '1235.00');
assert.equal(window.originalPostingTotal(['2.005']), null);
assert.equal(window.originalPostingTotal(['1e3']), null);
const inputs = [
    { dataset: { postingSide: 'amount' } }, { dataset: { postingSide: 'zero' } },
    { dataset: { postingSide: 'zero' } }, { dataset: { postingSide: 'amount' } },
];
const values = { amount: '1,234.56', payment: '1', status: '', receipt: '', action: '' };
const form = {
    dataset: {},
    querySelector(selector) {
        if (selector === '[data-original-posting-fields]') return { querySelectorAll: () => inputs };
        if (selector === '[name="amount"]') return { value: values.amount };
        if (selector === '[name="payment_type"]') return { value: values.payment };
        if (selector.includes('select[name="passing_status"]')) return { value: values.status };
        if (selector === '[name="selected_pending_payment_id"]') return { value: values.receipt };
        if (selector === '[name="action"]') return { value: values.action };
        return null;
    },
};
window.syncOriginalPostingForm(form);
assert.deepEqual(inputs.map(input => input.value), ['1234.56', '0.00', '0.00', '1234.56']);
assert.ok(inputs.every(input => !input.disabled));
values.payment = '2';
window.syncOriginalPostingForm(form);
assert.ok(inputs.every(input => input.disabled));
values.receipt = '42'; values.status = '1';
window.syncOriginalPostingForm(form);
assert.ok(inputs.every(input => !input.disabled));
values.status = '2';
window.syncOriginalPostingForm(form);
assert.ok(inputs.every(input => input.disabled));
console.log('Original posting UI exact-money and pending/clearance allocation checks PASS');
