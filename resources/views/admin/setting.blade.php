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
.settings-screen .settings-modal-trigger{display:flex;justify-content:flex-end;margin:0 0 12px}
.settings-screen .settings-field-image{max-width:96px;max-height:65px;object-fit:contain;margin:0 0 6px}
.settings-screen .settings-section-title{border-bottom:1px solid #cad8e5;padding-bottom:8px;margin:8px 0 15px;font-size:17px;font-weight:700;color:#224b70}
.settings-screen .settings-info-form{max-width:1200px}
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
                                    <div class="tab-pane {{ $voucherSettingsTabActive ? 'active' : '' }}" id="voucher-settings">
                                        <h4>Voucher display prefixes</h4>
                                        <p class="text-muted">Financial year starts in January. Existing voucher IDs and posted entries stay intact.</p>
                                        @if(!$voucherDisplaySettingsReady)
                                            <div class="alert alert-warning">Voucher display settings migration is required.</div>
                                        @else
                                            <form data-ajax-form method="POST" action="{{ route('setting.voucher-display.save') }}" class="form-row align-items-end">@csrf
                                                <div class="form-group col-md-3"><label>Voucher type</label><select name="series" class="form-control">@foreach(\App\Services\Finance\ProjectVoucherDisplay::SERIES as $series)<option value="{{ $series }}">{{ $series }}</option>@endforeach</select></div>
                                                <div class="form-group col-md-3"><label>Prefix</label><input name="prefix" class="form-control" maxlength="12" placeholder="CR" required></div>
                                                <div class="form-group col-md-3"><button class="btn btn-primary" @if(!$canManageFinanceSettings) disabled @endif>Save prefix</button></div>
                                            </form>
                                            @error('prefix')<div class="text-danger settings-form-error">{{ $message }}</div>@enderror
                                            <table class="table table-sm"><thead><tr><th>Type</th><th>Prefix</th><th>Example</th></tr></thead><tbody>
                                            @foreach(\App\Services\Finance\ProjectVoucherDisplay::SERIES as $series)
                                                <tr><td>{{ $series }}</td><td>{{ $voucherDisplaySettings[$series] ?? $series }}</td><td>{{ $voucherDisplaySettings[$series] ?? $series }}-{{ now()->year }}-0001</td></tr>
                                            @endforeach
                                            </tbody></table>
                                        @endif
                                        <hr>
                                        <h4 class="settings-section-title">Voucher Print Headers</h4>
                                        <p class="text-muted">Choose which voucher families show the project-branded receipt header. These settings apply globally as before.</p>
                                        <form id="settings-receipt-header-form" action="{{ route('setting.update') }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            @php($receiptHeaderSettings = \App\Models\Setting::where('type','toggle')->whereIn('key',array_map(fn($type) => $type.'_Receipt_Header',$types))->get()->keyBy('key'))
                                            <div class="table-responsive"><table class="table table-bordered table-sm settings-receipt-table">
                                                <thead><tr><th>Voucher Name</th><th>Print Header</th></tr></thead><tbody>
                                                @foreach($types as $type)
                                                    @php($keyType = $type.'_Receipt_Header')
                                                    <tr><td>{{ $type }} Receipt Header</td><td>
                                                        <div class="custom-control custom-switch">
                                                            <input type="hidden" name="toggles[{{ $keyType }}]" value="0">
                                                            <input type="checkbox" class="custom-control-input settings-receipt-toggle" id="setting-receipt-{{ $type }}" name="toggles[{{ $keyType }}]" value="1" @checked((int)($receiptHeaderSettings->get($keyType)?->value ?? 0)===1) @cannot('update setting') disabled @endcannot>
                                                            <label class="custom-control-label" for="setting-receipt-{{ $type }}">{{ (int)($receiptHeaderSettings->get($keyType)?->value ?? 0)===1 ? 'ON':'OFF' }}</label>
                                                        </div>
                                                    </td></tr>
                                                @endforeach
                                                </tbody></table></div>
                                        </form>
                                        <hr><h4>Custom payment methods</h4>
                                        <p class="text-muted">Each custom name maps to Cash, Online or Cheque. Voucher forms will offer these methods after their posting integration is complete.</p>
                                        @if(!$customPaymentMethodsReady)
                                            <div class="alert alert-warning">Custom payment method migration is required.</div>
                                        @else
                                            <form data-ajax-form method="POST" action="{{ route('setting.custom-payment-method.save') }}" class="form-row align-items-end">@csrf
                                                <div class="form-group col-md-2"><label>Code</label><input name="code" class="form-control" maxlength="20" required></div>
                                                <div class="form-group col-md-3"><label>Name</label><input name="name" class="form-control" maxlength="80" required></div>
                                                <div class="form-group col-md-3"><label>Behavior</label><select name="base_method_code" class="form-control"><option value="1">Cash</option><option value="2">Online</option><option value="3">Cheque</option></select></div>
                                                <input type="hidden" name="is_active" value="1">
                                                <div class="form-group col-md-2"><button class="btn btn-primary" @if(!$canManageFinanceSettings) disabled @endif>Add method</button></div>
                                            </form>
                                            <table class="table table-sm"><thead><tr><th>Code</th><th>Name</th><th>Behavior</th><th>Status</th></tr></thead><tbody>
                                            @foreach($customPaymentMethods as $method)
                                                <tr><td>{{ $method->code }}</td><td>{{ $method->name }}</td><td>{{ [1 => 'Cash', 2 => 'Online', 3 => 'Cheque'][$method->base_method_code] ?? '-' }}</td><td>{{ $method->is_active ? 'Active' : 'Inactive' }}</td></tr>
                                            @endforeach
                                            </tbody></table>
                                        @endif
                                    </div>
                                    <div class="tab-pane {{ $activeSettingsTab === 'information' ? 'active' : '' }}" id="information" data-settings-direct="1">
                                        <form id="settings-information-form" class="settings-info-form" action="{{ route('setting.update') }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            @method('PUT')
                                            @foreach(['information'=>'Application Information','contact'=>'Contact Details'] as $groupName=>$groupTitle)
                                                @php($groupSettings = \App\Models\Setting::where('type','!=','toggle')->where('category',$groupName)->get())
                                                @if($groupSettings->isNotEmpty())
                                                <h4 class="settings-section-title">{{ $groupTitle }}</h4>
                                                @foreach($groupSettings as $set)
                                                <div class="form-group row">
                                                    <label class="col-md-3 col-form-label" for="setting-{{ $set->key }}">{{ str_replace('Application ', '', $set->name) }}</label>
                                                    <div class="col-md-9">
                                                        <input type="hidden" name="key[]" value="{{ $set->key }}">
                                                        @if($set->type === 'text')
                                                            <input id="setting-{{ $set->key }}" class="form-control" type="text" name="value[]" value="{{ $set->value }}" required>
                                                        @elseif($set->type === 'textarea')
                                                            <textarea id="setting-{{ $set->key }}" class="form-control" rows="3" name="value[]" required>{{ $set->value }}</textarea>
                                                        @elseif($set->type === 'file')
                                                            <img class="settings-field-image" id="{{ $set->key }}-image" src="{{ $set->value ? asset($set->value) : '' }}" alt="{{ $set->name }}" @if(!$set->value) style="display:none" @endif>
                                                            <input id="{{ $set->key }}" name="value[]" value="{{ $set->value }}" class="form-control" readonly data-settings-file="1">
                                                            <small class="text-primary">Click the path to select a file from File Manager</small>
                                                        @endif
                                                        <small class="text-danger settings-input-error" data-error-key="{{ $set->key }}"></small>
                                                    </div>
                                                </div>
                                                @endforeach
                                                @endif
                                            @endforeach
                                            <div class="d-flex justify-content-end pt-2 border-top">
                                                <button type="submit" class="btn btn-primary" @cannot('update setting') disabled @endcannot>Save Information &amp; Contact</button>
                                            </div>
                                        </form>
                                    </div>
                                    @include('admin.attendance.settings-tab')
                                    <div class="tab-pane {{ $financeSettingsTabActive ? 'active' : '' }}" id="bank-accounts">
                                        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                                        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
                                        @if (!$financeSettingsSchemaReady)
                                            <div class="alert alert-warning">Project bank account settings are not installed. Apply the project bank account migration before configuring accounts.</div>
                                        @elseif (!$canManageFinanceSettings)
                                            <div class="alert alert-info">Select your assigned project and sign in as a project administrator to manage bank accounts.</div>
                                        @else
                                            @if(!$paymentMethodLabelsReady)<div class="alert alert-warning">Payment method label settings are not installed yet. Apply their migration to enable project-level labels.</div>@else
                                                <div class="card card-outline card-info"><div class="card-header"><strong>Payment method labels</strong><small class="text-muted ml-2">Codes and cash/transfer/cheque behavior stay fixed for old records.</small></div><div class="card-body">
                                                    <form data-ajax-form method="POST" action="{{ route('setting.payment-method-labels.save') }}" class="form-row align-items-end">@csrf
                                                        @foreach([1 => 'Cash', 2 => 'Online', 3 => 'Check'] as $code => $defaultLabel)
                                                            <div class="form-group col-md-3"><label>{{ $defaultLabel }} label (legacy code {{ $code }})</label><input class="form-control" name="labels[{{ $code }}]" maxlength="40" required value="{{ old('labels.'.$code, $projectPaymentMethodLabels->get($code, $defaultLabel)) }}"></div>
                                                        @endforeach
                                                        <div class="form-group col-md-3"><button class="btn btn-primary btn-block">Save method labels</button></div>
                                                    </form>
                                                </div></div>
                                            @endif
                                            <div class="row">
                                                <div class="col-lg-5">
                                                    <div class="card card-outline card-primary"><div class="card-header"><strong>{{ $projectBankAccount ? 'Edit project bank account' : 'Add project bank account' }}</strong></div><div class="card-body">
                                                        <form data-ajax-form method="POST" action="{{ route('setting.bank-account.save') }}">@csrf
                                                            @if($projectBankAccount)<input type="hidden" name="bank_account_id" value="{{ $projectBankAccount->id }}">@endif
                                                            <div class="form-group"><label>Bank name</label><input class="form-control" name="bank_name" maxlength="120" required value="{{ old('bank_name', $projectBankAccount->bank_name ?? '') }}" placeholder="e.g. Meezan Bank"></div>
                                                            <div class="form-group"><label>Account title</label><input class="form-control" name="account_title" maxlength="120" required value="{{ old('account_title', $projectBankAccount->account_title ?? '') }}"></div>
                                                            <div class="form-group"><label>Project Chart of Accounts mapping</label><select class="form-control" name="account_mapping_id" required><option value="">Select an active account</option>@foreach($projectBankMappings as $mapping)<option value="{{ $mapping->id }}" @selected((string)old('account_mapping_id', $projectBankAccount->account_mapping_id ?? '') === (string)$mapping->id)>{{ $mapping->headAccounting->name ?? 'Head' }} / {{ $mapping->subheadAccounting->name ?? 'Account' }}@if(!$mapping->headAccounting?->is_active || !$mapping->subheadAccounting?->is_active) (inactive historical link)@endif</option>@endforeach</select><small class="text-muted">New active bank accounts require an active mapping in Project #{{ $financeSettingsProjectId }}.</small></div>
                                                            <div class="form-group"><label>Branch</label><input class="form-control" name="branch" maxlength="120" value="{{ old('branch', $projectBankAccount->branch ?? '') }}"></div>
                                                            <div class="form-group"><label>Account number</label><input class="form-control" name="account_number" maxlength="255" autocomplete="off" placeholder="{{ $projectBankAccount ? 'Leave blank to keep saved number' : 'Optional account number' }}"><small class="text-muted">Encrypted before database storage. Existing number: @if($projectBankAccount && $projectBankAccount->account_number) ••••{{ substr($projectBankAccount->account_number, -4) }} @else not set @endif</small></div>
                                                            <div class="form-group"><label>Status</label><select class="form-control" name="is_active" required><option value="1" @selected(old('is_active', $projectBankAccount->is_active ?? true))>Active</option><option value="0" @selected((string)old('is_active', $projectBankAccount ? (int)$projectBankAccount->is_active : 1) === '0')>Inactive (history retained)</option></select></div>
                                                            <button class="btn btn-primary">Save bank account</button>@if($projectBankAccount)<a data-ajax-link class="btn btn-outline-secondary" href="{{ route('setting.index', ['tab' => 'bank-accounts']) }}">Cancel edit</a>@endif
                                                        </form>
                                                    </div></div>
                                                </div>
                                                <div class="col-lg-7"><div class="card"><div class="card-header"><strong>Project bank accounts</strong><span class="badge badge-primary float-right">{{ $projectBankAccounts->count() }}</span></div><div class="card-body table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Bank / Branch</th><th>Account title</th><th>COA mapping</th><th>Number</th><th>Status</th><th></th></tr></thead><tbody>
                                                    @forelse($projectBankAccounts as $bankAccount)
                                                        <tr><td>{{ $bankAccount->bank_name }}<br><small>{{ $bankAccount->branch ?: 'Branch not set' }}</small></td><td>{{ $bankAccount->account_title }}</td><td>{{ $bankAccount->accountMapping->headAccounting->name ?? 'Head' }} / {{ $bankAccount->accountMapping->subheadAccounting->name ?? 'Account' }}</td><td>{{ $bankAccount->account_number ? '••••'.substr($bankAccount->account_number, -4) : '—' }}</td><td><span class="badge badge-{{ $bankAccount->is_active ? 'success' : 'secondary' }}">{{ $bankAccount->is_active ? 'Active' : 'Inactive' }}</span></td><td><a data-ajax-link class="btn btn-sm btn-outline-primary" href="{{ route('setting.index', ['tab' => 'bank-accounts', 'bank_account_id' => $bankAccount->id]) }}">Edit</a></td></tr>
                                                    @empty<tr><td colspan="6" class="text-center text-muted">No bank accounts configured for this project.</td></tr>@endforelse
                                                </tbody></table><small class="text-muted">Changes are project-scoped and audited. Deactivation preserves history; bank setup does not rewrite existing vouchers.</small></div></div></div>
                                            </div>
                                            <div class="card mt-3"><div class="card-header"><strong>Recent bank account changes</strong><small class="text-muted ml-2">Latest 100 · account numbers are masked in audit details</small></div><div class="card-body table-responsive"><table class="table table-sm table-striped"><thead><tr><th>When</th><th>Action</th><th>Bank account</th><th>Changed by</th><th>Before / after</th></tr></thead><tbody>
                                                @forelse($projectBankAccountAudits as $audit)
                                                    <tr><td>{{ $audit->created_at }}</td><td>{{ ucfirst($audit->action) }}</td><td>{{ $audit->bank_name }} · {{ $audit->account_title }}</td><td>{{ $audit->actor_name ?? 'System' }}</td><td><small>{{ $audit->old_values ? json_encode(json_decode($audit->old_values, true)) : '—' }} → {{ json_encode(json_decode($audit->new_values, true)) }}</small></td></tr>
                                                @empty<tr><td colspan="5" class="text-center text-muted">No bank account changes recorded.</td></tr>@endforelse
                                            </tbody></table></div></div>
                                            @if($paymentMethodLabelsReady)<div class="card mt-3"><div class="card-header"><strong>Recent payment label changes</strong><small class="text-muted ml-2">Latest 100</small></div><div class="card-body table-responsive"><table class="table table-sm table-striped"><thead><tr><th>When</th><th>Method code</th><th>Previous label</th><th>New label</th><th>Changed by</th></tr></thead><tbody>@forelse($paymentMethodLabelAudits as $audit)<tr><td>{{ $audit->created_at }}</td><td>{{ $audit->method_code }}</td><td>{{ $audit->old_label ?? '—' }}</td><td>{{ $audit->new_label }}</td><td>{{ $audit->actor_name ?? 'System' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No payment label changes recorded.</td></tr>@endforelse</tbody></table></div></div>@endif
                                        @endif
                                    </div>
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
@endsection
@section('js')
    <script>
        (() => {
            let nextModal = 0;
            function modalizeSettingsForms(root=document){
                const $root=$(root);
                $root.find('form[method="POST"]:not([data-settings-modalized]):not(#settings-information-form):not(#settings-receipt-header-form)').each(function(){
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
        $(document).ready(function() {
            // input
            let inputId = '';

            $('.settings-field-image').on('error',function(){ $(this).hide(); });

            function fmSetLink($url) {
                $(inputId).val($url.substring(1));
                $(inputId + '-image').attr("src", "{{ asset(null) }}" + $url.substring(1)).show();
            }

            window.fmSetLink = fmSetLink;

            $(document).on("click", '[data-settings-file="1"]', function(event) {
                event.preventDefault();
                inputId = '#' + this.id;
                window.open('/file-manager/fm-button', 'fm', 'width=800,height=600');
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
        $form.on('submit',function(e){
            e.preventDefault();e.stopImmediatePropagation();
            var form=this,send=function(){
                // The hidden PUT method is passed through FormData for Laravel.
                var formData=new FormData(form);
                var $button=$form.find('button[type=submit]').prop('disabled',true);
                $.ajax({url:$form.attr('action'),type:'POST',data:formData,processData:false,contentType:false,
                    headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},dataType:'json'})
                .done(function(response){alertUser(response.message||'Information saved.',false);})
                .fail(function(xhr){
                    var message=xhr.responseJSON&&xhr.responseJSON.message||'Unable to save information.';
                    if(xhr.responseJSON&&xhr.responseJSON.errors)$.each(xhr.responseJSON.errors,function(_,v){message=v[0]||message;return false;});
                    alertUser(message,true);
                }).always(function(){$button.prop('disabled',false);});
            };
            if(window.Swal)Swal.fire({icon:'question',title:'Save Information & Contact?',showCancelButton:true,
                confirmButtonText:'Yes, Save',cancelButtonText:'No'}).then(function(result){if(result.isConfirmed)send();});
            else if(window.confirm('Save information and contact?'))send();
        });
        $receipt.on('change','.settings-receipt-toggle',function(){
            var $input=$(this),old=!this.checked,key=$input.attr('name');
            if($input.data('busy'))return;
            var apply=function(){
                var payload={_token:$receipt.find('[name=_token]').val(),_method:'PUT'};
                payload[key]=$input.prop('checked')?'1':'0';
                $input.data('busy',true).prop('disabled',true);
                putSettings($receipt,payload,function(ok){
                    if(!ok)$input.prop('checked',old);
                    $input.siblings('label').text($input.prop('checked')?'ON':'OFF');
                    $input.prop('disabled',false).data('busy',false);
                });
            };
            if(window.Swal)Swal.fire({icon:'question',title:'Update Print Header?',showCancelButton:true,
                confirmButtonText:'Yes, Apply',cancelButtonText:'No'})
                .then(function(result){if(result.isConfirmed)apply();else $input.prop('checked',old);});
            else if(window.confirm('Update print header?'))apply();
            else $input.prop('checked',old);
        });
    });
    </script>
@endsection
