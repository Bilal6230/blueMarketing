@extends('admin.layouts.master')
@section('title', $kind === 'deposit' ? 'Bank Deposit Voucher' : 'Bank Withdrawal Voucher')
@section('content')
<style>
/* Align with the master voucher list; apply frame compensation to this page only. */
body:has(#bankTransferApp) .main-content.page-content {margin-left:250px!important;width:calc(100% - 250px)!important;max-width:calc(100% - 250px);padding:76px 24px 28px!important;box-sizing:border-box;overflow-x:hidden}
body.sidebar-collapse:has(#bankTransferApp) .main-content.page-content {margin-left:4.6rem!important;width:calc(100% - 4.6rem)!important;max-width:calc(100% - 4.6rem)}
#bankTransferApp {max-width:1600px;margin:0 auto}
#bankTransferApp .bt-heading h2 {font-size:1.55rem;font-weight:600;color:#223750}
#bankTransferApp .bt-list-card {border-radius:10px;border:1px solid #e0e7f0}
#bankTransferApp .bt-list-card>.card-header {background:#f8fafc;padding:16px 20px}
#bankTransferApp .bt-filter-box {background:#f7f9fd;border:1px solid #e0e8f2;border-radius:9px;padding:16px}
#bankTransferApp .bt-filter-box label {font-weight:600;font-size:13px}
#bankTransferApp #transferTable thead th {background:#edf3fa;color:#263e59;white-space:nowrap;font-size:13px}
#bankTransferApp #transferTable td {vertical-align:middle;font-size:13px}
#bankTransferApp .bt-actions .dropdown-menu {min-width:140px}
#bankTransferCreateModal .modal-body label {font-weight:600}
#bankTransferCreateModal .form-control {min-height:39px;border-radius:7px}
#bankTransferCreateModal .modal-content,#bankTransferViewModal .modal-content {border-radius:12px}
@media(max-width:991.98px) {body:has(#bankTransferApp) .main-content.page-content,body.sidebar-collapse:has(#bankTransferApp) .main-content.page-content {margin-left:0!important;width:100%!important;max-width:100%;padding:74px 12px 24px!important}}
</style>
<div class="container-fluid bt-page" id="bankTransferApp" data-kind="{{ $kind }}"
    data-post-url="{{ route('finance.voucher.transfer.post',['kind'=>$kind]) }}"
    data-history-url="{{ route('finance.voucher.transfer.history',['kind'=>$kind]) }}"
    data-source-url="{{ route('finance.voucher.transfer.sources',['kind'=>$kind]) }}"
    data-print-base="{{ url('admin/finance/voucher/bank-transfer/'.$kind) }}">
    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3 bt-heading">
        <div><h2 class="mb-1">{{ $kind === 'deposit' ? 'Bank Deposit Vouchers' : 'Bank Withdrawal Vouchers' }}</h2>
            <div class="text-muted">Daily Entries / {{ $kind === 'deposit' ? 'Bank Deposit' : 'Bank Withdrawal' }} · Project #{{ (int)getSelectedTown() }}</div></div>
        <div class="d-flex align-items-center mt-2 mt-sm-0" style="gap:8px">
            <button type="button" class="btn btn-outline-secondary" id="refreshTransfers"><i class="fas fa-sync-alt"></i> Refresh</button>
            <button type="button" class="btn btn-primary" id="openTransferCreate" data-toggle="modal" data-target="#bankTransferCreateModal"><i class="fas fa-plus"></i> Add Voucher</button>
        </div>
    </div>
    <section class="card shadow-sm bt-list-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
            <strong>{{ $kind === 'deposit' ? 'Bank Deposit' : 'Bank Withdrawal' }} Voucher List</strong>
            <button type="button" class="btn btn-sm btn-outline-primary" id="toggleTransferFilters" aria-expanded="true" aria-controls="transferFilters"><i class="fas fa-filter"></i> Filters</button>
        </div>
        <div class="card-body">
            <div id="transferFilters" class="bt-filter-box mb-3">
                <div class="row">
                    <div class="form-group col-md-3"><label for="transferFrom">From date</label><input type="date" class="form-control" id="transferFrom"></div>
                    <div class="form-group col-md-3"><label for="transferTo">To date</label><input type="date" class="form-control" id="transferTo"></div>
                    <div class="form-group col-md-3"><label for="transferBankFilter">Bank account</label>
                        <select class="form-control" id="transferBankFilter"><option value="">All bank accounts</option>
                        @foreach($bankAccounts as $bank)<option value="{{ $bank->bank_name }} / {{ $bank->account_title }}">{{ $bank->bank_name }} / {{ $bank->account_title }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-3"><label for="transferSearch">Search vouchers</label><input class="form-control" id="transferSearch" maxlength="120" placeholder="Voucher, cash, cheque or note"></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearTransferFilters">Reset filters</button>
            </div>
            <div id="transferListError" class="alert alert-danger d-none" role="alert"></div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover w-100" id="transferTable">
                    <thead><tr><th>Voucher No.</th><th>Date</th><th>Cash account</th><th>Bank account</th><th>Cheque reference</th><th>Amount</th><th>Action</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="small text-muted mt-2">Posted vouchers are immutable. View and Print are available; financial correction requires an approved reversal workflow.</div>
        </div>
    </section>
</div>

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
    var table = null, rows = [], busy = false, chequeLoading = false;
    var escapeHtml = function (value) { return $('<div>').text(value == null ? '' : String(value)).html(); };
    var showError = function ($box, message) { $box.text(message || 'Request failed').removeClass('d-none'); };
    var printUrl = function (id) { return $root.attr('data-print-base') + '/' + encodeURIComponent(id) + '/print'; };
    var valid = function () {
        var amount = Number($('#voucherAmount').val());
        var ok = !busy && !chequeLoading && !!$('#voucherDate').val() && Number.isFinite(amount)
            && amount > 0 && !!$('#cashAccount').val() && !!$('#bankAccount').val()
            && !!$.trim($('#voucherDescription').val());
        $save.prop('disabled', !ok);
        return ok;
    };
    var notify = function (type, title, message) {
        if (window.Swal) Swal.fire({icon:type,title:title,text:message});
        else window.alert(title + ': ' + message);
    };
    var dateValue = function (row) { return String(row.date || '').slice(0,10); };
    var selectedRows = function () {
        var from = $('#transferFrom').val(), to = $('#transferTo').val();
        var bank = $('#transferBankFilter').val().toLowerCase();
        var term = $.trim($('#transferSearch').val()).toLowerCase();
        return $.grep(rows, function (row) {
            var date = dateValue(row);
            var haystack = [row.voucher,row.date,row.cash_account,row.bank,row.cheque,row.narration,row.amount].join(' ').toLowerCase();
            return (!from || date >= from) && (!to || date <= to)
                && (!bank || String(row.bank || '').toLowerCase() === bank)
                && (!term || haystack.indexOf(term) !== -1);
        });
    };
    var drawRows = function () {
        if (table) {
            table.clear().rows.add(selectedRows()).draw();
        } else {
            var $tbody = $('#transferTable tbody').empty(), filtered = selectedRows();
            if (!filtered.length) $tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">No matching vouchers</td></tr>');
            $.each(filtered, function (_, row) { $tbody.append(renderRow(row)); });
        }
    };
    var renderRow = function (r) {
        var id = Number(r.posting_batch_id);
        var $tr = $('<tr>');
        $('<td>').text(r.voucher || '').appendTo($tr);
        $('<td>').text(r.date || '').appendTo($tr);
        $('<td>').text(r.cash_account || '').appendTo($tr);
        $('<td>').text(r.bank || '').appendTo($tr);
        $('<td>').text(r.cheque || '—').appendTo($tr);
        $('<td>').addClass('text-right').text(Number(r.amount || 0).toFixed(2)).appendTo($tr);
        var $action = $('<td>').addClass('bt-actions text-nowrap');
        var $group = $('<div class="dropdown"><button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Actions</button><div class="dropdown-menu dropdown-menu-right"></div></div>');
        $('<button type="button" class="dropdown-item bt-view"><i class="far fa-eye mr-2"></i>View</button>').attr('data-id', id).appendTo($group.find('.dropdown-menu'));
        $('<a class="dropdown-item"><i class="fas fa-print mr-2"></i>Print</a>').attr({href:printUrl(id),target:'_blank',rel:'noopener'}).appendTo($group.find('.dropdown-menu'));
        $action.append($group).appendTo($tr);
        return $tr;
    };
    if ($.fn.DataTable) {
        table = $('#transferTable').DataTable({
            data:[], columns:[
                {data:'voucher',render: function (d,type) {return type === 'display' ? escapeHtml(d) : d;}},
                {data:'date',render:function(d,type){return type === 'display' ? escapeHtml(d) : d;}},
                {data:'cash_account',render:function(d,type){return type === 'display' ? escapeHtml(d) : d;}},
                {data:'bank',render:function(d,type){return type === 'display' ? escapeHtml(d) : d;}},
                {data:'cheque',render:function(d,type){return type === 'display' ? escapeHtml(d || '—') : d;}},
                {data:'amount',className:'text-right',render:function(d,type){return type === 'display' ? Number(d||0).toFixed(2) : Number(d||0);}},
                {data:null,orderable:false,searchable:false,render:function(d,type,row){
                    if (type !== 'display') return '';
                    var id = Number(row.posting_batch_id);
                    return '<div class="dropdown bt-actions"><button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">Actions</button>'
                        + '<div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item bt-view" data-id="' + id + '"><i class="far fa-eye mr-2"></i>View</button>'
                        + '<a class="dropdown-item" target="_blank" rel="noopener" href="' + escapeHtml(printUrl(id)) + '"><i class="fas fa-print mr-2"></i>Print</a></div></div>';
                }}
            ],order:[[1,'desc']],pageLength:10,responsive:false,autoWidth:false,
            language:{emptyTable:'No vouchers posted yet'}
        });
    }
    var loadHistory = function () {
        $('#transferListError').addClass('d-none');
        return $.ajax({url:$root.attr('data-history-url'),dataType:'json',headers:{Accept:'application/json'}})
            .done(function (data) { rows = $.isArray(data.data) ? data.data : []; drawRows(); })
            .fail(function (xhr) { showError($('#transferListError'), (xhr.responseJSON || {}).message || 'Could not load voucher history'); });
    };
    $('#transferFrom,#transferTo,#transferBankFilter,#transferSearch').on('change input', drawRows);
    $('#clearTransferFilters').on('click',function () {
        $('#transferFrom,#transferTo,#transferSearch,#transferBankFilter').val(''); drawRows();
    });
    $('#toggleTransferFilters').on('click',function () {
        var $filters = $('#transferFilters');
        $filters.toggleClass('d-none');
        $(this).attr('aria-expanded', !$filters.hasClass('d-none'));
    });
    $('#refreshTransfers').on('click', loadHistory);
    $('#transferTable').on('click','.bt-view',function () {
        var id = Number($(this).attr('data-id')), row = $.grep(rows,function(r){return Number(r.posting_batch_id)===id;})[0];
        if (!row) return;
        $('#bankTransferViewTitle').text(row.voucher || 'Voucher details');
        var detail = [
            ['Voucher', row.voucher],['Date', row.date],['Cash account', row.cash_account],
            ['Bank account', row.bank],['Cheque', row.cheque || '—'],
            ['Amount', Number(row.amount || 0).toFixed(2)],['Narration', row.narration]
        ];
        var $body = $('#transferViewBody').empty();
        $.each(detail,function(_,item){
            var $line = $('<div class="d-flex justify-content-between py-2 border-bottom">');
            $('<strong class="mr-3">').text(item[0]).appendTo($line);
            $('<span class="text-right" style="overflow-wrap:anywhere">').text(item[1] || '—').appendTo($line);
            $body.append($line);
        });
        $('#transferViewPrint').attr('href',printUrl(id));
        $('#bankTransferViewModal').modal('show');
    });
    var loadCheques = function () {
        var $cheque = $('#chequeSource');
        if (!$cheque.length) return;
        chequeLoading = true;valid();
        $.ajax({url:$root.attr('data-source-url'),dataType:'json',headers:{Accept:'application/json'}})
            .done(function (json) {
                $cheque.empty().append($('<option>').val('').text('Manual bank withdrawal'));
                $.each(json.data || [],function(_,source){
                    $('<option>').val(source.id).text([source.voucher,source.party,source.amount,source.reference].join(' · '))
                        .attr({'data-amount':source.amount,'data-date':source.date,'data-ref':source.reference,'data-voucher':source.voucher})
                        .appendTo($cheque);
                });
            })
            .fail(function(xhr){showError($('#transferFormError'),(xhr.responseJSON || {}).message || 'Failed to load verified cheques');})
            .always(function(){chequeLoading=false;valid();});
    };
    $('#chequeSource').on('change',function () {
        var $opt = $(this).find('option:selected'), linked = !!$opt.val();
        $('#voucherAmount').val(linked ? $opt.attr('data-amount') : '').prop('readonly',linked);
        if (linked) {
            $('#voucherDate').val($opt.attr('data-date'));
            $('#voucherReference').val($opt.attr('data-ref'));
            $('#voucherDescription').val('Withdrawal against ' + $opt.attr('data-voucher') + ' cheque ' + $opt.attr('data-ref'));
        }
        valid();
    });
    $('#bankTransferCreateModal').on('show.bs.modal',function () {
        $form[0].reset();
        $('#voucherAmount').prop('readonly',false);
        $('#transferFormError').addClass('d-none');
        loadCheques();
        valid();
    });
    $('#voucherDate,#voucherAmount,#cashAccount,#bankAccount,#voucherReference,#voucherDescription').on('input change',valid);
    $form.on('submit',function(e){
        e.preventDefault();if (!valid() || busy) return;
        busy=true;valid();$('#transferFormError').addClass('d-none');
        var data = {
            date:$('#voucherDate').val(),amount:Number($('#voucherAmount').val()).toFixed(2),
            cash_account_id:Number($('#cashAccount').val()),bank_account_id:Number($('#bankAccount').val()),
            reference:$('#voucherReference').val(),description:$('#voucherDescription').val(),
            request_key:'transfer' + Date.now().toString(36) + Math.random().toString(36).slice(2,14),
            cheque_source_id:$('#chequeSource').val() ? Number($('#chequeSource').val()) : null
        };
        $.ajax({url:$root.attr('data-post-url'),method:'POST',data:JSON.stringify(data),
            contentType:'application/json',dataType:'json',
            headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content'),Accept:'application/json'}})
            .done(function(json){
                $('#bankTransferCreateModal').modal('hide');
                notify('success','Voucher posted','Posting batch #' + json.data.posting_batch_id);
                loadHistory();
            })
            .fail(function(xhr){
                var response = xhr.responseJSON || {};
                showError($('#transferFormError'),response.message || 'Voucher could not be posted');
            })
            .always(function(){busy=false;valid();});
    });
    loadHistory();
});
</script>
@endsection