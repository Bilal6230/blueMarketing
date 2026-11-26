@extends('admin.layouts.master')
@section('content')
<style>
html.settings-s1-viewport,body.settings-s1-viewport{overflow:hidden!important;height:100dvh}
body.settings-s1-viewport .wrapper{height:100dvh;overflow:hidden}
body.settings-s1-viewport .main-footer{position:fixed;bottom:0;left:0;right:0;z-index:12}
.settings-screen{height:auto;min-height:0;padding-bottom:0}
.settings-screen .content-header{padding:10px 0 6px;flex:none}
.settings-screen .content-header h1{font-size:22px}
.settings-screen .content,.settings-screen .content>.container-fluid,.settings-screen .content>.container-fluid>.row,.settings-screen .content>.container-fluid>.row>.col-md-12{min-height:0;display:flex;flex:1;flex-direction:column}
.settings-screen .content{padding-bottom:0}
.settings-screen .settings-main-card{border:1px solid #b5c4d4;border-radius:9px;box-shadow:0 5px 18px #1a305314;overflow:hidden;display:flex;flex:1;min-height:0;flex-direction:column;margin-bottom:0}
.settings-screen .settings-main-card>.card-header{flex:none;background:#f5f8fc;padding:9px 12px;border-bottom:1px solid #bfceda}
.settings-screen .settings-main-card>.card-body{display:flex;min-height:0;flex:1;overflow:hidden;padding:12px}
.settings-screen .settings-main-card .tab-content{flex:1;min-height:0;overflow:auto;overscroll-behavior:contain}
.settings-screen .nav-pills .nav-link{border-radius:7px;padding:7px 12px;font-size:14px;font-weight:600}
.settings-screen .form-control{border-radius:6px;min-height:36px;height:36px;font-size:14px}
.settings-screen textarea.form-control{height:auto;min-height:80px}
.settings-screen .btn{border-radius:6px;font-weight:600;font-size:14px}
.settings-screen label{font-size:14px;font-weight:600}
.settings-screen .table{font-size:14px}
.settings-screen .table thead{background:#eaf2f9;color:#304a6c}
.settings-screen .table-responsive{max-height:calc(100dvh - 340px);overflow:auto}
.settings-screen .tab-pane{min-height:0}
/* Reusable full-height workspace convention for Settings tabs (S2 onward).
   Individual tab sections opt in instead of stretching legacy form tabs. */
.settings-screen .settings-main-card .tab-content>.tab-pane.settings-fill-tab{height:100%;min-height:0}
.settings-screen .settings-fill-tab .settings-fill-card{min-height:0;display:flex;flex-direction:column;overflow:hidden}
.settings-screen .settings-fill-tab .settings-fill-scroll{min-height:0;flex:1;overflow:auto;overscroll-behavior:contain}
@media(max-width:1100px){.settings-screen .settings-main-card .tab-content>.tab-pane.settings-fill-tab{height:auto;min-height:100%}}

.settings-screen .settings-modal-trigger{display:flex;justify-content:flex-end;margin:0 0 12px}
.settings-screen .settings-field-image{max-width:96px;max-height:65px;object-fit:contain;margin:0 0 6px}
.settings-screen .settings-section-title{border-bottom:1px solid #cad8e5;padding-bottom:8px;margin:8px 0 15px;font-size:17px;font-weight:700;color:#224b70}
.settings-screen .settings-info-form{max-width:none;width:100%}
.settings-screen .settings-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 20px}
.settings-screen .settings-info-field{min-width:0}
.settings-screen .settings-info-field.is-wide{grid-column:1/-1}
.settings-screen .settings-info-field>label{display:block;font-weight:700;color:#25425e;margin-bottom:6px}
.settings-screen .settings-info-field textarea{min-height:94px;resize:vertical}
.settings-screen .settings-media-field{display:flex;align-items:center;gap:12px;min-width:0}
.settings-screen .settings-media-canvas{height:98px;width:120px;min-width:120px;border:1.5px dashed #9ebbd4;border-radius:10px;background:repeating-linear-gradient(45deg,#f4f9fd,#f4f9fd 10px,#ebf2f8 10px,#ebf2f8 20px);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.settings-screen .settings-media-canvas img{width:100%;height:100%;object-fit:contain;padding:7px}
.settings-screen .settings-media-empty{font-size:12px;font-weight:600;color:#64819b;display:flex;align-items:center;flex-direction:column;gap:4px;text-align:center}
.settings-screen .settings-media-controls{flex:1;min-width:0}
.settings-screen .settings-media-controls input{width:100%;cursor:pointer}
.settings-screen .settings-s1-savebar{display:flex;justify-content:flex-end;position:sticky;bottom:0;padding:11px 0 4px;background:#fff;border-top:1px solid #ccd8e5;z-index:2;margin-top:14px}
.settings-screen .settings-s1-busy{display:none;position:absolute;inset:0;z-index:6;background:#f6f9fce8;align-items:center;justify-content:center}
.settings-screen .settings-s1-busy.is-active{display:flex}
.settings-screen .settings-s1-busy-card{background:#fff;border:1px solid #c8daea;border-radius:14px;box-shadow:0 10px 35px #1d3c5c22;padding:22px;display:flex;align-items:center;gap:16px}
.settings-screen .settings-s1-busy-preview{width:58px;height:58px;position:relative;border-radius:12px;background:#eef5fa;display:flex;align-items:center;justify-content:center;color:#2473b4}
.settings-screen .settings-s1-busy-preview:before{content:'';position:absolute;inset:-5px;border:3px solid #bed7e8;border-top-color:#0a78d5;border-radius:15px;animation:settingsS1Spin 1s linear infinite}
.settings-screen .settings-s1-busy-preview img{max-width:38px;max-height:38px;object-fit:contain}
@keyframes settingsS1Spin{to{transform:rotate(360deg)}}
.settings-screen .settings-s1-modal .modal-content{border-radius:12px;border:0;overflow:hidden}
.settings-screen .settings-s1-modal .modal-header{background:#f1f7fc}
@media(max-width:800px){.settings-screen .settings-info-grid{grid-template-columns:1fr}.settings-screen .settings-media-canvas{height:86px;width:105px;min-width:105px}}

.settings-screen .settings-upload-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:12px 0 18px}
.settings-screen .settings-upload-item{min-width:0}
.settings-screen .settings-upload-item>label{display:block;color:#25425e;margin-bottom:6px}
.settings-screen .settings-dropzone{min-height:137px;border:2px dashed #9bbddb;background:#f7fbff;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:5px;padding:10px;cursor:pointer;transition:border-color .15s,background .15s;position:relative;outline:none}
.settings-screen .settings-dropzone:hover,.settings-screen .settings-dropzone.is-dragover,.settings-screen .settings-dropzone:focus-visible{border-color:#1976d2;background:#e8f4ff}
.settings-screen .settings-dropzone.is-invalid{border-color:#db5353;background:#fff5f5}
.settings-screen .settings-upload-input{position:absolute!important;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none}
.settings-screen .settings-drop-preview{height:80px;width:100%;display:flex;justify-content:center;align-items:center}
.settings-screen .settings-upload-preview{max-width:100%;max-height:80px;object-fit:contain}
.settings-screen .settings-drop-empty{display:flex;align-items:center;justify-content:center;flex-direction:column;gap:5px;color:#6586a4;font-size:13px}
.settings-screen .settings-drop-empty i{font-size:26px;color:#5b98d0}
.settings-screen .settings-drop-caption{font-size:12px;font-weight:600;color:#36709d}
.settings-screen .settings-upload-status{display:block;margin-top:5px;color:#6b7c8e;font-size:12px}
.settings-screen .settings-upload-error{display:block}
@media(max-width:767px){.settings-screen .settings-upload-grid{grid-template-columns:1fr}}
@media(min-width:768px) and (max-width:1050px){.settings-screen .settings-upload-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.settings-screen .settings-dropzone{min-height:120px}}

.settings-screen .settings-info-form .form-group{margin-bottom:12px}
.settings-screen .settings-receipt-table{max-width:900px}
.settings-screen .settings-receipt-table td{vertical-align:middle;padding:8px 11px}
.settings-screen .settings-receipt-table tr:nth-child(even){background:#f0f5f9}
.settings-screen .settings-receipt-table th{padding:9px 11px}
.settings-screen .ajax-panel-loading{opacity:.65;pointer-events:none}
@media(max-width:767px){.settings-screen .settings-main-card>.card-body{padding:8px}.settings-screen .content-header{padding:6px 0}.settings-screen .nav-pills{flex-wrap:nowrap;overflow-x:auto}}
</style>
    <div class="content-wrapper settings-screen">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">{{ $title }}</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card settings-main-card">
                            <div class="card-header p-2">
                                <ul class="nav nav-pills">
                                    <li class="nav-item"><a class="nav-link {{ $activeSettingsTab === 'information' ? 'active' : '' }}" href="#information" data-toggle="tab">Information &amp; Contact</a></li>
                                    <li class="nav-item"><a class="nav-link {{ $attendanceTabActive ? 'active' : '' }}" href="#attendance-machine" data-toggle="tab">Attendance Machine</a></li>
                                    <li class="nav-item"><a class="nav-link {{ $financeSettingsTabActive ? 'active' : '' }}" href="#bank-accounts" data-toggle="tab">Bank Accounts</a></li>
                                    <li class="nav-item"><a class="nav-link {{ $voucherSettingsTabActive ? 'active' : '' }}" href="#voucher-settings" data-toggle="tab">Voucher Display</a></li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    @include('admin.finance.settings-voucher-display')
                                    <div class="tab-pane {{ $activeSettingsTab === 'information' ? 'active' : '' }}" id="information" data-settings-direct="1" style="position:relative">
                                        <div class="settings-s1-busy" aria-live="polite"><div class="settings-s1-busy-card"><div class="settings-s1-busy-preview"><i class="far fa-image fa-lg"></i></div><div><strong>Saving settings</strong><div class="text-muted">Updating information without reloading...</div></div></div></div>
                                        <form id="settings-information-form" class="settings-info-form" action="{{ route('setting.update') }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            @method('PUT')
                                            @foreach(['information'=>'Application Information','contact'=>'Contact Details'] as $groupName=>$groupTitle)
                                                @php($groupSettings = \App\Models\Setting::where('type','!=','toggle')->where('category',$groupName)->get())
                                                @if($groupSettings->isNotEmpty())
                                                    <h4 class="settings-section-title">{{ $groupTitle }}</h4>
                                                    <div class="settings-info-grid">
                                                        @foreach($groupSettings->where('type','!=','file') as $set)
                                                            @php($isWide = $set->type === 'textarea' || str_contains(strtolower((string)$set->key), 'map') || str_contains(strtolower((string)$set->name), 'location'))
                                                            <div class="settings-info-field {{ $isWide ? 'is-wide' : '' }}">
                                                                <label for="setting-{{ $set->key }}">{{ str_replace('Application ', '', $set->name) }}</label>
                                                                <input type="hidden" name="key[]" value="{{ $set->key }}">
                                                                @if($set->type === 'text')
                                                                    <input id="setting-{{ $set->key }}" class="form-control" type="text" name="value[]" value="{{ $set->value }}" required>
                                                                @elseif($set->type === 'textarea')
                                                                    <textarea id="setting-{{ $set->key }}" class="form-control" rows="3" name="value[]" required>{{ $set->value }}</textarea>
                                                                @endif
                                                                <small class="text-danger settings-input-error" data-error-key="{{ $set->key }}"></small>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    @if($groupSettings->where('type','file')->isNotEmpty())
                                                        <div class="settings-upload-grid" aria-label="Application image uploads">
                                                            @foreach($groupSettings->where('type','file') as $set)
                                                                <div class="settings-upload-item">
                                                                    <label for="settings-upload-{{ $set->key }}">{{ str_replace('Application ', '', $set->name) }}</label>
                                                                    <div class="settings-dropzone" data-upload-key="{{ $set->key }}" role="button" tabindex="0" aria-label="Choose or drop {{ $set->name }} image">
                                                                        <input type="file" id="settings-upload-{{ $set->key }}" name="setting_images[{{ $set->key }}]" accept="image/png,image/jpeg,image/webp,image/gif" class="settings-upload-input" tabindex="-1">
                                                                        <div class="settings-drop-preview">
                                                                            <img class="settings-upload-preview" src="{{ $set->value ? asset($set->value) : '' }}" alt="{{ $set->name }} preview" @if(!$set->value) style="display:none" @endif>
                                                                            <div class="settings-drop-empty"><i class="far fa-image"></i><span>Drop image or click</span></div>
                                                                        </div>
                                                                        <span class="settings-drop-caption">Browse or drag image here</span>
                                                                    </div>
                                                                    <small class="settings-upload-status" aria-live="polite">{{ $set->value ? 'Saved image' : 'No image uploaded' }}</small>
                                                                    <small class="text-danger settings-upload-error" aria-live="polite"></small>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @endif
                                            @endforeach
                                            <div class="settings-s1-savebar">
                                                <button type="submit" class="btn btn-primary" @cannot('update setting') disabled @endcannot>Save Information &amp; Contact</button>
                                            </div>
                                        </form>
                                    </div>
                                    @include('admin.attendance.settings-tab')
                                    @include('admin.finance.settings-accounts')
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.row -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <div class="modal fade settings-s1-modal" id="settingsS1Confirm" tabindex="-1" role="dialog" aria-labelledby="settingsS1ConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="settingsS1ConfirmTitle"><i class="far fa-save text-primary mr-2"></i>Confirm Settings</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
                <div class="modal-body">Save updated Information &amp; Contact details?</div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">No, Cancel</button><button type="button" class="btn btn-primary" id="settingsS1ConfirmYes"><i class="fas fa-check mr-1"></i>Yes, Save</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="settingsVoucherToggleConfirm" tabindex="-1" role="dialog" aria-labelledby="settingsVoucherToggleConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="settingsVoucherToggleConfirmTitle">Confirm Voucher Setting</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
            <div class="modal-body" id="settingsVoucherToggleConfirmBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="settingsVoucherToggleConfirmYes">Yes, Apply</button></div>
        </div></div>
    </div>
@endsection
@section('js')
    <script>
        (() => {
            let nextModal = 0;
            function modalizeSettingsForms(root=document){
                const $root=$(root);
                $root.find('form[method="POST"]:not([data-settings-modalized]):not([data-ajax="off"]):not(#settings-information-form):not(#settings-receipt-header-form):not(#as-device-form):not(#as-mapping-form):not(#s3-account-form)').each(function(){
                    const $form=$(this),$pane=$form.closest('.settings-screen .tab-pane');
                    if(!$pane.length)return;
                    const id='settingsEditor'+(++nextModal),action=$form.attr('action')||'';
                    const label=action.includes('bank-account')?'Bank account'
                        :action.includes('attendance')?(action.includes('mapping')?'Employee ID mapping':'Attendance device')
                        :action.includes('voucher-display')?'Voucher prefix'
                        :action.includes('payment-method-labels')?'Payment method labels'
                        :action.includes('custom-payment-method')?'Payment method':'Settings';
                    const $launcher=$('<div>',{'class':'settings-modal-trigger'});
                    const $open=$('<button>',{type:'button','class':'btn btn-sm btn-outline-primary',
                        'id':'settings-launcher-'+id,'data-toggle':'modal','data-target':'#'+id,text:'Configure '+label});
                    $launcher.append($open);
                    const $modal=$('<div>',{'class':'modal fade settings-editor-modal',id,tabindex:-1,'data-launcher':'settings-launcher-'+id});
                    $modal.html('<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"></div></div></div>');
                    $modal.find('.modal-title').text(label);
                    $form.before($launcher);
                    $modal.find('.modal-body').append($form);
                    // Place the actual modal outside hidden tabs/cards so Bootstrap's
                    // backdrop and stacking contexts cannot cover an invisible modal.
                    $('body').append($modal);
                    $form.attr('data-settings-modalized','1');
                    // Do not auto-open on validation errors: several forms share
                    // a tab and a single error previously opened ALL their modals.
                    // Users open the exact form explicitly through its launcher.
                });
                $root.find('.alert-success,.alert-danger').each(function(){
                    const $alert=$(this);
                    if(!window.toastr||!$alert.closest('.settings-screen').length)return;
                    const message=$.trim($alert.text());
                    if(!message)return;
                    ($alert.hasClass('alert-success')?toastr.success:toastr.error)(message);
                    $alert.remove();
                });
                $root.find('.settings-form-error').each(function(){
                    if(!window.toastr)return;
                    toastr.error($.trim($(this).text()));
                    $(this).remove();
                });
            }
            $(function(){ modalizeSettingsForms(document); });
            $(document).on('ajax:panel-updated', (_event, detail) => {
                // AJAX may replace a tab and its launcher. Remove orphaned
                // inactive modal shells rather than leaving stale overlays.
                $('body > .settings-editor-modal').each(function(){
                    const $modal=$(this);
                    if (!$modal.hasClass('show') && !document.getElementById($modal.attr('data-launcher'))) {
                        $modal.remove();
                    }
                });
                modalizeSettingsForms(detail?.panel || document);
            });
        })();
    </script>
    <script>
        $(function(){
            function uploadError($drop,message){
                $drop.toggleClass('is-invalid',!!message);
                $drop.closest('.settings-upload-item').find('.settings-upload-error').text(message||'');
            }
            function validateImage(file){
                if(!file)return 'Select an image file.';
                if(!['image/jpeg','image/png','image/webp','image/gif'].includes(file.type))
                    return 'Use JPG, PNG, WEBP or GIF only.';
                if(file.size>4*1024*1024)return 'Image must be 4 MB or smaller.';
                return '';
            }
            function showSelectedFile($drop,file){
                var problem=validateImage(file);
                uploadError($drop,problem);
                if(problem)return false;
                var $preview=$drop.find('.settings-upload-preview');
                var reader=new FileReader();
                reader.onload=function(event){
                    $preview.attr('src',event.target.result).show();
                    $drop.find('.settings-drop-empty').hide();
                };
                reader.readAsDataURL(file);
                $drop.closest('.settings-upload-item').find('.settings-upload-status')
                    .text(file.name+' • ready to save');
                return true;
            }
            $('.settings-dropzone').on('click',function(event){
                if($(event.target).is('input'))return;
                $(this).find('.settings-upload-input').trigger('click');
            }).on('keydown',function(event){
                if(event.key==='Enter'||event.key===' '){event.preventDefault();$(this).trigger('click');}
            }).on('dragenter dragover',function(event){
                event.preventDefault();event.stopPropagation();$(this).addClass('is-dragover');
            }).on('dragleave',function(event){
                event.preventDefault();event.stopPropagation();$(this).removeClass('is-dragover');
            }).on('drop',function(event){
                event.preventDefault();event.stopPropagation();
                var $drop=$(this).removeClass('is-dragover');
                var files=event.originalEvent&&event.originalEvent.dataTransfer&&event.originalEvent.dataTransfer.files;
                if(!files||!files.length)return;
                if(validateImage(files[0])){uploadError($drop,validateImage(files[0]));return;}
                var input=$drop.find('input[type=file]')[0];
                // Assign a dropped file to the input; FormData then uploads it on Save.
                if(typeof DataTransfer!=='undefined'){
                    var transfer=new DataTransfer();
                    transfer.items.add(files[0]);
                    input.files=transfer.files;
                    showSelectedFile($drop,files[0]);
                }else uploadError($drop,'Drag and drop is unsupported in this browser. Click to browse.');
            });
            $('.settings-upload-input').on('change',function(){
                if(!this.files||!this.files.length)return;
                if(!showSelectedFile($(this).closest('.settings-dropzone'),this.files[0])){
                    // Invalid file cannot be submitted accidentally.
                    $(this).val('');
                }
            });
            $('.settings-upload-preview').on('error',function(){
                $(this).hide();
                $(this).closest('.settings-dropzone').find('.settings-drop-empty').show();
            }).each(function(){
                if(this.getAttribute('src'))$(this).siblings('.settings-drop-empty').hide();
            });
        });
    </script>
    <script>
    $(function(){
        // Keep the shared footer visible while the Settings card itself owns scrolling.
        function fitSettingsCard(){
            var $screen=$('.settings-screen'),$footer=$('.main-footer');
            if(!$screen.length)return;
            $('html, body').addClass('settings-s1-viewport');
            var y=$screen[0].getBoundingClientRect().top;
            var footerTop=$footer.length?$footer[0].getBoundingClientRect().top:window.innerHeight-40;
            var room=footerTop-y-4;
            $screen.css({height:Math.max(300,room)+'px',overflow:'hidden',display:'flex',flexDirection:'column'});
            $screen.find('> .content').css({flex:'1',minHeight:0});
        }
        fitSettingsCard();$(window).on('resize.settingsS1',fitSettingsCard);
        var $form=$('#settings-information-form'), $receipt=$('#settings-receipt-header-form');
        function alertUser(message,error){
            if(window.toastr){if(error)toastr.error(message);else toastr.success(message);}
            else if(window.Swal)Swal.fire({icon:error?'error':'success',text:message});
        }
        function putSettings(form,values,callback){
            var $f=$(form),$btn=$f.find('button[type=submit]');
            $btn.prop('disabled',true);
            $.ajax({url:$f.attr('action'),method:'POST',data:values,
                headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},
                dataType:'json'
            }).done(function(response){alertUser(response.message||'Settings saved.',false);if(callback)callback(true);})
            .fail(function(xhr){
                var message=xhr.responseJSON&&xhr.responseJSON.message||'Unable to save settings.';
                if(xhr.responseJSON&&xhr.responseJSON.errors){
                    $.each(xhr.responseJSON.errors,function(_,value){message=value[0]||message;return false;});
                }
                alertUser(message,true);if(callback)callback(false);
            }).always(function(){$btn.prop('disabled',false);});
        }
        function sendInformationAjax(){
            var form=$form[0],formData=new FormData(form);
            $('.settings-upload-error').text('');
            $('.settings-dropzone').removeClass('is-invalid');
            var $button=$form.find('button[type=submit]');
            $button.prop('disabled',true);
            $('#information .settings-s1-busy').addClass('is-active');
            $.ajax({url:$form.attr('action'),type:'POST',data:formData,processData:false,contentType:false,
                headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},dataType:'json'})
            .done(function(response){
                alertUser(response.message||'Information saved.',false);
                if(response.images){
                    $.each(response.images,function(key,url){
                        var $zone=$('.settings-dropzone').filter(function(){return $(this).attr('data-upload-key')===key;});
                        $zone.find('.settings-upload-preview').attr('src',url).show();
                        $zone.find('.settings-drop-empty').hide();
                        $zone.find('input[type=file]').val('');
                        $zone.closest('.settings-upload-item').find('.settings-upload-status').text('Saved image');
                    });
                }
            })
            .fail(function(xhr){
                var message=xhr.responseJSON&&xhr.responseJSON.message||'Unable to save information.';
                if(xhr.responseJSON&&xhr.responseJSON.errors)$.each(xhr.responseJSON.errors,function(field,issues){
                    if(field.indexOf('setting_images.')===0){
                        var key=field.substring('setting_images.'.length);
                        var $drop=$('.settings-dropzone').filter(function(){return $(this).attr('data-upload-key')===key;});
                        $drop.addClass('is-invalid').closest('.settings-upload-item').find('.settings-upload-error').text(issues[0]||'Invalid image.');
                    }
                    message=issues[0]||message;
                });
                alertUser(message,true);
            }).always(function(){
                $('#information .settings-s1-busy').removeClass('is-active');
                $button.prop('disabled',false);
            });
        }
        $form.on('submit',function(e){
            e.preventDefault();e.stopImmediatePropagation();
            $('#settingsS1Confirm').modal('show');
        });
        $('#settingsS1ConfirmYes').on('click',function(){
            $('#settingsS1Confirm').modal('hide');
            sendInformationAjax();
        });
        $receipt.on('change','.settings-receipt-toggle',function(){
            var $input=$(this),old=!this.checked,next=this.checked,key=$input.attr('name');
            if($input.data('busy')){$input.prop('checked',old);return;}
            // Keep the saved state until the user confirms.
            $input.prop('checked',old);
            var $dialog=$('#settingsVoucherToggleConfirm');
            $('#settingsVoucherToggleConfirmTitle').text('Update Print Header?');
            $('#settingsVoucherToggleConfirmBody').text('Turn Print Header '+(next?'ON':'OFF')+' for this voucher?');
            $('#settingsVoucherToggleConfirmYes').off('click.voucherToggle').one('click.voucherToggle',function(){
                $dialog.modal('hide');
                var payload={_token:$receipt.find('[name=_token]').val(),_method:'PUT'};
                payload[key]=next?'1':'0';
                $input.data('busy',true).prop('disabled',true);
                putSettings($receipt,payload,function(ok){
                    $input.prop('checked',ok?next:old);
                    $input.siblings('label').text($input.prop('checked')?'ON':'OFF');
                    $input.prop('disabled',false).data('busy',false);
                });
            });
            $dialog.modal('show');
        });
    });
    </script>
@endsection
