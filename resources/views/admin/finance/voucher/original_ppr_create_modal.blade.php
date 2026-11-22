{{-- Original single-booking PPR writer; displayed in the modern register. --}}
<div class="modal fade" id="prOriginalCreateModal" tabindex="-1" role="dialog" aria-labelledby="prOriginalTitle" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header"><h5 id="prOriginalTitle" class="modal-title">New Plot Receive Payment (PPR)</h5>
   <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="prOriginalForm" method="POST" action="{{ route('booking.customer.deposit') }}">
   @csrf
   <input type="hidden" name="action" value="deposit">
   <div class="modal-body">
    <div class="alert alert-danger d-none" id="prOriginalError" role="alert"></div>
    <div class="form-row">
     <div class="form-group col-md-4"><label>Voucher No.</label><input type="text" id="prOriginalNumber" name="voucher" class="form-control" value="{{ $nextVoucherNumber }}" readonly></div>
     <div class="form-group col-md-4"><label>Date</label><input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required></div>
     <div class="form-group col-md-4"><label>Reference / Slip No.</label><input type="text" name="reference" class="form-control" maxlength="128" required></div>
     <div class="form-group col-md-6"><label>Customer</label><select id="prOriginalCustomer" name="customer_id" class="form-control" required><option value="">Select customer</option></select></div>
     <div class="form-group col-md-6"><label>Plot / Booking</label><select id="prOriginalPlot" name="plot_id" class="form-control" required><option value="">Select plot</option></select></div>
     <div class="form-group col-md-6"><label>Amount</label><input type="number" name="amount" class="form-control" min="0.01" step="0.01" required></div>
     <div class="form-group col-md-6"><label>Payment Type</label><select id="prOriginalPayment" name="payment_type" class="form-control" required><option value="1">Cash</option><option value="2">Online</option><option value="3">Cheque</option></select></div>
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
