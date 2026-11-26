/* S4: modern Settings prefix editor, with legacy numbering still fail-closed. */
(function($){
'use strict';
$(function(){
 var $tab=$('#voucher-settings'),$modal=$('#s4-display-modal'),$form=$('#s4-display-form');
 if(!$tab.length||!$modal.length||!$form.length)return;
 $modal.appendTo('body');
 var $series=$form.find('[name=series]'),$prefix=$form.find('[name=prefix]'),busy=false;
 function notify(message,failed){
  if(window.toastr){failed?toastr.error(message):toastr.success(message);}
  else if(window.Swal)Swal.fire({icon:failed?'error':'success',text:message});
 }
 function fillSeries(series){
  $series.val(series||$series.find('option').first().val());
  $prefix.val($series.find('option:selected').attr('data-prefix')||$series.val());
 }
 $series.on('change',function(){fillSeries($(this).val());});
 $tab.on('click','#s4-configure',function(){fillSeries();$modal.modal('show');});
 $tab.on('click','.s4-edit',function(){fillSeries($(this).attr('data-s4-edit'));$modal.modal('show');});
 $form.on('submit',function(e){
  e.preventDefault();e.stopImmediatePropagation();
  if(busy)return;
  if(!this.checkValidity()){this.reportValidity();return;}
  function send(){
   busy=true;var $button=$form.find('button[type=submit]').prop('disabled',true);
   $.ajax({url:$form.attr('action'),method:'POST',data:$form.serialize(),
    dataType:'json',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
   .done(function(data){
    if(data.success===false){notify(data.message||'Unable to save prefix.',true);return;}
    var series=String(data.series||$series.val()),prefix=String(data.prefix||$prefix.val());
    $tab.find('tr[data-s4-series]').filter(function(){return $(this).attr('data-s4-series')===series;})
     .find('.s4-current-prefix').text(prefix);
    $series.find('option:selected').attr('data-prefix',prefix);
    $modal.modal('hide');
    notify(data.message||'Prefix configuration saved.',false);
   }).fail(function(xhr){
    var data=xhr.responseJSON||{},errors=data.errors||{},message=data.message||'Unable to save prefix.';
    $.each(errors,function(_,messages){message=messages[0]||message;return false;});
    notify(message,true);
   }).always(function(){busy=false;$button.prop('disabled',false);});
  }
  if(window.Swal)Swal.fire({icon:'question',title:'Save prefix configuration?',
    text:'This does not yet activate new voucher numbering. Existing vouchers are unchanged.',
    showCancelButton:true,confirmButtonText:'Yes, Save'}).then(function(res){if(res.isConfirmed)send();});
  else if(window.confirm('Save prefix configuration without changing voucher numbering?'))send();
 });
});
})(window.jQuery);
