<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use App\Models\AttendanceDevice;
use App\Models\User;
use App\Models\ProjectBankAccount;
use App\Models\ProjectHeadSubhead;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function index()
    {
        $x['title']     = 'Settings';
        $x['types'] = Ledger::distinct()->pluck('type')->toArray();
        $x['category']  = Setting::where('type', '!=', 'toggle')->select('category')->groupBy('category')->get();
        $categoryNames = $x['category']->pluck('category')->all();
        $requestedTab = (string) request()->query('tab', '');
        $x['activeSettingsTab'] = $requestedTab === ''
            ? ($categoryNames[0] ?? null)
            : (in_array($requestedTab, $categoryNames, true) ? $requestedTab : null);
        $x['attendanceTabActive'] = $requestedTab === 'attendance-machine';
        $x['financeSettingsTabActive'] = $requestedTab === 'bank-accounts';
        $x['voucherSettingsTabActive'] = $requestedTab === 'voucher-settings';
        $projectId = (int) getSelectedTown();
        $isAttendanceAdmin = auth()->user()->hasAnyRole(['admin', 'superadmin']);
        if (!auth()->user()->hasRole('superadmin') && $projectId > 0) {
            abort_unless((int) auth()->user()->project_id === $projectId, 403);
        }
        $x['attendanceTablesAvailable'] = Schema::hasTable('attendance_devices') && Schema::hasTable('attendance_device_user_mappings');
        $x['attendanceDevices'] = $isAttendanceAdmin && $x['attendanceTablesAvailable'] && $projectId > 0
            ? AttendanceDevice::where('project_id', $projectId)->orderBy('name')->get()
            : collect();
        $x['attendanceDevice'] = $isAttendanceAdmin && $x['attendanceTablesAvailable'] && $projectId > 0
            ? AttendanceDevice::with(['mappings.user:id,name'])->where('project_id', $projectId)
                ->when(request()->filled('device_id'), fn ($q) => $q->whereKey(request('device_id')))
                ->when(!request()->filled('device_id') && !request()->boolean('new_device'), fn ($q) => $q->orderBy('id'))
                ->when(request()->boolean('new_device'), fn ($q) => $q->whereRaw('1 = 0'))
                ->first()
            : null;
        $x['attendanceEmployees'] = $isAttendanceAdmin && $projectId > 0
            ? User::where('project_id', $projectId)->where('status_id', 1)->orderBy('name')->get(['id', 'name'])
            : collect();
        $x['attendanceProjectId'] = $projectId;
        $x['canManageAttendanceDevice'] = $isAttendanceAdmin;
        $x['financeSettingsProjectId'] = $projectId;
        $x['voucherDisplaySettingsReady'] = Schema::hasTable('project_voucher_display_settings') && Schema::hasTable('project_voucher_display_setting_audits');
        $x['customPaymentMethodsReady'] = Schema::hasTable('project_custom_payment_methods') && Schema::hasTable('project_custom_payment_method_audits');
        $x['customPaymentMethods'] = $x['customPaymentMethodsReady'] && $projectId > 0
            ? DB::table('project_custom_payment_methods')->where('project_id', $projectId)->orderBy('name')->get()
            : collect();
        $x['voucherDisplaySettings'] = $x['voucherDisplaySettingsReady'] && $projectId > 0
            ? DB::table('project_voucher_display_settings')->where('project_id', $projectId)->pluck('prefix', 'series')
            : collect();
        $x['canManageFinanceSettings'] = $isAttendanceAdmin && auth()->user()->can('update setting') && $projectId > 0;
        $financeSchemaReady = Schema::hasTable('project_bank_accounts') && Schema::hasTable('project_bank_account_audits');
        $x['financeSettingsSchemaReady'] = $financeSchemaReady;
        $defaultPaymentLabels = [1 => 'Cash', 2 => 'Online', 3 => 'Check'];
        $x['projectPaymentMethodLabels'] = $projectId > 0 && Schema::hasTable('project_payment_method_labels')
            ? collect($defaultPaymentLabels)->map(fn ($label, $code) => DB::table('project_payment_method_labels')
                ->where('project_id', $projectId)->where('method_code', $code)->value('label') ?: $label)
            : collect($defaultPaymentLabels);
        $x['paymentMethodLabelsReady'] = Schema::hasTable('project_payment_method_labels') && Schema::hasTable('project_payment_method_label_audits');
        $x['paymentMethodLabelAudits'] = $x['paymentMethodLabelsReady'] && $projectId > 0
            ? DB::table('project_payment_method_label_audits as audit')->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
                ->where('audit.project_id', $projectId)->orderByDesc('audit.created_at')->limit(100)
                ->get(['audit.method_code', 'audit.old_label', 'audit.new_label', 'audit.created_at', 'actor.name as actor_name'])
            : collect();
        $x['projectBankAccounts'] = $financeSchemaReady && $projectId > 0
            ? ProjectBankAccount::with(['accountMapping.headAccounting', 'accountMapping.subheadAccounting'])
                ->where('project_id', $projectId)->orderByDesc('is_active')->orderBy('bank_name')->get()
            : collect();
        $x['projectBankAccount'] = $financeSchemaReady && $projectId > 0
            ? ProjectBankAccount::where('project_id', $projectId)->when(request()->filled('bank_account_id'),
                fn ($q) => $q->whereKey((int) request('bank_account_id')))->first()
            : null;
        $x['projectBankMappings'] = $financeSchemaReady && $projectId > 0
            ? ProjectHeadSubhead::availableForPosting()->with(['headAccounting:id,name', 'subheadAccounting:id,name'])
                ->where('project_id', $projectId)->whereNull('plot_id')->whereNull('customer_id')
                ->orderBy('head_accounting_id')->orderBy('subhead_accounting_id')->get()
            : collect();
        $x['projectBankAccountAudits'] = $financeSchemaReady && $projectId > 0
            ? DB::table('project_bank_account_audits as audit')
                ->join('project_bank_accounts as bank', function ($join) { $join->on('bank.id', '=', 'audit.bank_account_id')->on('bank.project_id', '=', 'audit.project_id'); })
                ->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
                ->where('audit.project_id', $projectId)
                ->orderByDesc('audit.created_at')->limit(100)
                ->get(['audit.action', 'audit.old_values', 'audit.new_values', 'audit.created_at', 'bank.bank_name', 'bank.account_title', 'actor.name as actor_name'])
            : collect();
        if ($x['projectBankAccount'] && !$x['projectBankMappings']->contains('id', $x['projectBankAccount']->account_mapping_id)) {
            $legacyMapping = ProjectHeadSubhead::with(['headAccounting:id,name', 'subheadAccounting:id,name'])
                ->where('project_id', $projectId)->whereKey($x['projectBankAccount']->account_mapping_id)->first();
            if ($legacyMapping) $x['projectBankMappings']->prepend($legacyMapping);
        }
        return view('admin.setting', $x);
    }

    public function saveVoucherDisplaySettings(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'superadmin']) && $request->user()->can('update setting'), 403);
        abort_unless(Schema::hasTable('project_voucher_display_settings') && Schema::hasTable('project_voucher_display_setting_audits'), 409,
            'Voucher display settings migration is required.');
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!$request->user()->hasRole('superadmin')) abort_unless((int) $request->user()->project_id === $projectId, 403);
        $validator = Validator::make($request->all(), ['series' => ['required', 'in:CR,CP,JV,CV,BO,BR,PPR,SV,ECP'],
            'prefix' => ['required', 'regex:/^[A-Z][A-Z0-9]{1,11}$/D']], [
                'series.in' => 'Select a supported voucher type.',
                'prefix.regex' => 'The voucher prefix format is invalid. Use 2–12 uppercase letters or numbers, starting with a letter.',
                'prefix.required' => 'Enter a voucher prefix.',
            ]);
        if ($validator->fails()) {
            return redirect()->route('setting.index', ['tab' => 'voucher-settings'])
                ->withErrors($validator)->withInput();
        }
        $data = $validator->validated();
        DB::transaction(function () use ($projectId, $data, $request): void {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $query = DB::table('project_voucher_display_settings')->where('project_id', $projectId)->where('series', $data['series']);
            $old = $query->lockForUpdate()->value('prefix');
            if ($old === $data['prefix']) return;
            if ($old === null) {
                $query->insert(['project_id' => $projectId, 'series' => $data['series'],
                    'prefix' => $data['prefix'], 'financial_year_start_month' => 1,
                    'updated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $query->update(['prefix' => $data['prefix'], 'updated_by' => $request->user()->id, 'updated_at' => now()]);
            }
            DB::table('project_voucher_display_setting_audits')->insert([
                'project_id' => $projectId, 'series' => $data['series'], 'old_prefix' => $old,
                'new_prefix' => $data['prefix'], 'actor_id' => $request->user()->id, 'created_at' => now(),
            ]);
        }, 3);
        return redirect()->route('setting.index', ['tab' => 'voucher-settings'])->with('success', 'Voucher display prefix saved. Existing voucher IDs were not changed.');
    }

    public function saveCustomPaymentMethod(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'superadmin']) && $request->user()->can('update setting'), 403);
        abort_unless(Schema::hasTable('project_custom_payment_methods') && Schema::hasTable('project_custom_payment_method_audits'), 409,
            'Custom payment method migration is required.');
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!$request->user()->hasRole('superadmin')) abort_unless((int) $request->user()->project_id === $projectId, 403);
        $data = $request->validate([
            'method_id' => ['nullable', 'integer', 'min:1'],
            'code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,19}$/D'],
            'name' => ['required', 'string', 'max:80'],
            'base_method_code' => ['required', 'integer', 'in:1,2,3'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.regex' => 'The payment method code format is invalid. Use 2–20 uppercase letters, numbers, or underscores, starting with a letter.',
            'code.required' => 'Enter a payment method code.',
        ]);
        DB::transaction(function () use ($projectId, $data, $request): void {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $query = DB::table('project_custom_payment_methods')->where('project_id', $projectId);
            $existing = !empty($data['method_id']) ? (clone $query)->where('id', $data['method_id'])->lockForUpdate()->first() : null;
            abort_if(!empty($data['method_id']) && !$existing, 404);
            $duplicate = (clone $query)->where('code', $data['code'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
            abort_if($duplicate, 422, 'This payment method code already exists in this project.');
            if ($existing) {
                abort_if((int) $existing->base_method_code !== (int) $data['base_method_code'], 422,
                    'Payment method behavior cannot be changed. Create a new method instead.');
                abort_if($existing->code !== $data['code'], 422,
                    'Payment method code cannot be changed. Create a new method instead.');
            }
            $values = ['code' => $data['code'], 'name' => trim($data['name']),
                'base_method_code' => (int) $data['base_method_code'], 'is_active' => (bool) $data['is_active']];
            if ($existing) {
                (clone $query)->where('id', $existing->id)->update($values + ['updated_by' => $request->user()->id, 'updated_at' => now()]);
                $methodId = $existing->id;
            } else {
                $methodId = (clone $query)->insertGetId($values + ['project_id' => $projectId,
                    'updated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('project_custom_payment_method_audits')->insert([
                'project_id' => $projectId, 'method_id' => $methodId, 'action' => $existing ? 'updated' : 'created',
                'old_values' => $existing ? json_encode($existing, JSON_THROW_ON_ERROR) : null,
                'new_values' => json_encode($values, JSON_THROW_ON_ERROR), 'actor_id' => $request->user()->id,
                'created_at' => now(),
            ]);
        }, 3);
        return redirect()->route('setting.index', ['tab' => 'voucher-settings'])->with('success', 'Custom payment method saved.');
    }

    public function saveProjectBankAccount(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'superadmin']) && $request->user()->can('update setting'), 403);
        abort_unless(Schema::hasTable('project_bank_accounts') && Schema::hasTable('project_bank_account_audits'), 409,
            'Project bank account migration is required.');
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!$request->user()->hasRole('superadmin')) abort_unless((int) $request->user()->project_id === $projectId, 403);

        $data = $request->validate([
            'bank_account_id' => ['nullable', 'integer', 'min:1'],
            'account_mapping_id' => ['required', 'integer', 'min:1'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_title' => ['required', 'string', 'max:120'],
            'branch' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        try {
            $accountId = DB::transaction(function () use ($request, $data, $projectId): int {
                $account = !empty($data['bank_account_id'])
                    ? ProjectBankAccount::where('project_id', $projectId)->lockForUpdate()->findOrFail((int) $data['bank_account_id'])
                    : new ProjectBankAccount(['project_id' => $projectId, 'created_by' => $request->user()->id]);
                $mappingQuery = ProjectHeadSubhead::where('project_id', $projectId)
                    ->whereNull('plot_id')->whereNull('customer_id')
                    ->whereKey((int) $data['account_mapping_id'])->lockForUpdate();
                if ((int) $data['is_active'] !== 1) $mappingQuery->where(function ($q) use ($account, $data) {
                    $q->whereHas('headAccounting', fn ($h) => $h->where('is_active', 1))
                        ->whereHas('subheadAccounting', fn ($s) => $s->where('is_active', 1));
                    if ($account->exists && (int) $account->account_mapping_id === (int) $data['account_mapping_id']) {
                        $q->orWhereKey((int) $data['account_mapping_id']);
                    }
                });
                else $mappingQuery->whereHas('headAccounting', fn ($h) => $h->where('is_active', 1))
                    ->whereHas('subheadAccounting', fn ($s) => $s->where('is_active', 1));
                $mapping = $mappingQuery->first();
                abort_unless($mapping, 422, 'Select an active account mapping from this project. Inactive mappings can only remain linked to a deactivated bank account.');
                if ($account->exists && (int) $account->account_mapping_id !== (int) $mapping->id) {
                    try {
                        app(\App\Services\Finance\AccountMappingHistoryGuard::class)
                            ->assertNoFinancialHistory((int) $account->account_mapping_id);
                    } catch (\DomainException $exception) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'account_mapping_id' => 'This bank account has posted financial history. Keep its existing accounting mapping and create a separate bank profile for another account.',
                        ]);
                    }
                }
                $old = $account->exists ? $this->bankAccountAuditValues($account) : null;
                $account->fill([
                    'account_mapping_id' => $mapping->id, 'bank_name' => trim($data['bank_name']),
                    'account_title' => trim($data['account_title']), 'branch' => isset($data['branch']) ? trim($data['branch']) : null,
                    'is_active' => (bool) $data['is_active'], 'updated_by' => $request->user()->id,
                ]);
                if (!$account->exists || trim((string) ($data['account_number'] ?? '')) !== '') {
                    $account->account_number = trim((string) ($data['account_number'] ?? '')) ?: null;
                }
                $account->save();
                DB::table('project_bank_account_audits')->insert([
                    'project_id' => $projectId, 'bank_account_id' => $account->id,
                    'action' => $old === null ? 'created' : 'updated',
                    'old_values' => $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR),
                    'new_values' => json_encode($this->bankAccountAuditValues($account), JSON_THROW_ON_ERROR),
                    'actor_id' => $request->user()->id, 'created_at' => now(),
                ]);
                return (int) $account->id;
            }, 3);
        } catch (\Illuminate\Database\QueryException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'account_mapping_id' => 'This accounting mapping is already assigned to another bank account in this project.',
            ]);
        }

        return redirect()->route('setting.index', ['tab' => 'bank-accounts'])
            ->with('success', 'Project bank account settings saved. Existing transaction history was not changed.');
    }

    public function savePaymentMethodLabels(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'superadmin']) && $request->user()->can('update setting'), 403);
        abort_unless(Schema::hasTable('project_payment_method_labels') && Schema::hasTable('project_payment_method_label_audits'), 409,
            'Payment method settings migration is required.');
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!$request->user()->hasRole('superadmin')) abort_unless((int) $request->user()->project_id === $projectId, 403);
        $data = $request->validate([
            'labels' => ['required', 'array'],
            'labels.1' => ['required', 'string', 'max:40'],
            'labels.2' => ['required', 'string', 'max:40'],
            'labels.3' => ['required', 'string', 'max:40'],
        ]);
        DB::transaction(function () use ($data, $projectId, $request): void {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            foreach ([1, 2, 3] as $code) {
                $newLabel = trim($data['labels'][$code]);
                abort_if($newLabel === '', 422, 'Payment method labels cannot be blank.');
                $oldLabel = DB::table('project_payment_method_labels')->where('project_id', $projectId)
                    ->where('method_code', $code)->lockForUpdate()->value('label');
                if ($oldLabel === $newLabel) continue;
                if ($oldLabel === null) {
                    DB::table('project_payment_method_labels')->insert([
                        'project_id' => $projectId, 'method_code' => $code, 'label' => $newLabel,
                        'updated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    DB::table('project_payment_method_labels')->where('project_id', $projectId)->where('method_code', $code)
                        ->update(['label' => $newLabel, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
                }
                DB::table('project_payment_method_label_audits')->insert([
                    'project_id' => $projectId, 'method_code' => $code, 'old_label' => $oldLabel,
                    'new_label' => $newLabel, 'actor_id' => $request->user()->id, 'created_at' => now(),
                ]);
            }
        }, 3);
        return redirect()->route('setting.index', ['tab' => 'bank-accounts'])->with('success', 'Project payment method labels saved. Their accounting behavior and legacy codes are unchanged.');
    }

    private function bankAccountAuditValues(ProjectBankAccount $account): array
    {
        $number = (string) ($account->account_number ?? '');
        return [
            'account_mapping_id' => (int) $account->account_mapping_id, 'bank_name' => $account->bank_name,
            'account_title' => $account->account_title, 'branch' => $account->branch,
            'account_number_last4' => $number === '' ? null : substr($number, -4), 'is_active' => (bool) $account->is_active,
        ];
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'  => ['required', 'string'],
            'role'      => ['required']
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        try {
            $setting = Setting::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'password'  => bcrypt($request->password)
            ]);
            $setting->assignRole($request->role);
            Alert::success('Notification', 'Data <b>' . $setting->name . '</b> successfully Save')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Data <b>' . $setting->name . '</b> failed to Save : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function show(Request $request)
    {
        $setting = Setting::find($request->id);
        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data setting by id',
            'data'      => $setting
        ], Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => ['nullable'],
            'toggles' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            /**
             * Normal settings (text, textarea, file)
             */
            if ($request->filled('key')) {
                foreach ($request->key as $i => $key) {
                    $keyValue = str_replace('_', ' ', $key);
                    $value = $request->value[$i] ?? null;

                    $setting = Setting::firstOrNew(['key' => $key]);

                    if ($setting->exists) {
                        $setting->update([
                            'value' => $value
                        ]);
                    } else {
                        $setting->fill([
                            'key'      => $key,
                            'value'    => $value,
                            'name'     => $keyValue,
                            'type'     => 'text',
                            'category' => 'information',
                        ])->save();
                    }
                }
            }

            /**
             * Toggle settings
             */
            if ($request->filled('toggles')) {
                foreach ($request->toggles as $key => $value) {
                    $keyValue = str_replace('_', ' ', $key);

                    $setting = Setting::firstOrNew(['key' => $key]);

                    if ($setting->exists) {
                        $setting->update([
                            'value' => $value
                        ]);
                    } else {
                        $setting->fill([
                            'key'      => $key,
                            'value'    => $value,
                            'name'     => $keyValue,
                            'type'     => 'toggle',
                            'category' => 'information',
                        ])->save();
                    }
                }
            }

            Alert::success('Notification', 'Settings saved successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Settings failed to save : ' . $th->getMessage())->toToast()->toHtml();
        }

        return back();
    }

    public function destroy(Request $request)
    {
        try {
            $setting = Setting::find($request->id);
            $setting->delete();
            Alert::success('Notification', 'Data <b>' . $setting->name . '</b> successfully deleted')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Data <b>' . $setting->name . '</b> failed to delete : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }
}
