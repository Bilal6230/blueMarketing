/* BlueMarketing compact voucher directory chrome.
 * Only presentation relocation: original jQuery handlers, forms and finance routes remain unchanged.
 * Keep controls in their original DOM identity via detach(), never clone posting forms.
 */
(function ($) {
    'use strict';
    // Central popover anatomy shared by Cheque, Online, CV, Draft, Extra.
    // CR/JV already ship their own controls; the same CSS tokens style them.
    $.financeVoucherUi=$.financeVoucherUi||{};
    $.financeVoucherUi.normalizePopover=function($popup){
        if(!$popup||!$popup.length||$popup.data('fvd-normalized'))return;
        var $actions=$popup.find('.bm-heading-column-actions,.fvd-pop-actions').first();
        var $control=$popup.find('.bm-heading-column-control,.fvd-pop-filter').first();
        if(!$actions.length||!$control.length)return;
        var $sort=$('<div>',{'class':'fvd-sort-options'});
        $actions.find('.bm-col-asc,.bm-col-desc,.fvd-sort-asc,.fvd-sort-desc')
            .detach().appendTo($sort);
        $control.before($sort);
        $popup.data('fvd-normalized',true);
    };
    $(function () {
        // Popovers are attached to body, so explicitly inherit page palette.
        if($('.jv-page').length)$('#jv-excel-pop').css('--fvd-accent','#087cfa');
        if($('#dv-page').length)$('#dv-excel-popover').css('--fvd-accent',$('#dv-page').css('--dv-accent'));
        // Finance directory chrome is declarative. Legacy top filters stay in their
        // original DOM for backwards-compatible data loading, but are never exposed
        // as an extra toolbar/modal. Column headings own filtering and sorting.
        function headerActions($header) {
            var $right=$header.children('.bm-header-actions').first();
            if(!$right.length){
                $right=$('<div>',{'class':'bm-header-actions d-flex align-items-center flex-wrap'});
                $header.append($right);
            }
            return $right;
        }
        function moveCreate($root, selector, $header) {
            var $button=$root.find(selector).first();
            if($button.length)$button.detach().addClass('bm-header-new btn-sm').appendTo(headerActions($header));
        }
        // Centralize the complete header action group; do not leave Dashboard
        // floating beside the title on Bank Transfer or Plot vouchers.
        function alignActions($header) {
            if(!$header.length)return;
            var $group=headerActions($header);
            $header.children('a[href],button').filter(function(){
                return /dashboard|approvals/i.test($.trim($(this).text()));
            }).detach().prependTo($group);
            $header.children('div').not($group).each(function(){
                var $div=$(this);
                if(!$div.find('h1,h2,h3,h4,h5').length){
                    $div.find('a[href],button').filter(function(){
                        return /dashboard|approvals/i.test($.trim($(this).text()));
                    }).detach().prependTo($group);
                    if(!$div.children().length)$div.remove();
                }
            });
        }
        var $daily=$('#dv-page');
        if($daily.length){
            var $dh=$daily.find('.content-header .container-fluid').first();
            moveCreate($daily,'#dv-create-voucher',$dh);
        }
        var $jv=$('.jv-page');
        if($jv.length){
            var $jh=$jv.find('.jv-head').first();
            moveCreate($jv,'#jv-add',$jh);
        }
        var $cv=$('.cv-directory');
        if($cv.length){
            var $ch=$cv.find('.cv-shell>.card-header').first();
            moveCreate($cv,'#filtersSection a[href*="create"]',$ch);
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
                $commissionModal.addClass('fvc-modern-modal fvc-modal-blue');
                $('body').append($commissionModal);
                function loadCommissionForm(url){
                    var $body=$commissionModal.find('.modal-body');
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.skeleton($body,true);
                    var $frame=$('#bmCommissionFrame');
                    $frame.off('load.fvc').one('load.fvc',function(){
                        if($.financeVoucherFeedback)$.financeVoucherFeedback.skeleton($body,false);
                    }).attr('src',url+(url.indexOf('?')===-1?'?':'&')+'embed=1');
                }
                $commissionCreate.on('click.bmCommission',function(e){
                    e.preventDefault();
                    var url=$(this).attr('href');
                    loadCommissionForm(url);
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
                // Closing an unchanged form must not re-fetch the directory.
                // A successful save refreshes exactly once after the modal closes.
                var commissionNeedsRefresh=false;
                $(document).on('finance:commission-saved.fvc',function(_,response){
                    commissionNeedsRefresh=true;
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.toast('success',
                        (response&&response.message)||'Commission voucher saved.');
                    $commissionModal.modal('hide');
                });
                $commissionModal.on('hidden.bs.modal',function(){
                    $('#bmCommissionFrame').off('load.fvc').attr('src','about:blank');
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.skeleton($commissionModal.find('.modal-body'),false);
                    if(commissionNeedsRefresh){
                        commissionNeedsRefresh=false;
                        if($.fn.DataTable && $.fn.DataTable.isDataTable('#journalTable')){
                            $('#journalTable').DataTable().ajax.reload(null,false);
                        }
                    }
                });
            }
        }
        $.each([
            ['#bankTransferApp','#openTransferCreate','.bt-heading'],
            ['#plotReceiptApp','#prFilterForm [data-target="#plotReceiptCreateModal"]','.pr-heading'],
            ['#plotPurchaseApp','#ppFilterForm [data-target="#plotPurchaseCreateModal"]','.bm-voucher-heading']
        ],function(_,cfg){
            var $root=$(cfg[0]);if(!$root.length)return;
            var $header=$root.find(cfg[2]).first();
            alignActions($header);
            moveCreate($root,cfg[1],$header);
        });
        // Voucher Links remains a real selectable control beside Search.
        $.each([
            ['.cheque-register-page','#cheque-link-filter','#cheque-filter-form','.cheque-table-panel','.cheque-heading'],
            ['.online-register-page','#online-link-filter','#online-filters','.online-table','.online-heading']
        ],function(_,cfg){
            var $root=$(cfg[0]);if(!$root.length)return;
            var $link=$root.find(cfg[1]),$form=$(cfg[2]);
            var $toolbar=$root.find(cfg[3]+'>.d-flex').first();
            var $search=$toolbar.children('div').last();
            if($link.length&&$toolbar.length){
                var $label=$('<label>',{'class':'bm-inline-link-filter mb-0'}).text('Voucher Links');
                // TomSelect hides its original <select>. Keep that original
                // inside the hidden form, and build a clean native toolbar select.
                // This avoids the label-only bug on both registers.
                var $native=$('<select>',{'class':'form-control form-control-sm fvd-voucher-links',
                    id:$link.attr('id')+'-toolbar','aria-label':'Filter voucher linkage'});
                $link.children('option').each(function(){
                    $native.append($('<option>',{value:$(this).val(),text:$(this).text()}));
                });
                $link.removeAttr('name');
                $native.val($link.val()||'');
                $label.append($native);
                if($search.length)$search.before($label);else $toolbar.append($label);
                var $hidden=$('<input>',{type:'hidden',name:'link_status'}).appendTo($form);
                var sync=function(){$hidden.val($native.val()||'');$link.val($native.val()||'');};
                sync();
                $native.on('change.bmLinks',function(){sync();$form.trigger('submit');});
                $form.on('reset.bmLinks',function(){$native.val('');$hidden.val('');});
            }
        });
        // One viewport-height contract for every modern voucher directory.
        // CR remains the verified reference and keeps its own matching sizing code.
        var $directories=$('.bm-voucher-directory').not('.daily-voucher-page');
        $directories.addClass('fvd-fit-viewport');
        function fitDirectories(){
            var $footer=$('.main-footer:visible').first(),footerHeight=$footer.length?$footer.outerHeight():0;
            $directories.each(function(){
                var $page=$(this),top=$page.offset().top;
                var h=Math.max(320,$(window).height()-top-footerHeight-8);
                $page.css('--fvd-directory-height',h+'px');
            });
        }
        fitDirectories();
        $(window).on('resize.fvdLayout',fitDirectories);
        // Draft vouchers: style existing server-rendered list, preserve approval/reject/edit buttons.
        var $draft=$('.bm-modern-draft');
        if ($draft.length) {
            // Draft has no New Voucher and no standalone top filter button.
            $draft.find('#toggleFilters').remove();
            var $draftTable=$draft.find('#bmDraftTable');
            // The list card, table chrome and footer share the CR contract;
            // preserve original server-rendered rows and approval buttons.
            var $draftCard=$draftTable.closest('.custom_card');
            if($draftCard.length){
                // The server Blade nests three visually redundant cards. Move
                // the actual permitted register intact into the shared
                // viewport-height card, preserving all existing handlers/forms.
                $draftCard.detach().addClass('bm-voucher-table-card fvc-draft-list-card')
                    .appendTo($draft);
                $draft.children('.content-header,section.content').hide();
            }
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
            $('#modal-charge-type,#modal-edit,#modal-view,#modal-approve,#modal-delete').addClass('fvc-modern-modal fvc-modal-purple');
            var extraSaveCompleted=false;
            function refreshExtraDirectory(){
                var $table=$extra.find('#example1');
                if($.financeVoucherFeedback && $table.length &&
                    $.fn.DataTable && $.fn.DataTable.isDataTable($table)){
                    $.financeVoucherFeedback.tableSkeleton($table,true);
                }
                $.ajax({url:window.location.href,type:'GET',dataType:'html',
                    cache:false,headers:{'X-Requested-With':'XMLHttpRequest'}})
                .done(function(html){
                    // The page still owns its Laravel-rendered, project-scoped
                    // rows. Refresh the DataTable without navigating away.
                    var $response=$('<div>').append($.parseHTML(html,document,false));
                    var $source=$response.find('#example1');
                    if($table.length&&$source.length&&$.fn.DataTable&&
                        $.fn.DataTable.isDataTable($table)){
                        var api=$table.DataTable();
                        var rows=$source.find('tbody>tr').filter(function(){
                            return $(this).children('td').length===
                                $table.find('thead tr:first th').length;
                        }).toArray();
                        api.clear();
                        if(rows.length)api.rows.add(rows);
                        api.draw(false);
                        mountExtraPaymentActions();
                        if($.blueVoucherRegisterActions && $.blueVoucherRegisterActions.enhance)
                            $.blueVoucherRegisterActions.enhance($table);
                    }
                    var $old=$extra.find('#charge_type'),$fresh=$response.find('#charge_type');
                    if($old.length&&$fresh.length){
                        if($old[0].tomselect)$old[0].tomselect.destroy();
                        $old.empty().append($fresh.children('option').clone());
                        if($.financeVoucherFeedback &&
                           $('#bmExtraEntryModal').hasClass('show'))
                            $.financeVoucherFeedback.enhanceSelects($old);
                    }
                }).fail(function(){
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.toast('error',
                      'Saved, but the register could not refresh. Please refresh the listing.');
                }).always(function(){
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.tableSkeleton($table,false);
                });
            }
            // Shared, project-safe AJAX form submission. Existing endpoints
            // still own validation, authorization, posting and idempotency.
            function submitExtraAjax($entry,onSaved){
                if($entry.data('fvc-busy'))return;
                var $feedback=$entry.find('.fvc-modal-feedback').first();
                if(!$feedback.length){
                    $feedback=$('<div>',{'class':'alert alert-danger fvc-modal-feedback d-none',
                        role:'alert','aria-live':'polite'}).prependTo($entry);
                }
                $feedback.addClass('d-none').text('');
                $entry.data('fvc-busy',true);
                var $buttons=$entry.find('[type="submit"],.bm-extra-save').prop('disabled',true);
                var $loadingBody=$entry.is('#voucherForm')?
                    $('#bmExtraConfirmModal .modal-body'):$entry.closest('.modal-body');
                if($.financeVoucherFeedback)$.financeVoucherFeedback.skeleton($loadingBody,true);
                $.ajax({
                    url:$entry.attr('action'),type:($entry.attr('method')||'POST').toUpperCase(),
                    data:new FormData($entry[0]),processData:false,contentType:false,dataType:'json',
                    headers:{Accept:'application/json'}
                }).done(function(response){
                    if(response&&response.status==='success'){
                        if($.financeVoucherFeedback)$.financeVoucherFeedback.toast('success',response.message||'Saved.');
                        onSaved(response);
                    }
                    else $feedback.removeClass('d-none').text('The server did not confirm the save.');
                }).fail(function(xhr){
                    var data=xhr.responseJSON||{};
                    var message=$.financeVoucherFeedback?
                        $.financeVoucherFeedback.error(xhr,'Unable to save. Check the entered data.'):
                        'Unable to save. Check the entered data.';
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.toast('error',message);
                    $feedback.text(message).removeClass('d-none');
                    if($entry.is('#voucherForm')){
                        $('#bmExtraConfirmModal').modal('hide');
                        $('#bmExtraEntryModal').modal('show');
                    }
                }).always(function(){
                    $entry.data('fvc-busy',false);$buttons.prop('disabled',false);
                    if($.financeVoucherFeedback)$.financeVoucherFeedback.skeleton($loadingBody,false);
                    $('#bmExtraConfirmSave').prop('disabled',false);
                });
            }
            var $chargeTypeForm=$('#modal-charge-type form').first();
            $chargeTypeForm.on('submit.fvcAjax',function(event){
                event.preventDefault();
                submitExtraAjax($(this),function(){
                    $('#modal-charge-type').modal('hide');
                    $chargeTypeForm[0].reset();
                    refreshExtraDirectory();
                });
            });
            var $form=$extra.find('#voucherForm');
            $form.on('submit.fvcAjax',function(event){
                event.preventDefault();
                submitExtraAjax($form,function(){
                    extraSaveCompleted=true;
                    $('#bmExtraConfirmModal').modal('hide');
                    $('#bmExtraEntryModal').modal('hide');
                    refreshExtraDirectory();
                });
            });
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
                $modal.addClass('fvc-modern-modal fvc-modal-purple');
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
                $confirm.addClass('fvc-modern-modal fvc-modal-purple');
                $('body').append($confirm);
                $form.on('click.bmExtraSave','.bm-extra-save',function(event){
                    event.preventDefault();
                    $modal.one('hidden.bs.modal',function(){$confirm.modal('show');});
                    $modal.modal('hide');
                });
                $confirm.on('click','#bmExtraConfirmSave',function(){
                    if($(this).prop('disabled'))return;
                    $(this).prop('disabled',true);
                    $form.trigger('submit');
                });
                $confirm.on('hidden.bs.modal',function(){
                    $('#bmExtraConfirmSave').prop('disabled',false);
                    if(extraSaveCompleted){extraSaveCompleted=false;return;}
                    if (!$('body').hasClass('modal-open')) $modal.modal('show');
                });
                if($extra.attr('data-extra-errors')==='1'){
                    $modal.modal('show');
                }
                $('<button>',{type:'button','class':'btn btn-primary btn-sm'})
                    .html('<i class="fas fa-plus mr-1"></i> New voucher')
                    .on('click',function(){$modal.modal('show');})
                    .appendTo($head.find('.bm-extra-actions'));
            }
            // Pending online/cheque collections are a separate finance workflow:
            // retain their original POST forms and make the panel independently accessible.
            var $pendingHeading=$extra.find('h5').filter(function(){
                return $.trim($(this).text()).indexOf('Extra Charge Online / Cheque Pending Clearance')===0;
            }).first();
            if($pendingHeading.length){
                var $pendingPanel=$pendingHeading.closest('.card-body').detach();
                var $pendingModal=$('<div>',{'class':'modal fade bm-extra-pending-modal',id:'bmExtraPendingModal',tabindex:'-1',role:'dialog','aria-hidden':'true'})
                    .append($('<div>',{'class':'modal-dialog modal-dialog-scrollable modal-xl',role:'document'})
                    .append($('<div>',{'class':'modal-content'})
                    .append($('<div>',{'class':'modal-header'})
                    .append($('<h5>',{'class':'modal-title'}).text('Extra Charge Pending Clearance'))
                    .append($('<button>',{type:'button','class':'close','data-dismiss':'modal'}).html('&times;')))
                    .append($('<div>',{'class':'modal-body'}).append($pendingPanel))));
                $pendingModal.addClass('fvc-modern-modal fvc-modal-purple');
                $('body').append($pendingModal);
                $('<button>',{type:'button','class':'btn btn-outline-warning btn-sm'})
                    .text('Pending Clearance')
                    .on('click',function(){$pendingModal.modal('show');})
                    .appendTo($head.find('.bm-extra-actions'));
            }
            // Expand an existing row's Cash/Online/Cheque collection forms on demand
            // rather than occupying a very wide table column permanently.
            var $paymentModal=$('<div>',{'class':'modal fade bm-extra-payment-modal',id:'bmExtraPaymentModal',tabindex:'-1',role:'dialog','aria-hidden':'true'})
                .append($('<div>',{'class':'modal-dialog modal-dialog-scrollable modal-lg',role:'document'})
                .append($('<div>',{'class':'modal-content'})
                .append($('<div>',{'class':'modal-header'})
                .append($('<h5>',{'class':'modal-title'}).text('Extra Charge Collection'))
                .append($('<button>',{type:'button','class':'close','data-dismiss':'modal'}).html('&times;')))
                .append($('<div>',{'class':'modal-body'}))));
            $paymentModal.addClass('fvc-modern-modal fvc-modal-purple');
            $('body').append($paymentModal);
            var $activePayment=null;
            function mountExtraPaymentActions(){
             $extra.find('#example1 tbody tr').each(function(){
                var $cell=$(this).children('td').eq(10);
                if(!$cell.find('form').length ||
                   $cell.find('.bm-extra-payment-fields,.bm-extra-payment-open').length)return;
                var $holder=$('<div>',{'class':'bm-extra-payment-fields'});
                $holder.append($cell.contents().detach()).hide().appendTo($cell).data('bm-owner',$cell);
                $('<button>',{type:'button','class':'btn btn-outline-primary btn-sm bm-extra-payment-open'})
                    .text('Receive / Manage')
                    .on('click',function(){
                        $activePayment=$holder.detach().show();
                        $paymentModal.find('.modal-body').append($activePayment);
                        $paymentModal.modal('show');
                    }).prependTo($cell);
             });
            }
            mountExtraPaymentActions();
            // DataTables redraws rows on pagination, searching and AJAX
            // refresh. Reattach only newly-rendered payment controls.
            $extra.find('#example1').on('draw.dt.fvcExtraPayments',mountExtraPaymentActions);
            $paymentModal.on('hidden.bs.modal',function(){
                if($activePayment){
                    var $owner=$activePayment.data('bm-owner');
                    if($owner&&$owner.length)$activePayment.detach().hide().appendTo($owner);
                    $activePayment=null;
                }
            });
        }
    });
})(jQuery);
