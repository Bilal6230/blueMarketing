/* Party Account form assistance: native 13-digit validation and optional Head TS. */
(function($){
'use strict';
$(function(){
 var $scope=$('.bm-modern-accounts[data-account-kind="subhead"]');
 if(!$scope.length)return;
 function initHead(){
  var input=document.getElementById('party-create-head');
  if(!input || input.tomselect || !window.TomSelect)return;
  new TomSelect(input,{create:false,maxItems:1,allowEmptyOption:true,
   placeholder:'Select Head Account (optional)',dropdownParent:'body'});
 }
 // Modern account directory moves the source form into its Bootstrap modal.
 initHead();
 var $forms=$scope.find('form').add($('#modal-edit form'));
 $forms.on('input.partyNic','[name="cnic"]',function(){
  var clean=String($(this).val()||'').replace(/\D/g,'').slice(0,13);
  if($(this).val()!==clean)$(this).val(clean);
  this.setCustomValidity(clean.length>0&&clean.length!==13?'NIC must be exactly 13 digits.':'');
 });
 $forms.on('input.partyPhone','[name="phone"]',function(){
  this.setCustomValidity($.trim($(this).val())?'':'Phone Number is required.');
 });
 $scope.find('.bm-account-create-modal form').add($('#modal-edit form'))
  .on('submit.partyValidate',function(e){
   var $form=$(this),$nic=$form.find('[name="cnic"]'),
       $phone=$form.find('[name="phone"]');
   if($nic.length){$nic.trigger('input.partyNic');}
   if($phone.length){$phone.trigger('input.partyPhone');}
   if(!this.checkValidity()){
    e.preventDefault();e.stopImmediatePropagation();this.reportValidity();
   }
  });
});
})(window.jQuery);
