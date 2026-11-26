/* Modern Plot Add/Edit: preserve existing payloads while arranging definition inputs. */
(function($){
'use strict';
$(function(){
 $('.plot-editor').each(function(){
  var $modal=$(this),$form=$modal.find('form').first();
  if(!$form.length)return;
  var $body=$modal.find('.modal-body').first();
  // Plot type and physical attributes are different concepts. Keep every
  // legacy input in the form, but give the new master selectors their own row.
  var $fields=$form.find('select[name$="_definition_id"]');
  if($fields.length){
   var $typeRow=$form.find('select[name="type"]').closest('.row');
   var $definitionRow=$('<div>',{'class':'row plot-definition-row'});
   var definitions=[
    ['plot_category_definition_id','Category Definition'],
    ['plot_type_definition_id','Type Definition'],
    ['plot_size_definition_id','Saved Size Definition'],
    ['plot_unit_definition_id','Unit Definition']
   ];
   $.each(definitions,function(_,item){
    var $field=$fields.filter('[name="'+item[0]+'"]');
    if(!$field.length)return;
    var id='plot-'+$modal.attr('id')+'-'+item[0];
    $field.attr('id',id).removeClass('mt-1');
    $('<div>',{'class':'col-sm-6'})
     .append($('<label>',{'for':id,text:item[1]}),$field)
     .appendTo($definitionRow);
   });
   if($definitionRow.children().length){
    $typeRow.after($definitionRow);
    $definitionRow.before($('<div>',{'class':'col-12 plot-section-heading',text:'Saved Plot Definitions (selected project)'}));
    $definitionRow.after($('<div>',{'class':'col-12 plot-definition-note',text:'Size selection fills the plot size and unit. The final Plot Amount is still entered separately.'}));
   }
  }
  $form.find('select[name="project_id"]').prop('disabled',true);
  // Disabled fields are omitted by FormData: retain selected project securely
  // as a hidden field while the controller independently validates its scope.
  var $project=$form.find('select[name="project_id"]');
  if($project.length)$('<input>',{type:'hidden',name:'project_id',value:$project.val()}).insertAfter($project);
  $form.find('input[name="name"]').attr('placeholder','e.g. A-10');
  $form.find('input[name="size"]').attr({type:'number',step:'0.01',min:'0.01'});
  $form.find('textarea[name="description"]').attr('placeholder','Optional plot details or remarks');
  // Searchable selects for definitions, without replacing form data identities.
  if(window.TomSelect){
   $form.find('select[name$="_definition_id"]').each(function(){
    if(this.tomselect)return;
    new TomSelect(this,{create:false,maxItems:1,allowEmptyOption:true,
     dropdownParent:'body',dropdownClass:'ts-dropdown plot-definition-dropdown'});
   });
  }
  $modal.on('shown.bs.modal',function(){
   $form.find('select[name$="_definition_id"]').each(function(){
    if(this.tomselect)this.tomselect.setValue($(this).val()||'',true);
   });
  });
  $form.on('change','.plot-size-definition',function(){
   var $unit=$form.find('.plot-unit-definition'),value=$unit.val();
   if($unit.length&&$unit[0].tomselect)$unit[0].tomselect.setValue(value||'',true);
  });
 });
});
})(window.jQuery);
