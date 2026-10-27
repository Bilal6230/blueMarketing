<?php

namespace App\Http\Controllers;

use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JournalVoucherController extends Controller
{
    public function index(Request $request)
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'General Journal Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'JV';
        $x['class'] = 'Journal voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        // Build base query for vouchers
        $baseQuery = JournalVoucher::latest()
            ->where('project_id', $selectedProjectId)
            ->where(function ($query) {
                $query->where('type', 'JV')
                    ->orWhereNull('type');
            });
        $query = clone $baseQuery;

        // Apply filters if they exist
        $voucherNumberFilter = trim((string) $request->input('filter_voucher_number', ''));
        if ($voucherNumberFilter !== '') {
            $normalizedVoucherNumber = preg_replace('/^[A-Z]+-/i', '', $voucherNumberFilter);
            $normalizedVoucherNumber = ltrim($normalizedVoucherNumber, '0');
            $normalizedVoucherNumber = $normalizedVoucherNumber === '' ? '0' : $normalizedVoucherNumber;

            $query->where(function ($q) use ($voucherNumberFilter, $normalizedVoucherNumber) {
                $q->where('voucher_number', 'like', '%' . $voucherNumberFilter . '%')
                    ->orWhere('voucher_number', 'like', '%' . $normalizedVoucherNumber . '%');
            });
        }

        $minAmount = $request->input('filter_amount_min');
        $maxAmount = $request->input('filter_amount_max');

        if ($minAmount !== null && $minAmount !== '') {
            $query->where('total_debit', '>=', (float) $minAmount);
        }

        if ($maxAmount !== null && $maxAmount !== '') {
            $query->where('total_debit', '<=', (float) $maxAmount);
        }


        if ($request->has('filter_reference') && $request->filter_reference) {
            $query->where('reference', 'like', '%' . $request->filter_reference . '%');
        }

        $filterDate = $this->normalizeFilterDate($request->input('filter_date'));
        if ($filterDate !== null) {
            $query->whereDate('date', '=', $filterDate);
        }

        $searchValue = trim((string) $request->input('search.value', ''));
        if ($searchValue !== '') {
            $normalizedSearchValue = preg_replace('/^[A-Z]+-/i', '', $searchValue);
            $normalizedSearchValue = ltrim($normalizedSearchValue, '0');
            $normalizedSearchValue = $normalizedSearchValue === '' ? '0' : $normalizedSearchValue;

            $query->where(function ($q) use ($searchValue, $normalizedSearchValue) {
                $q->where('voucher_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('voucher_number', 'like', '%' . $normalizedSearchValue . '%')
                    ->orWhere('reference', 'like', '%' . $searchValue . '%')
                    ->orWhere('description', 'like', '%' . $searchValue . '%')
                    ->orWhere('date', 'like', '%' . $searchValue . '%')
                    ->orWhere('total_debit', 'like', '%' . $searchValue . '%');
            });
        }

        // If it's an AJAX request, return JSON data
        if ($request->ajax()) {
            // Pagination parameters
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 10)));

            $recordsTotal = (clone $baseQuery)->count();
            $recordsFiltered = (clone $query)->count();
            $vouchers = $query->skip($start)->take($length)->get();

            // Prepare the data, including the "actions" column
            $data = $vouchers->map(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => 'JV-' . get_jv_number($voucher->voucher_number),
                    'reference' => $voucher->reference,
                    'date' => $voucher->date,
                    'description' => $voucher->description,
                    'total_debit' => $voucher->total_debit,
                    'actions' => view('admin.reports.vouchers.actions', compact('voucher'))->render() // Render actions view
                ];
            });

            // Return JSON data
            return response()->json([
                'draw' => $request->input('draw', 1),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);
        }

        // For non-AJAX requests, return the view
        $x['vouchers'] = $query->get(); // Fetch all vouchers initially

        return view('admin.reports.vouchers.index', $x);
    }
    public function salesIndex(Request $request)
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'General Sales Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'SV';
        $x['class'] = 'Journal voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        // Build base query for vouchers
        $baseQuery = JournalVoucher::query()
            ->where('project_id', $selectedProjectId)
            ->where('type', 'SV');
        $query = clone $baseQuery;

        // Apply filters if they exist
        $voucherNumberFilter = trim((string) $request->input('filter_voucher_number', ''));
        if ($voucherNumberFilter !== '') {
            $normalizedVoucherNumber = $this->normalizeVoucherNumberInput($voucherNumberFilter, 'SV');

            $query->where(function ($q) use ($voucherNumberFilter, $normalizedVoucherNumber) {
                if ($normalizedVoucherNumber !== null) {
                    $q->where('voucher_number', (int) $normalizedVoucherNumber);
                } else {
                    $q->where('voucher_number', 'like', '%' . $voucherNumberFilter . '%');
                }
            });
        }

        $minAmount = $request->input('filter_amount_min');
        $maxAmount = $request->input('filter_amount_max');

        if ($minAmount !== null && $minAmount !== '') {
            $query->where('total_debit', '>=', (float) $minAmount);
        }

        if ($maxAmount !== null && $maxAmount !== '') {
            $query->where('total_debit', '<=', (float) $maxAmount);
        }


        if ($request->has('filter_reference') && $request->filter_reference) {
            $query->where('reference', 'like', '%' . $request->filter_reference . '%');
        }

        $filterDate = $this->normalizeFilterDate($request->input('filter_date'));
        if ($filterDate !== null) {
            $query->whereDate('date', '=', $filterDate);
        }

        $searchValue = trim((string) $request->input('search.value', ''));
        if ($searchValue !== '') {
            $normalizedSearchValue = $this->normalizeVoucherNumberInput($searchValue, 'SV');

            $query->where(function ($q) use ($searchValue, $normalizedSearchValue) {
                $q->where('voucher_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('reference', 'like', '%' . $searchValue . '%')
                    ->orWhere('description', 'like', '%' . $searchValue . '%')
                    ->orWhere('date', 'like', '%' . $searchValue . '%')
                    ->orWhere('total_debit', 'like', '%' . $searchValue . '%')
                    ->orWhere('total_credit', 'like', '%' . $searchValue . '%');

                if ($normalizedSearchValue !== null) {
                    $q->orWhere('voucher_number', (int) $normalizedSearchValue);
                }
            });
        }

        // If it's an AJAX request, return JSON data
        if ($request->ajax()) {
            // Pagination parameters
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);

            $recordsTotal = (clone $baseQuery)->count();
            $recordsFiltered = (clone $query)->count();

            $orderableColumns = [
                0 => 'id',
                1 => 'voucher_number',
                2 => 'reference',
                3 => 'date',
                4 => 'description',
                5 => 'total_debit',
            ];
            $orderColumnIndex = (int) $request->input('order.0.column', 0);
            $orderDirection = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $orderColumn = $orderableColumns[$orderColumnIndex] ?? 'id';

            $query->orderBy($orderColumn, $orderDirection);
            $vouchers = $query->skip($start)->take($length)->get();

            // Prepare the data, including the "actions" column
            $data = $vouchers->map(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => 'SV-' . (int) $voucher->voucher_number,
                    'reference' => $voucher->reference,
                    'date' => $voucher->date,
                    'description' => $voucher->description,
                    'total_debit' => $voucher->total_debit,
                    'actions' => view('admin.reports.vouchers.actions', compact('voucher'))->render() // Render actions view
                ];
            });

            // Return JSON data
            return response()->json([
                'draw' => $request->input('draw', 1),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);
        }

        // For non-AJAX requests, return the view
        $x['vouchers'] = $query->orderByDesc('id')->get(); // Fetch all vouchers initially

        return view('admin.reports.vouchers.index', $x);
    }

    private function normalizeVoucherNumberInput(string $value, string $prefix): ?string
    {
        $normalized = strtoupper(trim($value));
        $normalized = preg_replace('/^' . preg_quote(strtoupper($prefix), '/') . '\s*-\s*/', '', $normalized);
        $normalized = ltrim($normalized, '0');

        if ($normalized === '') {
            $normalized = '0';
        }

        return ctype_digit($normalized) ? $normalized : null;
    }

    private function normalizeFilterDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }


    public function create()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Create General Journal Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'JV';
        $x['class'] = 'Journal voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();


        $projects = Project::where('id', $selectedProjectId)->get();
        $x['projects'] = $projects;

        $data_list = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();

        // dd($data_list[0]->headAccounting->name);

        $x['accounts'] = $data_list;


        // Existing production databases may not have the reviewed finance metadata
        // migration. Only issue a durable retry key when both tables are present.
        $x['financeRetryReady'] = app(\App\Services\Finance\FinancePostingSchemaGate::class)->isReady();
        $x['financeRetryKey'] = $x['financeRetryReady']
            ? old('idempotency_key', (string) \Illuminate\Support\Str::uuid())
            : null;

        return view('admin.reports.vouchers.journal_voucher', $x);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'idempotency_key' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
            'voucher_number' => 'required|string',
            'reference' => 'nullable|string',
            'date' => 'required|date',
            'description' => 'required|string',
            'accounts' => 'required|array|min:2',
            'accounts.*' => 'required|integer',
            'sub_accounts' => 'required|array|min:2',
            'sub_accounts.*' => 'required|integer',
            'line_description' => 'nullable|array',
            'line_description.*' => 'nullable|string',
            'debit' => 'required|array|min:2',
            'debit.*' => ['required', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'],
            'credit' => 'required|array|min:2',
            'credit.*' => ['required', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'],
        ]);

        $count = count($validated['accounts']);
        foreach (['sub_accounts', 'debit', 'credit'] as $field) {
            if (count($validated[$field]) !== $count ||
                array_keys($validated[$field]) !== array_keys($validated['accounts'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => 'Voucher line arrays must have identical row keys and lengths.',
                ]);
            }
        }
        if (isset($validated['line_description']) &&
            array_diff(array_keys($validated['line_description']), array_keys($validated['accounts']))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'line_description' => 'Descriptions must belong to existing voucher rows.',
            ]);
        }

        $selectedProjectId = getSelectedTown();
        if (!$selectedProjectId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'project' => 'Select a project before posting a journal voucher.',
            ]);
        }

        $subAccountIds = array_values(array_unique(array_map('intval', $validated['sub_accounts'])));
        $ownedAccounts = ProjectHeadSubhead::where('project_id', $selectedProjectId)
            ->whereIn('id', $subAccountIds)->get(['id', 'head_accounting_id']);
        if ($ownedAccounts->count() !== count($subAccountIds)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sub_accounts' => 'Every voucher account must belong to the selected project.',
            ]);
        }
        $headIds = $ownedAccounts->pluck('head_accounting_id', 'id');
        $totalDebitCents = 0;
        $totalCreditCents = 0;
        foreach ($validated['accounts'] as $index => $headId) {
            $subId = $validated['sub_accounts'][$index];
            if ((string) $headIds->get($subId) !== (string) $headId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'accounts' => 'Head and subaccount pairing does not match.',
                ]);
            }

            // Fixed-point addition avoids floating point equality errors.
            $debitParts = explode('.', (string) $validated['debit'][$index]);
            $creditParts = explode('.', (string) $validated['credit'][$index]);
            $debitCents = ((int) $debitParts[0] * 100) + (int) str_pad($debitParts[1] ?? '', 2, '0');
            $creditCents = ((int) $creditParts[0] * 100) + (int) str_pad($creditParts[1] ?? '', 2, '0');
            if (($debitCents === 0 && $creditCents === 0) ||
                ($debitCents > 0 && $creditCents > 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'debit' => 'Each row must have either debit or credit, but not both or neither.',
                ]);
            }
            $totalDebitCents += $debitCents;
            $totalCreditCents += $creditCents;
        }
        if ($totalDebitCents <= 0 || $totalDebitCents !== $totalCreditCents) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'debit' => 'Total debit and credit must be equal and greater than zero.',
            ]);
        }

        // Guard the REAL posting path, not only an audit CLI: table presence
        // without required columns and unique constraints is not safe.
        $metadataReady = app(\App\Services\Finance\FinancePostingSchemaGate::class)->mode() === 'typed';
        $requestKey = $request->input('idempotency_key');
        // Typed posting must always reserve a durable request identity.
        // Never silently downgrade a metadata-ready install to an unkeyed write.
        if ($metadataReady && $requestKey === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'idempotency_key' => 'A unique request key is required for typed financial posting.',
            ]);
        }
        if ($requestKey !== null && (!$metadataReady || !is_string($requestKey)
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $requestKey))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'idempotency_key' => 'Valid request key requires the approved finance metadata schema.',
            ]);
        }

        $post = function () use ($validated, $selectedProjectId, $totalDebitCents, $totalCreditCents, $metadataReady) {
            // Serialize numbering within this project's row (on databases supporting FOR UPDATE).
            Project::whereKey($selectedProjectId)->lockForUpdate()->firstOrFail();
            $lastVoucherNumber = JournalVoucher::where('project_id', $selectedProjectId)
                ->where('type', 'JV')->withTrashed()->orderByDesc('id')->value('voucher_number');
            $journalVoucher = JournalVoucher::create([
                'voucher_number' => ((int) $lastVoucherNumber) + 1,
                'type' => 'JV',
                'reference' => $validated['reference'] ?? null,
                'date' => $validated['date'],
                'description' => $validated['description'],
                'total_debit' => intdiv($totalDebitCents, 100) . '.' . str_pad((string) ($totalDebitCents % 100), 2, '0', STR_PAD_LEFT),
                'total_credit' => intdiv($totalCreditCents, 100) . '.' . str_pad((string) ($totalCreditCents % 100), 2, '0', STR_PAD_LEFT),
                'created_by' => auth()->id(),
                'project_id' => $selectedProjectId,
            ]);

            foreach ($validated['accounts'] as $index => $accountId) {
                // Preserve exact validated decimal digits; do not round-trip through float.
                $debitParts = explode('.', (string) $validated['debit'][$index]);
                $creditParts = explode('.', (string) $validated['credit'][$index]);
                $debit = $debitParts[0] . '.' . str_pad($debitParts[1] ?? '', 2, '0');
                $credit = $creditParts[0] . '.' . str_pad($creditParts[1] ?? '', 2, '0');
                JournalVoucherDetail::create([
                    'journal_voucher_id' => $journalVoucher->id,
                    'account_id' => $validated['sub_accounts'][$index],
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $validated['line_description'][$index] ?? '',
                    'created_by' => auth()->id(),
                ]);
                $ledger = Ledger::create([
                    'voucher_number' => getVocuherNumber('JV'),
                    'type' => 'JV',
                    'type_id' => $journalVoucher->id,
                    'project_head_subheads_id' => $validated['sub_accounts'][$index],
                    'reference' => $validated['reference'] ?? null,
                    'amount_in' => $debit,
                    'amount_out' => $credit,
                    'detail' => $validated['line_description'][$index] ?? '',
                    'create_by' => auth()->id(),
                    'is_active' => true,
                    'status' => '0',
                    'date' => $validated['date'],
                ]);
                if ($metadataReady) {
                    app(\App\Services\Finance\LedgerSourceAttributionWriter::class)
                        ->recordNewPosting((int) $ledger->id, (int) $selectedProjectId,
                            'journal_voucher', (int) $journalVoucher->id);
                }
            }
            return (int) $journalVoucher->id;
        };

        if ($requestKey !== null) {
            // A retry must match metadata AND amounts, not just the debit total.
            $canonical = [
                'reference' => $validated['reference'] ?? null,
                'date' => $validated['date'],
                'description' => $validated['description'],
                'lines' => [],
            ];
            foreach ($validated['accounts'] as $index => $headId) {
                $d = explode('.', (string) $validated['debit'][$index]);
                $c = explode('.', (string) $validated['credit'][$index]);
                $canonical['lines'][] = [
                    (int) $headId, (int) $validated['sub_accounts'][$index],
                    (int) $d[0] * 100 + (int) str_pad($d[1] ?? '', 2, '0'),
                    (int) $c[0] * 100 + (int) str_pad($c[1] ?? '', 2, '0'),
                    $validated['line_description'][$index] ?? '',
                ];
            }
            $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
            $posting = app(\App\Services\Finance\DurableFinancialRequest::class)->execute(
                'journal_voucher', (int) $selectedProjectId, $requestKey, $hash, $post
            );
            if ($posting['replayed']) {
                app(\App\Services\Finance\JournalVoucherReplayIntegrity::class)
                    ->assertIntact((int) $selectedProjectId, (int) $posting['voucher_id']);
            }
        } else {
            DB::transaction($post);
        }

        return redirect()->route('journal.voucher.index')->with('success', 'Journal Voucher created successfully.');
    }

    // public function edit($id)
    // {
    //     $voucher = JournalVoucher::with('details')->where('project_id', getSelectedTown())->findOrFail($id);
    //     $x['voucher'] = $voucher;
    //     $power = Auth::user()->roles[0]->name;
    //     $x['title']     = 'Edit General Journal Voucher';
    //     $x['role']      = Role::get();
    //     $x['users']      = User::get();
    //     $x['power']     = $power;
    //     $x['type']      =   'JV';

    //     // Get selected town's project_id
    //     $selectedProjectId = getSelectedTown();

    //     $projects = Project::where('id', $selectedProjectId )->get();
    //     $x['projects'] = $projects;

    //     $Accounts = ProjectHeadSubhead::select('project_id','head_accounting_id')
    //                     ->distinct()
    //                     ->with('headAccounting')
    //                     ->where(['project_id' => $selectedProjectId])
    //                     ->get();

    //     $x['accounts'] = $Accounts;

    //     return view('admin.reports.vouchers.edit_journal_voucher', $x );


    // }

    public function destroy($id)
    {
        // Temporary Phase 1K safety gate: posted/legacy identity is ambiguous.
        // Fail closed until append-only financial reversal and typed source mapping exist.
        abort(409, 'This voucher action is temporarily disabled to protect accounting history.');

        DB::beginTransaction();
        try {
            // Delete Journal Voucher and related details
            $journalVoucher = JournalVoucher::findOrFail($id);
            JournalVoucherDetail::where('journal_voucher_id', $id)->delete();
            Ledger::where('type_id', $id)->whereIn('type', ['JV', 'JV'])->delete();
            $journalVoucher->delete();

            DB::commit();
            return redirect()->route('journal.voucher.index')->with('success', 'Journal Voucher deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $voucher = JournalVoucher::with('details')->where('project_id', getSelectedTown())->findOrFail($id);
        $x = $this->getCommonData('Edit Journal Voucher');
        $x['voucher'] = $voucher;

        // Get selected town's project_id
        $x['selectedProjectId'] = $selectedProjectId = getSelectedTown();

        // Fetch projects and accounts
        $x['projects'] = Project::where('id', $selectedProjectId)->get();
        $x['accounts'] = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where('project_id', $selectedProjectId)
            ->get();

        return view('admin.reports.vouchers.edit_journal_voucher', $x);
    }

    public function update(Request $request, $id)
    {
        // Temporary Phase 1K safety gate: posted/legacy identity is ambiguous.
        // Fail closed until append-only financial reversal and typed source mapping exist.
        abort(409, 'This voucher action is temporarily disabled to protect accounting history.');

        $request->validate([
            'voucher_number' => 'required|string',
            'reference' => 'nullable|string',
            'date' => 'required|date',
            'description' => 'required|string',
            'accounts' => 'required|array|min:1',
            'sub_accounts' => 'required|array|min:1',
            'line_description' => 'nullable|array',
            'debit' => 'required|array|min:1',
            'credit' => 'required|array|min:1',
        ]);

        $totalDebit = array_sum($request->debit);
        $totalCredit = array_sum($request->credit);

        if ($totalDebit !== $totalCredit) {
            return redirect()->back()->withErrors(['Total debit and credit must be equal.']);
        }

        DB::beginTransaction();
        try {
            // Update Journal Voucher Record
            $journalVoucher = JournalVoucher::findOrFail($id);
            $journalVoucher->update([
                'type' => 'JV',
                'reference' => $request->reference,
                'date' => $request->date,
                'description' => $request->description,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'updated_by' => auth()->id(),
            ]);

            // Delete existing details and ledger entries
            $journalVoucherDetails = JournalVoucherDetail::withTrashed()->where('journal_voucher_id', $id)->get();

            $detailIds = $journalVoucherDetails->pluck('id');

            Ledger::where('type', 'JV')
                ->where(function ($q) use ($id, $detailIds) {
                    $q->where('type_id', $id)
                    ->orWhereIn('type_id', $detailIds);
                })
                ->delete();

            $journalVoucherDetails->each->delete();


            // Add updated Ledger Entries
            foreach ($request->accounts as $index => $accountId) {
                $JvDetails = JournalVoucherDetail::create([
                    'journal_voucher_id' => $journalVoucher->id,
                    'account_id' => $request->sub_accounts[$index],
                    'debit' => $request->debit[$index] ?? 0,
                    'credit' => $request->credit[$index] ?? 0,
                    'description' => $request->line_description[$index] ?? '',
                    'created_by' => auth()->id(),
                ]);

                $voucherNumber = getVocuherNumber(($request->debit[$index] ?? 0) > 0 ? 'JV' : 'JV');
                $ledgerData = Ledger::create([
                    'voucher_number' => $voucherNumber,
                    'type' => ($request->debit[$index] ?? 0) > 0 ? 'JV' : 'JV',
                    'type_id' => $id,
                    'project_head_subheads_id' => $request->sub_accounts[$index],
                    'reference' => $request->reference,
                    'amount_in' => $request->debit[$index] ?? 0,
                    'amount_out' => $request->credit[$index] ?? 0,
                    'detail' => $request->line_description[$index] ?? '',
                    'create_by' => auth()->id(),
                    'is_active' => true,
                    'status' => '0',
                    'date' => $request->date,
                ]);
            }

            DB::commit();
            return redirect()->route('journal.voucher.index')->with('success', 'Journal Voucher updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function print($id)
    {
        $voucher = JournalVoucher::with('details.account.headAccounting', 'details.account.subheadAccounting')->where('project_id', getSelectedTown())->findOrFail($id);
        return view('admin.reports.vouchers.print_journal_voucher', compact('voucher'));
    }

    private function getCommonData($title, $type = 'JV')
    {
        $power = Auth::user()->roles[0]->name;
        return [
            'title' => $title,
            'role' => Role::get(),
            'users' => User::get(),
            'power' => $power,
            'type' => $type,
            'class' => 'Journal voucher',
            'bg_voucher' => 'info-cash-in',
        ];
    }




}
