@extends('admin.layouts.master')
@section('title', $kind === 'deposit' ? 'Bank Deposit Voucher' : 'Bank Withdrawal Voucher')
@section('content')
<style>
/* Use the same dashboard chrome, density, and credit/debit palettes as daily_directory. */
body:has(#bankTransferApp) .main-content.page-content {margin-left:250px!important;width:calc(100% - 250px)!important;max-width:calc(100% - 250px);padding:66px 7px 0!important;box-sizing:border-box;overflow:hidden}
body.sidebar-collapse:has(#bankTransferApp) .main-content.page-content {margin-left:4.6rem!important;width:calc(100% - 4.6rem)!important;max-width:calc(100% - 4.6rem)}
body:has(#bankTransferApp) .main-footer{display:none!important}
#bankTransferApp {--dv-accent:#128044;--dv-pale:#e9f8ef;display:flex;flex-direction:column;height:calc(100dvh - 124px);min-height:350px;padding:.3rem .15rem!important;overflow:hidden}
#bankTransferApp.bt-debit {--dv-accent:#bd3140;--dv-pale:#fff0f1}
#bankTransferApp .bt-heading{flex:0 0 auto;padding:.15rem .25rem .35rem;margin:0!important}
#bankTransferApp .bt-heading h2 {font-size:1.35rem;color:var(--dv-accent);font-weight:700}
#bankTransferApp .bt-subtitle {color:#758397;font-size:.83rem}
#bankTransferApp .bt-filter-card,#bankTransferApp .bt-table-card{border:0;border-top:3px solid var(--dv-accent);border-radius:11px;box-shadow:0 5px 18px rgba(16,36,64,.08);overflow:hidden}
#bankTransferApp .bt-filter-card {flex:0 0 auto;margin-bottom:.55rem!important}
#bankTransferApp .bt-filter-card .card-body{padding:.58rem .75rem;background:#fff}
#bankTransferApp .bt-filter-row {display:grid;grid-template-columns:repeat(4,minmax(120px,1fr)) auto;gap:.55rem;align-items:end}
#bankTransferApp .bt-filter-row label{font-size:.8rem;font-weight:700;margin:0 0 .15rem}
#bankTransferApp .bt-filter-row .form-control{height:34px;padding:.22rem .55rem;font-size:.85rem}
#bankTransferApp .bt-filter-actions{display:flex;gap:.4rem;white-space:nowrap}
#bankTransferApp .bt-filter-actions .btn-primary,#bankTransferApp .bt-filter-actions .btn-success {background:var(--dv-accent);border-color:var(--dv-accent);color:#fff}
#bankTransferApp .bt-table-card{flex:1 1 0;min-height:0;margin:0!important;display:flex;flex-direction:column;background:#fff}
#bankTransferApp .bt-tools{flex:0 0 auto;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:.4rem .55rem;background:#fff}
#bankTransferApp .bt-tools label{font-size:.82rem;display:flex;gap:6px;align-items:center;margin:0}
#bankTransferApp .bt-tools select{width:73px}
#bankTransferApp .bt-tools input{width:195px}
#bankTransferApp .bt-table-wrap{flex:1 1 0;min-height:0;overflow:auto}
#bankTransferApp #transferTable{border-collapse:separate;border-spacing:0;min-width:1050px;margin:0;table-layout:fixed;font-size:.8rem}
#bankTransferApp #transferTable thead th {background:var(--dv-pale);border-bottom:2px solid var(--dv-accent);position:sticky;top:0;z-index:2;font-weight:700;padding:.52rem .45rem;white-space:nowrap}
#bankTransferApp #transferTable td {padding:.5rem .45rem;height:42px;vertical-align:middle;border-bottom:1px solid #dce3eb}
#bankTransferApp #transferTable tbody tr:nth-child(even) td{background:color-mix(in srgb,var(--dv-pale) 19%,white)}
#bankTransferApp #transferTable tbody tr:hover td{background:color-mix(in srgb,var(--dv-pale) 48%,white)}
#bankTransferApp #transferTable td:nth-child(3){color:var(--dv-accent);font-weight:700}
#bankTransferApp #transferTable td:last-child,#bankTransferApp #transferTable th:last-child{text-align:center;width:74px}
#bankTransferApp .blue-actions-cell{width:var(--bm-action-col,72px)!important}
#bankTransferApp .blue-actions-toggle{color:var(--dv-accent)}
#bankTransferApp .bt-footer{flex:0 0 auto;display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:43px;padding:.35rem .65rem;background:#fff}
#bankTransferApp .bt-page-btn{border:1px solid var(--dv-accent);background:#fff;color:var(--dv-accent);border-radius:5px;padding:4px 9px;min-width:30px}
#bankTransferApp .bt-page-btn.active{background:var(--dv-accent);color:#fff}
#bankTransferApp .bt-page-btn:disabled{opacity:.45}
#bankTransferApp .bt-record-count{font-size:.79rem;color:#657286}
#bankTransferCreateModal .modal-body label{font-weight:600}
#bankTransferApp.bt-page .bt-tools select{font-size:12px}
#bankTransferApp .bt-filter-row .ts-wrapper{width:100%;min-width:0}
#bankTransferApp .bt-filter-row .ts-control{min-height:34px;border-radius:5px;font-size:12px}
.bt-modern-modal .modal-content{overflow:visible!important}
.bt-modern-modal .modal-body{max-height:calc(100dvh - 205px);overflow-y:auto}
#bankTransferViewModal .modal-dialog,#bankTransferReverseModal .modal-dialog{max-width:min(540px,calc(100vw - 24px))}
#bankTransferCreateModal .modal-dialog{max-width:min(820px,calc(100vw - 24px))}
#bankTransferCreateModal .modal-body .alert-info{font-size:12px;border-radius:6px}
#bankTransferCreateModal .modal-body textarea{min-height:68px!important}
#bankTransferCreateModal .modal-body .ts-wrapper{width:100%;min-width:0}
#bankTransferCreateModal .modal-body .ts-dropdown{z-index:11000!important}

@media(max-width:1150px){#bankTransferApp .bt-filter-row{grid-template-columns:repeat(2,minmax(120px,1fr))}#bankTransferApp .bt-filter-actions{grid-column:1/-1;justify-content:flex-end}}
@media(max-width:991.98px){body:has(#bankTransferApp) .main-content.page-content,body.sidebar-collapse:has(#bankTransferApp) .main-content.page-content{margin-left:0!important;width:100%!important;max-width:100%;padding:66px 6px 0!important}}
@media(max-width:520px){#bankTransferApp .bt-filter-row{grid-template-columns:1fr}#bankTransferApp .bt-footer{flex-wrap:wrap}}
#bankTransferCreateForm #btAmountWords:disabled {background:#f1f5f9;color:#20364d;font-size:15px;font-weight:600;opacity:1}
</style>
<div class="container-fluid bm-ui bm-voucher-directory bt-page {{ $kind === 'deposit' ? 'bt-credit' : 'bt-debit' }}" style="--voucher-accent:{{ $kind === 'deposit' ? '#128044' : '#bd3140' }};--voucher-tint:{{ $kind === 'deposit' ? '#e9f8ef' : '#fff0f1' }}" id="bankTransferApp" data-kind="{{ $kind }}"
    data-post-url="{{ route('finance.voucher.transfer.post',['kind'=>$kind]) }}"
    data-next-number-url="{{ route('finance.voucher.transfer.next-number',['kind'=>$kind]) }}"
    data-update-base="{{ url('admin/finance/voucher/bank-transfer/'.$kind) }}"
    data-history-url="{{ route('finance.voucher.transfer.history',['kind'=>$kind]) }}"
    data-source-url="{{ route('finance.voucher.transfer.sources',['kind'=>$kind]) }}"
    data-print-base="{{ url('admin/finance/voucher/bank-transfer/'.$kind) }}"
    data-reverse-base="{{ url('admin/finance/voucher/bank-transfer/'.$kind) }}">
    <div class="bt-heading d-flex justify-content-between align-items-center">
        <div><h2 class="mb-0">{{ $kind === 'deposit' ? 'BD Vouchers' : 'BW Vouchers' }}</h2>
            <div class="bt-subtitle">Selected project records · Correctable manual vouchers</div></div>
        <div class="d-flex align-items-center" style="gap:8px">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboard') }}">Dashboard</a>
            @can('finance bank-'.$kind.' create')
            <button type="button" class="btn btn-success btn-sm" id="openTransferCreate"
                data-toggle="modal" data-target="#bankTransferCreateModal">
                <i class="fas fa-plus mr-1"></i> New voucher
            </button>
            @endcan

        </div>
    </div>
    <form id="btFilterForm" class="d-none" aria-hidden="true"></form>
    <x-finance.voucher-table-card class="bt-table-card">
        <div class="bt-tools bm-records-toolbar">
            <label>Show <select class="form-control form-control-sm" id="btLength"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select> entries</label>
            <label>Search: <input class="form-control form-control-sm" id="btQuickSearch" placeholder="Search vouchers"></label>
        </div>
        <div id="transferListError" class="alert alert-danger d-none m-2" role="alert"></div>
        <div class="bt-table-wrap">
            <table class="table bm-table table-bordered table-hover table-sm w-100" id="transferTable">
                <colgroup><col style="width:48px"><col style="width:112px"><col style="width:120px"><col style="width:200px"><col style="width:195px"><col><col style="width:110px"><col style="width:92px"><col style="width:74px"></colgroup>
                <thead><tr><th>#</th>
                    @foreach(['date'=>'Date','number'=>'Voucher #','head'=>'Head','subhead'=>'Sub Head','detail'=>'Details','amount'=>'Amount','status'=>'Status'] as $field=>$label)
                    <th class="dv-filter-heading" data-field="{{ $field }}"><span>{{ $label }}</span>
                        <button type="button" class="dv-excel-trigger" data-column="{{ $field }}" aria-label="Sort or filter {{ $label }}" aria-expanded="false" aria-haspopup="dialog" title="Sort and filter {{ $label }}"><i class="fas fa-caret-down" aria-hidden="true"></i></button></th>
                    @endforeach
                    <th>Actions</th></tr></thead>
                <tbody id="transferRows" aria-busy="true">@include('components.finance.voucher-skeleton-rows', ['columns' => 9])</tbody>
            </table>
        </div>
        <div class="bt-footer">
            <span id="btRecordSummary" class="bt-record-count">Loading records…</span>
            <div id="btPagination" class="d-flex align-items-center flex-wrap" style="gap:4px"></div>
        </div>
    </x-finance.voucher-table-card>
</div>
<x-finance.voucher-column-filters />
@endsection

@section('modal')
<div class="modal fade fvc-modern-modal bt-modern-modal" style="--fvc-modal-accent:{{ $kind === 'deposit' ? '#128044' : '#bd3140' }};--fvc-modal-tint:{{ $kind === 'deposit' ? '#e9f8ef' : '#fff0f1' }}" id="bankTransferCreateModal" tabindex="-1" role="dialog" aria-labelledby="bankTransferCreateTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bankTransferCreateTitle">Add {{ $kind === 'deposit' ? 'Bank Deposit' : 'Bank Withdrawal' }} Voucher</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="bankTransferCreateForm" novalidate>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="btVoucherNumber">Voucher No.</label>
                        <input id="btVoucherNumber" class="form-control" value="{{ $kind === 'deposit' ? 'BD' : 'BW' }} — loading" readonly aria-readonly="true">
                        <small class="text-muted">Next project-specific number. Final serial is reserved during save.</small>
                    </div>
                    <div class="alert alert-info py-2">{{ $kind === 'deposit' ? 'Bank debit / counter-account credit' : 'Counter-account debit / bank credit' }}. Cash/Manual keeps the original verified BD/BW transfer behavior.</div>
                    <div class="form-group">
                        <label for="btPaymentType">Payment Type <span class="text-danger">*</span></label>
                        <select id="btPaymentType" class="form-control fvc-tom-select">
                            <option value="1">Cash / Manual Transfer</option>
                            <option value="2">Online — Confirmed Management Source</option>
                            <option value="3">Cheque — Cleared Management Source</option>
                        </select>
                    </div>
                    <div class="form-group bt-verified-source d-none">
                        <label>Verified Cheque / Online Transaction <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input id="btPassedLabel" class="form-control" readonly placeholder="Choose a verified instrument">
                            <div class="input-group-append"><button type="button" class="btn btn-outline-primary" id="btOpenPassedPicker">Choose Source</button></div>
                        </div>
                        <small class="text-muted">The verified account mapping sets the counter-account. Amount cannot exceed the unallocated balance.</small>
                    </div>
                    @if($kind === 'withdrawal')
                    <div class="form-group"><label for="chequeSource">Verified cleared cheque (optional)</label>
                        <select id="chequeSource" class="form-control fvc-tom-select"><option value="">Manual bank withdrawal</option></select>
                        <small class="form-text text-muted">The verified source amount is fixed. A source cannot be posted twice.</small>
                    </div>
                    @endif
                    <div class="row">
                        <div class="form-group col-md-6"><label for="voucherDate">Date <span class="text-danger">*</span></label>
                            <input id="voucherDate" type="date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                        <div class="form-group col-md-6"><label id="btCounterLabel" for="cashAccount">Counter Account <span class="text-muted small">(Cash / Manual)</span></label>
                            <select id="cashAccount" class="form-control fvc-tom-select" required><option value="">Choose counter-account</option>
                            @foreach($counterAccounts as $account)
                                <option value="{{ $account->id }}" data-manual-cash="{{ $cashAccounts->contains('id',$account->id) ? '1' : '0' }}">{{ $account->headAccounting?->name }} / {{ $account->subheadAccounting?->name }} (#{{ $account->id }})</option>
                            @endforeach
                            </select>
                            @if($cashAccounts->isEmpty())<small class="text-danger">Configure an active Cash in Hand project account mapping for manual transfers.</small>@endif
                        </div>
                        <div class="form-group col-md-6"><label for="bankAccount">Bank account <span class="text-muted small">({{ $bankAccounts->count() }} available)</span></label>
                            <select id="bankAccount" class="form-control fvc-tom-select" required><option value="">Choose bank account</option>
                            @foreach($bankAccounts as $bank)<option value="{{ $bank->id }}">{{ $bank->bank_name }} · {{ $bank->account_title }}{{ $bank->branch ? ' · '.$bank->branch : '' }}</option>@endforeach</select>
                            @if($bankAccounts->isEmpty())<small class="text-danger">Configure an active Project Bank Account mapping first.</small>@endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4"><label for="voucherAmount">Amount <span class="text-danger">*</span></label>
                            <input id="voucherAmount" type="number" class="form-control" min="0.01" step="0.01" placeholder="0.00" required></div>
                        <div class="form-group col-md-8"><label for="btAmountWords">Amount in Words</label>
                            <input id="btAmountWords" type="text" class="form-control voucher-words-output"
                                placeholder="Amount in words will appear automatically" disabled readonly></div>
                    </div>
                    <div class="form-group"><label for="voucherReference">Reference number</label><input id="voucherReference" class="form-control" maxlength="120" placeholder="Optional reference"></div>
                    <div class="form-group"><label for="voucherDescription">Description / Narration <span class="text-danger">*</span></label>
                        <textarea id="voucherDescription" class="form-control" rows="3" maxlength="2000" required></textarea></div>
                    <div id="transferFormError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button id="saveTransfer" type="submit" class="btn btn-primary" disabled>Post Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="btPassedPickerModal" tabindex="-1" role="dialog" aria-label="Verified bank source">
 <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Select verified Cheque / Online transaction</h5>
   <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
  <div class="modal-body">
   <input id="btPassedSearch" class="form-control mb-2" placeholder="Search cheque number or online reference" maxlength="120">
   <div id="btPassedError" class="alert alert-danger d-none" role="alert"></div>
   <div class="table-responsive"><table class="table table-hover table-sm">
    <thead><tr><th>Select</th><th>Reference</th><th>Passing date</th><th>Remaining</th><th>Bank</th></tr></thead>
    <tbody id="btPassedRows"><tr><td colspan="5" class="text-muted text-center">Choose Payment Type to load verified sources.</td></tr></tbody>
   </table></div>
  </div>
  <div class="modal-footer">
   <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
   <button type="button" class="btn btn-primary" id="btApplyPassed" disabled>Apply Source</button>
  </div>
 </div></div>
</div>

<div class="modal fade fvc-modern-modal bt-modern-modal" style="--fvc-modal-accent:{{ $kind === 'deposit' ? '#128044' : '#bd3140' }};--fvc-modal-tint:{{ $kind === 'deposit' ? '#e9f8ef' : '#fff0f1' }}" id="bankTransferViewModal" tabindex="-1" role="dialog" aria-labelledby="bankTransferViewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="bankTransferViewTitle">Voucher details</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <div class="modal-body" id="transferViewBody"></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <a id="transferViewPrint" target="_blank" rel="noopener" class="btn btn-primary">Print voucher</a></div>
    </div></div>
</div>
<div class="modal fade fvc-modern-modal bt-modern-modal" id="bankTransferDeleteModal"
    tabindex="-1" role="dialog" aria-labelledby="bankTransferDeleteTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="bankTransferDeleteTitle">Delete bank voucher</h5>
   <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <div class="modal-body">
   <p>This manual bank voucher will be removed from active registers and balances. The original source and posting evidence remain available internally.</p>
   <div id="btDeleteError" class="alert alert-danger d-none" role="alert"></div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button>
    <button type="button" class="btn btn-danger" id="btDeleteSubmit">Delete voucher</button>
  </div>
 </div></div>
</div>
@can('finance bank-'.$kind.' reverse')
<div class="modal fade fvc-modern-modal bt-modern-modal" style="--fvc-modal-accent:{{ $kind === 'deposit' ? '#128044' : '#bd3140' }};--fvc-modal-tint:{{ $kind === 'deposit' ? '#e9f8ef' : '#fff0f1' }}" id="bankTransferReverseModal" tabindex="-1" aria-labelledby="bankTransferReverseTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 id="bankTransferReverseTitle" class="modal-title">Reverse Bank Voucher</h5>
   <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
  <form id="bankTransferReverseForm"><div class="modal-body">
   <p class="small text-muted">The original transaction remains in history. A reversing posting is added.</p>
   <label for="btReverseReason">Reason</label><textarea id="btReverseReason" class="form-control" required minlength="3" maxlength="200" rows="3"></textarea>
   <label for="btReverseDate" class="mt-2">Effective date</label><input type="date" id="btReverseDate" class="form-control" required value="{{ now()->toDateString() }}">
   <div id="btReverseError" class="alert alert-danger d-none mt-2" role="alert"></div>
  </div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button>
   <button class="btn btn-danger" id="btReverseSubmit" type="submit">Reverse voucher</button></div></form>
 </div></div>
</div>
@endcan
@endsection

@section('js')
<script src="{{ asset('js/finance/verified-source-narration.js') }}"></script>
<script>
$(function () {
    'use strict';
    var $root = $('#bankTransferApp');
    if (!$root.length) return;
    var $form = $('#bankTransferCreateForm');
    var $save = $('#saveTransfer');
    var allRows = [], page = 1, busy = false, chequeLoading = false;
    var pendingRequestKey = null, editingBatch=null;
    var btPassedRows=[],btPassedTimer=null,btPassedRequest=null,btPassedAmountMax=null;
    var clearBtPassed=function(){
        btPassedRows=[];btPassedAmountMax=null;
        $('#btPassedLabel').val('');$('#btPassedPickerModal input[name="btPassedChoice"]').prop('checked',false);
        $root.removeData('passed-source-id').removeData('passed-source-method');
    };
    var btBankMethod=function(){return $('#btPaymentType').val()==='2'?'online':
        ($('#btPaymentType').val()==='3'?'cheque':null);};
    var syncBtPayment=function(){
        var verified=!!btBankMethod()&&!editingBatch;
        $('.bt-verified-source').toggleClass('d-none',!verified);
        $('#btCounterLabel').text(verified?'Counter Account (Verified source)':'Counter Account (Cash / Manual)');
        $('#chequeSource').closest('.form-group').toggle(!verified);
        $('#chequeSource').prop('disabled',verified || !!editingBatch);
        $('#cashAccount option[data-manual-cash]').each(function(){
            $(this).prop('disabled',!verified && $(this).attr('data-manual-cash')!=='1');
        });
        if(verified){
            $('#cashAccount').prop('disabled',true);
        }else{
            $('#cashAccount').prop('disabled',false);
            if($('#cashAccount option:selected').prop('disabled'))$('#cashAccount').val('');
        }
        syncSelect('#cashAccount,#btPaymentType,#chequeSource');
    };
    var loadBtPassed=function(){
        var method=btBankMethod();if(!method || editingBatch)return;
        if(btPassedRequest&&btPassedRequest.readyState!==4)btPassedRequest.abort();
        $('#btPassedError').addClass('d-none').text('');
        var $tbody=$('#btPassedRows').empty().append($('<tr>').append($('<td>',{colspan:5,'class':'text-muted text-center',text:'Loading verified sources…'})));
        $('#btApplyPassed').prop('disabled',true);
        btPassedRequest=$.ajax({
            url:@json(url('admin/finance/voucher/passed-sources')),type:'GET',dataType:'json',
            headers:{Accept:'application/json'},
            data:{method:method,direction:@json($kind === 'deposit' ? 'receipt':'payment'),
                voucher_series:@json($kind === 'deposit' ? 'BD':'BW'),search:$('#btPassedSearch').val()}
        }).done(function(res){
            btPassedRows=res.data||[];$tbody.empty();
            if(!btPassedRows.length)$tbody.append($('<tr>').append($('<td>',{colspan:5,'class':'text-center text-muted',text:'No available verified Cheque/Online sources.'})));
            $.each(btPassedRows,function(_,row){
                var $tr=$('<tr>');
                $tr.append($('<td>').append($('<input>',{type:'radio',name:'btPassedChoice',value:row.id})));
                $tr.append($('<td>').text(row.reference||'—'));
                $tr.append($('<td>').text(row.passing_date||'—'));
                $tr.append($('<td>').text(row.remaining_amount||'0.00'));
                $tr.append($('<td>').text(row.bank||'—'));
                $tbody.append($tr);
            });
        }).fail(function(xhr,status){
            if(status==='abort')return;
            $tbody.empty();
            $('#btPassedError').removeClass('d-none').text((xhr.responseJSON||{}).message||'Unable to load verified bank sources.');
        });
    };
    function updateBtAmountWords(){
      var value=Number($('#voucherAmount').val()), words='';
      if(Number.isFinite(value)&&value>0&&window.numberToWords&&typeof numberToWords.toWords==='function') words=numberToWords.toWords(value);
      $('#btAmountWords').val(words);
    }
    $(document).on('input change','#bankTransferCreateForm #voucherAmount',updateBtAmountWords);
    $('#btPaymentType').on('change',function(){
        $.financeVerifiedSourceNarration.set($('#voucherDescription'),null);
        clearBtPassed();syncBtPayment();
        $('#voucherAmount').val('').prop('readonly',false).removeAttr('max');
        if(btBankMethod()&&!editingBatch){
            $('#btPassedSearch').val('');$('#btPassedPickerModal').modal('show');
        }
        pendingRequestKey=null;valid();
    });
    $('#btOpenPassedPicker').on('click',function(){if(btBankMethod())$('#btPassedPickerModal').modal('show');});
    $('#btPassedPickerModal').on('show.bs.modal',function(){
        $(this).css('z-index',1065);
        loadBtPassed();
    }).on('shown.bs.modal',function(){
        $('.modal-backdrop').last().css('z-index',1060);$('body').addClass('modal-open');
    }).on('hidden.bs.modal',function(){
        if($('#bankTransferCreateModal').hasClass('show'))$('body').addClass('modal-open');
    });
    $('#btPassedSearch').on('input',function(){
        clearTimeout(btPassedTimer);btPassedTimer=setTimeout(loadBtPassed,250);
    });
    $('#btPassedRows').on('change','input[name="btPassedChoice"]',function(){
        $('#btApplyPassed').prop('disabled',!$(this).val());
    });
    $('#btApplyPassed').on('click',function(){
        var id=$('#btPassedRows input[name="btPassedChoice"]:checked').val();
        var item=btPassedRows.find(function(row){return String(row.id)===String(id);});
        if(!item)return;
        var $mapped=$('#cashAccount option').filter(function(){
            return String($(this).val())===String(item.account_mapping_id) && !$(this).prop('disabled');
        });
        if(!$mapped.length){
            $('#btPassedError').removeClass('d-none').text('The verified source counter-account is unavailable in this project.');
            return;
        }
        var narration=$.financeVerifiedSourceNarration.set($('#voucherDescription'),item);
        if(!narration.ok){
            $('#btPassedError').removeClass('d-none').text(narration.message);
            return;
        }
        $root.data('passed-source-id',Number(item.id)).data('passed-source-method',item.method);
        btPassedAmountMax=Number(item.remaining_amount);
        $('#btPassedLabel').val((item.method==='online'?'Online: ':'Cheque: ')+item.reference+
            ' · Available '+item.remaining_amount);
        $('#cashAccount').val(String(item.account_mapping_id));syncSelect('#cashAccount');
        $('#voucherAmount').val(item.remaining_amount).attr('max',item.remaining_amount);
        $('#voucherReference').val(item.reference);
        // Existing manually typed narration is preserved; evidence is appended
        // once by the shared formatter rather than replacing it with purpose.
        var bankName=String(item.bank||'').trim().toLowerCase(),$bank=$('#bankAccount');
        if(bankName){
            var $choice=$bank.find('option').filter(function(){
                return $(this).text().trim().toLowerCase().startsWith(bankName+' ·');
            }).first();
            if($choice.length){$bank.val($choice.val());syncSelect('#bankAccount');}
        }
        $('#btPassedPickerModal').modal('hide');
        pendingRequestKey=null;valid();
    });

    var columnSort='date',columnDirection='desc',columnFilters=null;
    var columnValue=function(r,field){
        var cash=String(r.cash_account||'').split(' / ');
        var fields={date:r.date,number:r.voucher,head:cash[0]||'',subhead:cash.slice(1).join(' / '),
            detail:r.narration,amount:r.amount,status:r.approval_draft_id
                ?(r.approval_status==='rejected'?'Rejected':'Pending')
                :(r.reversed?'Reversed':(r.approval_status==='approved'?'Approved':'Posted'))};
        return String(fields[field] == null ? '' : fields[field]);
    };
    var printUrl = function (id) { return $root.attr('data-print-base') + '/' + encodeURIComponent(id) + '/print'; };
    var formatMoney = function (value) { return Number(value || 0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); };
    // Reuse the CR/DR finance toast/error UX. Never open browser alert dialogs.
    var feedback = $.financeVoucherFeedback || {};
    var notify = function (type, title, message) {
        var text=$.trim([title,message].filter(Boolean).join(' · '));
        if($.isFunction(feedback.toast))feedback.toast(type,text);
        else if(window.toastr&&$.isFunction(window.toastr[type]))window.toastr[type](text);
        else $('<div>',{'class':'alert alert-'+(type==='success'?'success':'danger')+' fvc-feedback-fallback',role:'alert',text:text})
            .appendTo('body').delay(5000).fadeOut(250,function(){$(this).remove();});
    };
    var showError = function ($box, messageOrXhr, defaultMessage) {
        var message=typeof messageOrXhr==='string'?messageOrXhr:
            ($.isFunction(feedback.error)?feedback.error(messageOrXhr,defaultMessage):
              (messageOrXhr.responseJSON||{}).message||defaultMessage||'Request failed');
        // For server responses pass the sanitized object to the common error handler.
        if(/sqlstate|stack trace|\binsert into\b|\bexception\b/i.test(message))
            message=defaultMessage||'The operation failed. Please retry.';
        $box.text(message).removeClass('d-none');
        notify('error','Request failed',message);
    };
    var syncSelect = function(selector) {
        $(selector).each(function(){if(this.tomselect)this.tomselect.sync();});
    };
    var initSelects = function() {
        if($.isFunction(feedback.enhanceSelects))
            feedback.enhanceSelects($('#bankTransferApp select.fvc-tom-select, #bankTransferCreateModal select.fvc-tom-select'));
    };
    var valid = function () {
        updateBtAmountWords();
        var amount = Number($('#voucherAmount').val());
        var verified=!!btBankMethod()&&!editingBatch;
        var ok = !busy && !chequeLoading && !!$('#voucherDate').val()
            && Number.isFinite(amount) && amount > 0
            && (!verified || ($root.data('passed-source-id') && Number.isFinite(btPassedAmountMax)
                && amount<=btPassedAmountMax))
            && !!$('#cashAccount').val()
            && !!$('#bankAccount').val() && !!$.trim($('#voucherDescription').val());
        $save.prop('disabled', !ok);
        return ok;
    };
    // Apply the toolbar only when requested, as in CR/DR. Quick search filters
    // the already loaded project-scoped entries without server mutations.
    var activeFilters = {from:'',to:'',bank:'',search:''};
    var selectRows = function () {
        var quick = $.trim($('#btQuickSearch').val()).toLowerCase();
        var filtered=$.grep(allRows, function (r) {
            var date = String(r.date || '').slice(0,10);
            var bank = String(r.bank || '').toLowerCase();
            var haystack = [r.voucher,r.date,r.cash_account,r.bank,r.cheque,r.narration,r.amount].join(' ').toLowerCase();
            return (!activeFilters.from || date >= activeFilters.from)
                && (!activeFilters.to || date <= activeFilters.to)
                && (!activeFilters.bank || bank === activeFilters.bank)
                && (!activeFilters.search || haystack.indexOf(activeFilters.search) >= 0)
                && (!quick || haystack.indexOf(quick) >= 0);
        });
        return columnFilters ? columnFilters.filterRows(filtered, columnValue) : filtered;
    };
    var drawRows = function () {
        var rows = selectRows();
        var length = Math.max(1, Number($('#btLength').val()) || 25);
        var pages = Math.max(1, Math.ceil(rows.length / length));
        page = Math.min(page,pages);
        var start = (page - 1) * length;
        if ($.blueActionMenu) $.blueActionMenu.close();
        var $tbody = $('#transferRows').empty();
        if (!rows.length) {
            $('<tr>').append($('<td>').attr('colspan',9).addClass('text-center text-muted py-4').text('No matching vouchers')).appendTo($tbody);
        }
        $.each(rows.slice(start,start+length),function(index,r){
            var isDraft = Number(r.approval_draft_id) > 0;
            var batch = Number(r.posting_batch_id);
            if (!isDraft && (!Number.isSafeInteger(batch) || batch < 1)) return;
            var cashParts = String(r.cash_account || '').split(' / ');
            var head = cashParts.shift() || '';
            var subhead = cashParts.join(' / ');
            var $tr = $('<tr>');
            $('<td>').text(start + index + 1).appendTo($tr);
            $('<td>').text(r.date || '').appendTo($tr);
            $('<td>').addClass('bt-voucher-number').text(r.voucher || '').appendTo($tr);
            $('<td>').text(head).appendTo($tr);
            $('<td>').text(subhead).appendTo($tr);
            $('<td>').text(r.narration || '—').appendTo($tr);
            $('<td>').addClass('text-right').text(formatMoney(r.amount)).appendTo($tr);
            var status = isDraft
                ? (r.approval_status === 'rejected' ? 'Rejected' : 'Pending')
                : (r.reversed?'Reversed':(r.approval_status==='approved'?'Approved':'Posted'));
            $('<td>').append($('<span>').addClass('badge '+(isDraft
                ? (status==='Pending'?'badge-warning':'badge-danger')
                : 'badge-secondary')).text(status)).appendTo($tr);
            if(isDraft){
                $('<td class="blue-actions-cell">').append(
                    $('<span class="text-muted small">').text(status==='Pending'
                        ? 'Awaiting approval' : 'Not posted')).appendTo($tr);
                $tbody.append($tr);
                return;
            }
            var $actions=$('<td class="blue-actions-cell">');
            $('<button type="button" class="btn btn-sm btn-outline-secondary bt-view" data-blue-action-type="view">View</button>').attr('data-id',batch).appendTo($actions);
            $('<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" data-blue-action-type="print">Print</a>').attr('href',printUrl(batch)).appendTo($actions);
            if(r.can_edit){
                @can('finance bank-'.$kind.' edit')
                $('<button type="button" class="btn btn-sm btn-outline-primary bt-edit" data-blue-action-type="edit">Edit</button>')
                    .attr('data-id',batch).appendTo($actions);
                @endcan
                @can('finance bank-'.$kind.' delete')
                $('<button type="button" class="btn btn-sm btn-outline-danger bt-delete" data-blue-action-type="delete">Delete</button>')
                    .attr('data-id',batch).appendTo($actions);
                @endcan
            }
            @can('finance bank-'.$kind.' reverse')
            if(r.can_reverse)$('<button type="button" class="btn btn-sm btn-outline-danger bt-reverse" data-blue-action-type="delete">Reverse</button>').attr('data-id',batch).appendTo($actions);
            @endcan
            $actions.appendTo($tr);
            $tbody.append($tr);
        });
        if ($.blueActionMenu) $.blueActionMenu.enhance($('#transferRows > tr > td.blue-actions-cell'));
        $.blueVoucherPagination.render({
            container:'#btPagination', summary:'#btRecordSummary',
            total:rows.length,start:start,length:length,
            buttonClass:'bt-page-btn',
            onPage:function(nextStart){page=Math.floor(nextStart/length)+1;drawRows();}
        });
    };
    var loadHistory = function () {
        $('#transferListError').addClass('d-none');
        if ($.blueTableSkeleton) $.blueTableSkeleton.show('#transferRows',{rows:10,columns:9});
        return $.ajax({
            url:$root.attr('data-history-url'),dataType:'json',headers:{Accept:'application/json'}
        }).done(function (json) {
            allRows = $.isArray(json.data) ? json.data : [];
            page = 1;drawRows();
            if ($.blueTableSkeleton) $.blueTableSkeleton.clear('#transferRows');
        }).fail(function(xhr) {
            if ($.blueTableSkeleton) $.blueTableSkeleton.clear('#transferRows');
            $('#transferRows').empty().append($('<tr>').append($('<td>',{colspan:9,'class':'text-center text-muted p-4',text:'Unable to load voucher history'})));
            showError($('#transferListError'),xhr,'Could not load voucher history');
        });
    };
    columnFilters=$.blueVoucherColumnFilters.init({
        initialOptions:{head:[],subhead:[],status:['Posted','Pending','Rejected'],account_relations:[]},
        formSelector:'#btFilterForm',quickSearchSelector:'#btQuickSearch',
        triggerSelector:'#bankTransferApp .dv-excel-trigger',
        tableWrapSelector:'.bt-table-wrap',
        getStart:()=>Math.max(0,(page-1)*(Number($('#btLength').val())||25)),
        getLength:()=>Number($('#btLength').val())||25,
        getSortColumn:()=>columnSort,getSortDirection:()=>columnDirection,
        onSort:(column,direction)=>{columnSort=column;columnDirection=direction;},
        onChange:()=>{page=1;drawRows();},
        choicesFor:function(field){
            return [...new Set(allRows.map(row=>columnValue(row,field)))].sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));
        }
    });
    $('#btQuickSearch').on('input',function(){page=1;drawRows();});
    $('#btLength').on('change',function(){page=1;drawRows();});
    $('#transferRows').on('click','.bt-view',function() {
        var id=Number($(this).attr('data-id'));
        var row=$.grep(allRows,function(r){return Number(r.posting_batch_id)===id;})[0];
        if (!row) return;
        $('#bankTransferViewTitle').text(row.voucher || 'Voucher details');
        var $body=$('#transferViewBody').empty();
        $.each([
            ['Voucher',row.voucher],['Date',row.date],
            ['Cash account',row.cash_account],['Bank account',row.bank],
            ['Cheque reference',row.cheque || '—'],['Amount',formatMoney(row.amount)],
            ['Status',row.reversed?'Reversed':'Posted'],['Details',row.narration]
        ],function(_,field){
            var $line=$('<div class="d-flex justify-content-between py-2 border-bottom">');
            $('<strong class="mr-3">').text(field[0]).appendTo($line);
            $('<span class="text-right" style="overflow-wrap:anywhere">').text(field[1] || '—').appendTo($line);
            $body.append($line);
        });
        $('#transferViewPrint').attr('href',printUrl(id));
        $('#bankTransferViewModal').modal('show');
    });
    @can('finance bank-'.$kind.' reverse')
    $('#transferRows').on('click','.bt-reverse',function(){
        var batch=Number($(this).attr('data-id'));
        var row=$.grep(allRows,function(r){return Number(r.posting_batch_id)===batch;})[0];
        if(!row||!row.can_reverse)return;
        $('#bankTransferReverseForm').data('batch',batch);
        $('#btReverseReason').val('');
        $('#btReverseError').addClass('d-none').text('');
        $('#bankTransferReverseModal').modal('show');
    });
    $('#bankTransferReverseForm').on('submit',function(e){
        e.preventDefault();
        var batch=Number($(this).data('batch')),$button=$('#btReverseSubmit');
        if(!batch||$button.prop('disabled'))return;
        $button.prop('disabled',true);
        $.ajax({
            url:$root.attr('data-reverse-base')+'/'+encodeURIComponent(batch)+'/reverse',
            type:'POST',dataType:'json',contentType:'application/json',
            headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest',
                'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
            data:JSON.stringify({effective_date:$('#btReverseDate').val(),
                reason:$.trim($('#btReverseReason').val()),
                request_key:'banktransferreverse'+Date.now().toString(36)+Math.random().toString(36).slice(2,14)})
        }).done(function(response){
            $('#bankTransferReverseModal').modal('hide');
            notify('success','Voucher reversed',response.message||'Reversal posted');
            loadHistory();
        }).fail(function(xhr){
            showError($('#btReverseError'),xhr,'Unable to reverse voucher');
        }).always(function(){$button.prop('disabled',false);});
    });
    @endcan
    var loadCheques = function () {
        var $cheque=$('#chequeSource');
        if (!$cheque.length) return;
        chequeLoading=true;valid();
        $.ajax({url:$root.attr('data-source-url'),dataType:'json',headers:{Accept:'application/json'}})
        .done(function(json) {
            $cheque.empty().append($('<option>').val('').text('Manual bank withdrawal'));
            $.each(json.data || [],function(_,source){
                $('<option>').val(source.id).text([source.voucher,source.party,source.amount,source.reference].join(' · '))
                    .attr({'data-amount':source.amount,'data-date':source.date,'data-ref':source.reference,'data-voucher':source.voucher})
                    .appendTo($cheque);
            });
        }).fail(function(xhr){
            showError($('#transferFormError'),xhr,'Could not load cleared cheques');
        }).always(function(){syncSelect('#chequeSource');chequeLoading=false;valid();});
    };
    $('#chequeSource').on('change',function(){
        var $selected=$(this).find('option:selected'),linked=!!$selected.val();
        $('#voucherAmount').val(linked?$selected.attr('data-amount'):'').prop('readonly',linked);
        if(linked) {
            $('#voucherDate').val($selected.attr('data-date'));
            $('#voucherReference').val($selected.attr('data-ref'));
            $('#voucherDescription').val('Withdrawal against ' + $selected.attr('data-voucher') + ' cheque ' + $selected.attr('data-ref'));
        }
        if(!busy) pendingRequestKey=null;
        valid();
    });
    $('#bankTransferCreateModal').on('show.bs.modal',function(){
        $form[0].reset();pendingRequestKey=null;clearBtPassed();
        $('#btPaymentType').val('1').prop('disabled',!!editingBatch);
        $('#voucherAmount').removeAttr('max');syncBtPayment();
        $('#bankTransferCreateTitle').text((editingBatch?'Edit ':'Add ')+
            @json($kind === 'deposit' ? 'Bank Deposit Voucher' : 'Bank Withdrawal Voucher'));
        $('#saveTransfer').text(editingBatch?'Update Voucher':'Post Voucher');
        syncSelect('#cashAccount,#bankAccount,#chequeSource');
        $('#voucherAmount').prop('readonly',false);
        $('#transferFormError').addClass('d-none');
        if(!editingBatch){
            $('#btVoucherNumber').val('Loading…');
            $.ajax({url:$root.attr('data-next-number-url'),type:'GET',dataType:'json'})
            .done(function(data){$('#btVoucherNumber').val(data.voucher_number);})
            .fail(function(xhr){
                $('#btVoucherNumber').val('Unavailable');
                showError($('#transferFormError'),xhr,'Cannot load the next voucher number');
            });
        }else{
            $('#btVoucherNumber').val('Existing voucher');
        }
        if(editingBatch){
            $('#chequeSource').prop('disabled',true);
            $.ajax({url:$root.attr('data-update-base')+'/'+encodeURIComponent(editingBatch)+'/details',
                dataType:'json',headers:{Accept:'application/json'}})
            .done(function(json){
                if(!json.data||!json.data.editable||!json.data.cash_account_id){
                    showError($('#transferFormError'),'This voucher cannot be edited.','Bank voucher Edit is unavailable.');
                    $('#saveTransfer').prop('disabled',true);return;
                }
                var d=json.data;
                $('#btVoucherNumber').val(d.voucher || 'Existing voucher');
                $('#voucherDate').val(d.date);$('#voucherAmount').val(d.amount);
                $('#cashAccount').val(String(d.cash_account_id));
                $('#bankAccount').val(String(d.bank_account_id));
                $('#voucherReference').val('');
                $('#voucherDescription').val(d.narration);
                syncSelect('#cashAccount,#bankAccount');valid();
            }).fail(function(xhr){showError($('#transferFormError'),xhr,'Unable to open bank voucher');});
        }else{
            $('#chequeSource').prop('disabled',false);
            loadCheques();
        }
        valid();
    });
    $('#voucherDate,#voucherAmount,#cashAccount,#bankAccount,#voucherReference,#voucherDescription').on('input change',function(){
        if(!busy) pendingRequestKey=null;
        valid();
    });
    $('#transferRows').on('click','.bt-delete',function(){
        var batch=Number($(this).attr('data-id'));
        var row=$.grep(allRows,function(r){return Number(r.posting_batch_id)===batch;})[0];
        if(!row||!row.can_edit)return;
        $('#btDeleteSubmit').data('batch',batch);
        $('#btDeleteError').addClass('d-none').text('');
        $('#bankTransferDeleteModal').modal('show');
    });
    $('#btDeleteSubmit').on('click',function(){
        var $button=$(this),batch=Number($button.data('batch'));
        if(!batch||$button.prop('disabled'))return;
        $button.prop('disabled',true);
        $.ajax({
            url:$root.attr('data-update-base')+'/'+encodeURIComponent(batch),
            type:'DELETE',dataType:'json',
            headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest',
                'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}
        }).done(function(response){
            $('#bankTransferDeleteModal').modal('hide');
            notify('success','Voucher deleted',response.message||'Bank voucher removed.');
            loadHistory();
        }).fail(function(xhr){
            showError($('#btDeleteError'),xhr,'Unable to delete bank voucher');
        }).always(function(){$button.prop('disabled',false);});
    });
    $('#transferRows').on('click','.bt-edit',function(){
        var batch=Number($(this).attr('data-id'));
        if(!batch)return;
        editingBatch=batch;
        $('#bankTransferCreateModal').modal('show');
    });
    $('#openTransferCreate').on('click',function(){editingBatch=null;});
    $('#bankTransferCreateModal').on('hidden.bs.modal',function(){editingBatch=null;});
    $form.on('submit',function(event){
        event.preventDefault();
        if(!valid()||busy)return;
        busy=true;valid();$('#transferFormError').addClass('d-none');
        if(!pendingRequestKey) pendingRequestKey='transfer'+Date.now().toString(36)+Math.random().toString(36).slice(2,14);
        var payload={
            date:$('#voucherDate').val(),amount:Number($('#voucherAmount').val()).toFixed(2),
            cash_account_id:Number($('#cashAccount').val()),bank_account_id:Number($('#bankAccount').val()),
            reference:$('#voucherReference').val(),description:$('#voucherDescription').val(),
            request_key:pendingRequestKey,
            cheque_source_id:btBankMethod()?null:($('#chequeSource').val()?Number($('#chequeSource').val()):null),
            payment_type:Number($('#btPaymentType').val())||1,
            passed_source_method:btBankMethod(),
            passed_source_id:btBankMethod()?Number($root.data('passed-source-id'))||null:null
        };
        $.ajax({
            url:editingBatch?$root.attr('data-update-base')+'/'+encodeURIComponent(editingBatch):$root.attr('data-post-url'),
            method:editingBatch?'PUT':'POST',data:JSON.stringify(editingBatch?{
                date:payload.date,amount:payload.amount,
                cash_account_id:payload.cash_account_id,bank_account_id:payload.bank_account_id,
                narration:$.trim([payload.reference?('Ref: '+payload.reference+' | '):'',payload.description].join(''))
            }:payload),
            contentType:'application/json',dataType:'json',
            headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content'),Accept:'application/json'}
        }).done(function(json){
            pendingRequestKey=null;
            $('#bankTransferCreateModal').modal('hide');
            notify('success',editingBatch?'Voucher updated':(json.data&&json.data.pending?'Pending approval':'Voucher posted'),
                json.message||(editingBatch?'Voucher updated successfully.':
                    (json.data&&json.data.pending?'Awaiting assigned approver.':
                        'Posting batch #'+json.data.posting_batch_id)));
            loadHistory();
        }).fail(function(xhr){
            showError($('#transferFormError'),xhr,'Voucher could not be posted');
        }).always(function(){busy=false;valid();});
    });
    initSelects();
    $('#bankTransferCreateModal').on('shown.bs.modal',initSelects);
    loadHistory();
});
</script>
@endsection