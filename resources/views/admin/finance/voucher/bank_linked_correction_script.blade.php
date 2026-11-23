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
    $(document).on('click','#bank-correction-approval-open',function(){
        if(busy||!window.Swal)return;
        $.getJSON(pendingUrl).done(function(resp){
            var rows=resp.data||[], $layout=$('<div class="text-left">');
            if(!rows.length){window.Swal.fire({icon:'info',title:'Bank Corrections',text:'No pending correction assigned to you.'});return;}
            $layout.append($('<label class="d-block">').text('Correction awaiting approval'));
            var $select=$('<select class="form-control bank-decision-request mb-3">');
            $.each(rows,function(_,r){
                $select.append($('<option>').val(r.id).text(
                    (r.source_method==='online'?'Online':'Cheque')+' #'+r.source_id+
                    ' · Rs. '+r.proposed_amount+' · Request '+r.id));
            });
            $layout.append($select);
            $layout.append($('<p class="small text-muted">').text('Review the new source fields and vouchers to archive. Approval rechecks each linked ledger and source before applying.'));
            var $details=$('<pre class="small bank-decision-preview p-2">').css({maxHeight:'190px',overflow:'auto'});
            $layout.append($details);
            function draw(){
                var row=rows.filter(function(x){return String(x.id)===String($select.val());})[0];
                if(!row)return;
                var proposed=JSON.parse(row.proposed_vouchers_json||'{}');
                var impacted=(proposed.released_vouchers||[]);
                $details.text('Corrected source amount: '+row.proposed_amount+'\n'+
                    'Fields to change: '+JSON.stringify(proposed.changes||{},null,2)+'\n'+
                    'Vouchers to soft-void on approval: '+impacted.length+'\n'+
                    impacted.map(function(v){return v.voucher_type+' / '+v.voucher_id+' / Rs. '+v.amount;}).join('\n')+
                    '\nReason: '+row.reason);
            }
            $select.on('change',draw);draw();
            $layout.append($('<label class="d-block mt-2">').text('Decision'));
            var $decision=$('<select class="form-control bank-decision-choice mb-2">')
                .append($('<option value="approved">').text('Approve correction'))
                .append($('<option value="rejected">').text('Reject correction'));
            $layout.append($decision);
            $layout.append($('<textarea class="form-control bank-decision-reason" rows="2" maxlength="500" placeholder="Reason (mandatory for reject)">'));
            window.Swal.fire({title:'Review Bank Correction',html:$layout[0],width:690,
                showCancelButton:true,confirmButtonText:'Submit Decision',focusConfirm:false,
                preConfirm:function(){
                    var d=$decision.val(), reason=$.trim($('.bank-decision-reason').val());
                    if(d==='rejected' && reason.length<3){
                        window.Swal.showValidationMessage('Enter a rejection reason of at least 3 characters.');return false;
                    }
                    return {id:$select.val(),decision:d,reason:reason};
                }
            }).then(function(result){
                if(!result.isConfirmed)return;
                var data=result.value;
                $.ajax({url:decideUrl.replace('__REQUEST_ID__',data.id),method:'POST',
                    data:{_token:$('meta[name="csrf-token"]').attr('content'),decision:data.decision,reason:data.reason},
                    dataType:'json',headers:{Accept:'application/json'}})
                .done(function(resp){
                    window.Swal.fire({icon:'success',title:'Decision Saved',text:'Correction '+resp.data.status+'.'});
                    $('#cheque-filter-form,#online-filters').filter(':first').trigger('submit');
                }).fail(function(xhr){window.Swal.fire({icon:'error',title:'Decision Refused',text:error(xhr)});});
            });
        }).fail(function(xhr){window.Swal.fire({icon:'error',title:'Cannot Load Approvals',text:error(xhr)});});
    });
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
                    $(cfg.reloadSelector).trigger('submit');
                    window.Swal.fire({icon:'success',
                        title:result.data&&result.data.pending?'Sent For Approval':'Correction Applied',
                        text:result.message});
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
