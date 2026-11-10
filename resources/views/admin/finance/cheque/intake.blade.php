@extends('admin.layouts.master')
@section('title', 'Cheque Management')
@section('content')
<style>
    html.cheque-register-active,body.cheque-register-active {height:100%;overflow:hidden!important}
    body.cheque-register-active .main-footer {position:fixed!important;bottom:0;left:0;right:0;z-index:10;margin-bottom:0!important}
    .main-content.page-content:has(> .cheque-register-page) {height:auto!important;min-height:0!important;max-height:none!important;overflow:hidden!important;padding:0!important;margin-bottom:0!important}
    .cheque-register-page .cheque-table-panel{margin-bottom:0!important}
    .cheque-register-page { --cheque-green:#168348; --cheque-border:#b9e1c6; height:var(--cheque-height,calc(100dvh - 165px)); max-height:var(--cheque-height,calc(100dvh - 165px)); min-height:0; display:flex; flex-direction:column; overflow:hidden; }
    .cheque-register-page .cheque-filter-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1.2fr) minmax(0,1.05fr) minmax(0,1.35fr) auto; align-items:end; column-gap:10px; row-gap:8px; margin:0; width:100%; }
    .cheque-register-page .cheque-filter-row > div { min-width:0; width:100%; padding:0; margin:0!important; max-width:none; }
    .cheque-register-page .cheque-filter-row .form-control { width:100%; min-width:0; }
    .cheque-register-page .cheque-filter-row .cheque-filter-actions { display:flex; align-items:center; justify-content:flex-end; padding:0; gap:5px; white-space:nowrap; height:31px; }
    .cheque-register-page .cheque-filter-row .cheque-filter-actions .btn { height:31px; }
    .cheque-register-page .cheque-filter-row .select2-container { width:100%!important; }
    .cheque-register-page .cheque-filter-row .select2-selection--single { height:31px!important; padding-top:1px; }
    @media(max-width:1199px){.cheque-register-page .cheque-filter-row {grid-template-columns:repeat(3,minmax(0,1fr));}.cheque-register-page .cheque-filter-row .cheque-filter-actions{justify-content:flex-start;}}
    @media(max-width:650px){.cheque-register-page .cheque-filter-row {grid-template-columns:repeat(2,minmax(0,1fr));}}
    .cheque-register-page > .container-fluid {display:flex;flex-direction:column;flex:1;min-height:0;}
    .cheque-register-page .cheque-heading,.cheque-register-page .cheque-filter-panel {flex-shrink:0;}
    .cheque-register-page .cheque-table-panel {display:flex;flex-direction:column;flex:1;min-height:0;margin-bottom:0;}
    .cheque-register-page .cheque-table-panel > .table-responsive {flex:1;min-height:80px;max-height:none;overflow:auto;}
    .cheque-register-page .cheque-table-panel > .d-flex:last-child {flex-shrink:0;}
    .cheque-filter-actions {display:flex;align-items:end;gap:5px;padding-bottom:8px;}
    #cheque-intake-form .is-invalid {border-color:#dc3545!important;box-shadow:0 0 0 .1rem rgba(220,53,69,.12);}
    #cheque-intake-form .cheque-field-error {display:block;color:#dc3545;font-size:11px;margin-top:4px;}
    #cheque-intake-form .cheque-upload {border:1px dashed #8cbea0;border-radius:7px;background:#f4faf6;padding:6px 10px;text-align:center;cursor:pointer;min-height:61px;height:61px;overflow:hidden;}
    #cheque-intake-form .cheque-upload .fa-cloud-upload-alt { display:inline-block!important; margin:0 6px 0 0!important; font-size:13px; }
    #cheque-intake-form .cheque-upload .cheque-file-name { margin:0!important; max-height:17px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    #cheque-intake-form .cheque-upload small { line-height:13px; font-size:10px; }
    #cheque-intake-form textarea[name=purpose] { min-height:61px; resize:vertical; }
    .cheque-status-badge{display:inline-block;border-radius:4px;padding:3px 6px;font-size:11px;font-weight:700;text-transform:capitalize}
    .cheque-status-cleared{background:#198754;color:white}.cheque-status-returned{background:#ffc107;color:#242424}.cheque-status-rejected{background:#dc3545;color:white}
    .cheque-status-withdrawn{background:#0d6efd;color:white}.cheque-status-received,.cheque-status-issued{background:#6c757d;color:white}.cheque-status-pending_clearing{background:#17a2b8;color:white}
    #cheque-create-modal .select2-container { width:100%!important; }
    #cheque-create-modal .select2-container .select2-selection--single { height:31px!important; }
    .swal2-container {z-index:20000!important;}
    #cheque-intake-form .cheque-upload:hover,#cheque-intake-form .cheque-upload:focus-within {background:#e9f7ed;border-color:#168348;}
    #cheque-intake-form .cheque-upload.is-invalid {border-color:#dc3545;}
    #cheque-intake-form .cheque-file {position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;}
    #cheque-intake-form .cheque-file-name {display:block;font-size:12px;overflow-wrap:anywhere;}

    .cheque-register-page .cheque-heading { background: #eaf7ee; border-bottom: 2px solid var(--cheque-green); padding: 12px 15px; }
    .cheque-register-page .cheque-heading h4 { color: #087a38; font-size: 21px; font-weight: 700; }
    .cheque-register-page .cheque-filter-panel, .cheque-register-page .cheque-table-panel { border: 1px solid var(--cheque-border); border-top: 3px solid var(--cheque-green); border-radius: 12px; background: #fff; padding: 12px; margin-bottom: 10px; }
    .cheque-register-page .cheque-table-panel th { background: #e6f4eb; border-bottom: 2px solid var(--cheque-green); white-space: nowrap; }
    .cheque-register-page .cheque-table-panel td { vertical-align: middle; }
    .cheque-register-page .cheque-table-panel .table { margin-bottom: 0; }
    .cheque-register-page .cheque-table-panel .form-control-sm { min-width: 85px; }
    .cheque-register-page .cheque-table-panel tbody tr:nth-child(even) { background: #f7fbf8; }

    .cheque-register-page .cheque-table-panel { margin-bottom:0; }
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

                @can('create voucher')
                    <button type="button" class="btn btn-success btn-sm" id="cheque-add-btn"><i class="fas fa-plus mr-1"></i> Add Cheque</button>
                @endcan
            </div>
        </div>
        <div class="cheque-filter-panel mt-2">
            <form id="cheque-filter-form" autocomplete="off">
                <div class="row cheque-filter-row">
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-from">From</label><input type="date" id="cheque-from" name="from" class="form-control form-control-sm"></div>
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-to">To</label><input type="date" id="cheque-to" name="to" class="form-control form-control-sm"></div>
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-customer">Child Account</label><select name="account_mapping_id" id="cheque-customer" class="form-control form-control-sm"><option value="">All child accounts</option>@foreach($accountMappings as $account)<option value="{{ $account['id'] }}">{{ $account['head_name'] }} / {{ $account['subhead_name'] }}</option>@endforeach</select></div>
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-status">Status</label><select name="status" id="cheque-status" class="form-control form-control-sm"><option value="">All statuses</option><option value="received">Received</option><option value="issued">Issued</option><option value="pending_clearing">Pending Clearing</option><option value="cleared">Cleared</option><option value="returned">Returned</option><option value="rejected">Rejected</option><option value="withdrawn">Withdrawn</option></select></div>
                    <div class="col-lg-2 col-md-8 mb-2"><label class="cheque-filter-label" for="cheque-search">Search</label><input name="search" id="cheque-search" class="form-control form-control-sm" placeholder="Cheque number, bank, person, purpose"></div>
                    <div class="col-lg-2 col-md-4 mb-2 cheque-filter-actions"><button type="submit" class="btn btn-success btn-sm"><i class="fas fa-filter mr-1"></i> Apply</button><button type="button" id="cheque-reset" class="btn btn-outline-secondary btn-sm">Reset</button></div>
                </div>
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
                        <tr><th>#</th><th>Received Date</th><th>Cheque #</th><th>Bank</th><th>Received From</th><th>Purpose</th><th>Amount</th><th>Status</th><th>Slip</th><th>Actions</th></tr>
                        <tr class="cheque-column-filters">
                            <th></th><th></th>
                            <th><input class="form-control form-control-sm" data-column="cheque_number" placeholder="Filter"></th>
                            <th><input class="form-control form-control-sm" data-column="bank_name" placeholder="Filter"></th>
                            <th><input class="form-control form-control-sm" data-column="received_from" placeholder="Filter"></th>
                            <th><input class="form-control form-control-sm" data-column="purpose" placeholder="Filter"></th>
                            <th></th><th></th><th></th><th></th>
                        </tr>
                    </thead>
                    <tbody id="cheque-table-body"><tr><td colspan="10" class="text-center text-muted py-4">Loading cheques...</td></tr></tbody>
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
        <form id="cheque-intake-form" action="{{ route('finance.cheques.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="modal-body">
                <div class="row">
                    <div class="form-group col-md-4"><label>Cheque Number *</label><input class="form-control form-control-sm" name="cheque_number" maxlength="100" required></div>
                    <div class="form-group col-md-4"><label>Cheque Title *</label><input class="form-control form-control-sm" name="cheque_title" maxlength="200" required></div>
                    <div class="form-group col-md-4"><label>Cheque Direction *</label><select class="form-control form-control-sm" name="direction" id="cheque-direction" required>
                        <option value="in">In — Cheque Received</option><option value="out">Out — Cheque Issued</option>
                    </select></div>
                    <div class="form-group col-md-4"><label>Cheque Date *</label><input class="form-control form-control-sm" type="date" name="cheque_date" required></div>
                    <div class="form-group col-md-4"><label>Receiving Date *</label><input class="form-control form-control-sm" type="date" name="received_date" value="{{ date('Y-m-d') }}" required></div>
                    <div class="form-group col-md-4"><label>Amount (Rs.) *</label><input class="form-control form-control-sm" name="amount" type="number" min="0.01" max="9999999999999.99" step="0.01" inputmode="decimal" required placeholder="0.00"></div>
                    <div class="form-group col-md-4"><label>Bank Name *</label><input class="form-control form-control-sm" name="bank_name" maxlength="150" required></div>
                    <div class="form-group col-md-4"><label>Branch</label><input class="form-control form-control-sm" name="branch" maxlength="150"></div>
                    <div class="form-group col-md-4"><label>Received From *</label><input class="form-control form-control-sm" name="received_from" maxlength="150" required></div>
                    <div class="form-group col-md-4"><label>Head Account *</label>
                        <select class="form-control form-control-sm" name="head_accounting_id" id="cheque-head-account" required>
                            <option value="">Select Head Account</option>@foreach($headAccounts as $head)<option value="{{ $head['head_id'] }}">{{ $head['head_name'] }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-4"><label>Child Account *</label>
                        <select class="form-control form-control-sm" name="subhead_accounting_id" id="cheque-child-account" required>
                            <option value="">Select Child Account</option>
                            @foreach($accountMappings as $account)<option value="{{ $account['subhead_id'] }}" data-head="{{ $account['head_id'] }}">{{ $account['subhead_name'] }} — {{ $account['head_name'] }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-8"><label>Purpose / Description *</label><textarea class="form-control form-control-sm" name="purpose" rows="2" maxlength="500" required></textarea></div>
                    <div class="form-group col-md-4"><label for="cheque-attachment">Cheque Image / PDF</label>
                        <div id="cheque-upload" class="cheque-upload"><label for="cheque-attachment" class="w-100 mb-0" style="cursor:pointer">
                            <i class="fas fa-cloud-upload-alt fa-lg d-block mb-2 text-success"></i>
                            <strong class="text-success">Choose cheque file</strong>
                            <span id="cheque-file-name" class="cheque-file-name text-muted mt-1">No file selected</span>
                            <small class="d-block text-muted">JPG, PNG, PDF · Max 5 MB</small>
                        </label><input id="cheque-attachment" class="cheque-file" type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf"></div>
                    </div>
                </div>
                <small class="text-muted">Receiving record only — no bank clearance or accounting voucher is posted.</small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" id="cheque-save" class="btn btn-success">Save Cheque</button></div>
        </form>
    </div></div>
</div>
@endcan

<div class="modal fade" id="cheque-action-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document"><div class="modal-content">
        <div class="modal-header" style="background:#eaf7ee;border-bottom:2px solid #168348">
            <h5 class="modal-title font-weight-bold text-success" id="cheque-action-title">Cheque Action</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <form id="cheque-action-form" novalidate>
            @csrf
            <input type="hidden" name="action" id="cheque-action-kind">
            <div class="modal-body">
                <p class="text-muted mb-2">Original cheque: <strong id="cheque-action-number"></strong></p>
                <div class="form-group">
                    <label>Original Cheque Amount (Fixed)</label>
                    <input type="text" id="cheque-action-amount" class="form-control" readonly>
                    <small class="text-muted">The amount is read directly from the original cheque and cannot be changed.</small>
                </div>
                <div class="row">
                    <div class="form-group col-md-6"><label for="cheque-action-date">Clearing / Withdrawal Date *</label>
                        <input type="date" class="form-control" name="action_date" id="cheque-action-date" required></div>
                    <div class="form-group col-md-6" id="cheque-clearing-account-group">
                        <label for="cheque-action-bank-account">Deposit Bank Account *</label>
                        <select id="cheque-action-bank-account" name="bank_account_id" class="form-control">
                            <option value="">Select bank account</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->bank_name }} / {{ $account->account_title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6" id="cheque-action-bank-name-group"><label for="cheque-action-bank-name">Bank Name *</label>
                        <input class="form-control" id="cheque-action-bank-name" name="bank_name" maxlength="150"></div>
                    <div class="form-group col-md-6" id="cheque-action-branch-group"><label for="cheque-action-branch">Branch</label>
                        <input class="form-control" id="cheque-action-branch" name="branch" maxlength="150"></div>
                    <div class="form-group col-md-6 cheque-withdrawal-only"><label for="cheque-action-collector">Person Who Collected *</label>
                        <input class="form-control" id="cheque-action-collector" name="collector_name" maxlength="150"></div>
                    <div class="form-group col-md-6 cheque-withdrawal-only"><label for="cheque-action-cnic">Collector CNIC (if applicable)</label>
                        <input class="form-control" id="cheque-action-cnic" name="collector_cnic" maxlength="15" placeholder="XXXXX-XXXXXXX-X"></div>
                    <div class="form-group col-12"><label for="cheque-action-remarks">Remarks</label>
                        <textarea class="form-control" id="cheque-action-remarks" name="remarks" rows="2" maxlength="2000"></textarea></div>
                </div>
                <div id="cheque-action-errors" class="text-danger small" role="alert"></div>
                <p class="small text-muted mb-0">This action changes the cheque's workflow status only. No accounting voucher or bank posting will be created.</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" id="cheque-action-submit" class="btn btn-success">Confirm Action</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="cheque-finalize-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document"><div class="modal-content">
        <div class="modal-header" style="background:#eaf7ee;border-bottom:2px solid #168348">
            <h5 class="modal-title text-success">Cheque Clearing Decision</h5>
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
        </div>
        <form id="cheque-finalize-form" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="modal-body">
                <p>Cheque <strong id="cheque-finalize-number"></strong> — Original Amount:
                    <strong id="cheque-finalize-amount"></strong> (fixed)</p>
                <input type="hidden" name="decision" id="cheque-finalize-decision">
                <div class="form-group"><label>Decision Date *</label>
                    <input name="action_date" id="cheque-finalize-date" type="date" class="form-control" required></div>
                <div id="cheque-finalize-cleared">
                    <div class="alert alert-warning">
                        <span id="cheque-finalize-verification-message">Bank statement se cheque ki entry verify karne ke baad hi confirm karein.</span>
                    </div>
                    <div class="form-group"><label>Bank Statement Reference *</label>
                        <input name="bank_statement_reference" id="cheque-finalize-reference" maxlength="150" class="form-control"></div>
                    <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" class="custom-control-input" name="bank_statement_verified"
                            value="1" id="cheque-finalize-verified">
                        <label class="custom-control-label" for="cheque-finalize-verified">
                            Maine bank statement/account audit se credit amount verify kar li hai *
                        </label>
                    </div>
                </div>
                <div id="cheque-finalize-rejected" style="display:none">
                    <div class="form-group"><label>Return / Rejection Reason *</label>
                        <textarea class="form-control" name="reason" id="cheque-finalize-reason" maxlength="1000" rows="2"></textarea></div>
                    <div class="form-group"><label>Bank Memo / Attachment * (PDF, JPG, PNG; max 5 MB)</label>
                        <input type="file" name="bank_memo" id="cheque-finalize-memo" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png"></div>
                </div>
                <div class="form-group"><label>Remarks</label>
                    <textarea name="remarks" class="form-control" maxlength="2000" rows="2"></textarea></div>
                <div class="small text-danger" id="cheque-finalize-errors" role="alert"></div>
                <small class="text-muted">Is action se koi accounting voucher ya ledger posting create nahi hogi.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="cheque-finalize-save">Continue</button>
            </div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="cheque-history-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document"><div class="modal-content">
        <div class="modal-header" style="background:#eaf7ee;border-bottom:2px solid #168348">
            <h5 class="modal-title text-success">Cheque Lifecycle History</h5>
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
            <p id="cheque-history-heading" class="font-weight-bold"></p>
            <div class="table-responsive" style="max-height:60vh;overflow:auto">
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Date / Actor</th><th>Action</th><th>Status</th><th>Bank / Ref</th><th>Reason / Remarks / Memo</th></tr></thead>
                    <tbody id="cheque-history-rows"></tbody>
                </table>
            </div>
        </div>
    </div></div>
</div>
<div id="cheque-toast" class="alert alert-success shadow" role="status" aria-live="polite"></div>
@endsection
@push('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    'use strict';
    var currentPage=1, listRequest=null, saveBusy=false, searchTimer=null, listSeq=0;
    var $form=$('#cheque-intake-form'), $create=$('#cheque-create-modal');
    var actionUrlTemplate=@json(route('finance.cheques.action',['id'=>'__CHEQUE_ID__']));
    var finalizeUrlTemplate=@json(route('finance.cheques.finalize',['id'=>'__CHEQUE_ID__']));
    var historyUrlTemplate=@json(route('finance.cheques.history',['id'=>'__CHEQUE_ID__']));
    var finalizeBusy=false,finalizeTarget=null;
    var actionBusy=false;
    var actionTarget=null;
    var slipUrlTemplate=@json(route('finance.cheques.slip',['id'=>'__CHEQUE_ID__']));
    function slipUrl(id){return slipUrlTemplate.replace('__CHEQUE_ID__',String(id));}
    $('html,body').addClass('cheque-register-active');
    function fitPage() {
        var $page=$('.cheque-register-page');
        if (!$page.length || !$page.offset()) return;
        // Subtract the real footer height: the document itself must not scroll.
        var footerHeight=$('.main-footer').outerHeight() || 40;
        var available=$(window).height()-$page.offset().top-footerHeight-2;
        $page.css('--cheque-height',Math.max(200,available)+'px');
    }
    fitPage();
    $(window).on('resize.chequeRegister',fitPage);
    function toast(message,error) {
        if (window.toastr && $.isFunction(window.toastr.error)) {
            if (error) window.toastr.error(message); else window.toastr.success(message);
        } else {
            $('#cheque-toast').toggleClass('alert-success',!error).toggleClass('alert-danger',!!error).text(message)
                .stop(true,true).fadeIn(150).delay(3500).fadeOut(200);
        }
    }
    function fieldError($input,message) {
        var $group=$input.closest('.form-group');
        $input.addClass('is-invalid').attr('aria-invalid','true');
        if ($input.is('[type=file]')) $('#cheque-upload').addClass('is-invalid');
        $group.find('.cheque-field-error').remove();
        $('<small>').addClass('cheque-field-error').text(message).appendTo($group);
    }
    function clearError($input) {
        $input.removeClass('is-invalid').removeAttr('aria-invalid');
        $input.closest('.form-group').find('.cheque-field-error').remove();
        if ($input.is('[type=file]')) $('#cheque-upload').removeClass('is-invalid');
    }
    function clearErrors() {
        $form.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
        $form.find('.cheque-field-error').remove();
        $('#cheque-upload').removeClass('is-invalid');
    }
    function validate() {
        clearErrors();
        var valid=true, first=null;
        $form.find('input[name],select[name],textarea[name]').not('[type=hidden]').each(function () {
            var $f=$(this), val=$.trim(String($f.val()||'')), error='';
            if ($f.prop('required') && !val) error='This field is required.';
            else if (val && $f.prop('validity') && !$f.prop('validity').valid) error=$f.is('[type=number]')?'Enter a valid amount (greater than zero, max 2 decimals).':'Enter a valid value.';
            if (error) { fieldError($f,error);valid=false;if(!first)first=$f; }
        });
        var $amount=$form.find('[name=amount]');
        if (!$amount.hasClass('is-invalid') && !(Number($amount.val())>0)) {
            fieldError($amount,'Enter a valid amount greater than zero.');valid=false;if(!first)first=$amount;
        }
        var $file=$('#cheque-attachment'), files=$file.prop('files');
        if (files && files.length) {
            if (!/\.(jpg|jpeg|png|pdf)$/i.test(files[0].name) || files[0].size>5*1024*1024) {
                fieldError($file,'Only JPG, PNG or PDF up to 5MB is allowed.');
                valid=false;if(!first)first=$('#cheque-upload');
            }
        }
        if(first)first.trigger('focus');
        return valid;
    }
    $form.on('input change','input[name],select[name],textarea[name]',function(){
        if($(this).hasClass('is-invalid'))clearError($(this));
    });
    $('#cheque-attachment').on('change',function(){
        var files=$(this).prop('files');
        $('#cheque-file-name').text(files&&files.length?files[0].name:'No file selected');
    });
    function resetForm() {
        $form.trigger('reset');
        $form.find('[name=received_date]').val(@json(date('Y-m-d')));
        $('#cheque-file-name').text('No file selected');
        clearErrors();
        refreshChildren('',false);
        $('#cheque-direction').trigger('change');
    }
    function filters(page) {
        var result={page:page,per_page:$('#cheque-per-page').val()};
        $.each($('#cheque-filter-form').serializeArray(),function(_,item){if(item.value)result[item.name]=item.value;});
        var quick=$.trim($('#cheque-quick-search').val());
        if(quick)result.search=quick;
        $('.cheque-column-filters [data-column]').each(function(){
            var val=$.trim($(this).val());if(val)result[$(this).data('column')]=val;
        });
        return result;
    }
    function row(r,index) {
        var $tr=$('<tr>');
        $('<td>').text(index).appendTo($tr);
        $('<td>').text(r.received_date||'').appendTo($tr);
        $('<td>').addClass('cheque-number').text(r.cheque_number||'').appendTo($tr);
        $('<td>').text(r.bank_name||'').appendTo($tr);
        $('<td>').text(r.received_from||'').appendTo($tr);
        $('<td>').text(r.purpose||'').appendTo($tr);
        var amount=Number(r.amount);
        $('<td>').addClass('text-right').text(Number.isFinite(amount)?amount.toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2}):'0.00').appendTo($tr);
        $('<td>').append($('<span>').addClass('cheque-status-badge cheque-status-'+(r.status||'received')).text((r.status||'received').replace(/_/g,' '))).appendTo($tr);
        var $slip=$('<td>').addClass('text-nowrap');
        $('<a>').addClass('btn btn-sm btn-outline-success mr-1').attr({
            href:slipUrl(r.id)+'?size=a5&print=1',target:'_blank',rel:'noopener',title:'A5 receipt'
        }).text('A5 Print').appendTo($slip);
        $('<a>').addClass('btn btn-sm btn-outline-primary').attr({
            href:slipUrl(r.id)+'?size=pos80&print=1',target:'_blank',rel:'noopener',title:'POS 80mm receipt'
        }).text('POS 80').appendTo($slip);
        $slip.appendTo($tr);
        var $actions=$('<td>').addClass('text-nowrap');
        if(@json(auth()->user()->can('create voucher')) && (r.status==='received'||r.status==='issued')) {
            $('<button type="button">').addClass('btn btn-sm btn-outline-success mr-1 cheque-action')
                .attr('data-id',r.id).attr('data-cheque',r.cheque_number)
                .attr('data-amount',r.amount).attr('data-kind','clearing').text('Send for Clearing').appendTo($actions);
            if((r.direction||'in')==='in') {
                $('<button type="button">').addClass('btn btn-sm btn-outline-primary cheque-action')
                    .attr('data-id',r.id).attr('data-cheque',r.cheque_number)
                    .attr('data-amount',r.amount).attr('data-kind','withdrawal').text('Direct Withdrawal').appendTo($actions);
            }
        } else if(@json(auth()->user()->can('create voucher')) && r.status==='pending_clearing') {
            $.each([['cleared','Mark Cleared','success'],['returned','Returned / Bounced','warning'],['rejected','Rejected / Memo','danger']],function(_,item){
                $('<button type="button">').addClass('btn btn-sm btn-outline-'+item[2]+' mr-1 cheque-finalize')
                    .attr({'data-id':r.id,'data-cheque':r.cheque_number,'data-amount':r.amount,'data-direction':r.direction||'in','data-decision':item[0]})
                    .text(item[1]).appendTo($actions);
            });
        }
        $('<button type="button">').addClass('btn btn-sm btn-outline-secondary cheque-history')
            .attr({'data-id':r.id,'data-cheque':r.cheque_number}).text('History').appendTo($actions);
        $actions.appendTo($tr);
        return $tr;
    }
    function pagination(data) {
        var $nav=$('#cheque-pagination').empty(),page=data.current_page||1,last=data.last_page||1;
        function button(name,p,disabled,active) {
            $('<button type="button">').addClass('btn '+(active?'btn-success':'btn-outline-success'))
                .prop('disabled',!!disabled).text(name).on('click',function(){load(p);}).appendTo($nav);
        }
        button('Previous',page-1,page<=1,false);
        var first=Math.max(1,page-2),end=Math.min(last,first+4);
        for(var i=first;i<=end;i++)button(String(i),i,false,i===page);
        button('Next',page+1,page>=last,false);
        $('#cheque-count').text(data.total?'Showing '+data.from+'–'+data.to+' of '+data.total+' records':'Showing 0 records');
    }
    function load(page) {
        currentPage=page;
        var seq=++listSeq;
        if(listRequest)listRequest.abort();
        $.blueTableSkeleton.show($('#cheque-table-body'),{rows:10,columns:10,tone:'credit'});
        $('#cheque-count').text('Loading records...');
        $('#cheque-pagination').empty();
        listRequest=$.ajax({url:@json(route('finance.cheques.data')),method:'GET',data:filters(page),dataType:'json'})
        .done(function(data){
            if(seq!==listSeq)return;
            $.blueTableSkeleton.clear($('#cheque-table-body'));
            var $body=$('#cheque-table-body').empty();
            $.each(data.data||[],function(i,r){$body.append(row(r,((data.current_page-1)*data.per_page)+i+1));});
            if(!(data.data||[]).length)$body.append($('<tr>').append($('<td colspan="10" class="text-center text-muted py-4">').text('No matching cheques found.')));
            pagination(data);
        }).fail(function(xhr,status){
            if(status==='abort'||seq!==listSeq)return;
            $.blueTableSkeleton.clear($('#cheque-table-body'));
            $('#cheque-table-body').empty().append($('<tr>').append($('<td colspan="10" class="text-center text-danger py-4">').text('Unable to load cheque records.')));
            $('#cheque-count').text('Could not load records');
            toast('Cheque list could not be loaded.',true);
        });
    }
    $('#cheque-add-btn').on('click',function(){clearErrors();$create.modal('show');});
    $('#cheque-filter-form').on('submit',function(e){e.preventDefault();load(1);});
    $('#cheque-reset').on('click',function(){$('#cheque-filter-form').trigger('reset');$('#cheque-quick-search').val('');$('.cheque-column-filters [data-column]').val('');load(1);});
    $('#cheque-per-page').on('change',function(){load(1);});
    $('#cheque-quick-search,.cheque-column-filters [data-column]').on('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){load(1);},300);});
    // Reuse the same SweetAlert2 dialogs as the CR voucher. Keep the cheque form open behind them.
    $form.on('submit',function(e){
        e.preventDefault();
        if(saveBusy) return;
        if(!validate()) {
            if($.isFunction(window.Swal && window.Swal.fire)) {
                window.Swal.fire({icon:'warning',title:'Validation error',text:'Please correct the highlighted fields.'});
            }
            return;
        }
        if(!window.Swal || !$.isFunction(window.Swal.fire)) {
            toast('Confirmation dialog is unavailable. Please reload the page.',true);
            return;
        }
        window.Swal.fire({
            icon:'question',
            title:'Confirm Cheque Receiving',
            text:'Save this In/Out cheque record? No bank clearance or accounting voucher will be created.',
            showCancelButton:true,
            confirmButtonText:'Yes, Save Cheque',
            cancelButtonText:'Cancel',
            confirmButtonColor:'#168348',
            allowOutsideClick:false
        }).then(function(result){
            if(!result.isConfirmed || saveBusy) return;
            saveBusy=true;
            $('#cheque-save').prop('disabled',true);
            $.ajax({
                url:$form.attr('action'),method:'POST',data:new FormData($form[0]),
                processData:false,contentType:false,dataType:'json',headers:{Accept:'application/json'}
            }).done(function(data){
                toast(data.message||'Cheque saved successfully.',false);
                // Leave the modal visible behind the SweetAlert popup.
                window.Swal.fire({icon:'success',title:'Cheque Saved Successfully',
                    text:data.message||'Cheque received.',confirmButtonText:'OK'}).then(function(){
                    $create.modal('hide');
                    resetForm();
                });
                load(1);
            }).fail(function(xhr){
                var msg='Unable to save cheque. Please try again.';
                if(xhr.status===419) msg='Session expired. Refresh and try again.';
                else if(xhr.status===403) msg='You do not have permission to create cheques.';
                else if(xhr.responseJSON) {
                    msg=xhr.responseJSON.message||msg;
                    if(xhr.responseJSON.errors) $.each(xhr.responseJSON.errors,function(name,messages){
                        var $field=$form.find('[name="'+name+'"]');
                        if($field.length) fieldError($field,messages[0]||msg);
                        msg=messages[0]||msg;
                    });
                }
                toast(msg,true);
                window.Swal.fire({icon:'error',title:'Cheque Not Saved',text:msg,confirmButtonText:'OK'});
            }).always(function(){
                saveBusy=false;
                $('#cheque-save').prop('disabled',false);
            });
        });
    });
    // CR Voucher project mapping. Child selection resolves its Head automatically.
    var mappingOptions=@json($accountMappings);
    var $head=$('#cheque-head-account'),$child=$('#cheque-child-account');
    function refreshChildren(headId,keep) {
        var previous=keep?String($child.val()||''):'';
        $child.empty().append($('<option>').val('').text('Select Child Account'));
        $.each(mappingOptions,function(_,item){
            if(!headId||String(item.head_id)===String(headId)) {
                $('<option>').val(item.subhead_id).text(item.subhead_name+' — '+item.head_name).appendTo($child);
            }
        });
        if(previous&&$child.find('option').filter(function(){return $(this).val()===previous;}).length)$child.val(previous);
        $child.trigger('change.select2');
    }
    $head.on('change',function(){
        if($head.data('syncing'))return;
        refreshChildren($head.val(),false);
    });
    $child.on('change',function(){
        var selected=$child.val();if(!selected)return;
        var matches=$.grep(mappingOptions,function(i){return String(i.subhead_id)===String(selected);});
        if(!matches.length)return;
        var match=$.grep(matches,function(i){return String(i.head_id)===String($head.val());})[0]||matches[0];
        if(String($head.val())!==String(match.head_id)){
            $head.data('syncing',true).val(String(match.head_id)).trigger('change.select2').removeData('syncing');
            refreshChildren(match.head_id,true);
        }
    });
    $('#cheque-direction').on('change',function(){
        $form.find('[name=received_from]').closest('.form-group').find('label')
            .text($(this).val()==='out'?'Issued To *':'Received From *');
    });
    // Select2 is already supplied by the ERP layout; match CR voucher dropdown appearance.
    if($.isFunction($.fn.select2)) {
        $('#cheque-customer,#cheque-status').select2({theme:'bootstrap4',width:'100%'});
        $form.find('[name="direction"],[name="head_accounting_id"],[name="subhead_accounting_id"]').select2({theme:'bootstrap4',width:'100%',dropdownParent:$create});
    }
    // Bank-clearing / direct-withdrawal workflows. No accounting postings are made here.
    @can('create voucher')
    $('#cheque-table-body').on('click','.cheque-action',function(){
        var $button=$(this),kind=$button.attr('data-kind');
        actionTarget={id:$button.attr('data-id'),kind:kind};
        var $modal=$('#cheque-action-modal'),$form=$('#cheque-action-form');
        $form.trigger('reset');
        $('#cheque-action-errors').empty();
        $('#cheque-action-kind').val(kind);
        $('#cheque-action-title').text(kind==='clearing'?'Send Cheque for Bank Clearing':'Direct Bank Withdrawal');
        $('#cheque-action-number').text($button.attr('data-cheque'));
        $('#cheque-action-amount').val(Number($button.attr('data-amount')).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2}));
        $('#cheque-action-date').val(@json(date('Y-m-d')));
        $('#cheque-clearing-account-group').toggle(kind==='clearing');
        $('.cheque-withdrawal-only,#cheque-action-bank-name-group,#cheque-action-branch-group').toggle(kind==='withdrawal');
        $modal.modal('show');
    });
    $('#cheque-action-form').on('submit',function(e){
        e.preventDefault();
        if(actionBusy || !actionTarget)return;
        var $form=$(this),kind=actionTarget.kind;
        var bankId=$('#cheque-action-bank-account').val(),date=$('#cheque-action-date').val();
        var bankName=$.trim($('#cheque-action-bank-name').val()),collector=$.trim($('#cheque-action-collector').val());
        var errors=[];
        if(!date)errors.push('Action date is required.');
        if(kind==='clearing'&&!bankId)errors.push('Select the receiving bank account.');
        if(kind==='withdrawal'&&!bankName)errors.push('Bank name is required.');
        if(kind==='withdrawal'&&!collector)errors.push('Collector name is required.');
        if(errors.length){$('#cheque-action-errors').text(errors.join(' '));return;}
        if(!window.Swal || !$.isFunction(window.Swal.fire)){toast('Confirmation dialog unavailable.',true);return;}
        window.Swal.fire({
            icon:'warning',title:'Confirm Cheque Action',
            text:kind==='clearing'?'Submit this cheque for bank clearing? It will remain unposted until clearance is verified.':'Confirm this cheque was withdrawn directly from the bank? This records withdrawal but does not post an accounting voucher.',
            showCancelButton:true,confirmButtonText:'Yes, Confirm',cancelButtonText:'Cancel',confirmButtonColor:'#168348'
        }).then(function(result){
            if(!result.isConfirmed || actionBusy)return;
            actionBusy=true;
            $('#cheque-action-submit').prop('disabled',true);
            $.ajax({
                url:actionUrlTemplate.replace('__CHEQUE_ID__',String(actionTarget.id)),
                method:'POST',data:$form.serialize(),dataType:'json',headers:{Accept:'application/json'}
            }).done(function(data){
                $('#cheque-action-modal').modal('hide');
                toast(data.message||'Cheque status updated.',false);
                load(1);
                window.Swal.fire({icon:'success',title:'Action Recorded',text:data.message||'Cheque updated.'});
            }).fail(function(xhr){
                var message=xhr.responseJSON&&xhr.responseJSON.message?xhr.responseJSON.message:'Unable to update cheque.';
                if(xhr.responseJSON&&xhr.responseJSON.errors){
                    $.each(xhr.responseJSON.errors,function(_,messages){message=messages[0]||message;return false;});
                }
                $('#cheque-action-errors').text(message);
                toast(message,true);
                window.Swal.fire({icon:'error',title:'Action Not Saved',text:message});
            }).always(function(){actionBusy=false;$('#cheque-action-submit').prop('disabled',false);});
        });
    });
    @endcan
    // Milestone 01.4 — history, mandatory bank verification and terminal status decisions.
    $('#cheque-table-body').on('click','.cheque-history',function(){
        var id=$(this).attr('data-id'), number=$(this).attr('data-cheque');
        $('#cheque-history-heading').text('Cheque #'+number);
        var $body=$('#cheque-history-rows').empty();
        $('<tr>').append($('<td colspan="5">').text('Loading history...')).appendTo($body);
        $('#cheque-history-modal').modal('show');
        $.ajax({url:historyUrlTemplate.replace('__CHEQUE_ID__',id),dataType:'json'}).done(function(result){
            $body.empty();
            var events=result.events||[];
            if(!events.length){
                $('<tr>').append($('<td colspan="5">').text('Cheque received. No bank lifecycle actions recorded yet.')).appendTo($body);
            }
            $.each(events,function(_,e){
                var $row=$('<tr>');
                $('<td>').text((e.created_at||e.action_date)+' / '+(e.actor_name||'User #'+e.actor_id)).appendTo($row);
                $('<td>').text(e.action||'').appendTo($row);
                $('<td>').text((e.from_status||'')+' → '+(e.to_status||'')).appendTo($row);
                $('<td>').text([e.bank_name,e.bank_statement_reference].filter(Boolean).join(' / ')).appendTo($row);
                var $details=$('<td>');
                $details.text([e.reason,e.remarks,e.verification_confirmed_at?'Bank verified: '+e.verification_confirmed_at:''].filter(Boolean).join(' | '));
                if(e.has_memo&&e.memo_url) $('<a>').addClass('d-block').attr({href:e.memo_url,target:'_blank',rel:'noopener'}).text('Download Bank Memo').appendTo($details);
                $details.appendTo($row);$body.append($row);
            });
        }).fail(function(){
            $body.empty().append($('<tr>').append($('<td colspan="5" class="text-danger">').text('Could not load cheque history.')));
        });
    });
    @can('create voucher')
    $('#cheque-table-body').on('click','.cheque-finalize',function(){
        var $btn=$(this),decision=$btn.attr('data-decision');
        finalizeTarget={id:$btn.attr('data-id'),decision:decision};
        $('#cheque-finalize-form').trigger('reset');
        $('#cheque-finalize-errors').empty();
        $('#cheque-finalize-decision').val(decision);
        var direction=$btn.attr('data-direction')||'in';
        $('#cheque-finalize-verification-message').text(direction==='out'
            ?'Outgoing cheque ki bank statement debit entry verify karein. Bank se amount deduct hua ho, tab confirm karein.'
            :'Incoming cheque ki bank statement credit entry verify karein. Bank mein amount receive hua ho, tab confirm karein.');
        $('#cheque-finalize-date').val(@json(date('Y-m-d')));
        $('#cheque-finalize-number').text($btn.attr('data-cheque'));
        $('#cheque-finalize-amount').text(Number($btn.attr('data-amount')).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2}));
        $('#cheque-finalize-cleared').toggle(decision==='cleared');
        $('#cheque-finalize-rejected').toggle(decision!=='cleared');
        $('#cheque-finalize-save').text(decision==='cleared'?'Verify and Mark Cleared':(decision==='returned'?'Mark Returned / Bounced':'Mark Rejected / Memo'));
        $('#cheque-finalize-modal').modal('show');
    });
    $('#cheque-finalize-form').on('submit',function(e){
        e.preventDefault();
        if(!finalizeTarget||finalizeBusy)return;
        var decision=finalizeTarget.decision,errors=[];
        if(!$('#cheque-finalize-date').val())errors.push('Decision date is required.');
        if(decision==='cleared'){
            if(!$.trim($('#cheque-finalize-reference').val()))errors.push('Bank statement reference is required.');
            if(!$('#cheque-finalize-verified').prop('checked'))errors.push('Bank statement verification is required.');
        }else{
            if(!$.trim($('#cheque-finalize-reason').val()))errors.push('Return/rejection reason is required.');
            var files=$('#cheque-finalize-memo').prop('files');
            if(!files||!files.length)errors.push('Bank memo attachment is required.');
            else if(files[0].size>5*1024*1024||!/\.(pdf|jpe?g|png)$/i.test(files[0].name))errors.push('Bank memo must be PDF/JPG/PNG under 5 MB.');
        }
        if(errors.length){$('#cheque-finalize-errors').text(errors.join(' '));return;}
        if(!window.Swal||!$.isFunction(window.Swal.fire)){toast('Confirmation dialog unavailable.',true);return;}
        window.Swal.fire({
            icon:decision==='cleared'?'warning':'question',
            title:decision==='cleared'?'Bank Statement Verification':'Confirm Cheque Decision',
            text:decision==='cleared'
                ?$('#cheque-finalize-verification-message').text()
                :'Confirm cheque status as '+decision+'? The original cheque amount will remain unchanged.',
            showCancelButton:true,confirmButtonText:decision==='cleared'?'OK – Confirm Cheque Cleared':'Yes, Confirm',
            cancelButtonText:decision==='cleared'?'Cancel – Do Not Confirm':'Cancel',
            confirmButtonColor:'#168348',allowOutsideClick:false
        }).then(function(result){
            if(!result.isConfirmed||finalizeBusy)return;
            finalizeBusy=true;
            $('#cheque-finalize-save').prop('disabled',true);
            var formData=new FormData($('#cheque-finalize-form')[0]);
            $.ajax({
                url:finalizeUrlTemplate.replace('__CHEQUE_ID__',String(finalizeTarget.id)),
                method:'POST',data:formData,processData:false,contentType:false,
                dataType:'json',headers:{Accept:'application/json'}
            }).done(function(response){
                toast(response.message||'Cheque status updated.',false);
                $('#cheque-finalize-modal').modal('hide');
                load(1);
                window.Swal.fire({icon:'success',title:'Cheque Status Updated',text:response.message||'Saved'});
            }).fail(function(xhr){
                var msg=xhr.responseJSON&&xhr.responseJSON.message?xhr.responseJSON.message:'Unable to finalize cheque.';
                if(xhr.responseJSON&&xhr.responseJSON.errors)$.each(xhr.responseJSON.errors,function(_,messages){msg=messages[0]||msg;return false;});
                $('#cheque-finalize-errors').text(msg);
                toast(msg,true);
                window.Swal.fire({icon:'error',title:'Cheque Not Updated',text:msg});
            }).always(function(){finalizeBusy=false;$('#cheque-finalize-save').prop('disabled',false);});
        });
    });
    @endcan
    load(1);
});
</script>
@endpush
