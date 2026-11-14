/* BlueMarketing compact voucher directory chrome.
 * Only presentation relocation: original jQuery handlers, forms and finance routes remain unchanged.
 * Keep controls in their original DOM identity via detach(), never clone posting forms.
 */
(function ($) {
    'use strict';
    $(function () {
        function addFilterModal($root, $panel, $heading, $headerActions, label) {
            if (!$root.length || !$panel.length || !$heading.length || !$headerActions.length) return;
            var $form = $panel.find('form').first();
            // CV has direct filter inputs inside the card-body rather than a form.
            // Keep their original ids and attached handlers intact.
            if (!$form.length) $form = $panel.children('.card-body').first();
            if (!$form.length) return;
            var $create = $form.find('button,a').filter(function () {
                return /new voucher/i.test($.trim($(this).text())) || $(this).is('#dv-create-voucher,#openTransferCreate');
            }).first();
            if ($create.length) {
                $create.detach().addClass('bm-header-new btn-sm').appendTo($headerActions);
            }
            var $modal = $('<div>', {'class':'modal fade bm-quick-filter-modal',tabindex:'-1',role:'dialog','aria-hidden':'true'})
                .append($('<div>',{'class':'modal-dialog modal-dialog-centered modal-lg',role:'document'})
                    .append($('<div>',{'class':'modal-content'})
                        .append($('<div>',{'class':'modal-header'})
                            .append($('<h5>',{'class':'modal-title'}).text(label+' Filters'))
                            .append($('<button>',{'class':'close',type:'button','data-dismiss':'modal','aria-label':'Close'}).html('&times;')))
                        .append($('<div>',{'class':'modal-body'}))));
            $form.detach().addClass('bm-quick-filter-form').appendTo($modal.find('.modal-body'));
            $panel.remove();
            $('body').append($modal);
            var $button = $('<button>',{'class':'btn btn-outline-primary btn-sm bm-heading-filter',type:'button'})
                .append($('<i>',{'class':'fas fa-filter mr-1'})).append($('<span>').text(' Filters'));
            $button.on('click', function () {$modal.modal('show');});
            $headerActions.prepend($button);
            $form.on('submit.bmFilter', function () {$modal.modal('hide');});
            $form.find('button[type=button]').filter(function(){return /reset/i.test($.trim($(this).text()));})
                .on('click.bmFilter',function () {$modal.modal('hide');});
        }
        function headerActions($header) {
            var $right=$header.children().last();
            if ($right.length===1 && $right.is('div') && !$right.is('h1,h2,h4')) return $right;
            return $('<div>',{'class':'bm-header-actions d-flex align-items-center flex-wrap'}).appendTo($header);
        }
        // Existing finance directories keep their original form submissions, authorization and table actions.
        var $daily=$('#dv-page');
        if ($daily.length) {
            var $dh=$daily.find('.content-header .container-fluid').first();
            addFilterModal($daily,$daily.find('.daily-voucher-card.mb-3').first(),$dh,
                headerActions($dh),'Voucher');
        }
        var $jv=$('.jv-page');
        if ($jv.length) {
            var $jh=$jv.find('.jv-head').first();
            var $ja=headerActions($jh);
            $jh.find('#jv-add').detach().appendTo($ja);
            addFilterModal($jv,$jv.find('.jv-filters').first(),$jh,$ja,'Journal');
        }
        var $cv=$('.cv-directory');
        if ($cv.length) {
            var $ch=$cv.find('.cv-shell>.card-header').first();
            addFilterModal($cv,$cv.find('#filtersSection').first(),$ch,headerActions($ch),'Commission');
        }
        // Commission uses its original create route/form in an iframe: do not duplicate its posting logic.
        if ($cv.length) {
            var $commissionCreate = $cv.find('a.bm-header-new[href]').first();
            if ($commissionCreate.length) {
                var $commissionModal = $('<div>',{'class':'modal fade bm-commission-entry-modal',id:'bmCommissionEntryModal',tabindex:'-1',role:'dialog','aria-hidden':'true'})
                    .append($('<div>',{'class':'modal-dialog',role:'document'})
                        .append($('<div>',{'class':'modal-content'})
                            .append($('<div>',{'class':'modal-header'})
                                .append($('<h5>',{'class':'modal-title'}).text('New Commission Voucher'))
                                .append($('<button>',{'class':'close',type:'button','data-dismiss':'modal','aria-label':'Close'}).html('&times;')))
                            .append($('<div>',{'class':'modal-body'})
                                .append($('<iframe>',{id:'bmCommissionFrame',title:'Commission Voucher Form',src:'about:blank'})))
                            .append($('<div>',{'class':'modal-footer'})
                                .append($('<button>',{type:'button','class':'btn btn-outline-secondary btn-sm','data-dismiss':'modal'}).text('Close')))));
                $('body').append($commissionModal);
                $commissionCreate.on('click.bmCommission',function(e){
                    e.preventDefault();
                    var url=$(this).attr('href');
                    $('#bmCommissionFrame').attr('src',url+(url.indexOf('?')===-1?'?':'&')+'embed=1');
                    $commissionModal.modal('show');
                });
                $cv.on('click.bmCommission','#journalTable a[href*="/edit"]',function(e){
                    e.preventDefault();
                    var url=$(this).attr('href');
                    $commissionModal.find('.modal-title').text('Edit Commission Voucher');
                    $('#bmCommissionFrame').attr('src',url+(url.indexOf('?')===-1?'?':'&')+'embed=1');
                    $commissionModal.modal('show');
                });
                $commissionCreate.on('click.bmCommissionTitle',function(){
                    $commissionModal.find('.modal-title').text('New Commission Voucher');
                });
                $commissionModal.on('hidden.bs.modal',function(){
                    $('#bmCommissionFrame').attr('src','about:blank');
                    if($.fn.DataTable && $.fn.DataTable.isDataTable('#journalTable')){
                        $('#journalTable').DataTable().ajax.reload(null,false);
                    }
                });
            }
        }
        $.each([
            ['#bankTransferApp','.bt-filter-card','.bt-heading','Bank Transfer'],
            ['#plotReceiptApp','.pr-filter-card','.pr-heading','Plot Receipt'],
            ['#plotPurchaseApp','.pp-filter-card','.bm-voucher-heading','Plot Purchase']
        ],function (_,cfg) {
            var $root=$(cfg[0]);
            if (!$root.length) return;
            var $head=$root.find(cfg[2]).first();
            addFilterModal($root,$root.find(cfg[1]).first(),$head,headerActions($head),cfg[3]);
        });
        // Cheque and Online have persistent live KPI cards. Keep link selector beside table Search,
        // while opening all other source filters from the heading.
        $.each([
            ['.cheque-register-page','.cheque-filter-panel','.cheque-heading','#cheque-link-filter','.cheque-table-panel>.d-flex:first','cheque-filter-form'],
            ['.online-register-page','.online-filter','.online-heading','#online-link-filter','.online-table>.d-flex:first','online-filters']
        ],function (_,cfg) {
            var $root=$(cfg[0]); if (!$root.length) return;
            var $link=$root.find(cfg[3]);
            var $form=$root.find('#'+cfg[5]);
            if ($link.length && $form.length) {
                var $oldLinkRow=$link.parent();
                var $wrap=$('<label>',{'class':'bm-inline-link-filter mb-0'}).text('Voucher Links ');
                $link.detach().appendTo($wrap);
                // Do not leave a label-only second filter row in the popup.
                if($oldLinkRow.is('div'))$oldLinkRow.remove();
                var $bar=$root.find(cfg[4]).first();
                if ($bar.length) $bar.append($wrap);
                var $hidden=$('<input>',{type:'hidden',name:'link_status'}).appendTo($form);
                function syncLink(){$hidden.val($link.val()||'');}
                syncLink();
                $link.on('change.bmLinks',function(){syncLink();$form.trigger('submit');});
                $form.on('reset.bmLinks',function(){ $link.val(''); $hidden.val(''); });
            }
            var $head=$root.find(cfg[2]).first();
            var $actions=headerActions($head);
            if(cfg[0].indexOf('online')!==-1){
                $head.find('#online-add').detach().appendTo($actions);
            }
            addFilterModal($root,$root.find(cfg[1]).first(),$head,$actions,
                cfg[0].indexOf('cheque')!==-1?'Cheque':'Online Transaction');
        });
        // Draft vouchers: style existing server-rendered list, preserve approval/reject/edit buttons.
        var $draft=$('.bm-modern-draft');
        if ($draft.length) {
            var $head=$draft.find('.bm-draft-heading').first(),$actions=$head.find('.bm-draft-actions').first();
            var $filter=$draft.find('#filtersSection');
            if ($filter.length) {
                var $modal=$('<div>',{'class':'modal fade bm-quick-filter-modal',tabindex:'-1',role:'dialog','aria-hidden':'true'})
                    .append($('<div>',{'class':'modal-dialog modal-dialog-centered modal-xl',role:'document'})
                    .append($('<div>',{'class':'modal-content'})
                    .append($('<div>',{'class':'modal-header'})
                    .append($('<h5>',{'class':'modal-title'}).text('Draft Filters'))
                    .append($('<button>',{'class':'close',type:'button','data-dismiss':'modal'}).html('&times;')))
                    .append($('<div>',{'class':'modal-body'}))));
                $filter.detach().show().appendTo($modal.find('.modal-body'));
                $('body').append($modal);
                $draft.find('#toggleFilters').off('click').on('click',function(){$modal.modal('show');}).detach().appendTo($actions);
                $filter.find('form').on('submit',function(){$modal.modal('hide');});
            }
            var $draftTable=$draft.find('#bmDraftTable');
            if($draftTable.length && $.fn.DataTable && !$.fn.DataTable.isDataTable($draftTable)){
                // Blade's "No vouchers found" colspan row is replaced by the DataTables empty message.
                $draftTable.find('tbody tr').filter(function(){
                    return $(this).children('td[colspan]').length>0;
                }).remove();
                $draftTable.DataTable({
                    pageLength:25,lengthMenu:[[10,25,50,100],[10,25,50,100]],
                    order:[[1,'desc']],autoWidth:false,
                    language:{emptyTable:'No draft vouchers found'},
                    dom:'<"d-flex justify-content-between align-items-center flex-wrap p-2"l f>rt<"d-flex justify-content-between align-items-center flex-wrap p-2"ip>'
                });
            }
        }
        // Extra Charge: keep existing voucherForm/confirmation lifecycle, only relocate the entry UI.
        var $extra=$('.bm-modern-extra');
        if ($extra.length) {
            var $form=$extra.find('#voucherForm');
            var $head=$extra.find('.bm-extra-heading').first();
            if ($form.length && $head.length) {
                var $modal=$('<div>',{'class':'modal fade bm-extra-entry-modal',id:'bmExtraEntryModal',tabindex:'-1',role:'dialog','aria-hidden':'true'})
                    .append($('<div>',{'class':'modal-dialog modal-dialog-scrollable modal-xl',role:'document'})
                    .append($('<div>',{'class':'modal-content'})
                    .append($('<div>',{'class':'modal-header'})
                    .append($('<h5>',{'class':'modal-title'}).text('New Extra Charge'))
                    .append($('<button>',{'class':'close',type:'button','data-dismiss':'modal'}).html('&times;')))
                    .append($('<div>',{'class':'modal-body'}))));
                $form.detach().appendTo($modal.find('.modal-body'));
                $('body').append($modal);
                // Original Save referenced #confirmModal, which is not present on this page.
                // Preserve the original Laravel posting form and add an explicit confirmation
                // without allowing two Bootstrap modals to trap focus simultaneously.
                var $confirm=$('<div>',{'class':'modal fade',id:'bmExtraConfirmModal',tabindex:'-1',role:'dialog','aria-hidden':'true'})
                    .append($('<div>',{'class':'modal-dialog modal-dialog-centered',role:'document'})
                    .append($('<div>',{'class':'modal-content'})
                    .append($('<div>',{'class':'modal-header'})
                        .append($('<h5>',{'class':'modal-title'}).text('Confirm Extra Charge')))
                    .append($('<div>',{'class':'modal-body'}).text('Save this extra charge using the entered project, customer and posting accounts?'))
                    .append($('<div>',{'class':'modal-footer'})
                        .append($('<button>',{type:'button','class':'btn btn-outline-secondary btn-sm','data-dismiss':'modal'}).text('Cancel'))
                        .append($('<button>',{type:'button','class':'btn btn-primary btn-sm',id:'bmExtraConfirmSave'}).text('Confirm Save')))));
                $('body').append($confirm);
                $form.on('click.bmExtraSave','.bm-extra-save',function(event){
                    event.preventDefault();
                    $modal.one('hidden.bs.modal',function(){$confirm.modal('show');});
                    $modal.modal('hide');
                });
                $confirm.on('click','#bmExtraConfirmSave',function(){
                    $(this).prop('disabled',true);
                    $form.trigger('submit');
                    // Re-enable only if client validation prevented navigation.
                    $(this).prop('disabled',false);
                });
                $confirm.on('hidden.bs.modal',function(){
                    if (!$('body').hasClass('modal-open')) $modal.modal('show');
                });
                $('<button>',{type:'button','class':'btn btn-primary btn-sm'})
                    .html('<i class="fas fa-plus mr-1"></i> New voucher')
                    .on('click',function(){$modal.modal('show');})
                    .appendTo($head.find('.bm-extra-actions'));
            }
        }
    });
})(jQuery);
