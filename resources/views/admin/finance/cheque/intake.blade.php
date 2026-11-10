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
    #cheque-intake-form textarea[name=remarks] { height:61px; min-height:61px; resize:vertical; }
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
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-customer">Customer</label><select name="customer_id" id="cheque-customer" class="form-control form-control-sm"><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->first_name }} {{ $customer->last_name }} (#{{ $customer->id }})</option>@endforeach</select></div>
                    <div class="col-lg-2 col-md-4 mb-2"><label class="cheque-filter-label" for="cheque-status">Status</label><select name="status" id="cheque-status" class="form-control form-control-sm"><option value="">All statuses</option><option value="received">Received</option><option value="pending_clearing">Pending Clearing</option><option value="cleared">Cleared</option><option value="returned">Returned</option><option value="rejected">Rejected</option><option value="withdrawn">Withdrawn</option></select></div>
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
        <form id="cheque-intake-form" action="{{ route('finance.cheques.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="modal-body">
                <div class="row">
                    <div class="form-group col-md-4"><label>Cheque Number *</label><input class="form-control form-control-sm" name="cheque_number" maxlength="100" required></div>
                    <div class="form-group col-md-4"><label>Cheque Date *</label><input class="form-control form-control-sm" type="date" name="cheque_date" required></div>
                    <div class="form-group col-md-4"><label>Receiving Date *</label><input class="form-control form-control-sm" type="date" name="received_date" value="{{ date('Y-m-d') }}" required></div>
                    <div class="form-group col-md-4"><label>Amount (Rs.) *</label><input class="form-control form-control-sm" name="amount" type="number" min="0.01" max="9999999999999.99" step="0.01" inputmode="decimal" required placeholder="0.00"></div>
                    <div class="form-group col-md-4"><label>Bank Name *</label><input class="form-control form-control-sm" name="bank_name" maxlength="150" required></div>
                    <div class="form-group col-md-4"><label>Branch</label><input class="form-control form-control-sm" name="branch" maxlength="150"></div>
                    <div class="form-group col-md-4"><label>Received From *</label><input class="form-control form-control-sm" name="received_from" maxlength="150" required></div>
                    <div class="form-group col-md-8"><label>Related Customer *</label><select class="form-control form-control-sm" name="customer_id" required><option value="">Select Customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->first_name }} {{ $customer->last_name }} (#{{ $customer->id }})</option>@endforeach</select></div>
                    <div class="form-group col-12"><label>Purpose / Description *</label><textarea class="form-control form-control-sm" name="purpose" rows="2" maxlength="500" required></textarea></div>
                    <div class="form-group col-md-8"><label>Remarks</label><textarea class="form-control form-control-sm" name="remarks" rows="2" maxlength="2000"></textarea></div>
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

<div id="cheque-toast" class="alert alert-success shadow" role="status" aria-live="polite"></div>
@endsection
@push('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    'use strict';
    var currentPage=1, listRequest=null, saveBusy=false, searchTimer=null, listSeq=0;
    var $form=$('#cheque-intake-form'), $create=$('#cheque-create-modal');
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
        $('<td>').append($('<span>').addClass('badge badge-secondary').text((r.status||'received').replace(/_/g,' '))).appendTo($tr);
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
        $.blueTableSkeleton.show($('#cheque-table-body'),{rows:10,columns:8,tone:'credit'});
        $('#cheque-count').text('Loading records...');
        $('#cheque-pagination').empty();
        listRequest=$.ajax({url:@json(route('finance.cheques.data')),method:'GET',data:filters(page),dataType:'json'})
        .done(function(data){
            if(seq!==listSeq)return;
            $.blueTableSkeleton.clear($('#cheque-table-body'));
            var $body=$('#cheque-table-body').empty();
            $.each(data.data||[],function(i,r){$body.append(row(r,((data.current_page-1)*data.per_page)+i+1));});
            if(!(data.data||[]).length)$body.append($('<tr>').append($('<td colspan="8" class="text-center text-muted py-4">').text('No matching cheques found.')));
            pagination(data);
        }).fail(function(xhr,status){
            if(status==='abort'||seq!==listSeq)return;
            $.blueTableSkeleton.clear($('#cheque-table-body'));
            $('#cheque-table-body').empty().append($('<tr>').append($('<td colspan="8" class="text-center text-danger py-4">').text('Unable to load cheque records.')));
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
            text:'Save this cheque as Received? No bank clearance or accounting voucher will be created.',
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
    // Select2 is already supplied by the ERP layout; match CR voucher dropdown appearance.
    if($.isFunction($.fn.select2)) {
        $('#cheque-customer,#cheque-status').select2({theme:'bootstrap4',width:'100%'});
        $form.find('[name="customer_id"]').select2({theme:'bootstrap4',width:'100%',dropdownParent:$create});
    }
    load(1);
});
</script>
@endpush
