/* Self-service My Profile editor; jQuery only. No full-page form submission. */
(function($){
'use strict';
$(function(){
 var $form=$('#my-profile-form');
 if(!$form.length)return;
 var $save=$('#my-profile-save'),$avatar=$('#my-profile-avatar'),
     $preview=$('#my-profile-image-preview'),oldPreview=$preview.attr('src'),
     busy=false;
 function message(text,failed){
  if(window.toastr){failed?toastr.error(text):toastr.success(text);}
  else{
   var $note=$('<div>',{'class':'alert alert-'+(failed?'danger':'success'),'role':'status',text:text});
   $form.before($note);$note.delay(7000).fadeOut(function(){$(this).remove();});
  }
 }
 function errors(fields){
  $form.find('[data-error-for]').hide().text('');
  $form.find('.is-invalid').removeClass('is-invalid');
  $.each(fields||{},function(key,messages){
   var $field=$form.find('[name="'+key+'"]'),$tip=$form.find('[data-error-for="'+key+'"]');
   $field.addClass('is-invalid');
   $tip.text(messages[0]||'Invalid value.').show();
  });
 }
 $avatar.on('change',function(){
  var file=this.files&&this.files[0];
  if(!file){$preview.attr('src',oldPreview);return;}
  if(!/^image\/(jpeg|png|webp)$/.test(file.type)||file.size>2*1024*1024){
   $avatar.val('');errors({avatar:['Choose a JPG, PNG or WebP image up to 2 MB.']});
   $preview.attr('src',oldPreview);return;
  }
  var reader=new FileReader();
  reader.onload=function(e){$preview.attr('src',e.target.result);};
  reader.readAsDataURL(file);
  errors({});
 });
 $form.on('submit',function(e){
  e.preventDefault();e.stopImmediatePropagation();
  if(busy)return;
  errors({});
  var form=this;
  if(!form.checkValidity()){form.reportValidity();return;}
  var password=$form.find('[name=password]').val();
  if(password && !$form.find('[name=current_password]').val()){
   errors({current_password:['Enter your current password to set a new one.']});return;
  }
  function send(){
   busy=true;
   $save.prop('disabled',true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
   $.ajax({
    url:$form.attr('action'),type:'POST',data:new FormData(form),
    processData:false,contentType:false,dataType:'json',
    headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}
   }).done(function(data){
    $('#my-profile-current-name').text(data.profile.name);
    $form.find('[name=current_password],[name=password],[name=password_confirmation]').val('');
    $avatar.val('');
    if(data.profile.avatar_url){
     oldPreview=data.profile.avatar_url;
     $preview.attr('src',oldPreview);
     var $header=$('#my-profile-header-avatar');
     if($header.length)$header.attr('src',oldPreview);
     else{
      $('#my-profile-header-placeholder').replaceWith(
       $('<img>',{id:'my-profile-header-avatar','class':'profile-photo',src:oldPreview,alt:'Your profile picture'})
      );
     }
     $('.navbar .user-menu img.user-image,.navbar .user-header img').attr('src',oldPreview);
    }
    message(data.message||'Profile updated.',false);
   }).fail(function(xhr){
    var data=xhr.responseJSON||{};
    errors(data.errors||{});
    message(data.message||'Unable to update your profile.',true);
   }).always(function(){
    busy=false;$save.prop('disabled',false).html('<i class="fas fa-save mr-1"></i>Save Changes');
   });
  }
  // Existing projects may not load SweetAlert on every page; Bootstrap is
  // installed throughout the ERP and always provides confirmation.
  $('#my-profile-confirm').remove();
  var $dialog=$('<div>',{id:'my-profile-confirm','class':'modal fade',tabindex:'-1','aria-hidden':'true'})
   .append($('<div>',{'class':'modal-dialog modal-dialog-centered'})
    .append($('<div>',{'class':'modal-content'})
     .append($('<div>',{'class':'modal-header'}).append(
       $('<h5>',{'class':'modal-title',text:'Confirm Profile Changes'}),
       $('<button>',{type:'button','class':'close','data-dismiss':'modal',html:'&times;'})))
     .append($('<div>',{'class':'modal-body',text:'Save the changes to your own profile?'}))
     .append($('<div>',{'class':'modal-footer'}).append(
       $('<button>',{type:'button','class':'btn btn-outline-secondary','data-dismiss':'modal',text:'Cancel'}),
       $('<button>',{type:'button','class':'btn btn-primary',id:'my-profile-confirm-yes',text:'Yes, Save'})))));
  $dialog.appendTo('body');
  $dialog.on('click','#my-profile-confirm-yes',function(){$dialog.modal('hide');send();});
  $dialog.on('hidden.bs.modal',function(){$dialog.remove();}).modal('show');
 });
});
})(window.jQuery);
