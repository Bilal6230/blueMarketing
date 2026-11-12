<?php

namespace App\Http\Controllers\Finance;

use App\Models\Lead;
use App\Models\CustomerLedger;
use App\Models\PendingUpdate;
use App\Models\Plot;
use App\Models\User;
use App\Models\Ledger;
use App\Models\AccountType;
use App\Models\Project;
use App\Models\DraftLedger;
use App\Services\Finance\FinancialPendingChangeGuard;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\HeadAccounting;
use App\Models\AccountingExpense;
use App\Models\SubheadAccounting;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\Lead\LeadRepository as lead_repo;

class VoucherController extends Controller
{
    private function projectAccountOptionRelations(): array
    {
        $relations = ['subheadAccounting'];
        if (Schema::hasTable('project_bank_accounts')) {
            $relations[] = 'projectBankAccount';
        }

        return $relations;
    }

    private function typedCashData(Request $request, bool $incoming, ?int $paymentType = null)
    {
        $project = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($project);
        $data = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'start' => ['sometimes', 'integer', 'min:0'], 'length' => ['sometimes', 'integer', 'min:-1', 'max:1000', 'not_in:0'],
        ]);
        try {
            $rows = collect((new \App\Services\Finance\V2OriginalCashReport())->documents($project, $incoming, $data['date_from'] ?? null, $data['date_to'] ?? null));
        } catch (\DomainException $e) { abort(409, 'Cash report integrity checks failed.'); }
        if ($paymentType !== null) {
            $rows = $rows->filter(fn ($row) => (int) ($row['payment_type'] ?? 0) === $paymentType);
        }
        $total = $rows->count();
        foreach (['head_account' => 'head_ids', 'subhead_account' => 'subhead_ids'] as $input => $field) {
            $ids = array_filter(array_map('strval', (array) $request->input($input, [])), fn ($value) => $value !== '');
            if ($ids) { $rows = $rows->filter(fn ($row) => count(array_intersect($ids, array_map('strval', $row[$field]))) > 0); }
        }
        foreach (['voucher_number' => ['voucher_number'], 'search.value' => ['voucher_number', 'detail', 'head', 'subhead', 'submitted_by']] as $input => $fields) {
            $search = trim((string) $request->input($input, ''));
            if ($search !== '') { $rows = $rows->filter(fn ($row) => collect($fields)->contains(fn ($field) => stripos((string) $row[$field], $search) !== false)); }
        }
        foreach (['amount_min' => true, 'amount_max' => false] as $field => $minimum) {
            if ($request->filled($field)) {
                try { $limit = (int) str_replace('.', '', \App\Services\Finance\V2OriginalCustomerWorkflow::money($request->input($field))); }
                catch (\DomainException $e) { abort(422, 'Amount filter is invalid.'); }
                $rows = $rows->filter(fn ($row) => $minimum ? (int) str_replace('.', '', $row['amount']) >= $limit : (int) str_replace('.', '', $row['amount']) <= $limit);
            }
        }
        $length = (int) ($data['length'] ?? 20) === -1 ? null : (int) ($data['length'] ?? 20);
        $page = $rows->sortByDesc('date')->slice($data['start'] ?? 0, $length)->values()->map(function ($row) {
            foreach (['detail', 'head', 'subhead', 'submitted_by', 'project', 'voucher_number'] as $field) { $row[$field] = e($row[$field]); }
            return $row;
        });
        return response()->json(['draw' => (int) $request->input('draw', 1), 'recordsTotal' => $total,
            'recordsFiltered' => $rows->count(), 'data' => $page,
            'scope' => 'typed_v2_postings_only']);
    }

    public function printTypedSource($customerLedger)
    {
        $project = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($project);
        $source = CustomerLedger::query()->whereKey($customerLedger)->where('project_id', $project)->firstOrFail();
        $incoming = in_array((string) $source->transaction_type, ['CR', 'PPR', 'ECP'], true);
        $paymentType = (int) $source->payment_type;
        $voucherTitle = in_array($paymentType, [1, 2, 3], true)
            ? $this->cashVoucherTitle($incoming, $paymentType)
            : ($incoming ? 'Cash Receipt Voucher' : 'Cash Payment Voucher');
        $displayNumber = app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)
            ->lookup($project, 'customer_ledger', (int) $source->id)
            ?: ($source->transaction_type . '-' . $source->type_id);
        try {
            $lines = collect((new \App\Services\Finance\V2OriginalLedgerReport())->rows($project, null, null))
                ->where('source_kind', 'cash')->where('source_id', $source->id)->values();
        } catch (\DomainException $e) { abort(409, 'Voucher print integrity checks failed.'); }
        abort_if($lines->isEmpty(), 404);
        return view('admin.finance.voucher.typed_print', compact('source', 'lines', 'displayNumber', 'voucherTitle'));
    }
    private array $allowedPendingUpdateTables = ['ledgers', 'draft_ledgers', 'customer_ledger', 'leads'];
    private array $financePendingUpdateTables = ['ledgers', 'draft_ledgers', 'customer_ledger'];
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Double Entry Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        // $x['data']      = AccountingExpense::get();


        // $x['data'] = AccountingExpense::where(['is_active' => 1])->get();


        $x['data'] = AccountingExpense::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')->where('is_active', 1)->get();
        // dd($x['data']);


        $projects = Project::get();
        // $headaccounts = HeadAccounting::get();
        // $subheadaccounts = SubheadAccounting::get();

        $x['projects'] = $projects;
        // $x['headaccounts'] = $headaccounts;
        // $x['subheadaccounts'] = $subheadaccounts;
        return view('admin.finance.voucher.accounting_index', $x);
    }
    public function pendingIndex()
    {
        return redirect()->route('approvals.finance.index');
    }


    public function financeApprovalIndex()
    {
        $user = Auth::user();
        $this->authorizeFinanceApproval($user);

        return view('admin.pending-approve.finance_approvals_index', [
            'title' => 'Finance Change Approvals',
            'power' => $user->roles[0]->name ?? 'User',
            'pendingUpdates' => PendingUpdate::with(['submittedBy', 'approvedBy'])
                ->whereIn('table_name', $this->financePendingUpdateTables)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public static function canAccessFinanceApprovalsForUser(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // This legacy helper also authorizes unrelated finance operations;
        // keep its existing access rules. Voucher-specific approval uses
        // VoucherApproverAccess in the dedicated approval controller/service.
        return $user->can('read voucher')
            && ($user->hasRole('super-admin') || $user->hasRole('superadmin') || $user->can('direct-update'));
    }

    protected function authorizeFinanceApproval(?User $user = null): void
    {
        abort_unless(self::canAccessFinanceApprovalsForUser($user ?? Auth::user()), 403);
    }

    protected function resolveFinancePendingModel(string $tableName)
    {
        return match ($tableName) {
            'ledgers' => new Ledger(),
            'draft_ledgers' => new DraftLedger(),
            'customer_ledger' => new CustomerLedger(),
            default => null,
        };
    }

    protected function decodePendingUpdatePayload($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function approveFinancePending($id, Request $request)
    {
        $user = Auth::user();
        $this->authorizeFinanceApproval($user);

        DB::transaction(function () use ($id, $user) {
            $pending = PendingUpdate::query()
                ->whereKey((int) $id)
                ->lockForUpdate()
                ->firstOrFail();

            $model = $this->resolveFinancePendingModel($pending->table_name);
            abort_unless($model !== null, 404);

            if ($pending->status === 'approved') {
                return;
            }

            if ($pending->status !== 'pending') {
                abort(409, 'This request has already been processed.');
            }

            $pendingNewChanges = $this->decodePendingUpdatePayload($pending->new_values);
            try {
                app(FinancialPendingChangeGuard::class)->assertAllowed(
                    (string) $pending->table_name, $pendingNewChanges
                );
            } catch (\DomainException $e) {
                abort(409, 'Posted financial changes require reversal; only safe narrative updates are approvable.');
            }

            $record = $model->newQuery()
                ->whereKey($pending->record_id)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                abort(404, 'Record not found.');
            }

            if ($pending->table_name === 'ledgers'
                && isset($pendingNewChanges['is_active'])
                && $pending->new_values == $pendingNewChanges['is_active']) {
                $customerLedger = $record->customerLedger;
                if ($customerLedger) {
                    $customerLedger->update($pendingNewChanges);
                }
            }

            $record->update($pendingNewChanges);

            $pending->update([
                'status' => 'approved',
                'approved_by' => $user->id,
            ]);
        });

        return back()->with('success', 'Record approved successfully.');
    }

    public function rejectFinancePending($id, Request $request)
    {
        $user = Auth::user();
        $this->authorizeFinanceApproval($user);

        DB::transaction(function () use ($id, $user) {
            $pending = PendingUpdate::query()
                ->whereKey((int) $id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($this->resolveFinancePendingModel($pending->table_name) !== null, 404);

            if ($pending->status === 'rejected') {
                return;
            }

            if ($pending->status !== 'pending') {
                abort(409, 'This request has already been processed.');
            }

            $pending->update([
                'status' => 'rejected',
                'approved_by' => $user->id,
            ]);
        });

        return back()->with('success', 'Voucher rejected.');
    }

    /**
     * Read-only preview of the SAME canonical numeric source/ledger counter
     * used by ProjectVoucherAllocator::next() inside the posting transaction.
     * Never recycle gaps; another user's concurrent save may reserve it first.
     */
    private function previewCanonicalCashNumber(int $projectId, string $type): int
    {
        abort_unless($projectId > 0 && in_array($type, ['CR','CP','CCR','CDR','BOR','BOP'], true), 422);
        $sourceMax = (int) DB::table('customer_ledger')->where('project_id', $projectId)
            ->where('transaction_type', $type)->max('type_id');
        $ledgerMax = (int) DB::table('ledgers as ledger')
            ->join('project_head_subheads as account','account.id','=','ledger.project_head_subheads_id')
            ->where('account.project_id',$projectId)->where('ledger.type',$type)
            ->max('ledger.voucher_number');
        $counterMax = Schema::hasTable('ledger_voucher_counters')
            ? (int) DB::table('ledger_voucher_counters')->where('project_id',$projectId)
                ->where('voucher_type',$type)->value('last_number') : 0;
        return max($sourceMax,$ledgerMax,$counterMax) + 1;
    }

    private function cashVoucherPaymentType(?string $method): ?int
    {
        if ($method === null) {
            return null;
        }

        return match (strtolower($method)) {
            'cash' => 1,
            'online' => 2,
            'cheque' => 3,
            default => abort(404),
        };
    }

    private function cashVoucherDisplaySeries(Request $request, bool $incoming, ?int $paymentType): ?string
    {
        $series = strtoupper((string) $request->query('series', ''));
        if ($series === '') {
            return $paymentType === null ? null : match ([$incoming, $paymentType]) {
                [true, 1] => 'CCR', [false, 1] => 'CDR',
                [true, 2] => 'BOR', [false, 2] => 'BOP',
                [true, 3] => 'BOR', [false, 3] => 'BOP',
            };
        }

        $allowed = [
            'CR' => [true, [1, 2, 3]], 'CCR' => [true, 1], 'BOR' => [true, [2, 3]],
            'DR' => [false, [1, 2, 3]], 'CP' => [false, 1], 'CDR' => [false, 1], 'BOP' => [false, [2, 3]],
        ];
        abort_unless(isset($allowed[$series]) && $allowed[$series][0] === $incoming
            && ($paymentType === null || (is_array($allowed[$series][1])
                ? in_array($paymentType, $allowed[$series][1], true)
                : $allowed[$series][1] === $paymentType)), 404);
        return $series;
    }

    private function cashVoucherTitle(bool $incoming, int $paymentType): string
    {
        $direction = $incoming ? 'Receipt' : 'Payment';
        $method = match ($paymentType) {
            1 => 'Cash',
            2 => 'Bank Online',
            3 => 'Cheque',
        };

        return $method . ' ' . $direction . ' Voucher';
    }

    public function cash_in(Request $request, ?string $method = null)
    {
        $fixedPaymentType = $this->cashVoucherPaymentType($method);
        $displaySeries = $this->cashVoucherDisplaySeries($request, true, $fixedPaymentType);
        $power = Auth::user()->roles[0]->name;
        $x['title'] = match ($displaySeries) {
            'CCR' => 'Cash Credit (CCR)',
            'CR' => 'Credit Voucher (CR)',
            'BOR' => 'Bank Receipt Voucher (BOR)',
            default => $fixedPaymentType === null ? 'Cash In Voucher' : $this->cashVoucherTitle(true, $fixedPaymentType),
        };
        $x['fixedPaymentType'] = $fixedPaymentType;
        $x['displaySeries'] = $displaySeries;
        $x['embeddedVoucher'] = $request->boolean('embed');
        // Embedded entry must fail fast with a real 403 rather than render a
        // full page without #voucherForm (which otherwise looks like a spinner).
        if ($x['embeddedVoucher']) {
            abort_unless(Auth::user()->can('create voucher'), 403, 'Your role needs the create voucher permission to open this form.');
        }
        $x['role'] = $x['embeddedVoucher'] ? collect() : Role::get();
        $x['users'] = $x['embeddedVoucher'] ? collect() : User::get();
        $x['power'] = $power;
        $x['accountTypes'] = AccountType::where('status', 1)->orderBy('id')->get();
        $x['type'] = match($displaySeries) { 'CCR'=>'CCR','BOR'=>'BOR',default=>'CR' };
        $x['class'] = 'cash-in';
        $x['bg_voucher'] = 'info-cash-in';
        $x['theme'] = [
            'mode' => 'cash-in',
            'primary' => 'voucher-primary',
            'secondary' => 'voucher-secondary',
            'card' => 'voucher-card-primary',
            'card_body'=>'card_body_color_in',
        ];
        $x['pagination_color'] = $x['type'] === 'CR'
            ? '#0E7C3A'
            : '#B11226';

        $selectedProjectId = getSelectedTown();
        $x['customPaymentMethods'] = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('project_custom_payment_methods')) {
            $customMethods = DB::table('project_custom_payment_methods')->where('project_id', $selectedProjectId)->where('is_active', 1);
            if ($fixedPaymentType !== null) {
                $customMethods->where('base_method_code', $fixedPaymentType);
            }
            $x['customPaymentMethods'] = $customMethods->orderBy('name')->get();
        }
        $x['data'] = [];

        $x['headaccounts'] = ProjectHeadSubhead::availableForPosting()
            ->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();
        $x['partyaccounts'] = ProjectHeadSubhead::availableForPosting()->with($this->projectAccountOptionRelations())
            ->withSum('ledgers as total_in', 'amount_in')
            ->withSum('ledgers as total_out', 'amount_out')
            ->whereIn('head_accounting_id', $x['headaccounts']->pluck('head_accounting_id'))
            ->where('project_id', $selectedProjectId)
            ->get()
            ->map(function ($item) {
                $item->balance = ($item->total_in ?? 0) - ($item->total_out ?? 0);
                return $item;
            });
        $x['customers'] = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')
            ->where('leads.project_id', $selectedProjectId)
            ->whereNull('bookings.deleted_at') // Exclude soft-deleted bookings
            ->select('leads.*') // Select all columns from the leads table
            ->distinct() // Ensure uniqueness by lead id
            ->get();
        $x['plots'] = Plot::join('bookings', 'plots.id', '=', 'bookings.plot_id')
            ->where('plots.project_id', $selectedProjectId)
            ->get();
        $x['selectedProjectId'] = $selectedProjectId;
        $projects = Project::where('id', $selectedProjectId)->get();
        $x['projects'] = $projects;
        $x['table_data_route'] = $fixedPaymentType === null
            ? route('voucher.cash_in.data')
            : route('voucher.cash_in.method.data', ['method' => $method]);
        $nextNumber = $this->previewCanonicalCashNumber((int) $selectedProjectId, $displaySeries === 'CCR' ? 'CCR' : ($displaySeries === 'BOR' ? 'BOR' : 'CR'));

        $x['latest_voucher_number'] = $nextNumber;

        return view('admin.finance.voucher.cash_voucher', $x);
    }

    public function print($id, ?string $method = null)
    {
        $projectId = (int) getSelectedTown();
        $x['voucher'] = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')
            ->whereKey($id)->whereHas('projectHeadSubhead', fn ($query) => $query->where('project_id', $projectId))
            ->firstOrFail();
        if ($method !== null) {
            $paymentType = $this->cashVoucherPaymentType($method);
            abort_unless(in_array((string) $x['voucher']->type, ['CR', 'CP'], true), 404);
            $source = CustomerLedger::query()->whereKey($x['voucher']->customer_ledger_id)
                ->where('project_id', $projectId)->firstOrFail();
            abort_unless((int) $source->payment_type === $paymentType, 404);
            $x['voucherTitle'] = $this->cashVoucherTitle($x['voucher']->type === 'CR', $paymentType);
        }
        $sourceId = (int) ($x['voucher']->customer_ledger_id ?? 0);
        $x['displayNumber'] = $sourceId > 0
            ? app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)
                ->lookup($projectId, 'customer_ledger', $sourceId)
            : null;
        $x['displayNumber'] ??= app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)
            ->lookup($projectId, 'ledger', (int) $x['voucher']->id);
        $x['voucherTitle'] ??= match ((string) $x['voucher']->type) {
            'CR' => 'Cash Receipt Voucher',
            'CP' => 'Cash Payment Voucher',
            default => (string) $x['voucher']->type . ' Voucher',
        };
        return view('admin.finance.voucher.print-eng', $x);
    }
    public function approve($id, Request $request)
    {
        if (!in_array($request->table, $this->allowedPendingUpdateTables, true)) {
            abort(400, 'Invalid table name.');
        }

        // Legacy generic approval must never bypass the posted-finance
        // permission/locking/field-guard path using DB::table()->update().
        if (in_array($request->table, $this->financePendingUpdateTables, true)) {
            $pending = PendingUpdate::query()
                ->where('record_id', $id)
                ->where('table_name', $request->table)
                ->where('status', 'pending')
                ->firstOrFail();

            return $this->approveFinancePending($pending->id, $request);
        }

        // Generic non-financial pending changes follow the same atomic
        // state transition discipline as the finance-specific pathway.
        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }

        DB::transaction(function () use ($id, $request) {
            $pending = PendingUpdate::query()
                ->where('record_id', $id)
                ->where('table_name', $request->table)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pending->status === 'approved') {
                return;
            }
            abort_unless($pending->status === 'pending', 409, 'This request has already been processed.');

            $changes = $this->decodePendingUpdatePayload($pending->new_values);
            abort_if($changes === [], 422, 'No valid pending changes.');

            $affected = DB::table($request->table)
                ->where('id', $pending->record_id)
                ->update($changes);
            abort_if($affected === 0 && !DB::table($request->table)
                ->where('id', $pending->record_id)->exists(), 404, 'Record not found.');

            $pending->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Record approved successfully.');
    }


    public function reject($id, Request $request)
    {
        if (!in_array($request->table, $this->allowedPendingUpdateTables, true)) {
            abort(400, 'Invalid table name.');
        }

        // Generic finance rejection must use the exact same finance
        // authorization and locked pending-state transition as dedicated
        // finance rejection. Never authorize financial rejection via the
        // broader non-finance direct-update permission.
        if (in_array($request->table, $this->financePendingUpdateTables, true)) {
            $pending = PendingUpdate::query()
                ->where('record_id', $id)
                ->where('table_name', $request->table)
                ->firstOrFail();

            return $this->rejectFinancePending($pending->id, $request);
        }

        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }

        DB::transaction(function () use ($id, $request) {
            $pending = PendingUpdate::query()
                ->where('record_id', $id)
                ->where('table_name', $request->table)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pending->status === 'rejected') {
                return;
            }
            abort_unless($pending->status === 'pending', 409, 'This request has already been processed.');

            $pending->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Voucher rejected.');
    }
    public function GetComment(Request $request)
    {
        // dd($request->all());
        $user = Auth::user();
        $power = $user->roles[0]->name;
        if (isset($request->user)) {
            $user_id = $request->user;
        } else {
            $user_id = $user->id;
        }

        if (isset($request->id)) {
            $leadID = $request->id;
        } else {
            $leadID = null;
        }

        if (isset($request->status)) {
            $status = null;
        } else {
            $status = true;
        }
        $data = Lead_repo::getActiveList($user_id, $status, $leadID, $power);
        if (count($data) == 0) {
            echo "No More Leads";
            exit;
        }
        return response()->json([
            'success' => true,
            'data' => $data[0]->comments,
        ]);
    }
    public function approveAdmin($id, Request $request)
    {
        return $this->approveFinancePending($id, $request);
    }


    public function rejectAdmin($id, Request $request)
    {
        return $this->rejectFinancePending($id, $request);
    }


    public function cash_out(Request $request, ?string $method = null)
    {
        $fixedPaymentType = $this->cashVoucherPaymentType($method);
        $displaySeries = $this->cashVoucherDisplaySeries($request, false, $fixedPaymentType);
        $power = Auth::user()->roles[0]->name;
        $x['title'] = match ($displaySeries) {
            'CDR' => 'Cash Debit (CDR)',
            'DR' => 'Debit Voucher (DR)',
            'BOP' => 'Bank Payment Voucher (BOP)',
            default => $fixedPaymentType === null ? 'Cash Out Voucher' : $this->cashVoucherTitle(false, $fixedPaymentType),
        };
        $x['fixedPaymentType'] = $fixedPaymentType;
        $x['displaySeries'] = $displaySeries;
        $x['embeddedVoucher'] = $request->boolean('embed');
        // Embedded entry must fail fast with a real 403 rather than render a
        // full page without #voucherForm (which otherwise looks like a spinner).
        if ($x['embeddedVoucher']) {
            abort_unless(Auth::user()->can('create voucher'), 403, 'Your role needs the create voucher permission to open this form.');
        }
        $x['role'] = $x['embeddedVoucher'] ? collect() : Role::get();
        $x['users'] = $x['embeddedVoucher'] ? collect() : User::get();
        $x['power'] = $power;
        $x['accountTypes'] = AccountType::where('status', 1)->orderBy('id')->get();
        $x['type'] = match($displaySeries) { 'CDR'=>'CDR','BOP'=>'BOP','DR'=>'DR',default=>'CP' };
        $x['class'] = 'cash-out';
        $x['bg_voucher'] = 'info-cash-out';
        $x['theme'] = [
            'mode' => 'cash-out',
            'primary' => 'voucher-danger-primary',
            'secondary' => 'voucher-danger-secondary',
            'card' => 'voucher-card-danger',
            'card_body'=>'card_body_color_out',
        ];
        $x['pagination_color'] = $x['type'] === 'CR'
            ? '#0E7C3A'
            : '#B11226';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();
        $x['bankSettlementAccounts'] = \Illuminate\Support\Facades\Schema::hasTable('project_bank_accounts')
            ? DB::table('project_bank_accounts')->where('project_id', $selectedProjectId)->where('is_active', 1)
                ->orderBy('bank_name')->orderBy('account_title')->get(['id', 'account_mapping_id', 'bank_name', 'account_title'])
            : collect();
        $x['customPaymentMethods'] = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('project_custom_payment_methods')) {
            $customMethods = DB::table('project_custom_payment_methods')->where('project_id', $selectedProjectId)->where('is_active', 1);
            if ($fixedPaymentType !== null) {
                $customMethods->where('base_method_code', $fixedPaymentType);
            }
            $x['customPaymentMethods'] = $customMethods->orderBy('name')->get();
        }


        // $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
        //     ->where('is_active', 1)
        //     ->whereIn('type', ['CP', 'BO'])  // Use whereIn to check for either 'CP' or 'BO'
        //     ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
        //         $query->where('project_id', $selectedProjectId);
        //     })
        //     ->get();

        // $data = $data->map(function ($item) {
        //     $item['amount'] = floatval($item['amount_in']);

        //     $pending = PendingUpdate::where('table_name', 'ledgers')
        //         ->where('record_id', $item->id)
        //         ->latest()
        //         ->first();
        //     $item['submitted_by'] = $pending ? $pending->submittedBy?->name : '';
        //     $item['new_values'] = $pending ? json_decode($pending->new_values, true) : '';
        //     $item['old_values'] = $pending ? json_decode($pending->old_values, true) : '';
        //     $item['status'] = $pending ? ucfirst($pending->status) : 'Approved';
        //     return $item;
        // });

        // $x['data'] = $data;
        $x['data'] = [];
        $x['headaccounts'] = ProjectHeadSubhead::availableForPosting()->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with(['headAccounting:id,name'])
            ->where('project_id', $selectedProjectId)
            ->get();
        $x['partyaccounts'] = ProjectHeadSubhead::availableForPosting()
            ->select('id', 'project_id', 'head_accounting_id', 'subhead_accounting_id')
            ->with($this->projectAccountOptionRelations())
            ->withSum('ledgers as total_in', 'amount_in')
            ->withSum('ledgers as total_out', 'amount_out')
            ->where('project_id', $selectedProjectId)
            ->whereIn('head_accounting_id', $x['headaccounts']->pluck('head_accounting_id'))
            ->get()
            ->map(function ($item) {
                $item->balance = (float) ($item->total_in ?? 0) - (float) ($item->total_out ?? 0);
                return $item;
            });
        $x['customers'] = Lead::query()
            ->select('id', 'first_name', 'last_name', 'relate', 'father_name', 'phone_number', 'mobile_number', 'nic_number', 'home_address')
            ->where('project_id', $selectedProjectId)
            ->whereExists(function ($q) {
                $q->from('bookings')
                    ->whereColumn('bookings.customer_id', 'leads.id')
                    ->whereNull('bookings.deleted_at');
            })->get();
        $x['plots'] = Plot::query()
            ->select('plots.id as plot_id', 'plots.name', 'plots.type')
            ->where('plots.project_id', $selectedProjectId)
            ->whereExists(function ($q) {
                $q->from('bookings')->whereColumn('bookings.plot_id', 'plots.id')->whereNull('bookings.deleted_at');
            })->get();
        $x['selectedProjectId'] = $selectedProjectId;

        $x['projects'] = Project::query()->select('id', 'project')->where('id', $selectedProjectId)->get();
        $x['table_data_route'] = $fixedPaymentType === null
            ? route('voucher.cash_out.data')
            : route('voucher.cash_out.method.data', ['method' => $method]);
        $nextNumber = $this->previewCanonicalCashNumber((int) $selectedProjectId, $displaySeries === 'CDR' ? 'CDR' : ($displaySeries === 'BOP' ? 'BOP' : 'CP'));

        $x['latest_voucher_number'] = $nextNumber;

        return view('admin.finance.voucher.cash_voucher', $x);
    }

    public function cash_out_data(Request $request, ?string $method = null)
    {
        $paymentType = $this->cashVoucherPaymentType($method);
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) { return $this->typedCashData($request, false, $paymentType); }
        $selectedProjectId = getSelectedTown();

        // Build base query with only the necessary relationships and columns
        $q = Ledger::with([
            'projectHeadSubhead.headAccounting:id,name',
            'projectHeadSubhead.subheadAccounting:id,name',
            'projectHeadSubhead.project:id,project',
            'projectHeadSubhead.plot.bookings' => function ($q) {
                $q->where('cancel_type', 're_sale')
                    ->with([
                        'plot.projectHeadSubheads' => function ($p) {
                            // This ensures we only include project_head_subheads where
                            // both plot_id and customer_id match any re_sale booking
                            $p->whereExists(function ($sub) {
                                $sub->selectRaw(1)
                                    ->from('bookings')
                                    ->whereColumn('bookings.plot_id', 'project_head_subheads.plot_id')
                                    ->whereColumn('bookings.customer_id', 'project_head_subheads.customer_id')
                                    ->where('bookings.cancel_type', 're_sale');
                            });
                        }
                    ]);
            },
        ])
            ->where('is_active', 1)
            ->whereIn('type', $paymentType === null ? ['CP', 'BO'] : ['CP'])
            ->whereHas('projectHeadSubhead', function ($q) use ($selectedProjectId) {
                $q->where('project_id', $selectedProjectId);
            });
        if ($paymentType !== null) {
            $q->whereHas('customerLedger', fn ($source) => $source->where('payment_type', $paymentType));
        }
        // Apply filters only if user has selected them
        $headIds = $request->input('head_account'); // from ajax: d.head_account = $('#filter_head_account').val()

        if (is_string($headIds) && trim($headIds) !== '') {
            $headIds = [$headIds];
        }
        if (is_array($headIds)) {
            $headIds = array_values(array_filter($headIds, fn($v) => $v !== null && $v !== ''));
            if (count($headIds)) {
                $q->whereHas('projectHeadSubhead', function ($qq) use ($headIds) {
                    $qq->whereIn('head_accounting_id', $headIds);
                });
            }
        }

        // Subhead Account filter (IDs)
        $subheadIds = $request->input('subhead_account');

        if (is_string($subheadIds) && trim($subheadIds) !== '') {
            $subheadIds = [$subheadIds];
        }

        if (is_array($subheadIds)) {
            $subheadIds = array_values(array_filter($subheadIds, fn($v) => $v !== null && $v !== ''));
            if (count($subheadIds)) {
                $q->whereHas('projectHeadSubhead', function ($qq) use ($subheadIds) {
                    $qq->whereIn('subhead_accounting_id', $subheadIds);
                });
            }
        }
        if ($request->filled('voucher_number')) {
            $this->applyVoucherDisplayNumberFilter($q, (int) $selectedProjectId, (string) $request->input('voucher_number'));
        }

        // ----------------------------
        // Amount filters (NEW: min/max)
        // ----------------------------
        $sanitizeMoney = function ($v) {
            if ($v === null)
                return null;
            if (is_array($v))
                return null;
            $v = trim((string) $v);
            if ($v === '')
                return null;
            // allow commas from UI and remove spaces
            $v = str_replace([',', ' '], '', $v);
            // keep only digits + dot + minus (just in case)
            $v = preg_replace('/[^0-9\.\-]/', '', $v);
            if ($v === '' || !is_numeric($v))
                return null;
            return (float) $v;
        };

        $amountMin = $sanitizeMoney($request->input('amount_min'));
        $amountMax = $sanitizeMoney($request->input('amount_max'));

        if ($amountMin !== null && $amountMax !== null) {
            // if user accidentally swaps them, fix it
            if ($amountMin > $amountMax) {
                [$amountMin, $amountMax] = [$amountMax, $amountMin];
            }
            $q->whereBetween('amount_out', [$amountMin, $amountMax]);
        } elseif ($amountMin !== null) {
            $q->where('amount_out', '>=', $amountMin);
        } elseif ($amountMax !== null) {
            $q->where('amount_out', '<=', $amountMax);
        } else {
            // Backward compatible fallback: old single "amount" filter
            $legacyAmount = $sanitizeMoney($request->input('amount'));
            if ($legacyAmount !== null) {
                $q->where('amount_out', '>=', $legacyAmount);
            }
        }


        if ($request->filled('date_from')) {
            $q->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $q->whereDate('date', '<=', $request->date_to);
        }


        // Get total count before pagination/search
        $total = (clone $q)->count();

        $searchValue = trim((string) $request->input('search.value', ''));
        $filteredQuery = (clone $q);

        if ($searchValue !== '') {
            $this->applyVoucherSearchFilter($filteredQuery, $searchValue);
        }

        $recordsFiltered = (clone $filteredQuery)->count();

        // Pagination parameters
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 1);

        // Fetch only paginated rows, ordered by latest
        $rows = (clone $filteredQuery)->orderByDesc('id')->skip($start)->take($length)->get();

        // Transform result set
        $displayNumbers = Schema::hasTable('project_voucher_display_numbers')
            ? DB::table('project_voucher_display_numbers')->where('project_id', $selectedProjectId)
                ->where('source_kind', 'customer_ledger')->whereIn('source_id', $rows->pluck('customer_ledger_id')->filter()->unique())
                ->pluck('display_number', 'source_id')
            : collect();
        $ledgerDisplayNumbers = Schema::hasTable('project_voucher_display_numbers')
            ? DB::table('project_voucher_display_numbers')->where('project_id', $selectedProjectId)
                ->where('source_kind', 'ledger')->whereIn('source_id', $rows->pluck('id'))
                ->pluck('display_number', 'source_id')
            : collect();
        $data = $rows->map(function ($i) use ($displayNumbers, $ledgerDisplayNumbers) {
            $amount = $i->type === 'CP' ? (float) $i->amount_out : (float) $i->amount_in;

            // Latest pending update (if any)
            $p = $i->latestPendingUpdate ?? PendingUpdate::where('table_name', 'ledgers')
                ->where('record_id', $i->id)
                ->latest()
                ->first();

            // Always start from a Collection
            $bookings = collect(optional($i->projectHeadSubhead->plot)->bookings);

            // Get all related subheads from resale bookings safely
            $relatedSubheads = $bookings->flatMap(function ($booking) {
                return collect(optional($booking->plot)->projectHeadSubheads);
            });

            // Extract customer + head/subhead names safely
            $customers = $relatedSubheads->map(function ($sh) {
                return [
                    'head' => optional($sh->headAccounting)->name,
                    'subhead' => optional($sh->subheadAccounting)->name,
                ];
            })->filter(fn($c) => !empty($c['subhead']))
                ->unique('subhead')
                ->values()
                ->toArray();

            return [
                'date' => $i->date,
                'voucher_number' => $displayNumbers[$i->customer_ledger_id] ?? $ledgerDisplayNumbers[$i->id] ?? ($i->type . '-' . $i->voucher_number),
                'voucher_number_raw' => (int) $i->voucher_number,
                'project' => optional($i->projectHeadSubhead->project)->project ?? '',
                'head' => optional($i->projectHeadSubhead->headAccounting)->name ?? '',
                'subhead' => trim(
                    (optional($i->projectHeadSubhead->subheadAccounting)->name ?? '') .
                    (count($customers) > 0
                        ? ' <p style="font-size:12px;">(old customers: ' . e(collect($customers)->pluck('subhead')->implode(', ')) . ')</p>'
                        : ''
                    )
                ),
                'detail' => $i->detail,
                'amount' => $amount,
                'status' => $p ? ucfirst($p->status) : 'Approved',
                'submitted_by' => optional($p?->submittedBy)->name ?? '',
                'old_values' => json_decode($p?->old_values, true) ?? [],
                'new_values' => json_decode($p?->new_values, true) ?? [],
                'customers' => $customers, // List of customers + head/subhead
                'id' => $i->id,
                'type' => $i->type,
                'editable' => in_array((string) $i->type, ['CR', 'CP'], true)
                    && $i->customer_ledger_id !== null,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function cash_out_data_old()
    {
        $selectedProjectId = getSelectedTown();

        // Build base query with only the necessary relationships and columns
        $q = Ledger::with([
            'projectHeadSubhead.headAccounting:id,name',
            'projectHeadSubhead.subheadAccounting:id,name',
            'projectHeadSubhead.project:id,project'
        ])
            ->where('is_active', 1)
            ->whereIn('type', ['CP', 'BO'])
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId));

        // Get total count before pagination
        $total = (clone $q)->count();

        // Pagination parameters
        $start = (int) request('start', 0);
        $length = (int) request('length', 10);
        $draw = (int) request('draw', 1);

        // Fetch only paginated rows, ordered by latest
        $rows = $q->orderByDesc('id')->skip($start)->take($length)->get();

        // Transform the result set
        $displayNumbers = Schema::hasTable('project_voucher_display_numbers')
            ? DB::table('project_voucher_display_numbers')->where('project_id', $selectedProjectId)
                ->where('source_kind', 'customer_ledger')->whereIn('source_id', $rows->pluck('customer_ledger_id')->filter()->unique())
                ->pluck('display_number', 'source_id')
            : collect();
        $ledgerDisplayNumbers = Schema::hasTable('project_voucher_display_numbers')
            ? DB::table('project_voucher_display_numbers')->where('project_id', $selectedProjectId)
                ->where('source_kind', 'ledger')->whereIn('source_id', $rows->pluck('id'))
                ->pluck('display_number', 'source_id')
            : collect();
        $data = $rows->map(function ($i) use ($displayNumbers, $ledgerDisplayNumbers) {
            $amount = $i->type === 'CP' ? (float) $i->amount_out : (float) $i->amount_in;

            // Use preloaded relation if available or define relation in Ledger for latestPendingUpdate
            $p = $i->latestPendingUpdate ?? PendingUpdate::where('table_name', 'ledgers')
                ->where('record_id', $i->id)
                ->latest()
                ->first();

            return [
                'date' => $i->date,
                'project' => $i->projectHeadSubhead->project->project ?? '',
                'head' => $i->projectHeadSubhead->headAccounting->name ?? '',
                'subhead' => $i->projectHeadSubhead->subheadAccounting->name ?? '',
                'detail' => $i->detail,
                'amount' => $amount,
                'status' => $p ? ucfirst($p->status) : 'Approved',
                'submitted_by' => $p?->submittedBy?->name ?? '',
                'old_values' => json_decode($p?->old_values, true) ?? [],
                'new_values' => json_decode($p?->new_values, true) ?? [],
                'id' => $i->id,
                'type' => $i->type,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    public function pendingBankPayments(Request $request)
    {
        if (!$request->ajax() && !$request->expectsJson()) {
            return redirect()->route('finance.voucher.in');
        }

        $selectedProjectId = getSelectedTown();

        $query = CustomerLedger::with([
            'customer_list:id,first_name,last_name,phone_number',
            'plot_list:id,name,type',
            'ledger.projectHeadSubhead.subheadAccounting:id,name',
            'bookingVoucher:id,customer_ledger_id,voucher_series,voucher_number',
        ])
            ->where('project_id', $selectedProjectId)
            ->where('is_active', 1)
            ->whereIn('payment_type', [2, 3])
            ->whereIn('transaction_type', ['CR', 'PPR'])
            ->where('passing_status', 0)
            ->orderByDesc('id');

        if ($request->filled('payment_type') && in_array((int) $request->input('payment_type'), [2, 3], true)) {
            $query->where('payment_type', (int) $request->input('payment_type'));
        }

        $pendingSources = $query->get();
        $displayNumbers = Schema::hasTable('project_voucher_display_numbers')
            ? DB::table('project_voucher_display_numbers')->where('project_id', $selectedProjectId)
                ->where('source_kind', 'customer_ledger')->whereIn('source_id', $pendingSources->pluck('id'))
                ->pluck('display_number', 'source_id')
            : collect();
        $pendingRows = $pendingSources
            ->map(function (CustomerLedger $item) use ($displayNumbers) {
                $ledgerType = trim((string) optional($item->ledger)->type);
                $ledgerVoucherNumber = trim((string) optional($item->ledger)->voucher_number);
                $bookingVoucherSeries = trim((string) optional($item->bookingVoucher)->voucher_series);
                $bookingVoucherNumber = trim((string) optional($item->bookingVoucher)->voucher_number);
                $transactionType = trim((string) $item->transaction_type);
                $typeId = trim((string) ($item->type_id ?? ''));
                $reference = trim((string) ($item->reference ?? ''));

                $voucherNumberDisplay = null;
                if ($ledgerType !== '' && $ledgerVoucherNumber !== '') {
                    $voucherNumberDisplay = $ledgerType . '-' . $ledgerVoucherNumber;
                } elseif ($transactionType === 'PPR' && $bookingVoucherSeries !== '' && $bookingVoucherNumber !== '') {
                    $voucherNumberDisplay = $bookingVoucherSeries . '-' . $bookingVoucherNumber;
                } elseif (in_array($transactionType, ['CR', 'CP'], true) && $typeId !== '') {
                    $voucherNumberDisplay = $transactionType . '-' . $typeId;
                } elseif ($reference !== '' && preg_match('/[A-Za-z]/', $reference)) {
                    $voucherNumberDisplay = $reference;
                } elseif ($transactionType !== '' && $reference !== '') {
                    $voucherNumberDisplay = $transactionType . '-' . $reference;
                } else {
                    $voucherNumberDisplay = 'Pending #' . $item->id;
                }
                $voucherNumberDisplay = $displayNumbers[$item->id] ?? $voucherNumberDisplay;

                return [
                    'id' => $item->id,
                    'date' => $item->date,
                    'transaction_type' => $item->transaction_type,
                    'reference' => $item->reference,
                    'voucher_number_display' => $voucherNumberDisplay,
                    'customer' => trim(($item->customer_list?->first_name ?? '') . ' ' . ($item->customer_list?->last_name ?? '')),
                    'plot' => $item->plot_list
                        ? ((((int) $item->plot_list->type === 1) ? 'R' : (((int) $item->plot_list->type === 2) ? 'C' : '')) . '-' . $item->plot_list->name)
                        : '',
                    'bank' => $item->bank_id ? getBankNameById($item->bank_id) : '',
                    't_number' => $item->t_number,
                    'amount' => (float) (($item->amount_out > 0) ? $item->amount_out : $item->amount_in),
                    'passing_date' => $item->passing_date,
                    'payment_type' => (int) $item->payment_type,
                    'child_account' => optional(optional($item->ledger)->projectHeadSubhead)->subheadAccounting?->name,
                    'pending_days' => $item->date ? now()->diffInDays(\Carbon\Carbon::parse($item->date)) : null,
                    'ledger_exists' => (bool) $item->ledger,
                    'ledger_id' => $item->ledger?->id,
                ];
            });

        return response()->json([
            'status' => 'success',
            'count' => $pendingRows->count(),
            'data' => $pendingRows,
        ]);
    }

    private function unavailableV2Workspace(int $projectId, string $title)
    {
        abort_unless($projectId > 0 && DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 404);
        $user = Auth::user();
        abort_unless($user && ($user->hasRole('superadmin') || (int) $user->project_id === $projectId), 403);

        return response()->view('admin.finance.voucher.v2_unavailable', compact('title', 'projectId'));
    }

    /** Dedicated linked bank receipt/payment workspace for the isolated V2 pilot. */
    public function bankVoucherWorkspace(string $direction)
    {
        abort_unless(in_array($direction, ['receipt', 'payment'], true), 404);
        $projectId = (int) getSelectedTown();
        if (!\App\Services\Finance\V2IsolatedConnectionGuard::isAllowed()) {
            return $this->unavailableV2Workspace($projectId, $direction === 'receipt' ? 'Bank Receipt Voucher (BOR)' : 'Bank Payment Voucher (BOP)');
        }
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);

        $bankAccounts = DB::table('project_bank_accounts')
            ->where('project_id', $projectId)->where('is_active', 1)->orderBy('bank_name')->get();
        $accounts = ProjectHeadSubhead::availableForPosting()->with(['headAccounting:id,name', 'subheadAccounting:id,name'])
            ->where('project_id', $projectId)->orderBy('id')->get();

        return view('admin.finance.voucher.bank_linked_workspace', compact('direction', 'bankAccounts', 'accounts'));
    }

    /** Dedicated cash-to-bank deposit/withdrawal entry and register. */
    public function transferVoucherWorkspace(string $kind)
    {
        abort_unless(in_array($kind, ['deposit', 'withdrawal'], true), 404);
        $projectId = (int) getSelectedTown();
        if (!\App\Services\Finance\V2IsolatedConnectionGuard::isAllowed()) {
            return $this->unavailableV2Workspace($projectId, $kind === 'deposit' ? 'Bank Deposit' : 'Bank Withdrawal');
        }
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        $bankAccounts = DB::table('project_bank_accounts')->where('project_id', $projectId)->where('is_active', 1)->orderBy('bank_name')->get();
        $cashAccounts = ProjectHeadSubhead::availableForPosting()->with(['headAccounting:id,name', 'subheadAccounting:id,name'])
            ->where('project_id', $projectId)
            ->where(function ($query) {
                $query->whereHas('headAccounting', fn ($head) => $head->where('name', 'LIKE', '%cash%'))
                    ->orWhereHas('subheadAccounting', fn ($subhead) => $subhead->where('name', 'LIKE', '%cash%'));
            })
            ->orderBy('id')->get();
        return view('admin.finance.voucher.bank_transfer_workspace', compact('kind', 'bankAccounts', 'cashAccounts'));
    }

    /** Multi-plot receipt entry, project scoped to the selected project. */
    public function plotReceiptWorkspace()
    {
        $projectId = (int) getSelectedTown();
        if (!\App\Services\Finance\V2IsolatedConnectionGuard::isAllowed()) {
            return $this->unavailableV2Workspace($projectId, 'Plot Receive Payment');
        }
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        $accounts = ProjectHeadSubhead::availableForPosting()->with(['headAccounting:id,name', 'subheadAccounting:id,name'])
            ->where('project_id', $projectId)
            ->where(fn ($q) => $q->whereHas('headAccounting', fn ($h) => $h->where('name', 'LIKE', '%cash%')->orWhere('name', 'LIKE', '%bank%'))
                ->orWhereHas('subheadAccounting', fn ($s) => $s->where('name', 'LIKE', '%cash%')->orWhere('name', 'LIKE', '%bank%')))
            ->orderBy('id')->get();
        return view('admin.finance.voucher.plot_receipt_workspace', compact('accounts'));
    }

    public function plotReceiptOptions(Request $request)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $accountMap = ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
            ->whereNotNull('customer_id')->whereNotNull('plot_id')
            ->selectRaw('customer_id, plot_id, MIN(id) as id')->groupBy('customer_id', 'plot_id');
        $query = DB::table('bookings as booking')->join('plots as plot', function ($join) use ($projectId) {
                $join->on('plot.id', '=', 'booking.plot_id')->where('plot.project_id', '=', $projectId);
            })->join('leads as customer', function ($join) use ($projectId) {
                $join->on('customer.id', '=', 'booking.customer_id')->where('customer.project_id', '=', $projectId);
            })->leftJoinSub($accountMap, 'account', function ($join) {
                $join->on('account.customer_id', '=', 'booking.customer_id')->on('account.plot_id', '=', 'booking.plot_id');
            })->where('booking.project_id', $projectId)->where('booking.status', 'active')->where('booking.cancel_status', '0');
        if (!empty($data['q'])) {
            $term = '%'.trim($data['q']).'%';
            $query->where(fn ($q) => $q->where('customer.first_name', 'like', $term)->orWhere('customer.last_name', 'like', $term)
                ->orWhere('customer.business', 'like', $term)->orWhere('plot.name', 'like', $term));
        }
        $rows = $query->orderBy('customer.first_name')->orderBy('plot.name')->limit(500)
            ->get(['booking.id as booking_id', 'booking.customer_id', 'booking.plot_id', 'plot.name as plot_name',
                'customer.first_name', 'customer.last_name', 'customer.business', 'account.id as receivable_account_id']);
        return response()->json(['data' => $rows->map(fn ($row) => [
            'booking_id' => (int) $row->booking_id, 'customer_id' => (int) $row->customer_id, 'plot_id' => (int) $row->plot_id,
            'plot' => (string) $row->plot_name,
            'customer' => trim((string) $row->first_name.' '.(string) $row->last_name) ?: (string) ($row->business ?? 'Customer'),
            'receivable_account_id' => $row->receivable_account_id ? (int) $row->receivable_account_id : null,
        ])]);
    }

    public function postPlotReceipt(Request $request)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'], 'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
            'receipt_number' => ['required', 'string', 'max:64'], 'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\.[0-9]{1,2})?$/D'],
            'cash_account_id' => ['required', 'integer', 'min:1'], 'narration' => ['required', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:2', 'max:100'],
            'allocations.*.booking_id' => ['required', 'integer', 'min:1'], 'allocations.*.plot_id' => ['required', 'integer', 'min:1'],
            'allocations.*.receivable_account_id' => ['required', 'integer', 'min:1'],
            'allocations.*.amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\.[0-9]{1,2})?$/D'],
        ]);
        $result = (new \App\Services\Finance\V2PlotEntryWriter())->receive($projectId, (int) $data['customer_id'],
            $data['request_key'], $data, auth()->id());
        return response()->json(['status' => 'success', 'data' => $result], $result['replayed'] ? 200 : 201);
    }

    public function plotReceiptHistory()
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        abort_unless(Schema::hasTable('finance_plot_receipts'), 409, 'Plot receipt storage is not installed.');
        $rows = DB::table('finance_plot_receipts as receipt')->leftJoin('leads as customer', 'customer.id', '=', 'receipt.customer_id')
            ->where('receipt.project_id', $projectId)->orderByDesc('receipt.receipt_date')->orderByDesc('receipt.id')
            ->limit(250)->get(['receipt.id', 'receipt.receipt_number', 'receipt.receipt_date', 'receipt.amount', 'receipt.narration',
                'customer.first_name', 'customer.last_name', 'customer.business']);
        return response()->json(['data' => $rows->map(fn ($row) => [
            'id' => (int) $row->id, 'number' => (string) $row->receipt_number, 'date' => substr((string) $row->receipt_date, 0, 10),
            'customer' => trim((string) $row->first_name.' '.(string) $row->last_name) ?: (string) ($row->business ?? 'Customer'),
            'amount' => (string) $row->amount, 'narration' => (string) $row->narration,
        ])]);
    }

    public function plotReceiptDetails(int $receiptId)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        try { $details = (new \App\Services\Finance\V2PlotEntryWriter())->receipt($projectId, $receiptId); }
        catch (\DomainException $error) { abort(404); }
        $customer = $details['allocations']->first();
        return response()->json(['receipt' => array_merge((array) $details['receipt'], [
            'customer' => $customer ? (trim((string) $customer->first_name.' '.(string) $customer->last_name) ?: (string) ($customer->business ?? 'Customer')) : 'Customer',
        ]), 'allocations' => $details['allocations']]);
    }

    public function printPlotReceipt(int $receiptId, int $plotId)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        try { $details = (new \App\Services\Finance\V2PlotEntryWriter())->receipt($projectId, $receiptId); }
        catch (\DomainException $error) { abort(404); }
        $allocation = $details['allocations']->firstWhere('plot_id', $plotId);
        abort_unless($allocation, 404);
        return view('admin.finance.voucher.plot_receipt_print', ['receipt' => $details['receipt'], 'allocation' => $allocation]);
    }

    /** Dedicated project-scoped Plot Purchase entry and inventory register. */
    public function plotPurchaseWorkspace()
    {
        $projectId = (int) getSelectedTown();
        if (!\App\Services\Finance\V2IsolatedConnectionGuard::isAllowed()) {
            return $this->unavailableV2Workspace($projectId, 'Plot Purchase Voucher');
        }
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        $accounts = ProjectHeadSubhead::availableForPosting()->with(['headAccounting:id,name', 'subheadAccounting:id,name'])
            ->where('project_id', $projectId)->orderBy('id')->get();
        return view('admin.finance.voucher.plot_purchase_workspace', compact('accounts'));
    }

    public function plotPurchaseOptions()
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        abort_unless(Schema::hasTable('finance_plot_purchases'), 409, 'Plot purchase storage is not installed.');
        $definitions = Schema::hasTable('plot_definitions')
            ? DB::table('plot_definitions')->where('project_id', $projectId)->where('is_active', 1)->orderBy('kind')->orderBy('name')
                ->get(['id', 'kind', 'code', 'name', 'size_value', 'unit_code', 'unit_definition_id', 'legacy_value'])
            : collect();
        return response()->json([
            'roads' => DB::table('roads')->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'facings' => DB::table('facings')->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'definitions' => $definitions,
        ]);
    }

    public function postPlotPurchase(Request $request)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
        $data = $request->validate([
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
            'purchase_number' => ['required', 'string', 'max:64'], 'date' => ['required', 'date_format:Y-m-d'],
            'seller_account_id' => ['required', 'integer', 'min:1'], 'narration' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.plot_code' => ['required', 'string', 'max:64'], 'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.area' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,4})?$/D'],
            'items.*.unit' => ['required', 'string', 'max:24'], 'items.*.type' => ['required', 'integer', \Illuminate\Validation\Rule::in([1, 2])],
            'items.*.road_id' => ['required', 'integer', 'min:1'], 'items.*.facing_id' => ['required', 'integer', 'min:1'],
            'items.*.is_corner' => ['sometimes', 'boolean'],
            'items.*.plot_category_definition_id' => ['nullable', 'integer', 'min:1'],
            'items.*.plot_type_definition_id' => ['nullable', 'integer', 'min:1'],
            'items.*.plot_size_definition_id' => ['nullable', 'integer', 'min:1'],
            'items.*.plot_unit_definition_id' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_rate' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,2})?$/D'],
            'items.*.inventory_account_id' => ['required', 'integer', 'min:1'],
        ]);
        try {
            $result = (new \App\Services\Finance\V2PlotEntryWriter())->purchase($projectId, $data['request_key'], $data, auth()->id());
        } catch (\DomainException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }
        return response()->json(['status' => 'success', 'data' => $result], $result['replayed'] ? 200 : 201);
    }

    public function plotPurchaseHistory()
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        abort_unless(Schema::hasTable('finance_plot_purchases'), 409, 'Plot purchase storage is not installed.');
        $rows = DB::table('finance_plot_purchases as purchase')->join('project_head_subheads as seller', function ($join) use ($projectId) {
            $join->on('seller.id', '=', 'purchase.seller_account_id')->where('seller.project_id', '=', $projectId);
        })->leftJoin('subhead_accountings as sub', 'sub.id', '=', 'seller.subhead_accounting_id')
            ->where('purchase.project_id', $projectId)->orderByDesc('purchase.purchase_date')->orderByDesc('purchase.id')->limit(250)
            ->get(['purchase.id', 'purchase.purchase_number', 'purchase.purchase_date', 'purchase.amount', 'purchase.narration', 'sub.name as seller']);
        return response()->json(['data' => $rows->map(fn ($row) => [
            'id' => (int) $row->id, 'number' => (string) $row->purchase_number, 'date' => substr((string) $row->purchase_date, 0, 10),
            'seller' => (string) ($row->seller ?? 'Seller account'), 'amount' => (string) $row->amount, 'narration' => (string) $row->narration,
            'items' => (int) DB::table('finance_plot_purchase_items')->where('purchase_id', $row->id)->count(),
        ])]);
    }

    public function plotPurchaseDetails(int $purchaseId)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        try { $details = (new \App\Services\Finance\V2PlotEntryWriter())->purchaseDetails($projectId, $purchaseId); }
        catch (\DomainException $error) { abort(404); }
        $details['purchase']->seller = DB::table('project_head_subheads as account')->leftJoin('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')
            ->where('account.project_id', $projectId)->where('account.id', $details['purchase']->seller_account_id)->value('sub.name');
        return response()->json($details);
    }

    public function printPlotPurchase(int $purchaseId)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        try { $details = (new \App\Services\Finance\V2PlotEntryWriter())->purchaseDetails($projectId, $purchaseId); }
        catch (\DomainException $error) { abort(404); }
        $details['purchase']->seller = DB::table('project_head_subheads as account')->leftJoin('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')
            ->where('account.project_id', $projectId)->where('account.id', $details['purchase']->seller_account_id)->value('sub.name');
        return view('admin.finance.voucher.plot_purchase_print', $details);
    }

    public function transferVoucherSources(string $kind)
    {
        abort_unless($kind === 'withdrawal', 404);
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        abort_unless(Schema::hasTable('finance_original_customer_requests'), 409, 'Verified cheque source records are not installed.');
        $rows = DB::table('customer_ledger as source')
            ->join('finance_original_customer_requests as custody', function ($join) use ($projectId) {
                $join->on('custody.customer_ledger_id', '=', 'source.id')->where('custody.project_id', '=', $projectId);
            })->leftJoin('leads as customer', 'customer.id', '=', 'source.customer_id')
            ->where('source.project_id', $projectId)->where('source.transaction_type', 'CP')->where('source.payment_type', 3)
            ->where('source.is_active', 1)->where('source.passing_status', 1)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('finance_bank_transfer_sources as link')->whereColumn('link.linked_customer_ledger_id', 'source.id'))
            ->orderByDesc('source.date')->orderByDesc('source.id')->get(['source.*', 'customer.first_name', 'customer.last_name', 'custody.source_sha256'])
            ->filter(fn ($s) => hash_equals((string) $s->source_sha256, \App\Services\Finance\V2OriginalCustomerWorkflow::fingerprint($s)))
            ->map(fn ($s) => ['id' => (int) $s->id, 'voucher' => 'CP-'.(string) $s->type_id, 'date' => substr((string) $s->date, 0, 10),
                'party' => trim((string) $s->first_name.' '.(string) $s->last_name), 'reference' => (string) ($s->t_number ?? ''),
                'amount' => number_format(max((float) $s->amount_in, (float) $s->amount_out), 2, '.', '')])->values();
        return response()->json(['data' => $rows, 'count' => $rows->count()]);
    }

    public function postTransferVoucher(Request $request, string $kind)
    {
        abort_unless(in_array($kind, ['deposit', 'withdrawal'], true), 404);
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'min:1'], 'cash_account_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'], 'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'reference' => ['nullable', 'string', 'max:120'], 'description' => ['required', 'string', 'max:2000'],
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
            'cheque_source_id' => ['nullable', 'integer', 'min:1'],
        ]);
        abort_unless($kind === 'withdrawal' || empty($data['cheque_source_id']), 422, 'A cheque source can only be linked to a withdrawal.');
        $narration = trim(($data['reference'] ?? '') !== '' ? 'Ref: '.$data['reference'].' | ' : '').$data['description'];
        $result = (new \App\Services\Finance\V2BankTransferWriter())->post($projectId, (int) $data['bank_account_id'], $kind,
            $data['request_key'], $data['date'], (string) $data['amount'], $narration,
            [['account_id' => (int) $data['cash_account_id'], 'amount' => (string) $data['amount']]], auth()->id(),
            isset($data['cheque_source_id']) ? (int) $data['cheque_source_id'] : null);
        return response()->json(['status' => 'success', 'data' => $result], $result['replayed'] ? 200 : 201);
    }

    public function transferVoucherHistory(string $kind)
    {
        abort_unless(in_array($kind, ['deposit', 'withdrawal'], true), 404);
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        $rows = DB::table('finance_bank_transfer_sources as transfer')
            ->join('finance_posting_batches as batch', 'batch.id', '=', 'transfer.posting_batch_id')
            ->join('project_bank_accounts as bank', function ($j) use ($projectId) { $j->on('bank.id', '=', 'transfer.bank_account_id')->where('bank.project_id', '=', $projectId); })
            ->join('finance_posting_lines as cash_line', function ($j) use ($kind) {
                $j->on('cash_line.posting_batch_id', '=', 'batch.id')->where('cash_line.debit', $kind === 'withdrawal' ? '>' : '=', $kind === 'withdrawal' ? 0 : 0);
            })->join('project_head_subheads as cash_account', 'cash_account.id', '=', 'cash_line.project_head_subhead_id')
            ->join('head_accountings as cash_head', 'cash_head.id', '=', 'cash_account.head_accounting_id')
            ->leftJoin('customer_ledger as cheque', 'cheque.id', '=', 'transfer.linked_customer_ledger_id')
            ->where('transfer.project_id', $projectId)->where('transfer.transfer_kind', $kind)
            ->where('cash_head.name', 'LIKE', '%cash%')->orderByDesc('batch.effective_date')->orderByDesc('batch.id')
            ->get(['transfer.posting_batch_id', 'transfer.amount', 'transfer.narration', 'bank.bank_name', 'bank.account_title',
                'batch.voucher_series', 'batch.voucher_number', 'batch.effective_date', 'cash_head.name as cash_name', 'cheque.type_id as cheque_number', 'cheque.t_number as cheque_reference'])
            ->unique('posting_batch_id')->values()->map(fn ($r) => ['posting_batch_id' => (int) $r->posting_batch_id,
                'voucher' => trim((string) $r->voucher_series.'-'.(string) $r->voucher_number, '-'), 'date' => substr((string) $r->effective_date, 0, 10),
                'cash_account' => (string) $r->cash_name, 'bank' => trim((string) $r->bank_name.' / '.(string) $r->account_title, ' /'),
                'cheque' => $r->cheque_number ? 'CP-'.$r->cheque_number.' / '.(string) $r->cheque_reference : '', 'amount' => (string) $r->amount, 'narration' => (string) $r->narration]);
        return response()->json(['data' => $rows]);
    }

    public function printTransferVoucher(string $kind, int $postingBatch)
    {
        abort_unless(in_array($kind, ['deposit', 'withdrawal'], true), 404);
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        $source = DB::table('finance_bank_transfer_sources as transfer')->join('finance_posting_batches as batch', 'batch.id', '=', 'transfer.posting_batch_id')
            ->join('project_bank_accounts as bank', function ($j) use ($projectId) { $j->on('bank.id', '=', 'transfer.bank_account_id')->where('bank.project_id', '=', $projectId); })
            ->leftJoin('customer_ledger as cheque', 'cheque.id', '=', 'transfer.linked_customer_ledger_id')
            ->where('transfer.project_id', $projectId)->where('transfer.transfer_kind', $kind)->where('transfer.posting_batch_id', $postingBatch)
            ->first(['transfer.*', 'batch.voucher_series', 'batch.voucher_number', 'batch.effective_date', 'bank.bank_name', 'bank.account_title',
                'cheque.type_id as cheque_number', 'cheque.t_number as cheque_reference']);
        abort_unless($source, 404);
        $lines = DB::table('finance_posting_lines as line')->join('project_head_subheads as account', function ($j) use ($projectId) {
            $j->on('account.id', '=', 'line.project_head_subhead_id')->where('account.project_id', '=', $projectId);
        })->leftJoin('head_accountings as head', 'head.id', '=', 'account.head_accounting_id')
            ->leftJoin('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')->where('line.posting_batch_id', $postingBatch)
            ->orderBy('line.id')->get(['head.name as head_name', 'sub.name as subhead_name', 'line.debit', 'line.credit']);
        abort_unless($lines->count() === 2, 404);
        return view('admin.finance.voucher.bank_transfer_print', compact('kind', 'source', 'lines'));
    }

    /** JSON source list: only verified, active, unposted, pending bank sources in this project. */
    public function bankVoucherSources(Request $request, string $direction)
    {
        abort_unless(in_array($direction, ['receipt', 'payment'], true), 404);
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('finance_original_customer_requests'), 409,
            'Verified isolated voucher sources are not installed.');

        $types = $direction === 'receipt' ? ['CR', 'PPR', 'ECP'] : ['CP'];
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('finance_bank_transfer_sources'), 409,
            'Bank transfer source records are not installed.');
        $sources = DB::table('customer_ledger as source')
            ->leftJoin('leads as customer', 'customer.id', '=', 'source.customer_id')
            ->leftJoin('plots as plot', 'plot.id', '=', 'source.plot_id')
            ->join('finance_original_customer_requests as custody', function ($join) use ($projectId) {
                $join->on('custody.customer_ledger_id', '=', 'source.id')->where('custody.project_id', '=', $projectId);
            })
            ->where('source.project_id', $projectId)->where('source.is_active', 1)
            ->whereIn('source.transaction_type', $types)->whereIn('source.payment_type', [2, 3])
            ->whereIn('source.passing_status', [0, 2])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('ledgers as ledger')->whereColumn('ledger.customer_ledger_id', 'source.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('finance_bank_transfer_sources as link')->whereColumn('link.linked_customer_ledger_id', 'source.id'))
            ->orderByDesc('source.date')->orderByDesc('source.id')
            ->get(['source.*', 'customer.first_name', 'customer.last_name', 'plot.name as plot_name', 'custody.source_sha256']);

        $data = $sources->filter(function ($source) {
            return hash_equals((string) $source->source_sha256, \App\Services\Finance\V2OriginalCustomerWorkflow::fingerprint($source));
        })->map(function ($source) {
            return [
                'id' => (int) $source->id, 'date' => substr((string) $source->date, 0, 10),
                'voucher' => (string) $source->transaction_type . '-' . (string) $source->type_id,
                'source_type' => (string) $source->transaction_type,
                'method' => (int) $source->payment_type === 3 ? 'Cheque' : 'Online',
                'transaction_number' => (string) ($source->t_number ?? ''),
                'bank' => $source->bank_id ? (string) getBankNameById($source->bank_id) : '',
                'party' => trim((string) $source->first_name . ' ' . (string) $source->last_name) ?: (string) ($source->plot_name ?? ''),
                'amount' => number_format(max((float) $source->amount_in, (float) $source->amount_out), 2, '.', ''),
                'status' => (int) $source->passing_status === 2 ? 'Returned / rejected' : 'Pending confirmation',
            ];
        })->values();

        return response()->json(['data' => $data, 'count' => $data->count()]);
    }

    /** Project-scoped list of linked Bank Receipt / Bank Payment postings. */
    public function bankVoucherHistory(string $direction)
    {
        abort_unless(in_array($direction, ['receipt', 'payment'], true), 404);
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        abort_unless(Schema::hasTable('finance_bank_transfer_sources'), 409,
            'Bank transfer source records are not installed.');

        $kind = $direction;
        $rows = DB::table('finance_bank_transfer_sources as transfer')
            ->join('finance_posting_batches as batch', 'batch.id', '=', 'transfer.posting_batch_id')
            ->join('project_bank_accounts as bank', function ($join) use ($projectId) {
                $join->on('bank.id', '=', 'transfer.bank_account_id')->where('bank.project_id', '=', $projectId);
            })
            ->leftJoin('customer_ledger as source', 'source.id', '=', 'transfer.linked_customer_ledger_id')
            ->leftJoin('leads as customer', 'customer.id', '=', 'source.customer_id')
            ->leftJoin('plots as plot', 'plot.id', '=', 'source.plot_id')
            ->where('transfer.project_id', $projectId)->where('transfer.transfer_kind', $kind)
            ->orderByDesc('batch.effective_date')->orderByDesc('batch.id')
            ->get(['transfer.posting_batch_id', 'transfer.amount', 'transfer.narration', 'bank.bank_name',
                'bank.account_title', 'batch.voucher_series', 'batch.voucher_number', 'batch.effective_date',
                'source.id as source_id', 'source.transaction_type as source_type', 'source.type_id as source_number',
                'source.payment_type', 'source.t_number', 'customer.first_name', 'customer.last_name', 'plot.name as plot_name']);

        return response()->json(['data' => $rows->map(fn ($row) => [
            'posting_batch_id' => (int) $row->posting_batch_id,
            'voucher' => trim((string) $row->voucher_series . '-' . (string) $row->voucher_number, '-'),
            'date' => substr((string) $row->effective_date, 0, 10),
            'source' => $row->source_id ? trim((string) $row->source_type . '-' . (string) $row->source_number, '-') : 'Bank transfer',
            'party' => trim((string) $row->first_name . ' ' . (string) $row->last_name) ?: (string) ($row->plot_name ?? ''),
            'method' => (int) $row->payment_type === 3 ? 'Cheque' : ((int) $row->payment_type === 2 ? 'Online' : 'Bank transfer'),
            'transaction_number' => (string) ($row->t_number ?? ''),
            'bank' => trim((string) $row->bank_name . ' / ' . (string) $row->account_title, ' /'),
            'amount' => (string) $row->amount,
            'narration' => (string) $row->narration,
        ])]);
    }

    /** Printable, project-scoped details for one linked bank posting. */
    public function printBankVoucher(string $direction, int $postingBatch)
    {
        abort_unless(in_array($direction, ['receipt', 'payment'], true), 404);
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($projectId);
        $source = DB::table('finance_bank_transfer_sources as transfer')
            ->join('finance_posting_batches as batch', 'batch.id', '=', 'transfer.posting_batch_id')
            ->join('project_bank_accounts as bank', function ($join) use ($projectId) {
                $join->on('bank.id', '=', 'transfer.bank_account_id')->where('bank.project_id', '=', $projectId);
            })
            ->leftJoin('customer_ledger as customer_source', 'customer_source.id', '=', 'transfer.linked_customer_ledger_id')
            ->leftJoin('leads as customer', 'customer.id', '=', 'customer_source.customer_id')
            ->leftJoin('plots as plot', 'plot.id', '=', 'customer_source.plot_id')
            ->where('transfer.project_id', $projectId)->where('transfer.transfer_kind', $direction)
            ->where('transfer.posting_batch_id', $postingBatch)
            ->first(['transfer.*', 'batch.voucher_series', 'batch.voucher_number', 'batch.effective_date',
                'bank.bank_name', 'bank.account_title', 'customer_source.transaction_type as source_type',
                'customer_source.type_id as source_number', 'customer_source.payment_type', 'customer_source.t_number',
                'customer.first_name', 'customer.last_name', 'plot.name as plot_name']);
        abort_unless($source, 404);
        $lines = DB::table('finance_posting_lines as line')
            ->join('project_head_subheads as account', function ($join) use ($projectId) {
                $join->on('account.id', '=', 'line.project_head_subhead_id')->where('account.project_id', '=', $projectId);
            })
            ->leftJoin('head_accountings as head', 'head.id', '=', 'account.head_accounting_id')
            ->leftJoin('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')
            ->where('line.posting_batch_id', $postingBatch)->orderBy('line.id')
            ->get(['head.name as head_name', 'sub.name as subhead_name', 'line.debit', 'line.credit']);
        abort_unless($lines->isNotEmpty(), 404);

        return view('admin.finance.voucher.bank_linked_print', compact('direction', 'source', 'lines'));
    }

    public function updatePendingBankPaymentStatus(Request $request, $customerLedger)
    {
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            $project = (int) getSelectedTown();
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize($project);
            $data = $request->validate([
                'passing_status' => ['required', 'integer', 'in:1,2,3'],
                'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
                'bank_post_at' => ['required_if:passing_status,1', 'nullable', 'date_format:Y-m-d'],
                'note' => ['required', 'string', 'max:1000'],
                'bank_account_id' => ['required_if:passing_status,1', 'nullable', 'integer', 'min:1'],
                'posting_lines' => ['required_if:passing_status,1', 'array', 'min:2', 'max:100'],
                'posting_lines.*.account_id' => ['required', 'integer', 'min:1'],
                'posting_lines.*.debit' => ['required', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
                'posting_lines.*.credit' => ['required', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
            ]);
            try {
                $result = (new \App\Services\Finance\V2OriginalBankWorkflow())->settle(
                    $project, (int) $customerLedger, (int) $data['passing_status'], $data['request_key'],
                    $data['bank_post_at'] ?? '', $data['note'], $data['posting_lines'] ?? [], (int) Auth::id(),
                    isset($data['bank_account_id']) ? (int) $data['bank_account_id'] : null
                );
            } catch (\DomainException $e) { abort(409, 'Bank settlement could not be posted.'); }
            return response()->json(['status' => 'success', 'data' => $result]);
        }
        $selectedProjectId = getSelectedTown();
        $validated = $request->validate([
            'passing_status' => 'required|in:1,2,3',
            'note' => 'required|string|max:1000',
            'bank_post_at' => 'nullable|date',
            'accounts_id' => 'nullable|integer',
            'subaccounts_id' => 'nullable|integer',
        ]);

        try {
            DB::transaction(function () use ($validated, $customerLedger, $selectedProjectId) {
                $lockedLedger = CustomerLedger::where('project_id', $selectedProjectId)
                    ->where('id', $customerLedger)
                    ->whereIn('payment_type', [2, 3])
                    ->whereIn('transaction_type', ['CR', 'PPR'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lockedLedger->is_active !== 1) {
                    throw new \RuntimeException('Inactive payment cannot be cleared.');
                }

                if ((int) $validated['passing_status'] === 1 && empty($validated['bank_post_at'])) {
                    throw new \RuntimeException('Passing date is required for bank clearance.');
                }

                if ((int) $lockedLedger->passing_status !== 0) {
                    throw new \RuntimeException('This payment is already processed.');
                }

                $creditAccountId = null;
                if ((int) $validated['passing_status'] === 1) {
                    $accountsId = $validated['accounts_id'] ?? null;
                    $subaccountsId = $validated['subaccounts_id'] ?? null;

                    if ($accountsId || $subaccountsId) {
                        // Invalid explicitly supplied accounts must fail closed.
                        // Never silently fall back to another source account.
                        if (!$accountsId || !$subaccountsId) {
                            throw new \RuntimeException('Both account fields are required.');
                        }
                        $creditAccountId = ProjectHeadSubhead::availableForPosting()->where('head_accounting_id', $accountsId)
                            ->where('subhead_accounting_id', $subaccountsId)
                            ->where('project_id', $selectedProjectId)
                            ->value('id');
                        if (!$creditAccountId) {
                            throw new \RuntimeException('Selected bank clearance account is not valid for this project.');
                        }
                    }

                    if (!$creditAccountId) {
                        // A legacy receipt-linked account is not trusted unless it
                        // belongs to the currently selected project.
                        $fallbackAccountId = optional(optional($lockedLedger->ledger)->projectHeadSubhead)->id;
                        if ($fallbackAccountId) {
                            $creditAccountId = ProjectHeadSubhead::availableForPosting()
                                ->whereKey($fallbackAccountId)
                                ->where('project_id', $selectedProjectId)
                                ->value('id');
                        }
                    }

                    if (!$creditAccountId) {
                        throw new \RuntimeException('Please select valid Accounts and Child Account in the main form.');
                    }
                }

                $status = (int) $validated['passing_status'];
                $checkSlip = $lockedLedger->transaction_type . '-' . $lockedLedger->reference;
                $bankName = $lockedLedger->bank_id ? getBankNameById($lockedLedger->bank_id) : null;
                $note = '(' . $checkSlip . ') ' . $validated['note'];

                $lockedLedger->update([
                    'passing_status' => $status,
                    'note' => $note,
                    'bank_post_at' => $validated['bank_post_at'] ?? null,
                ]);

                $lockedLedger->addCheckHistory([
                    'id' => $lockedLedger->id,
                    'check_number' => $lockedLedger->t_number,
                    'passing_date' => $validated['bank_post_at'] ?? null,
                    'passing_status' => $status,
                    'description_note' => $note,
                    'bank_name' => $bankName,
                    'credit_account_id' => $creditAccountId,
                    'updated_by' => Auth::id(),
                ]);

                if ($status === 1) {
                    $clearanceReference = 'BANK_CLEARANCE#' . $lockedLedger->id;
                    $amount = (float) $lockedLedger->amount_out > 0
                        ? $lockedLedger->amount_out
                        : $lockedLedger->amount_in;

                    if ((float) $amount <= 0) {
                        throw new \RuntimeException('Cannot clear a zero or negative payment.');
                    }

                    // All matching reference rows must belong to this source and
                    // agree on business date, amount, account and active state.
                    $existingClearance = Ledger::query()
                        ->where('type', 'BR')
                        ->where('reference', $clearanceReference)
                        ->lockForUpdate()
                        ->get();

                    if ($existingClearance->count() > 1 ||
                        $existingClearance->contains(fn ($entry) =>
                            (int) $entry->customer_ledger_id !== (int) $lockedLedger->id ||
                            (int) $entry->is_active !== 1 ||
                            (int) $entry->project_head_subheads_id !== (int) $creditAccountId ||
                            substr((string) $entry->date, 0, 10) !== substr((string) $validated['bank_post_at'], 0, 10) ||
                            round((float) $entry->amount_in, 2) !== 0.0 ||
                            round((float) $entry->amount_out, 2) !== round((float) $amount, 2)
                        )) {
                        throw new \RuntimeException('Existing bank clearance posting conflicts with this payment.');
                    }

                    if ($existingClearance->isEmpty()) {
                        $voucherNumber = getVocuherNumber('BR');
                        $clearance = Ledger::create([
                            'customer_ledger_id' => $lockedLedger->id,
                            'type' => 'BR',
                            'voucher_number' => $voucherNumber,
                            'type_id' => app(\App\Services\Finance\ProjectVoucherAllocator::class)
                                ->nextLedgerId((int) $selectedProjectId, 'BR'),
                            'project_head_subheads_id' => $creditAccountId,
                            'reference' => $clearanceReference,
                            'amount_in' => 0.00,
                            'amount_out' => $amount,
                            'is_active' => 1,
                            'date' => $validated['bank_post_at'],
                            'detail' => $note,
                            'update_by' => Auth::id(),
                            'create_by' => Auth::id(),
                            'status' => 0,
                        ]);
                        app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)->assign(
                            (int) $selectedProjectId, 'ledger', (int) $clearance->id, 'BR',
                            substr((string) $validated['bank_post_at'], 0, 10)
                        );
                    }
                }
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'pending_payment_invalid_state',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'pending_payment_update_failed',
                'message' => 'Unable to update payment status right now.',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payment status updated successfully.',
        ]);
    }

    public function cash_in_data(Request $request, ?string $method = null)
    {
        $paymentType = $this->cashVoucherPaymentType($method);
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) { return $this->typedCashData($request, true, $paymentType); }
        $selectedProjectId = getSelectedTown();

        $q = Ledger::with([
            'projectHeadSubhead.headAccounting:id,name',
            'projectHeadSubhead.subheadAccounting:id,name',
            'projectHeadSubhead.project:id,project'
        ])
            ->where('is_active', 1)
            ->whereIn('type', $paymentType === null ? ['CR', 'PPR'] : ['CR'])
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId));
        if ($paymentType !== null) {
            $q->whereHas('customerLedger', fn ($source) => $source->where('payment_type', $paymentType));
        }

        // ----------------------------
        // Head Account filter (IDs)
        // ----------------------------
        $headIds = $request->input('head_account');

        if (is_string($headIds) && trim($headIds) !== '') {
            $headIds = [$headIds];
        }
        if (is_array($headIds)) {
            $headIds = array_values(array_filter($headIds, fn($v) => $v !== null && $v !== ''));
            if (count($headIds)) {
                $q->whereHas('projectHeadSubhead', function ($qq) use ($headIds) {
                    $qq->whereIn('head_accounting_id', $headIds);
                });
            }
        }

        // ----------------------------
        // Subhead Account filter (IDs)
        // ----------------------------
        $subheadIds = $request->input('subhead_account');

        if (is_string($subheadIds) && trim($subheadIds) !== '') {
            $subheadIds = [$subheadIds];
        }

        if (is_array($subheadIds)) {
            $subheadIds = array_values(array_filter($subheadIds, fn($v) => $v !== null && $v !== ''));
            if (count($subheadIds)) {
                $q->whereHas('projectHeadSubhead', function ($qq) use ($subheadIds) {
                    $qq->whereIn('subhead_accounting_id', $subheadIds);
                });
            }
        }

        if ($request->filled('voucher_number')) {
            $this->applyVoucherDisplayNumberFilter($q, (int) $selectedProjectId, (string) $request->input('voucher_number'));
        }

        // ----------------------------
        // Amount filters (NEW: min/max)
        // ----------------------------
        $sanitizeMoney = function ($v) {
            if ($v === null)
                return null;
            if (is_array($v))
                return null;
            $v = trim((string) $v);
            if ($v === '')
                return null;
            // allow commas from UI and remove spaces
            $v = str_replace([',', ' '], '', $v);
            // keep only digits + dot + minus (just in case)
            $v = preg_replace('/[^0-9\.\-]/', '', $v);
            if ($v === '' || !is_numeric($v))
                return null;
            return (float) $v;
        };

        $amountMin = $sanitizeMoney($request->input('amount_min'));
        $amountMax = $sanitizeMoney($request->input('amount_max'));

        if ($amountMin !== null && $amountMax !== null) {
            // if user accidentally swaps them, fix it
            if ($amountMin > $amountMax) {
                [$amountMin, $amountMax] = [$amountMax, $amountMin];
            }
            $q->whereBetween('amount_in', [$amountMin, $amountMax]);
        } elseif ($amountMin !== null) {
            $q->where('amount_in', '>=', $amountMin);
        } elseif ($amountMax !== null) {
            $q->where('amount_in', '<=', $amountMax);
        } else {
            // Backward compatible fallback: old single "amount" filter
            $legacyAmount = $sanitizeMoney($request->input('amount'));
            if ($legacyAmount !== null) {
                $q->where('amount_in', '>=', $legacyAmount);
            }
        }

        // ----------------------------
        // Date filters
        // ----------------------------
        if ($request->has('date') && $request->date) {
            $q->whereDate('date', '=', $request->date);
        }

        if ($request->filled('date_from')) {
            $q->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $q->whereDate('date', '<=', $request->date_to);
        }

        // Get total count before pagination/search
        $total = (clone $q)->count();

        $searchValue = trim((string) $request->input('search.value', ''));
        $filteredQuery = (clone $q);

        if ($searchValue !== '') {
            $this->applyVoucherSearchFilter($filteredQuery, $searchValue);
        }

        $recordsFiltered = (clone $filteredQuery)->count();

        // Pagination parameters
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 1);

        // Fetch only paginated rows, ordered by latest
        $rows = (clone $filteredQuery)->orderByDesc('id')->skip($start)->take($length)->get();

        // Transform the result set
        $displayNumbers = Schema::hasTable('project_voucher_display_numbers')
            ? DB::table('project_voucher_display_numbers')->where('project_id', $selectedProjectId)
                ->where('source_kind', 'customer_ledger')->whereIn('source_id', $rows->pluck('customer_ledger_id')->filter()->unique())
                ->pluck('display_number', 'source_id')
            : collect();
        $ledgerDisplayNumbers = Schema::hasTable('project_voucher_display_numbers')
            ? DB::table('project_voucher_display_numbers')->where('project_id', $selectedProjectId)
                ->where('source_kind', 'ledger')->whereIn('source_id', $rows->pluck('id'))
                ->pluck('display_number', 'source_id')
            : collect();
        $data = $rows->map(function ($i) use ($displayNumbers, $ledgerDisplayNumbers) {
            $amount = $i->type === 'CP' ? (float) $i->amount_out : (float) $i->amount_in;

            $p = $i->latestPendingUpdate ?? PendingUpdate::where('table_name', 'ledgers')
                ->where('record_id', $i->id)
                ->latest()
                ->first();

            return [
                'date' => $i->date,
                'voucher_number' => $displayNumbers[$i->customer_ledger_id] ?? $ledgerDisplayNumbers[$i->id] ?? ($i->type . '-' . $i->voucher_number),
                'voucher_number_raw' => (int) $i->voucher_number,
                'project' => $i->projectHeadSubhead->project->project ?? '',
                'head' => $i->projectHeadSubhead->headAccounting->name ?? '',
                'subhead' => $i->projectHeadSubhead->subheadAccounting->name ?? '',
                'detail' => $i->detail,
                'amount' => $amount,
                'status' => $p ? ucfirst($p->status) : 'Approved',
                'submitted_by' => $p?->submittedBy?->name ?? '',
                'old_values' => json_decode($p?->old_values, true) ?? [],
                'new_values' => json_decode($p?->new_values, true) ?? [],
                'id' => $i->id,
                'type' => $i->type,
                'editable' => in_array((string) $i->type, ['CR', 'CP'], true)
                    && $i->customer_ledger_id !== null,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }





    public function cash_draft()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Draft Vouchers';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['accountTypes'] = AccountType::where('status', 1)->orderBy('id')->get();
        $x['type'] = 'CR';
        $x['class'] = 'cash-out';
        $x['bg_voucher'] = 'info-cash-out';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        // 🔹 Replace everything from here until just before $x['headaccounts'] =
        // ───────────────────────────────────────────────────────────────
        $data = DraftLedger::with([
            'projectHeadSubhead.headAccounting',
            'projectHeadSubhead.subheadAccounting',
            'projectHeadSubhead.project'
        ])
            ->where('project_id', $selectedProjectId)
            ->where('is_active', 1);

        // Apply filters
        if ($from = request('from')) {
            $data->whereDate('date', '>=', $from);
        }
        if ($to = request('to')) {
            $data->whereDate('date', '<=', $to);
        }
        $amountMin = request('amount_min');
        $amountMax = request('amount_max');

        if ($amountMin !== null && $amountMin !== '') {
            $data->where('amount_in', '>=', (float) $amountMin);
        }

        if ($amountMax !== null && $amountMax !== '') {
            $data->where('amount_in', '<=', (float) $amountMax);
        }


        if ($heads = request('head')) {
            $data->whereHas('projectHeadSubhead', function ($q) use ($heads) {
                $q->whereIn('head_accounting_id', $heads);
            });
        }

        if ($subheads = request('subhead')) {
            $data->whereHas('projectHeadSubhead', function ($q) use ($subheads) {
                $q->whereIn('subhead_accounting_id', $subheads);
            });
        }

        if ($type = request('type')) {
            $data->where('transaction_type', $type);
        }

        $data = $data->get()->map(function ($item) {
            $item['amount'] = $item['transaction_type'] === 'CR'
                ? floatval($item['amount_in'])
                : floatval($item['amount_out']);

            $pending = PendingUpdate::where('table_name', 'draft_ledgers')
                ->where('record_id', $item->id)
                ->latest()
                ->first();

            $item['submitted_by'] = $pending ? $pending->submittedBy?->name : '';
            $item['new_values'] = $pending ? json_decode($pending->new_values, true) : '';
            $item['old_values'] = $pending ? json_decode($pending->old_values, true) : '';
            $item['status'] = $pending ? ucfirst($pending->status) : 'Approved';
            return $item;
        });

        $x['data'] = $data;
        // ───────────────────────────────────────────────────────────────
        // Keep everything from here downward as-is

        $x['headaccounts'] = ProjectHeadSubhead::availableForPosting()
            ->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();

        $x['partyaccounts'] = ProjectHeadSubhead::availableForPosting()->with($this->projectAccountOptionRelations())
            ->withSum('ledgers as total_in', 'amount_in')
            ->withSum('ledgers as total_out', 'amount_out')
            ->whereIn('head_accounting_id', $x['headaccounts']->pluck('head_accounting_id'))
            ->where('project_id', $selectedProjectId)
            ->get()
            ->map(function ($item) {
                $item->balance = ($item->total_in ?? 0) - ($item->total_out ?? 0);
                return $item;
            });

        $x['customers'] = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')
            ->where('leads.project_id', $selectedProjectId)
            ->whereNull('bookings.deleted_at')
            ->select('leads.*')
            ->distinct()
            ->get();

        $x['plots'] = Plot::join('bookings', 'plots.id', '=', 'bookings.plot_id')
            ->where('plots.project_id', $selectedProjectId)
            ->get();

        $x['selectedProjectId'] = $selectedProjectId;
        $projects = Project::where('id', $selectedProjectId)->get();
        $x['projects'] = $projects;

        return view('admin.finance.voucher.draft_voucher', $x);
    }

    public function checkNewVoucherNumber(Request $request)
    {
        $data = $request->validate([
            'type' => ['required','string','in:CR,DR,CP,CCR,CDR,BOR,BOP'],
            'number' => ['nullable','integer','min:1'],
        ]);
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0,403);
        return response()->json([
            'latest_voucher_number' => $this->previewCanonicalCashNumber($projectId,
                $data['type'] === 'DR' ? 'CP' : $data['type']),
        ]);
    }
    public function updateVoucherNumber()
    {
        // Historical voucher numbers are accounting identities. Renumbering the
        // whole ledger made existing JV rows appear missing or reassigned.
        abort(410, 'Ledger voucher renumbering has been permanently disabled.');
    }

    private function applyVoucherSearchFilter(Builder $query, string $search)
    {
        $query->where(function (Builder $q) use ($search) {
            $q->where('voucher_number', 'like', "%{$search}%")
                ->orWhere('detail', 'like', "%{$search}%")
                ->orWhereHas('projectHeadSubhead.headAccounting', fn (Builder $head) => $head->where('name', 'like', "%{$search}%"))
                ->orWhereHas('projectHeadSubhead.subheadAccounting', fn (Builder $sub) => $sub->where('name', 'like', "%{$search}%"));
        });

        return $query;
    }

    private function applyVoucherDisplayNumberFilter(Builder $query, int $projectId, string $search): void
    {
        $search = trim($search);
        $query->where(function (Builder $matches) use ($projectId, $search): void {
            $matches->where('voucher_number', 'like', "%{$search}%");

            if (!Schema::hasTable('project_voucher_display_numbers')) {
                return;
            }

            $displayNumbers = DB::table('project_voucher_display_numbers')
                ->where('project_id', $projectId)
                ->where('display_number', 'like', "%{$search}%")
                ->get(['source_kind', 'source_id'])
                ->groupBy('source_kind');

            $ledgerIds = $displayNumbers->get('ledger', collect())->pluck('source_id')->all();
            $sourceIds = $displayNumbers->get('customer_ledger', collect())->pluck('source_id')->all();

            if ($ledgerIds !== []) {
                $matches->orWhereIn('id', $ledgerIds);
            }
            if ($sourceIds !== []) {
                $matches->orWhereIn('customer_ledger_id', $sourceIds);
            }
        });
    }
}
