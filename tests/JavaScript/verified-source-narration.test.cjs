'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname,'../..');
const source = fs.readFileSync(path.join(root,'public/js/finance/verified-source-narration.js'),'utf8');
const $ = {trim: value => String(value).trim()};
vm.runInNewContext(source,{jQuery:$},{filename:'verified-source-narration.js'});
const formatter = $.financeVerifiedSourceNarration;
assert.equal(typeof formatter.set,'function');
function field(initial,max=2000){
  let value=initial;
  return {
    length:1,
    val(next){if(arguments.length===0)return value;value=next;return this;},
    attr(key){return key==='maxlength'?String(max):undefined;},
    trigger(type){assert.equal(type,'input');return this;}
  };
}
const online={
  method:'online',reference:'328',sending_bank:'Alfalah',
  receiving_bank:'Alhabib',passing_date:'2026-11-10'
};
const cheque={
  method:'cheque',reference:'12345678',cheque_last4:'5678',
  cheque_title:'Supplier cheque',bank:'Alfalah',passing_date:'2026-11-20'
};
assert.equal(formatter.evidence(online),
  'SOURCE Online | Transaction ID / Reference: 328 | Sending Bank: Alfalah | Receiving Bank: Alhabib | Clearing Date: 2026-11-10');
assert.equal(formatter.evidence(cheque),
  'SOURCE Cheque | Title: Supplier cheque | Cheque Number: ****5678 | Bank Name: Alfalah | Clearing Date: 2026-11-20');

for(const form of ['BOR','BOP','BD','BW','PPR']){
  const f=field('User typed '+form);
  assert.equal(formatter.set(f,online).ok,true);
  assert.equal(f.val().startsWith('User typed '+form+'\nSOURCE Online | '),true);
  assert.equal(formatter.set(f,online).ok,true);
  assert.equal((f.val().match(/SOURCE Online \|/g)||[]).length,1);
  assert.equal(formatter.set(f,cheque).ok,true);
  assert.equal(f.val().startsWith('User typed '+form+'\nSOURCE Cheque | '),true);
  assert.equal(f.val().includes('SOURCE Online'),false);
  assert.equal(formatter.set(f,null).ok,true);
  assert.equal(f.val(),'User typed '+form);
}
const tiny=field('Long text',40);
assert.equal(formatter.set(tiny,online).ok,false);
assert.equal(tiny.val(),'Long text','length refusal must not change manual narration');
const old=field('Manual note\n[BANK SOURCE]\nOld data\n[/BANK SOURCE]');
assert.equal(formatter.set(old,cheque).ok,true);
assert.equal(old.val().includes('[BANK SOURCE]'),false);
assert.equal(old.val().includes('SOURCE Cheque'),true);

const views=[
 'resources/views/admin/finance/voucher/cash_voucher.blade.php',
 'resources/views/admin/finance/voucher/bank_transfer_workspace.blade.php',
 'resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php'
];
for(const view of views){
 const text=fs.readFileSync(path.join(root,view),'utf8');
 assert.ok(text.includes("asset('js/finance/verified-source-narration.js')"),view+' must load shared formatter');
 assert.ok(text.includes('$.financeVerifiedSourceNarration.set('),view+' must call shared formatter');
}
console.log('Verified source narration PASS: 5 voucher families, online/cheque, manual preservation, replacement, clearing, limit refusal and three views.');
