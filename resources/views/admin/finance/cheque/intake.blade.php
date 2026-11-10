@extends('admin.layouts.master')
@section('content')
<style>
    .cheque-register-page { --cheque-green: #168348; --cheque-border: #b9e1c6; }
    .cheque-register-page .cheque-heading { background: #eaf7ee; border-bottom: 2px solid var(--cheque-green); padding: 12px 15px; }
    .cheque-register-page .cheque-heading h4 { color: #087a38; font-size: 21px; font-weight: 700; }
    .cheque-register-page .cheque-filter-panel, .cheque-register-page .cheque-table-panel { border: 1px solid var(--cheque-border); border-top: 3px solid var(--cheque-green); border-radius: 12px; background: #fff; padding: 12px; margin-bottom: 10px; }
    .cheque-register-page .cheque-table-panel th { background: #e6f4eb; border-bottom: 2px solid var(--cheque-green); white-space: nowrap; }
    .cheque-register-page .cheque-table-panel td { vertical-align: middle; }
    .cheque-register-page .cheque-table-panel .table { margin-bottom: 0; }
    .cheque-register-page .cheque-table-panel .form-control-sm { min-width: 85px; }
    .cheque-register-page .cheque-table-panel tbody tr:nth-child(even) { background: #f7fbf8; }
    .cheque-register-page .cheque-table-panel .table-responsive { max-height: 55vh; overflow: auto; }
    .cheque-register-page .cheque-table-panel thead th { position: sticky; top: 0; z-index: 1; }
    .cheque-register-page .cheque-number { color: #087b39; font-weight: 700; }
    .cheque-register-page .cheque-filter-label { font-size: 12px; font-weight: 700; margin-bottom: 5px; }
    #cheque-create-modal .modal-dialog { max-width: 970px; }
    #cheque-create-modal .modal-header { background: #eaf7ee; border-bottom: 2px solid #168348; }
    #cheque-create-modal .modal-title { color: #087a38; font-weight: 700; }
    #cheque-create-modal label { font-size: 12px; font-weight: 700; }
    #cheque-create-modal .modal-body { max-height: 75vh; overflow-y: auto; }
    #cheque-toast { position: fixed; top: 90px; right: 20px; z-index: 2100; min-width: 260px; max-width: 400px; display: none; }
</style>
<div class="content-wrapper cheque-register-page">
    <div class="container-fluid px-2 pt-2">
        <div class="cheque-heading d-flex align-items-center justify-content-between flex-wrap">
            <div><h4 class="mb-0">Cheque Management</h4><small class="text-muted">Selected project records · Receiving Register</small></div>
            <div class="d-flex align-items-center">
                <a href="{{ route('finance.daily-vouchers.index', ['type' => 'CR']) }}" class="btn btn-outline-secondary btn-sm mr-2">CR Vouchers</a>
                @can('create voucher')
                    <button type="button" class="btn btn-success btn-sm" id="cheque-add-btn"><i class="fas fa-plus mr-1"></i> Add Cheque</button>
                @endcan
            </div>
        </div>
        <div class="cheque-filter-panel mt-2">
            <form id="cheque-filter-form" autocomplete="off">
                <div class="row">
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-from">From</label><input type="date" id="cheque-from" name="from" class="form-control form-control-sm"></div>
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-to">To</label><input type="date" id="cheque-to" name="to" class="form-control form-control-sm"></div>
                    <div class="col-lg-3 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-customer">Customer</label><select name="customer_id" id="cheque-customer" class="form-control form-control-sm"><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->first_name }} {{ $customer->last_name }} (#{{ $customer->id }})</option>@endforeach</select></div>
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-status">Status</label><select name="status" id="cheque-status" class="form-control form-control-sm"><option value="">All statuses</option><option value="received">Received</option><option value="pending_clearing">Pending Clearing</option><option value="cleared">Cleared</option><option value="returned">Returned</option><option value="rejected">Rejected</option><option value="withdrawn">Withdrawn</option></select></div>
                    <div class="col-lg-3 col-md-8 mb-2"><label class="cheque-filter-label" for="cheque-search">Search</label><input name="search" id="cheque-search" class="form-control form-control-sm" placeholder="Cheque number, bank, person, purpose"></div>
                </div>
                <div class="d-flex justify-content-end"><button type="submit" class="btn btn-success btn-sm mr-2"><i class="fas fa-filter mr-1"></i> Apply</button><button type="button" id="cheque-reset" class="btn btn-outline-secondary btn-sm">Reset</button></div>
            </form>
        </div>
        <div class="cheque-table-panel">
            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
                <div class="d-flex align-items-center"><strong class="mr-2">Show</strong><select id="cheque-per-page" class="form-control form-control-sm mr-2" style="width:80px"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select><span>entries</span></div>
                <div><label class="mb-0" for="cheque-quick-search">Search: </label><input id="cheque-quick-search" class="form-control form-control-sm d-inline-block ml-1" style="width:190px" placeholder="Search cheques"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover" id="cheque-register-table">
                    <thead>
                        <tr><th>#</th><th>Received Date</th><th>Cheque #</th><th>Bank</th><th>Received From</th><th>Purpose</th><th>Amount</th><th>Status</th></tr>
                        <tr class="cheque-column-filters">
                            <th></th><th></th>
                            <th><input class="form-control form-control-sm" data-column="cheque_number" placeholder="Filter"></th>
                            <th><input class="form-control form-control-sm" data-column="bank_name" placeholder="Filter"></th>
                            <th><input class="form-control form-control-sm" data-column="received_from" placeholder="Filter"></th>
                            <th><input class="form-control form-control-sm" data-column="purpose" placeholder="Filter"></th>
                            <th></th><th></th>
                        </tr>
                    </thead>
                    <tbody id="cheque-table-body"><tr><td colspan="8" class="text-center text-muted py-4">Loading cheques...</td></tr></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap mt-2">
                <small id="cheque-count" class="text-muted">Loading...</small>
                <div class="btn-group btn-group-sm" id="cheque-pagination"></div>
            </div>
        </div>
    </div>
</div>

@can('create voucher')
<div class="modal fade" id="cheque-create-modal" tabindex="-1" role="dialog" aria-labelledby="cheque-create-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="cheque-create-title">Add New Cheque</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <form id="cheque-intake-form" action="{{ route('finance.cheques.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div class="row">
                    <div class="form-group col-md-4"><label>Cheque Number *</label><input class="form-control form-control-sm" name="cheque_number" maxlength="100" required></div>
                    <div class="form-group col-md-4"><label>Cheque Date *</label><input class="form-control form-control-sm" type="date" name="cheque_date" required></div>
                    <div class="form-group col-md-4"><label>Receiving Date *</label><input class="form-control form-control-sm" type="date" name="received_date" value="{{ date('Y-m-d') }}" required></div>
                    <div class="form-group col-md-4"><label>Amount (Rs.) *</label><input class="form-control form-control-sm" name="amount" inputmode="decimal" required placeholder="0.00"></div>
                    <div class="form-group col-md-4"><label>Bank Name *</label><input class="form-control form-control-sm" name="bank_name" maxlength="150" required></div>
                    <div class="form-group col-md-4"><label>Branch</label><input class="form-control form-control-sm" name="branch" maxlength="150"></div>
                    <div class="form-group col-md-4"><label>Received From *</label><input class="form-control form-control-sm" name="received_from" maxlength="150" required></div>
                    <div class="form-group col-md-8"><label>Related Customer *</label><select class="form-control form-control-sm" name="customer_id" required><option value="">Select Customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->first_name }} {{ $customer->last_name }} (#{{ $customer->id }})</option>@endforeach</select></div>
                    <div class="form-group col-12"><label>Purpose / Description *</label><textarea class="form-control form-control-sm" name="purpose" rows="2" maxlength="500" required></textarea></div>
                    <div class="form-group col-md-8"><label>Remarks</label><textarea class="form-control form-control-sm" name="remarks" rows="2" maxlength="2000"></textarea></div>
                    <div class="form-group col-md-4"><label>Cheque Image / PDF</label><input type="file" class="form-control-file" name="attachment" accept=".jpg,.jpeg,.png,.pdf"><small class="text-muted">JPG, PNG or PDF · Max 5MB</small></div>
                </div>
                <small class="text-muted">Receiving record only — no bank clearance or accounting voucher is posted.</small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" id="cheque-save" class="btn btn-success">Save Cheque</button></div>
        </form>
    </div></div>
</div>
@endcan

<div id="cheque-toast" class="alert alert-success shadow" role="status" aria-live="polite"></div>
<div class="modal fade" id="cheque-result-modal" tabindex="-1" role="dialog" aria-labelledby="cheque-result-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="cheque-result-title">Cheque Status</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <div class="modal-body" id="cheque-result-message"></div>
        <div class="modal-footer"><button type="button" class="btn btn-primary" data-dismiss="modal">OK</button></div>
    </div></div>
</div>
<div class="modal fade" id="cheque-confirm-modal" tabindex="-1" role="dialog" aria-labelledby="cheque-confirm-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="cheque-confirm-title">Confirm Cheque Receiving</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <div class="modal-body">Are you sure you want to save this cheque as <strong>Received</strong>? No accounting voucher or bank clearance will be created.</div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-success" id="cheque-confirm-save">Yes, Save Cheque</button></div>
    </div></div>
</div>
@endsection
@push('script')
<script>
$(function () {
    'use strict';
    let currentPage = 1;
    let listRequest = null;
    let saveBusy = false;
    let toastTimer = null;
    let searchTimer = null;

    function toast(message, error) {
        const $toast = $('#cheque-toast');
        $toast.toggleClass('alert-success', !error).toggleClass('alert-danger', !!error).text(message).stop(true, true).fadeIn(150);
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { $toast.fadeOut(250); }, 4500);
    }
    function resultModal(title, message) {
        $('#cheque-result-title').text(title);
        $('#cheque-result-message').text(message);
        $('#cheque-result-modal').modal('show');
    }
    function filters(page) {
        const result = { page: page, per_page: $('#cheque-per-page').val() };
        $.each($('#cheque-filter-form').serializeArray(), function (_, item) {
            if (item.value) result[item.name] = item.value;
        });
        const quick = $.trim($('#cheque-quick-search').val());
        if (quick) result.search = quick;
        $('.cheque-column-filters [data-column]').each(function () {
            const val = $.trim($(this).val());
            if (val) result[$(this).data('column')] = val;
        });
        return result;
    }
    function buildRow(row, index) {
        const $tr = $('<tr>');
        $('<td>').text(index).appendTo($tr);
        $('<td>').text(row.received_date || '').appendTo($tr);
        $('<td>').addClass('cheque-number').text(row.cheque_number || '').appendTo($tr);
        $('<td>').text(row.bank_name || '').appendTo($tr);
        $('<td>').text(row.received_from || '').appendTo($tr);
        $('<td>').text(row.purpose || '').appendTo($tr);
        const amount = Number(row.amount);
        $('<td>').addClass('text-right').text(Number.isFinite(amount) ? amount.toLocaleString('en-PK', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00').appendTo($tr);
        const $status = $('<td>');
        $('<span>').addClass('badge badge-secondary').text((row.status || 'received').replace(/_/g, ' ')).appendTo($status);
        $status.appendTo($tr);
        return $tr;
    }
    function pager(data) {
        const $nav = $('#cheque-pagination').empty();
        const current = data.current_page || 1;
        const last = data.last_page || 1;
        function button(text, page, disabled, active) {
            $('<button type="button">').addClass('btn ' + (active ? 'btn-success' : 'btn-outline-success'))
                .prop('disabled', !!disabled).text(text).on('click', function () { load(page); }).appendTo($nav);
        }
        button('Previous', current - 1, current <= 1, false);
        const from = Math.max(1, current - 2);
        const to = Math.min(last, from + 4);
        for (let p = from; p <= to; p++) button(String(p), p, false, p === current);
        button('Next', current + 1, current >= last, false);
        $('#cheque-count').text(data.total ? 'Showing ' + data.from + '–' + data.to + ' of ' + data.total + ' records' : 'Showing 0 records');
    }
    function load(page) {
        currentPage = page;
        if (listRequest) listRequest.abort();
        $('#cheque-table-body').empty().append($('<tr>').append($('<td colspan="8" class="text-center text-muted py-4">').text('Loading cheques...')));
        listRequest = $.ajax({
            url: @json(route('finance.cheques.data')),
            method: 'GET', data: filters(page), dataType: 'json'
        }).done(function (response) {
            const $body = $('#cheque-table-body').empty();
            $.each(response.data || [], function (i, row) {
                $body.append(buildRow(row, ((response.current_page - 1) * response.per_page) + i + 1));
            });
            if (!(response.data || []).length) $body.append($('<tr>').append($('<td colspan="8" class="text-center text-muted py-4">').text('No matching cheques found.')));
            pager(response);
        }).fail(function (xhr, status) {
            if (status === 'abort') return;
            $('#cheque-table-body').empty().append($('<tr>').append($('<td colspan="8" class="text-center text-danger py-4">').text('Unable to load cheque records.')));
            $('#cheque-count').text('Could not load records');
            toast('Cheque list could not be loaded.', true);
        });
    }
    $('#cheque-add-btn').on('click', function () { $('#cheque-create-modal').modal('show'); });
    $('#cheque-filter-form').on('submit', function (event) { event.preventDefault(); load(1); });
    $('#cheque-reset').on('click', function () {
        $('#cheque-filter-form').trigger('reset');
        $('#cheque-quick-search').val('');
        $('.cheque-column-filters [data-column]').val('');
        load(1);
    });
    $('#cheque-per-page').on('change', function () { load(1); });
    $('#cheque-quick-search, .cheque-column-filters [data-column]').on('input', function () {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { load(1); }, 350);
    });

    $('#cheque-intake-form').on('submit', function (event) {
        event.preventDefault();
        if (saveBusy) return;
        if (!$(this).find(':input:invalid').length) {
            $('#cheque-create-modal').modal('hide');
            $('#cheque-confirm-modal').modal('show');
        } else {
            $(this).find(':input:invalid').first().trigger('focus');
        }
    });
    $('#cheque-confirm-save').on('click', function () {
        if (saveBusy) return;
        saveBusy = true;
        $('#cheque-confirm-save, #cheque-save').prop('disabled', true);
        const $form = $('#cheque-intake-form');
        const formData = new FormData($form[0]);
        $.ajax({
            url: $form.attr('action'), method: 'POST', data: formData, processData: false, contentType: false,
            dataType: 'json', headers: {'Accept': 'application/json'}
        }).done(function (response) {
            $('#cheque-confirm-modal').modal('hide');
            $form.trigger('reset');
            $form.find('[name="received_date"]').val(@json(date('Y-m-d')));
            const row = response.data || {};
            const $body = $('#cheque-table-body');
            if (currentPage === 1 && !$('#cheque-filter-form').serializeArray().some(function (item) { return !!item.value; }) && !$('#cheque-quick-search').val() && !$('.cheque-column-filters [data-column]').filter(function () { return !!$(this).val(); }).length) {
                $body.find('td[colspan="8"]').closest('tr').remove();
                $body.prepend(buildRow(row, 1));
            }
            toast(response.message || 'Cheque received successfully.', false);
            resultModal('Cheque Saved Successfully', response.message || 'Cheque received.');
            load(1);
        }).fail(function (xhr) {
            $('#cheque-confirm-modal').modal('hide');
            let message = 'Unable to save cheque. Please try again.';
            if (xhr.responseJSON) {
                const err = xhr.responseJSON;
                message = err.message || message;
                if (err.errors) {
                    const keys = Object.keys(err.errors);
                    if (keys.length && err.errors[keys[0]].length) message = err.errors[keys[0]][0];
                }
            }
            toast(message, true);
            resultModal('Cheque Not Saved', message);
        }).always(function () {
            saveBusy = false;
            $('#cheque-confirm-save, #cheque-save').prop('disabled', false);
        });
    });
    load(1);
});
</script>
@endpush
