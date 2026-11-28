{{-- Original single-booking PPR writer; displayed in the modern register. --}}
<div class="modal fade" id="prOriginalCreateModal" tabindex="-1" role="dialog" aria-labelledby="prOriginalTitle" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h5 id="prOriginalTitle" class="modal-title">New Plot Receive Payment (PPR)</h5>
   <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="prOriginalForm" method="POST" action="{{ route('booking.customer.deposit') }}">
   @csrf
   <input type="hidden" name="passed_source_method" id="prPassedMethod" value="">
   <input type="hidden" name="passed_source_id" id="prPassedId" value="">
   <input type="hidden" name="action" value="deposit">
   <div class="modal-body">
    <div class="alert alert-danger d-none" id="prOriginalError" role="alert"></div>
    <div class="form-row">
     <div class="form-group col-md-4"><label>Voucher No.</label><input type="text" id="prOriginalNumber" name="voucher" class="form-control" value="{{ $nextVoucherNumber }}" readonly></div>
     <div class="form-group col-md-4"><label>Date</label><input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required></div>
     <div class="form-group col-md-4"><label>Reference / Slip No.</label><input type="text" name="reference" class="form-control" maxlength="128" required></div>
     <div class="form-group col-md-6"><label>Customer</label><select id="prOriginalCustomer" name="customer_id" class="form-control" required><option value="">Select customer</option></select></div>
     <div class="form-group col-md-6"><label>Plot / Booking</label><select id="prOriginalPlot" name="plot_id" class="form-control" required><option value="">Select plot</option></select></div>
     <div class="form-group col-md-4"><label for="prOriginalAmount">Amount</label><input id="prOriginalAmount" type="number" name="amount" class="form-control" min="0.01" step="0.01" required></div>
     <div class="form-group col-md-8"><label for="prOriginalAmountWords">Amount in Words</label><input id="prOriginalAmountWords" type="text" class="form-control" placeholder="Amount in words will appear automatically" disabled readonly style="background:#f1f5f9;color:#20364d;font-size:15px;font-weight:600;opacity:1"></div>
     <div class="form-group col-md-6"><label>Payment Type</label><select id="prOriginalPayment" name="payment_type" class="form-control" required><option value="1">Cash</option><option value="2">Online</option><option value="3">Cheque</option></select></div>
     <div class="form-group col-md-6"><label for="prOriginalCounterAccount">Credit Account (Cash / Bank) <span class="text-danger">*</span></label>
       <select id="prOriginalCounterAccount" name="counter_account_id" class="form-control" required>
        <option value="">Select active project Cash / Bank account</option>
        @foreach(($accounts ?? collect()) as $postingAccount)
         @php
          $counterName=strtolower((string)($postingAccount->headAccounting?->name ?? '').' '.(string)($postingAccount->subheadAccounting?->name ?? ''));
          $isCashCounter=str_contains($counterName,'cash');
          $isActiveBankCounter=in_array((int)$postingAccount->id,$activeBankCounterIds ?? [],true);
         @endphp
         <option value="{{ $postingAccount->id }}" data-cash="{{ $isCashCounter ? 1 : 0 }}"
           data-bank="{{ $isActiveBankCounter ? 1 : 0 }}">
          {{ $postingAccount->headAccounting?->name ?? '' }} / {{ $postingAccount->subheadAccounting?->name ?? '' }}
         </option>
        @endforeach
       </select>
       <small class="text-muted">Cash receipts use a project Cash account. Verified Online/Cheque receipts require an active project Bank account.</small>
     </div>
     <div class="form-group col-12 pr-original-bank d-none"><label>Verified Cheque / Online Source <span class="text-danger">*</span></label>
       <div class="input-group"><input id="prPassedLabel" class="form-control" placeholder="Select cleared cheque or confirmed online transaction" readonly>
        <div class="input-group-append"><button type="button" id="prOpenPassedPicker" class="btn btn-outline-primary">Choose Source</button></div></div>
       <small class="text-muted">Only verified incoming transactions with remaining balance are eligible. One source per PPR.</small>
     </div>
     <div class="form-group col-md-4 pr-original-bank d-none"><label>Transaction / Cheque Number</label><input type="text" name="t_number" class="form-control" maxlength="255"></div>
     <div class="form-group col-md-4 pr-original-bank d-none"><label>Bank</label><select name="bank_id" class="form-control"><option value="">Select bank</option>@foreach(getPakistanBanks() as $bank)<option value="{{ $bank['id'] }}">{{ $bank['name'] }}</option>@endforeach</select></div>
     <div class="form-group col-md-4 pr-original-bank d-none"><label>Passing Date</label><input type="date" name="passing_date" class="form-control"></div>
     <div class="form-group col-md-12 pr-original-cheque d-none"><label>Cheque Purpose</label><input type="text" name="cheque_purpose" class="form-control" maxlength="500"></div>
     <div class="form-group col-12"><label>Description / Narration</label><textarea name="detail" class="form-control" rows="3" required maxlength="2000"></textarea></div>
    </div>
    <small class="text-muted">One receipt per booking, using the original project-scoped PPR source and ledger flow.</small>
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
    <button type="submit" id="prOriginalSave" class="btn btn-primary btn-sm">Save PPR Voucher</button></div>
  </form>
 </div></div>
</div>

<div class="modal fade" id="prPassedPickerModal" tabindex="-1" role="dialog" aria-label="Verified PPR bank source" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Select Cleared Cheque / Confirmed Online Transaction</h5>
   <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
  <div class="modal-body">
   <input id="prPassedSearch" class="form-control mb-2" placeholder="Search reference or cheque number" maxlength="120">
   <div id="prPassedError" class="alert alert-danger d-none"></div>
   <div class="table-responsive"><table class="table table-sm table-hover">
    <thead><tr><th>Select</th><th>Reference</th><th>Passing date</th><th>Available</th><th>Bank</th></tr></thead>
    <tbody id="prPassedRows"><tr><td colspan="5" class="text-muted text-center">Choose Online or Cheque to load available sources.</td></tr></tbody>
   </table></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
   <button type="button" class="btn btn-primary" id="prApplyPassed" disabled>Apply selected source</button></div>
 </div></div>
</div>
<script>
jQuery(function($){
  function updatePprAmountWords(){
    var n=Number($('#prOriginalAmount').val()), words='';
    if(Number.isFinite(n)&&n>0&&window.numberToWords&&typeof numberToWords.toWords==='function') words=numberToWords.toWords(n);
    $('#prOriginalAmountWords').val(words);
  }
  $(document).on('input change','#prOriginalAmount',updatePprAmountWords);
  updatePprAmountWords();
});
</script>
