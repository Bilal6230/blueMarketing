@extends('admin.layouts.master')
@section('content')
<style>
.settings-screen .card{border:1px solid #e3eaf4;border-radius:14px;box-shadow:0 8px 26px rgba(26,48,83,.06);overflow:hidden}.settings-screen .card-header{background:linear-gradient(110deg,#f5f9ff,#fff);padding:11px 15px}.settings-screen .nav-pills .nav-link{border-radius:9px;padding:7px 12px;font-size:.9rem;font-weight:600}.settings-screen .form-control{border-radius:8px;min-height:36px;height:36px;font-size:.9rem}.settings-screen .btn{border-radius:8px;font-weight:600}.settings-screen .table thead{background:#eff5fc;color:#304a6c}.settings-screen .tab-pane{min-height:150px}.settings-screen .table-responsive{max-height:360px;overflow:auto}
.settings-screen .settings-modal-trigger{display:flex;justify-content:flex-end;margin:0 0 12px}.settings-screen .modal-content{border:0;border-radius:14px;box-shadow:0 18px 55px #172b4d33}.settings-screen .modal-header{background:#f5f8fd;border-radius:14px 14px 0 0}.settings-screen .modal-body{max-height:70vh;overflow:auto}.settings-screen label{font-size:.88rem}
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
                        <div class="card">
                            <div class="card-header p-2">
                                <ul class="nav nav-pills">
                                    @foreach ($category as $i)
                                        <li class="nav-item"><a class="nav-link {{ $activeSettingsTab === $i->category ? 'active' : '' }}"
                                                href="#{{ $i->category }}"
                                                data-toggle="tab">{{ Str::ucfirst($i->category) }}</a></li>
                                    @endforeach
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
                                    @foreach ($category as $i)
                                        @php
                                            $settings = \App\Models\Setting::where('type', '!=', 'toggle')->where(['category' => $i->category])->get();
                                        @endphp
                                        <div class="tab-pane {{ $activeSettingsTab === $i->category ? 'active' : '' }}"
                                            id="{{ $i->category }}">
                                            <form data-ajax-form class="form-horizontal" action="{{ route('setting.update') }}"
                                                method="POST">
                                                @csrf
                                                @method('PUT')
                                                @foreach ($settings as $set)
                                                    <div class="form-group row">
                                                        <label for="{{ $set->key }}"
                                                            class="col-sm-2 col-form-label">{{ str_replace('Application ', '', $set->name) }}</label>
                                                        <div class="col-sm-10">
                                                            @if ($set->type == 'text')
                                                                <input type="hidden" name="key[]"
                                                                    value="{{ $set->key }}">
                                                                <input type="text" name="value[]"
                                                                    value="{{ $set->value }}" class="form-control"
                                                                    id="{{ $set->key }}"
                                                                    placeholder="{{ $set->name }}" required>
                                                            @elseif($set->type == 'textarea')
                                                                <input type="hidden" name="key[]"
                                                                    value="{{ $set->key }}">
                                                                <textarea type="text" name="value[]" rows="3" class="form-control" id="{{ $set->key }}"
                                                                    placeholder="{{ $set->name }}" required>{{ $set->value }}</textarea>
                                                            @elseif($set->type == 'file')
                                                                <img src="{{ asset($set->value) }}"
                                                                    alt="{{ $set->name }}"
                                                                    id="{{ $set->key }}-image" width="8%">
                                                                <div class="input-group">
                                                                    <input type="hidden" name="key[]"
                                                                        value="{{ $set->key }}">
                                                                    <input type="text" readonly class="form-control"
                                                                        placeholder="{{ $set->name }}" name="value[]"
                                                                        id="{{ $set->key }}"
                                                                        value="{{ $set->value }}" readonly required>
                                                                    {{-- <div class="input-group-append">
                                                                        <button class="btn btn-primary" type="button" id="{{ $set->key }}button">Pilih Foto</button>
                                                                    </div> --}}
                                                                </div>
                                                                <small class="text-primary">Click to select file</small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                                @php
                                                    $settings = \App\Models\Setting::where('type', 'toggle')->get();
                                                @endphp
                                                @foreach ($types as $type)
                                                    @php
                                                        $keyType = $type . '_Receipt_Header';
                                                    @endphp

                                                    <div class="form-group row">
                                                        <label class="col-sm-2 col-form-label">
                                                            {{ str_replace('Application ', '', $type) }} Receipt Header
                                                        </label>

                                                        <div class="col-sm-10">
                                                            <div class="custom-control custom-switch">

                                                                {{-- always send 0 --}}
                                                                <input type="hidden" name="toggles[{{ $keyType }}]" value="0">

                                                                {{-- checked sends 1 --}}
                                                                <input type="checkbox"
                                                                    class="custom-control-input"
                                                                    id="{{ $type }}"
                                                                    name="toggles[{{ $keyType }}]"
                                                                    value="1"
                                                                    {{ $settings->where('key', $keyType)->first()?->value == 1 ? 'checked' : '' }}>

                                                                <label class="custom-control-label" for="{{ $type }}">
                                                                    {{ $type }} Receipt Header
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                                <div class="form-group row">
                                                    <div class="offset-sm-2 col-sm-10">
                                                        <button type="submit" class="btn btn-primary">Save</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    @endforeach
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
    @php
        $setts = \App\Models\Setting::where(['type' => 'file'])->get();
    @endphp
    @foreach ($setts as $set)
        @php
            $setid[] = '#' . $set->key;
            $jsonsetid = json_encode($setid, true);
        @endphp
    @endforeach
    <script>
        (() => {
            let nextModal = 0;
            function modalizeSettingsForms(root = document) {
                root.querySelectorAll('form[method="POST"]:not([data-settings-modalized])').forEach((form) => {
                    if (!form.closest('.settings-screen .tab-pane')) return;
                    const pane = form.closest('.tab-pane'); if (!pane) return;
                    const id = `settingsEditor${++nextModal}`;
                    const label = form.action.includes('bank-account') ? 'Bank account'
                        : form.action.includes('attendance') ? (form.action.includes('mapping') ? 'Employee ID mapping' : 'Attendance device')
                        : form.action.includes('voucher-display') ? 'Voucher prefix'
                        : form.action.includes('payment-method-labels') ? 'Payment method labels'
                        : form.action.includes('custom-payment-method') ? 'Payment method' : 'Settings';
                    const launcher = document.createElement('div'); launcher.className = 'settings-modal-trigger';
                    const open = document.createElement('button'); open.type = 'button'; open.className = 'btn btn-sm btn-outline-primary'; open.dataset.toggle = 'modal'; open.dataset.target = `#${id}`; open.textContent = `Configure ${label}`;
                    launcher.appendChild(open);
                    const modal = document.createElement('div'); modal.className = 'modal fade'; modal.id = id; modal.tabIndex = -1; modal.innerHTML = `<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">${label}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"></div></div></div>`;
                    form.parentElement.insertBefore(launcher, form); modal.querySelector('.modal-body').appendChild(form); pane.appendChild(modal); form.dataset.settingsModalized = '1';
                    if (form.querySelector('[name="bank_account_id"]') || pane.querySelector('.alert-danger,.settings-form-error')) { if (window.jQuery) window.jQuery(modal).modal('show'); }
                });
                root.querySelectorAll('.alert-success,.alert-danger').forEach((alert) => {
                    if (!window.toastr || !alert.closest('.settings-screen')) return;
                    const message=alert.innerText.trim(); if(!message) return;
                    alert.classList.contains('alert-success') ? toastr.success(message) : toastr.error(message);
                    alert.remove();
                });
                root.querySelectorAll('.settings-form-error').forEach((message) => {
                    if (!window.toastr) return;
                    toastr.error(message.innerText.trim());
                    message.remove();
                });
            }
            document.addEventListener('DOMContentLoaded', () => modalizeSettingsForms());
            document.addEventListener('ajax:panel-updated', (event) => modalizeSettingsForms(event.detail?.panel || document));
        })();
    </script>
    <script>
        $(document).ready(function() {
            // input
            let inputId = '';

            function fmSetLink($url) {
                $(inputId).val($url.substring(1));
                $(inputId + '-image').attr("src", "{{ asset(null) }}" + $url.substring(1));
            }

            window.fmSetLink = fmSetLink;

            $(document).on("click", '{{ implode(',', $setid) }}', function(event) {
                event.preventDefault();
                inputId = '#' + event.target.id;
                window.open('/file-manager/fm-button', 'fm', 'width=800,height=600');
            });
        });
    </script>
@endsection
