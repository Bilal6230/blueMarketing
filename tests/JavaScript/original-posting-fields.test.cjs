const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const template = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/finance/voucher/typed_posting_fields.blade.php'), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];
const window = {};
vm.runInNewContext(script, { window, document: { readyState: 'loading', addEventListener() {} }, queueMicrotask });
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
