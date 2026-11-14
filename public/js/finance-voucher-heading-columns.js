/* CR/DR-style column-heading filters for Cheque / Online registers.
 * Keeps the legacy inputs in a hidden row so their existing jQuery handlers
 * and project-scoped list API contracts are unchanged. */
(function ($) {
    'use strict';
    $(function () {
        function setup(config) {
            var $root=$(config.root),$table=$root.find(config.table);
            if (!$table.length) return;
            var $form=$(config.form),$filterRow=$table.find(config.row).first();
            if (!$form.length || !$filterRow.length) return;
            var $headings=$table.find('thead tr:first th'),$cells=$filterRow.children('th');
            var $sortColumn=$('<input>',{type:'hidden',name:'sort_column'}).appendTo($form);
            var $sortDirection=$('<input>',{type:'hidden',name:'sort_direction'}).appendTo($form);
            var $dateFrom=$('<input>',{type:'hidden',name:'column_date_from'}).appendTo($form);
            var $dateTo=$('<input>',{type:'hidden',name:'column_date_to'}).appendTo($form);
            // Every register uses the very same canonical CR-style popup DOM.
            var widget=$.financeVoucherComponents.createFilter({kind:'heading',
                accent:$root.css('--fvd-accent')||'#168348'});
            var $popover=widget.$popup;
            var active=null,activeTrigger=null;
            function close() {
                widget.close();
                if(activeTrigger)activeTrigger.attr('aria-expanded','false');
                active=null;activeTrigger=null;
            }
            function updateButtons() {
                $.each(config.fields,function(index,field){
                    var $button=$headings.eq(Number(index)).find('.bm-column-heading-trigger');
                    var $origin=$cells.eq(Number(index)).find('[data-column]').first();
                    var selected=$origin.length && String($origin.val()||'')!=='';
                    var sorted=$sortColumn.val()===field;
                    if(field==='received_date'||field==='transaction_date')
                        selected=!!($dateFrom.val()||$dateTo.val());
                    $button.toggleClass('bm-column-active',!!selected||sorted)
                        .attr('aria-sort',sorted?($sortDirection.val()==='asc'?'ascending':'descending'):'none');
                });
            }
            $.each(config.fields,function(index,field){
                var $th=$headings.eq(Number(index));
                if(!$th.length)return;
                var $button=$('<button>',{type:'button','class':'bm-column-heading-trigger',
                    'aria-label':'Sort or filter '+$.trim($th.text()),
                    'aria-expanded':'false','aria-haspopup':'dialog'})
                    .append($('<i>',{'class':'fas fa-caret-down','aria-hidden':'true'}));
                $th.append($button);
                $button.on('click',function(event){
                    event.preventDefault();event.stopPropagation();
                    if(active===Number(index)){close();return;}
                    close();active=Number(index);activeTrigger=$button;
                    widget.setTitle($.trim($th.text()));
                    var $container=$popover.find('.bm-heading-column-control').empty();
                    var $origin=$cells.eq(active).find('[data-column]').first();
                    if($origin.length && $origin.is('[type=date]')){
                        widget.dates({from:$dateFrom.val(),to:$dateTo.val()},function(key,value){
                            (key==='from'?$dateFrom:$dateTo).val(value);
                            $origin.val('');
                            updateButtons();
                            $form.trigger('submit');
                        });
                    }else if($origin.length){
                        var $copy=$origin.clone(false).removeAttr('id').removeAttr('name')
                            .removeClass('tomselected').addClass('bm-heading-column-input');
                        $copy.val($origin.val());
                        $copy.on('input change',function(){
                            $origin.val($(this).val()).trigger($(this).is('select')?'input':'input');
                            updateButtons();
                        });
                        $container.append($copy).removeClass('d-none');
                    }else{$container.addClass('d-none');}
                    widget.position($button);
                    $popover.find('.bm-heading-column-input,.fvc-date-input').first().trigger('focus');
                });
            });
            function sort(direction){
                if(active===null)return;
                $sortColumn.val(config.fields[active]);
                $sortDirection.val(direction);
                updateButtons();close();$form.trigger('submit');
            }
            $popover.find('.bm-col-asc').on('click',function(){sort('asc');});
            $popover.find('.bm-col-desc').on('click',function(){sort('desc');});
            $popover.find('.bm-col-clear').on('click',function(){
                if(active===null)return;
                var $origin=$cells.eq(active).find('[data-column]').first();
                if($origin.is('[type=date]')){
                    $dateFrom.val('');$dateTo.val('');$origin.val('');
                    $form.trigger('submit');
                }else if($origin.length)$origin.val('').trigger('input');
                if($sortColumn.val()===config.fields[active]){$sortColumn.val('');$sortDirection.val('');$form.trigger('submit');}
                updateButtons();close();
            });
            $(document).on('click.bmColumn'+config.key,function(event){
                if(!$(event.target).closest($popover).length && !$(event.target).closest($table.find('.bm-column-heading-trigger')).length)close();
            });
            $(document).on('keydown.bmColumn'+config.key,function(event){if(event.key==='Escape')close();});
            $form.on('reset.bmColumn'+config.key,function(){
                $sortColumn.val('');$sortDirection.val('');
                $dateFrom.val('');$dateTo.val('');
                close();
                // The caller clears column-filter fields and reloads itself.
                updateButtons();
            });
            if(config.reset){
                $(config.reset).on('click.bmColumn'+config.key,function(){updateButtons();});
            }
            $filterRow.addClass('bm-column-filter-storage');
            updateButtons();
        }
        setup({key:'Cheque',root:'.cheque-register-page',table:'#cheque-register-table',
            row:'.cheque-column-filters',form:'#cheque-filter-form',reset:'#cheque-reset',
            fields:{1:'received_date',2:'cheque_number',3:'direction',4:'bank_name',
                5:'received_from',6:'purpose',7:'amount',8:'status'}});
        setup({key:'Online',root:'.online-register-page',table:'.online-table table',
            row:'.online-column-filters',form:'#online-filters',reset:'#online-reset',
            fields:{1:'transaction_date',2:'reference',3:'type',4:'sending_bank',
                5:'receiving_bank',6:'sender_name',7:'purpose',8:'amount',9:'status'}});
    });
})(jQuery);
