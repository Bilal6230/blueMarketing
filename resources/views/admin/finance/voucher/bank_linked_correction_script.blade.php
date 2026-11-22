<script>
jQuery(function($) {
    'use strict';
    var current=null, busy=false;
    var previewUrl=@json(route('finance.bank-management.correction.preview',['method'=>'__METHOD__','id'=>'__SOURCE_ID__']));
    var confirmUrl=@json(route('finance.bank-management.correction.confirm',['method'=>'__METHOD__','id'=>'__SOURCE_ID__']));
    function endpoint(template, cfg) {
        return template.replace('__METHOD__',cfg.method).replace('__SOURCE_ID__',String(cfg.id));
    }
    function error(xhr) {
        var data=xhr.responseJSON||{}, message=data.message||'Correction failed. No changes saved.';
        if(data.errors) $.each(data.errors,function(_,messages){message=messages[0]||message;return false;});
        return message;
    }
    $(document).on('bank-linked-correction-ready',function(_,cfg) {
        current=cfg;
        var $save=$(cfg.saveSelector),$existing=$('#bank-linked-correct-button');
        $existing.remove();
        if(!cfg.verified) return;
        $('<button type="button" id="bank-linked-correct-button" class="btn btn-outline-primary mr-2">')
            .text('Correct Transaction / Amounts').insertBefore($save);
    });
    $(document).on('click','#bank-linked-correct-button',function(){
        if(busy||!current||!window.Swal) return;
        var cfg=current, allocations=(cfg.impact.allocations||[]).filter(function(a){return !a.released;});
         // Avoid Bootstrap focus enforcement stealing typing from SweetAlert.
        var $modal=$(cfg.modalSelector);
        $modal.one('hidden.bs.modal',function(){openEditor(cfg,allocations);});
        $modal.modal('hide');
    });
    function openEditor(cfg,allocations) {
        var $layout=$('<div class="text-left">');
        $layout.append($('<label class="d-block">').text('Corrected source amount (PKR)'));
        var $source=$('<input type="number" min="0.01" step="0.01" class="form-control mb-3 bank-correction-source">')
            .val(cfg.record.amount);
        $layout.append($source);
        $layout.append($('<p class="small text-muted">').text(allocations.length
            ? 'Review each active linked voucher. Unchanged amounts stay the same; remaining unallocated balance is allowed.'
            : 'No active linked vouchers. You can safely correct the source amount while preserving its bank verification evidence.'));
        $.each(allocations,function(_,a) {
            var $wrap=$('<div class="mb-2">').append($('<label class="small d-block">')
                .text(a.voucher_type+' / Voucher ID '+a.voucher_id+' (current '+a.amount+')'));
            $wrap.append($('<input type="number" class="form-control bank-correction-voucher" min="0.01" step="0.01">')
                .attr('data-voucher-id',String(a.voucher_id)).val(a.amount));
            $layout.append($wrap);
        });
        $layout.append($('<label class="d-block mt-3">').text('Correction reason (required)'));
        $layout.append($('<textarea class="form-control bank-correction-reason" rows="2" maxlength="500">'));
        window.Swal.fire({
            title:'Review Linked Amount Correction',html:$layout[0],width:700,
            showCancelButton:true,confirmButtonText:'Preview Changes',
            focusConfirm:false,preConfirm:function(){
                var reason=$.trim($('.bank-correction-reason').val()),amount=$.trim($('.bank-correction-source').val());
                var vouchers={},bad=false;
                $('.bank-correction-voucher').each(function(){
                    var id=$(this).attr('data-voucher-id'),val=$.trim($(this).val());
                    if(!val||Number(val)<=0)bad=true;
                    vouchers[id]=val;
                });
                if(!amount||Number(amount)<=0||bad||reason.length<3||reason.length>500){
                    window.Swal.showValidationMessage('Enter positive amounts and a reason of 3–500 characters.');
                    return false;
                }
                return {amount:amount,vouchers:vouchers,reason:reason};
            }
        }).then(function(result){if(result.isConfirmed)review(cfg,result.value);});
    }
    function review(cfg,values) {
        busy=true;
        $.ajax({url:endpoint(previewUrl,cfg),method:'POST',data:$.extend({_token:$('meta[name="csrf-token"]').attr('content')},values),
            dataType:'json',headers:{Accept:'application/json'}})
        .done(function(response) {
            var plan=response.data,$details=$('<div class="text-left">');
            $details.append($('<p>').text('Source: Rs. '+plan.amount_before+' → Rs. '+plan.amount_after));
            $details.append($('<p>').text('Allocated: Rs. '+plan.allocated_before+' → Rs. '+plan.allocated_after));
            $details.append($('<p>').text('Remaining: Rs. '+plan.unallocated_before+' → Rs. '+plan.unallocated_after));
            if(plan.historical_released_count){$details.append($('<p class="text-muted small">').text('Historical released vouchers (unchanged): '+plan.historical_released_count));}
            var $table=$('<table class="table table-sm">'),$body=$('<tbody>');
            $.each(plan.vouchers,function(_,v){
                $body.append($('<tr>')
                    .append($('<td>').text(v.voucher_number))
                    .append($('<td>').text(v.amount_before+' → '+v.amount_after)));
            });
            $table.append($body);$details.append($table);
            $details.append($('<p class="small text-muted">').text('Same source/voucher/ledger IDs will be retained. All changes will save together or none will save.'));
            window.Swal.fire({icon:'question',title:'Confirm Financial Correction',
                html:$details[0],width:700,showCancelButton:true,
                confirmButtonText:'Submit / Apply Correction',confirmButtonColor:'#168348'
            }).then(function(result){
                if(!result.isConfirmed)return;
                busy=true;
                $.ajax({url:endpoint(confirmUrl,cfg),method:'POST',
                    data:$.extend({_token:$('meta[name="csrf-token"]').attr('content'),
                        fingerprint:plan.fingerprint},values),
                    dataType:'json',headers:{Accept:'application/json'}})
                .done(function(data){
                    window.Swal.fire({icon:'success',title:data.data&&data.data.pending?'Sent For Approval':'Correction Applied',text:data.message});
                    // Trigger original register reload through its existing filters, not full navigation.
                    $(cfg.reloadSelector).trigger('submit');
                }).fail(function(xhr){window.Swal.fire({icon:'error',title:'Correction Refused',text:error(xhr)});})
                .always(function(){busy=false;});
            });
        })
        .fail(function(xhr){window.Swal.fire({icon:'error',title:'Preview Refused',text:error(xhr)});})
        .always(function(){busy=false;});
    }
});
</script>
