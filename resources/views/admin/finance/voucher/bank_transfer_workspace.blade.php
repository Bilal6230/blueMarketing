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
#bankTransferCreateModal .form-control{min-height:39px}
@media(max-width:1150px){#bankTransferApp .bt-filter-row{grid-template-columns:repeat(2,minmax(120px,1fr))}#bankTransferApp .bt-filter-actions{grid-column:1/-1;justify-content:flex-end}}
@media(max-width:991.98px){body:has(#bankTransferApp) .main-content.page-content,body.sidebar-collapse:has(#bankTransferApp) .main-content.page-content{margin-left:0!important;width:100%!important;max-width:100%;padding:66px 6px 0!important}}
@media(max-width:520px){#bankTransferApp .bt-filter-row{grid-template-columns:1fr}#bankTransferApp .bt-footer{flex-wrap:wrap}}
</style>
<div class="container-fluid bm-ui bm-voucher-directory bt-page {{ $kind === 'deposit' ? 'bt-credit' : 'bt-debit' }}" id="bankTransferApp" data-kind="{{ $kind }}"
    data-post-url="{{ route('finance.voucher.transfer.post',['kind'=>$kind]) }}"
    data-history-url="{{ route('finance.voucher.transfer.history',['kind'=>$kind]) }}"
    data-source-url="{{ route('finance.voucher.transfer.sources',['kind'=>$kind]) }}"
    data-print-base="{{ url('admin/finance/voucher/bank-transfer/'.$kind) }}">
    <div class="bt-heading d-flex justify-content-between align-items-center">
        <div><h2 class="mb-0">{{ $kind === 'deposit' ? 'BD Vouchers' : 'BW Vouchers' }}</h2>
            <div class="bt-subtitle">Selected project records · Read-only history</div></div>
        <div class="d-flex align-items-center" style="gap:8px">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div>
    <x-finance.voucher-filter-card class="bt-filter-card">
            <form id="btFilterForm" class="bt-filter-row">
                <div><label for="transferFrom">From</label><input type="date" class="form-control" id="transferFrom"></div>
                <div><label for="transferTo">To</label><input type="date" class="form-control" id="transferTo"></div>
                <div><label for="transferBankFilter">Account</label><select id="transferBankFilter" class="form-control">
                    <option value="">All accounts</option>
                    @foreach($bankAccounts as $bank)<option value="{{ $bank->bank_name }} / {{ $bank->account_title }}">{{ $bank->bank_name }} / {{ $bank->account_title }}</option>@endforeach
                </select></div>
                <div><label for="transferSearch">Search</label><input class="form-control" id="transferSearch" maxlength="120" placeholder="Number, reference, account or detail"></div>
                <div class="bt-filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit" id="applyTransferFilters"><i class="fas fa-filter mr-1"></i> Apply</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearTransferFilters">Reset</button>
                    <button type="button" class="btn btn-success btn-sm" id="openTransferCreate" data-toggle="modal" data-target="#bankTransferCreateModal"><i class="fas fa-plus mr-1"></i> New voucher</button>
                </div>
            </form>
    </x-finance.voucher-filter-card>
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
                <tbody id="transferRows"><tr><td colspan="9" class="text-center text-muted py-4">Loading vouchers…</td></tr></tbody>
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
<div class="modal fade" id="bankTransferCreateModal" tabindex="-1" role="dialog" aria-labelledby="bankTransferCreateTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bankTransferCreateTitle">Add {{ $kind === 'deposit' ? 'Bank Deposit' : 'Bank Withdrawal' }} Voucher</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="bankTransferCreateForm" novalidate>
                <div class="modal-body">
                    <div class="alert alert-info py-2">Voucher number is assigned by the posting workflow. {{ $kind === 'deposit' ? 'Bank debit / Cash credit' : 'Cash debit / Bank credit' }}.</div>
                    @if($kind === 'withdrawal')
                    <div class="form-group"><label for="chequeSource">Verified cleared cheque (optional)</label>
                        <select id="chequeSource" class="form-control"><option value="">Manual bank withdrawal</option></select>
                        <small class="form-text text-muted">The verified source amount is fixed. A source cannot be posted twice.</small>
                    </div>
                    @endif
                    <div class="row">
                        <div class="form-group col-md-6"><label for="voucherDate">Date <span class="text-danger">*</span></label>
                            <input id="voucherDate" type="date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                        <div class="form-group col-md-6"><label for="voucherAmount">Amount <span class="text-danger">*</span></label>
                            <input id="voucherAmount" type="number" class="form-control" min="0.01" step="0.01" placeholder="0.00" required></div>
                        <div class="form-group col-md-6"><label for="cashAccount">Cash account <span class="text-muted small">({{ $cashAccounts->count() }} available)</span></label>
                            <select id="cashAccount" class="form-control" required><option value="">Choose cash account</option>
                            @foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }} / {{ $account->subheadAccounting?->name }}</option>@endforeach</select>
                            @if($cashAccounts->isEmpty())<small class="text-danger">Configure an active Cash in Hand project account mapping first.</small>@endif
                        </div>
                        <div class="form-group col-md-6"><label for="bankAccount">Bank account <span class="text-muted small">({{ $bankAccounts->count() }} available)</span></label>
                            <select id="bankAccount" class="form-control" required><option value="">Choose bank account</option>
                            @foreach($bankAccounts as $bank)<option value="{{ $bank->id }}">{{ $bank->bank_name }} · {{ $bank->account_title }}{{ $bank->branch ? ' · '.$bank->branch : '' }}</option>@endforeach</select>
                            @if($bankAccounts->isEmpty())<small class="text-danger">Configure an active Project Bank Account mapping first.</small>@endif
                        </div>
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

<div class="modal fade" id="bankTransferViewModal" tabindex="-1" role="dialog" aria-labelledby="bankTransferViewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="bankTransferViewTitle">Voucher details</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <div class="modal-body" id="transferViewBody"></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <a id="transferViewPrint" target="_blank" rel="noopener" class="btn btn-primary">Print voucher</a></div>
    </div></div>
</div>
@endsection

@section('js')
<script>
$(function () {
    'use strict';
    var $root = $('#bankTransferApp');
    if (!$root.length) return;
    var $form = $('#bankTransferCreateForm');
    var $save = $('#saveTransfer');
    var allRows = [], page = 1, busy = false, chequeLoading = false;
    var pendingRequestKey = null;
    var columnSort='date',columnDirection='desc',columnFilters=null;
    var columnValue=function(r,field){
        var cash=String(r.cash_account||'').split(' / ');
        var fields={date:r.date,number:r.voucher,head:cash[0]||'',subhead:cash.slice(1).join(' / '),
            detail:r.narration,amount:r.amount,status:'Posted'};
        return String(fields[field] == null ? '' : fields[field]);
    };
    var printUrl = function (id) { return $root.attr('data-print-base') + '/' + encodeURIComponent(id) + '/print'; };
    var formatMoney = function (value) { return Number(value || 0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); };
    var showError = function ($box, message) { $box.text(message || 'Request failed').removeClass('d-none'); };
    var notify = function (type, title, message) {
        if (window.Swal) Swal.fire({icon:type,title:title,text:message});
        else window.alert(title + ': ' + message);
    };
    var valid = function () {
        var amount = Number($('#voucherAmount').val());
        var ok = !busy && !chequeLoading && !!$('#voucherDate').val()
            && Number.isFinite(amount) && amount > 0 && !!$('#cashAccount').val()
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
            var batch = Number(r.posting_batch_id);
            if (!Number.isSafeInteger(batch) || batch < 1) return;
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
            $('<td>').append($('<span class="badge badge-secondary">').text('Posted')).appendTo($tr);
            var $actions=$('<td class="blue-actions-cell">');
            $('<button type="button" class="btn btn-sm btn-outline-secondary bt-view" data-blue-action-type="view">View</button>').attr('data-id',batch).appendTo($actions);
            $('<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" data-blue-action-type="print">Print</a>').attr('href',printUrl(batch)).appendTo($actions);
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
        return $.ajax({
            url:$root.attr('data-history-url'),dataType:'json',headers:{Accept:'application/json'}
        }).done(function (json) {
            allRows = $.isArray(json.data) ? json.data : [];
            page = 1;drawRows();
        }).fail(function(xhr) {
            showError($('#transferListError'),(xhr.responseJSON || {}).message || 'Could not load voucher history');
        });
    };
    columnFilters=$.blueVoucherColumnFilters.init({
        initialOptions:{head:[],subhead:[],status:['Posted'],account_relations:[]},
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
    $('#btFilterForm').on('submit',function(event) {
        event.preventDefault();
        var from=$('#transferFrom').val(),to=$('#transferTo').val();
        if (from && to && from>to) { notify('error','Invalid date range','From date must be on or before To date.');return; }
        activeFilters = {
            from:from,to:to,
            bank:String($('#transferBankFilter').val() || '').toLowerCase(),
            search:$.trim($('#transferSearch').val()).toLowerCase()
        };
        page=1;drawRows();
    });
    $('#clearTransferFilters').on('click',function(){
        $('#transferFrom,#transferTo,#transferBankFilter,#transferSearch,#btQuickSearch').val('');
        activeFilters={from:'',to:'',bank:'',search:''};
        columnSort='date';columnDirection='desc';columnFilters.reset();page=1;drawRows();
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
            ['Status','Posted'],['Details',row.narration]
        ],function(_,field){
            var $line=$('<div class="d-flex justify-content-between py-2 border-bottom">');
            $('<strong class="mr-3">').text(field[0]).appendTo($line);
            $('<span class="text-right" style="overflow-wrap:anywhere">').text(field[1] || '—').appendTo($line);
            $body.append($line);
        });
        $('#transferViewPrint').attr('href',printUrl(id));
        $('#bankTransferViewModal').modal('show');
    });
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
            showError($('#transferFormError'),(xhr.responseJSON || {}).message || 'Could not load cleared cheques');
        }).always(function(){chequeLoading=false;valid();});
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
        $form[0].reset();pendingRequestKey=null;
        $('#voucherAmount').prop('readonly',false);
        $('#transferFormError').addClass('d-none');
        loadCheques();valid();
    });
    $('#voucherDate,#voucherAmount,#cashAccount,#bankAccount,#voucherReference,#voucherDescription').on('input change',function(){
        if(!busy) pendingRequestKey=null;
        valid();
    });
    $form.on('submit',function(event){
        event.preventDefault();
        if(!valid()||busy)return;
        busy=true;valid();$('#transferFormError').addClass('d-none');
        if(!pendingRequestKey) pendingRequestKey='transfer'+Date.now().toString(36)+Math.random().toString(36).slice(2,14);
        var payload={
            date:$('#voucherDate').val(),amount:Number($('#voucherAmount').val()).toFixed(2),
            cash_account_id:Number($('#cashAccount').val()),bank_account_id:Number($('#bankAccount').val()),
            reference:$('#voucherReference').val(),description:$('#voucherDescription').val(),
            request_key:pendingRequestKey,cheque_source_id:$('#chequeSource').val()?Number($('#chequeSource').val()):null
        };
        $.ajax({
            url:$root.attr('data-post-url'),method:'POST',data:JSON.stringify(payload),
            contentType:'application/json',dataType:'json',
            headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content'),Accept:'application/json'}
        }).done(function(json){
            pendingRequestKey=null;
            $('#bankTransferCreateModal').modal('hide');
            notify('success','Voucher posted','Posting batch #'+json.data.posting_batch_id);
            loadHistory();
        }).fail(function(xhr){
            showError($('#transferFormError'),(xhr.responseJSON || {}).message || 'Voucher could not be posted');
        }).always(function(){busy=false;valid();});
    });
    loadHistory();
});
</script>
@endsection