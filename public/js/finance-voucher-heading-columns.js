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
            var $popover=$('<div>',{'class':'bm-heading-column-popover','role':'dialog','aria-label':'Column sort and filter'})
                .append($('<div>',{'class':'bm-heading-column-title'}))
                .append($('<div>',{'class':'bm-heading-column-control'}))
                .append($('<div>',{'class':'bm-heading-column-actions'})
                    .append($('<button>',{type:'button','class':'btn btn-outline-primary btn-sm bm-col-asc'}).text('Sort ascending'))
                    .append($('<button>',{type:'button','class':'btn btn-outline-primary btn-sm bm-col-desc'}).text('Sort descending'))
                    .append($('<button>',{type:'button','class':'btn btn-outline-secondary btn-sm bm-col-clear'}).text('Clear')));
            $('body').append($popover);
            var active=null,activeTrigger=null;
            function close() {
                $popover.removeClass('bm-col-open').hide();
                if(activeTrigger)activeTrigger.attr('aria-expanded','false');
                active=null;activeTrigger=null;
            }
            function updateButtons() {
                $.each(config.fields,function(index,field){
                    var $button=$headings.eq(Number(index)).find('.bm-column-heading-trigger');
                    var $origin=$cells.eq(Number(index)).find('[data-column]').first();
                    var selected=$origin.length && String($origin.val()||'')!=='';
                    var sorted=$sortColumn.val()===field;
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
                    $popover.find('.bm-heading-column-title').text($.trim($th.text()));
                    var $container=$popover.find('.bm-heading-column-control').empty();
                    var $origin=$cells.eq(active).find('[data-column]').first();
                    if($origin.length){
                        var $copy=$origin.clone(false).removeAttr('id').removeAttr('name')
                            .removeClass('tomselected').addClass('bm-heading-column-input');
                        $copy.val($origin.val());
                        $copy.on('input change',function(){
                            $origin.val($(this).val()).trigger($(this).is('select')?'input':'input');
                            updateButtons();
                        });
                        $container.append($copy).removeClass('d-none');
                    }else{$container.addClass('d-none');}
                    var offset=$button.offset(),left=Math.max(8,Math.min(offset.left-$(window).scrollLeft(),$(window).width()-290));
                    var top=Math.max(8,Math.min(offset.top-$(window).scrollTop()+$button.outerHeight()+4,$(window).height()-225));
                    $popover.css({left:left+'px',top:top+'px'}).addClass('bm-col-open').show();
                    $popover.find('.bm-heading-column-input').trigger('focus');
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
                if($origin.length)$origin.val('').trigger('input');
                if($sortColumn.val()===config.fields[active]){$sortColumn.val('');$sortDirection.val('');$form.trigger('submit');}
                updateButtons();close();
            });
            $(document).on('click.bmColumn'+config.key,function(event){
                if(!$(event.target).closest($popover).length && !$(event.target).closest($table.find('.bm-column-heading-trigger')).length)close();
            });
            $(document).on('keydown.bmColumn'+config.key,function(event){if(event.key==='Escape')close();});
            $form.on('reset.bmColumn'+config.key,function(){
                $sortColumn.val('');$sortDirection.val('');
                close();
                // The caller clears column-filter fields and reloads itself.
                updateButtons();
            });
            $filterRow.addClass('bm-column-filter-storage');
            updateButtons();
        }
        setup({key:'Cheque',root:'.cheque-register-page',table:'#cheque-register-table',
            row:'.cheque-column-filters',form:'#cheque-filter-form',
            fields:{1:'received_date',2:'cheque_number',3:'direction',4:'bank_name',
                5:'received_from',6:'purpose',7:'amount',8:'status'}});
        setup({key:'Online',root:'.online-register-page',table:'.online-table table',
            row:'.online-column-filters',form:'#online-filters',
            fields:{1:'transaction_date',2:'reference',3:'type',4:'sending_bank',
                5:'receiving_bank',6:'sender_name',7:'purpose',8:'amount',9:'status'}});
    });
})(jQuery);
