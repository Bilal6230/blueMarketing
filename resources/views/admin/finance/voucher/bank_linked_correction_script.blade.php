<style>
.bank-approval-popup{border-radius:15px!important;text-align:left!important;max-width:calc(100vw - 24px)!important}
.bank-approval-popup .swal2-title{text-align:left;font-size:21px;margin:20px 24px 10px}
.bank-approval-popup .swal2-html-container{margin:0 24px 8px!important;overflow:visible!important;text-align:left}
.bank-approval-popup .swal2-actions{justify-content:flex-end;padding:4px 24px 17px;margin-top:14px}
.bank-approval-content{color:#334155;font-size:13px;max-height:min(68vh,650px);overflow-y:auto;padding:2px 3px 8px}
.bank-approval-content .bank-review-label{display:block;font-size:12px;font-weight:700;color:#475569;margin:0 0 6px}
.bank-approval-content .bank-review-section{border:1px solid #e2e8f0;border-radius:10px;margin:12px 0;padding:14px;background:#fff}
.bank-approval-content .bank-review-section-title{font-size:14px;font-weight:700;color:#0f172a;margin:0 0 12px}
.bank-approval-content .bank-review-intro{font-size:12px;color:#64748b;line-height:1.5;margin:8px 0}
.bank-approval-content .bank-review-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.bank-approval-content .bank-review-stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px}
.bank-approval-content .bank-review-stat span{display:block;color:#64748b;font-size:11px;font-weight:600;margin-bottom:5px}
.bank-approval-content .bank-review-stat strong{font-size:14px;color:#0f172a;overflow-wrap:anywhere}
.bank-approval-content .bank-review-badge{display:inline-block;font-size:11px;font-weight:700;border-radius:99px;padding:5px 10px;background:#fff4d6;color:#92400e}
.bank-approval-content .bank-review-badge-danger{background:#fee2e2;color:#991b1b}
.bank-approval-content .bank-review-badge-info{background:#dbeafe;color:#1d4ed8}
.bank-approval-content .bank-review-table-wrap{max-width:100%;overflow-x:auto;border:1px solid #e2e8f0;border-radius:8px}
.bank-approval-content .bank-review-table{width:100%;border-collapse:collapse;margin:0;font-size:12px;min-width:540px}
.bank-approval-content .bank-review-table th{background:#f1f5f9;color:#334155;font-weight:700;padding:10px;text-align:left;border-bottom:1px solid #cbd5e1}
.bank-approval-content .bank-review-table td{padding:10px;vertical-align:top;border-bottom:1px solid #e2e8f0;overflow-wrap:anywhere}
.bank-approval-content .bank-review-table tbody tr:last-child td{border-bottom:0}
.bank-approval-content .bank-review-table .bank-review-updated{color:#047857;font-weight:700}
.bank-approval-content .bank-review-table .bank-review-previous{color:#64748b}
.bank-approval-content .bank-review-outcome{background:#eff6ff;border-left:3px solid #2563eb;border-radius:6px;padding:12px;line-height:1.7}
.bank-approval-content .bank-review-outcome strong{display:block;color:#1d4ed8;margin-bottom:3px}
.bank-approval-content .bank-review-reason{background:#f8fafc;border-radius:7px;padding:10px;white-space:pre-wrap;overflow-wrap:anywhere}
.bank-approval-content .bank-review-decision{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.bank-approval-content .bank-review-decision textarea{grid-column:1/-1;resize:vertical}
.bank-approval-content .bank-review-empty{color:#64748b;font-size:12px;margin:0}
@media(max-width:600px){.bank-approval-content .bank-review-summary{grid-template-columns:1fr 1fr}.bank-approval-content .bank-review-decision{grid-template-columns:1fr}.bank-approval-popup .swal2-html-container{margin:0 12px 8px!important}}
</style>
<script>
jQuery(function($) {
    'use strict';
    var current=null, busy=false;
    var previewUrl=@json(route('finance.bank-management.correction.preview',['method'=>'__METHOD__','id'=>'__SOURCE_ID__']));
    var confirmUrl=@json(route('finance.bank-management.correction.confirm',['method'=>'__METHOD__','id'=>'__SOURCE_ID__']));
    var canDecide=@json(\App\Services\Finance\VoucherApproverAccess::mayApprove(auth()->user()) &&
        (auth()->user()->can('finance online correction approve') || auth()->user()->can('finance cheque correction approve')));
    var pendingUrl=@json(route('finance.bank-management.correction.pending'));
    var decideUrl=@json(route('finance.bank-management.correction.decide',['requestId'=>'__REQUEST_ID__']));
    if(canDecide){
        var $toolbar=$('#cheque-add-btn').length?$('#cheque-add-btn'):$('#online-add');
        if(!$toolbar.length)$toolbar=$('.cheque-heading h4,.online-heading h4,.vap-heading h4').first();
        $('<button type="button" id="bank-correction-approval-open" class="btn btn-outline-warning btn-sm ml-2">')
            .text('Pending Bank Corrections').insertAfter($toolbar);
    }
    var bankFieldLabels={
        cheque_number:'Cheque Number',bank_name:'Bank Name',direction:'Cheque Direction',
        cheque_title:'Cheque Title',cheque_date:'Cheque Date',received_date:'Receiving Date',
        received_from:'Received From / Issued To',branch:'Branch',purpose:'Purpose / Description',
        reference:'Transaction ID / Reference',type:'Transaction Type',
        transaction_date:'Transaction Date',sending_bank:'Sending Bank',
        sending_account:'Sending Account',receiving_bank:'Receiving Bank',
        receiving_account:'Receiving Account',sender_name:'Sender Name',
        receiver_name:'Receiver Name',amount:'Amount (Rs.)',account_mapping_id:'Head / Child Account Mapping'
    };
    function bankFieldValue(field,value){
        if(value===null||value===undefined||String(value).trim()==='')return '—';
        if(field==='amount'){
            var amount=Number(String(value).replace(/,/g,''));
            return Number.isFinite(amount)?'Rs. '+amount.toLocaleString('en-PK',{
                minimumFractionDigits:2,maximumFractionDigits:2}):String(value);
        }
        if(field==='account_mapping_id')return 'Mapping #'+String(value);
        if(field==='direction')return String(value).toLowerCase()==='in'?'IN — Received':
            (String(value).toLowerCase()==='out'?'OUT — Issued':String(value));
        return String(value);
    }
    function bankValuesDiffer(field,before,after){
        if(field==='amount'){
            var oldAmount=Number(String(before??'').replace(/,/g,''));
            var newAmount=Number(String(after??'').replace(/,/g,''));
            if(Number.isFinite(oldAmount)&&Number.isFinite(newAmount))
                return Math.round(oldAmount*100)!==Math.round(newAmount*100);
        }
        return String(before??'')!==String(after??'');
    }
    function bankApprovalComparison(review,$target){
        var before=review.before||{},after=review.after||{},count=0;
        var $table=$('<table class="bank-review-table">'),
            $head=$('<thead>').append($('<tr>')
                .append($('<th scope="col">').text('Changed Field'))
                .append($('<th scope="col">').text('Before'))
                .append($('<th scope="col">').text('After')));
        var $body=$('<tbody>');
        $.each(after,function(field,newValue){
            var oldValue=before[field];
            if(!bankValuesDiffer(field,oldValue,newValue))return;
            count++;
            $body.append($('<tr>')
                .append($('<td>').text(bankFieldLabels[field]||field.replace(/_/g,' ')))
                .append($('<td class="bank-review-previous">').text(bankFieldValue(field,oldValue)))
                .append($('<td class="bank-review-updated">').text(bankFieldValue(field,newValue))));
        });
        if(count){
            $target.append($('<div class="bank-review-table-wrap">').append(
                $table.append($head,$body)));
        }else{
            $target.append($('<p class="bank-review-empty">').text(
                'No field values changed. This request reissues existing linked vouchers.'));
        }
    }
    function bankReviewSection(title){
        return $('<section class="bank-review-section">')
            .append($('<h4 class="bank-review-section-title">').text(title));
    }
    function bankApprovalImpact(review,$section){
        var list=Array.isArray(review.vouchers)?review.vouchers:[],
            history=Number(review.linked_voucher_history_count||list.length),
            archived=Number(review.historical_released_count||0);
        if(!list.length){
            $section.append($('<p class="bank-review-empty">').text(
                history?'All '+history+' historical voucher allocation(s) are already released.':
                    'No vouchers have ever been created from this source.'));
        }else{
            var $table=$('<table class="bank-review-table">'),
                $body=$('<tbody>');
            $table.append($('<thead>').append($('<tr>')
                .append($('<th>').text('Voucher'))
                .append($('<th>').text('Amount'))
                .append($('<th>').text('Current Status'))
                .append($('<th>').text('On Approval'))));
            $.each(list,function(_,voucher){
                var status=String(voucher.approval_status||'posted').toLowerCase();
                var outcome=status==='pending'?'Cancel Pending Approval':
                    status==='rejected'?'Release Rejected Voucher':'Soft-void & Release';
                $body.append($('<tr>')
                    .append($('<td>').text(String(voucher.voucher_type||'Voucher')+
                        ' · ID '+String(voucher.voucher_id||'—')))
                    .append($('<td>').text(bankFieldValue('amount',voucher.amount)))
                    .append($('<td>').text(status.charAt(0).toUpperCase()+status.slice(1)))
                    .append($('<td>').append($('<strong>').text(outcome))));
            });
            $section.append($('<div class="bank-review-table-wrap">').append($table.append($body)));
        }
        if(archived)$section.append($('<p class="bank-review-intro">').text(
            archived+' earlier released allocation(s) remain in audit history.'));
    }
    $(document).on('click','#bank-correction-approval-open',function(){
        if(busy||!window.Swal)return;
        busy=true;
        $.getJSON(pendingUrl).done(function(resp){
            var rows=resp.data||[];
            if(!rows.length){
                window.Swal.fire({icon:'info',title:'Bank Corrections',
                    text:'No pending correction assigned to you.'});return;
            }
            var $layout=$('<div class="bank-approval-content">');
            $layout.append($('<label class="bank-review-label" for="bank-correction-request">')
                .text('Select correction request'));
            var $select=$('<select id="bank-correction-request" class="form-control mb-2">');
            $.each(rows,function(_,r){
                $select.append($('<option>').val(r.id).text(
                    (r.source_method==='online'?'Online':'Cheque')+' #'+r.source_id+
                    ' · '+bankFieldValue('amount',r.proposed_amount)+' · Request #'+r.id));
            });
            $layout.append($select);
            var $review=$('<div class="bank-approval-review" aria-live="polite">');
            $layout.append($review);
            var $decisionSection=bankReviewSection('Approval Decision');
            $decisionSection.append($('<label class="bank-review-label" for="bank-correction-decision">')
                .text('Decision'));
            var $decision=$('<select id="bank-correction-decision" class="form-control mb-2">')
                .append($('<option value="approved">').text('Approve & Apply Correction'))
                .append($('<option value="rejected">').text('Reject Correction'));
            var $reasonWrap=$('<div>').append($('<label class="bank-review-label" for="bank-correction-reason">')
                .text('Rejection Reason (required)'));
            var $reason=$('<textarea id="bank-correction-reason" class="form-control" rows="2" maxlength="500" placeholder="Explain why this correction is rejected">');
            $reasonWrap.append($reason);
            $decisionSection.append($decision,$reasonWrap);
            $layout.append($decisionSection);
            function syncDecision(){
                var rejecting=$decision.val()==='rejected';
                $reasonWrap.toggle(rejecting);
                $reason.prop('required',rejecting);
                var $approve=$('.bank-approval-popup .swal2-confirm');
                if($approve.length)$approve.text(rejecting?'Reject Request':'Approve & Apply');
            }
            function draw(){
                var row=rows.filter(function(x){return String(x.id)===String($select.val());})[0];
                $review.empty();
                if(!row)return;
                var proposed={};
                try{proposed=JSON.parse(row.proposed_vouchers_json||'{}')||{};}catch(e){}
                var view=row.review||{};
                var review={
                    before:view.before||proposed.before||{},
                    after:view.after||proposed.after||proposed.changes||{},
                    vouchers:view.vouchers||proposed.released_vouchers||[],
                    requires_replacement:view.requires_replacement!==undefined
                        ?!!view.requires_replacement:!!proposed.requires_replacement,
                    original_status:view.original_status||proposed.original_status||'—',
                    linked_voucher_history_count:view.linked_voucher_history_count||
                        proposed.linked_voucher_history_count||0,
                    historical_released_count:view.historical_released_count||
                        proposed.historical_released_count||0
                };
                var linked=review.requires_replacement;
                var $intro=$('<div class="d-flex align-items-center flex-wrap mb-2">')
                    .css({gap:'8px'})
                    .append($('<span class="bank-review-badge bank-review-badge-info">').text(
                        row.source_method==='online'?'Online Transaction':'Cheque'))
                    .append($('<span class="bank-review-badge">').text('Awaiting Approval'))
                    .append($('<span class="bank-review-badge '+(linked?'bank-review-badge-danger':'bank-review-badge-info')+'">')
                        .text(linked?'Withdraw & Replace':'Same-ID Update'));
                $review.append($intro);
                var $summary=bankReviewSection('Correction Summary');
                var $stats=$('<div class="bank-review-summary">');
                $.each([
                    ['Source ID','#'+row.source_id],
                    ['Current Status',review.original_status],
                    ['Proposed Amount',bankFieldValue('amount',row.proposed_amount)],
                    ['Request Number','#'+row.id],
                    ['Submitted By','User #'+row.submitted_by],
                    ['Voucher History',String(review.linked_voucher_history_count)+' linked']
                ],function(_,entry){
                    $stats.append($('<div class="bank-review-stat">')
                        .append($('<span>').text(entry[0]))
                        .append($('<strong>').text(entry[1])));
                });
                $summary.append($stats);
                if(row.reason)$summary.append($('<p class="bank-review-intro mb-1 mt-3">')
                    .text('Reason submitted'));
                if(row.reason)$summary.append($('<div class="bank-review-reason">').text(row.reason));
                $review.append($summary);
                var $changes=bankReviewSection('What Will Change?');
                bankApprovalComparison(review,$changes);
                $review.append($changes);
                var $impact=bankReviewSection('Linked Voucher Impact');
                bankApprovalImpact(review,$impact);
                $review.append($impact);
                var $outcome=bankReviewSection('Outcome After Approval');
                var $info=$('<div class="bank-review-outcome">');
                if(linked){
                    $info.append($('<strong>').text('Old source Withdrawn → New source Pending Verification'));
                    $info.append($('<div>').text(
                        'Old source #'+row.source_id+' will become Withdrawn. A NEW '+
                        (row.source_method==='cheque'?'Received / Issued Cheque':'Pending Online Transaction')+
                        ' record will be created. Pending vouchers will be cancelled; posted vouchers will be soft-voided and released. The replacement must be verified again before fresh vouchers.'));
                }else{
                    $info.append($('<strong>').text('Original Source ID #'+row.source_id+' Preserved'));
                    $info.append($('<div>').text(
                        'Changes will be applied to the original source. No replacement is created and no vouchers are cancelled. If bank identity changes, normal bank re-verification is required.'));
                }
                $outcome.append($info);
                $review.append($outcome);
            }
            $select.on('change',draw);
            $decision.on('change',syncDecision);
            draw();syncDecision();
            window.Swal.fire({
                title:'Review Bank Correction',
                html:$layout[0],width:940,
                customClass:{popup:'bank-approval-popup'},
                showCancelButton:true,confirmButtonText:'Approve & Apply',
                confirmButtonColor:'#168348',cancelButtonText:'Cancel',
                focusConfirm:false,allowOutsideClick:false,
                didOpen:syncDecision,
                preConfirm:function(){
                    var decision=$decision.val(),reason=$.trim($reason.val());
                    if(decision==='rejected'&&reason.length<3){
                        window.Swal.showValidationMessage('Enter a rejection reason of at least 3 characters.');
                        return false;
                    }
                    return {id:$select.val(),decision:decision,reason:reason};
                }
            }).then(function(result){
                if(!result.isConfirmed)return;
                var data=result.value;
                $.ajax({
                    url:decideUrl.replace('__REQUEST_ID__',data.id),method:'POST',
                    data:{_token:$('meta[name="csrf-token"]').attr('content'),
                        decision:data.decision,reason:data.reason},
                    dataType:'json',headers:{Accept:'application/json'}
                }).done(function(resp){
                    var applied=resp.data&&resp.data.result||{};
                    var selected=rows.filter(function(row){return String(row.id)===String(data.id);})[0];
                    if(applied.replacement_source_id&&selected){
                        resetReplacementRegisterFilters(selected.source_method);
                    }
                    window.Swal.fire({icon:'success',title:'Decision Saved',text:
                        'Correction '+resp.data.status+'.'+(applied.replacement_source_id
                            ? ' New source #'+applied.replacement_source_id+
                              ' requires bank verification before fresh vouchers.' : '')});
                    $('#cheque-filter-form,#online-filters').filter(':first').trigger('submit');
                }).fail(function(xhr){
                    window.Swal.fire({icon:'error',title:'Decision Refused',text:error(xhr)});
                });
            });
        }).fail(function(xhr){
            window.Swal.fire({icon:'error',title:'Cannot Load Approvals',text:error(xhr)});
        }).always(function(){busy=false;});
    });
    function resetReplacementRegisterFilters(method){
        var $status=method==='cheque'?$('#cheque-status'):$('#online-status-filter');
        if($status.length){
            var select=$status[0].tomselect;
            if(select)select.clear(true);
            else $status.val('');
        }
        if(method==='cheque'){
            $('#cheque-link-filter').val('');
            $('#cheque-search').val('');
        }else{
            $('#online-link-filter').val('');
            $('#online-search').val('');
        }
    }
    function endpoint(template, cfg) {
        return template.replace('__METHOD__',cfg.method).replace('__SOURCE_ID__',String(cfg.id));
    }
    function error(xhr) {
        var data=xhr.responseJSON||{}, message=data.message||'Correction failed. No changes saved.';
        if(data.errors) $.each(data.errors,function(_,messages){message=messages[0]||message;return false;});
        return message;
    }
    // The edit form remains the single source of field validation. Only an
    // explicitly selected Correct action invokes this handler; Replace is separate.
    $(document).on('bank-source-reissue-submit',function(_,cfg){
        if(busy||!window.Swal)return;
        busy=true;
        var changes=cfg.changes;
        $.ajax({
            url:endpoint(previewUrl,cfg),method:'POST',dataType:'json',
            data:{_token:$('meta[name="csrf-token"]').attr('content'),changes:changes},
            headers:{Accept:'application/json'}
        }).done(function(resp){
            var plan=resp.data,$details=$('<div class="text-left">');
            if(plan.requires_replacement){
                $details.append($('<p class="text-danger font-weight-bold">').text(
                    'Linked voucher history found ('+plan.linked_voucher_history_count+'). Old source #'+plan.source_id+
                    ' will become WITHDRAWN; a NEW source record will be created with status '+plan.new_status+'.'));
                $details.append($('<p class="text-warning">').text(
                    'New cheque/bank or online reference must be UNIQUE. New source must pass bank verification again before any new voucher.'));
            }else{
                $details.append($('<p class="text-success font-weight-bold">').text(
                    'No vouchers were created. Source #'+plan.source_id+' will be updated on its ORIGINAL ID; no replacement record.'));
            }
            var $table=$('<table class="table table-sm">'),$body=$('<tbody>');
            $.each(plan.after,function(field,value){
                var old=String(plan.before[field]??''),next=String(value??'');
                if(field==='amount' ? Number(old)!==Number(next) : old!==next) {
                    $body.append($('<tr>')
                        .append($('<th>').text(field.replace(/_/g,' ')))
                        .append($('<td>').text(old+' → '+next)));
                }
            });
            $details.append($table.append($body));
            if(plan.active_vouchers.length){
                $details.append($('<p class="font-weight-bold text-danger">').text(
                    plan.active_vouchers.length+' active linked voucher(s) will be soft-voided. Their accounting balances will be released and fresh voucher numbers will be issued on recreation.'));
                $.each(plan.active_vouchers,function(_,item){
                    $details.append($('<div class="small">').text(item.voucher_type+
                        ' / Voucher ID '+item.voucher_id+' / Rs. '+item.amount));
                });
            }else{
                $details.append($('<p class="text-success">').text(
                    plan.requires_replacement
                        ? 'All historical vouchers are already released; the original source will still be withdrawn and replaced.'
                        : 'No voucher history: this source stays on the same original ID.'));
            }
            if(plan.requires_replacement){
                $details.append($('<p class="small text-warning">').text(
                    'Approval ON: no changes until approval. Then old vouchers are archived and the new source requires bank re-verification.'));
            }else{
                $details.append($('<p class="small text-muted">').text(
                    'This update preserves the original source ID. If verified identity changes, its status will require bank verification.'));
            }
            if(plan.historical_released_count){
                $details.append($('<p class="small text-muted">').text(
                    plan.historical_released_count+' previously released voucher(s) remain archived.'));
            }
            $details.append($('<p class="small text-muted">').text(
                'Audit history and old voucher numbers remain unchanged. Approval ON stages this operation for the authorized approver.'));
            window.Swal.fire({
                title:plan.requires_replacement?'Review Withdrawal & Replacement':'Review Same-ID Update',html:$details[0],width:780,
                icon:'warning',showCancelButton:true,confirmButtonText:plan.requires_replacement?'Submit Replacement':'Submit Update',
                confirmButtonColor:'#168348',allowOutsideClick:false
            }).then(function(answer){
                if(!answer.isConfirmed)return;
                busy=true;
                $.ajax({
                    url:endpoint(confirmUrl,cfg),method:'POST',dataType:'json',
                    data:{_token:$('meta[name="csrf-token"]').attr('content'),
                        changes:changes,reason:cfg.reason,fingerprint:plan.fingerprint},
                    headers:{Accept:'application/json'}
                }).done(function(result){
                    if(cfg.modalSelector)$(cfg.modalSelector).modal('hide');
                    var applied=result.data&&result.data.result||{};
                    if(applied.replacement_source_id){
                        resetReplacementRegisterFilters(cfg.method);
                    }
                    $(cfg.reloadSelector).trigger('submit');
                    window.Swal.fire({icon:'success',
                        title:result.data&&result.data.pending?'Sent For Approval':
                            (applied.replacement_source_id?'Replacement Created':'Original Updated'),
                        text:result.message+(applied.replacement_source_id
                            ? ' New source ID #'+applied.replacement_source_id+
                              '. Complete bank clearing/confirmation before creating vouchers.'
                            : '')});
                }).fail(function(xhr){
                    window.Swal.fire({icon:'error',title:'Correction Refused',text:error(xhr)});
                }).always(function(){busy=false;});
            });
        }).fail(function(xhr){
            window.Swal.fire({icon:'error',title:'Correction Preview Refused',text:error(xhr)});
        }).always(function(){busy=false;});
    });
});
</script>
