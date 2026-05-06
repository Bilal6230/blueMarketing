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
        $baseQuery = JournalVoucher::latest()->where('project_id', $selectedProjectId)->where('type', 'JV');
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

        if ($request->has('filter_date') && $request->filter_date) {
            $query->whereDate('date', '=', $request->filter_date); // Apply date filter if provided
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
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);

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

        if ($request->has('filter_date') && $request->filter_date) {
            $query->whereDate('date', '=', $request->filter_date); // Apply date filter if provided
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


        return view('admin.reports.vouchers.journal_voucher', $x);
    }

    public function store(Request $request)
    {
        $lastVoucherId = getLastJvVNumber();

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
        $selectedProjectId = getSelectedTown();

        DB::beginTransaction();
        // try {
        // Create Journal Voucher Record
        $journalVoucher = JournalVoucher::create([
            'voucher_number' => $lastVoucherId + 1,
            'reference' => $request->reference,
            'date' => $request->date,
            'description' => $request->description,
            'total_debit' => $totalDebit, // Add total debit
            'total_credit' => $totalCredit, // Add total credit
            'created_by' => auth()->id(),
            'project_id' => $selectedProjectId,
        ]);


        // Add Ledger Entries
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
                'type_id' => $journalVoucher->id,
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
        return redirect()->route('journal.voucher.index')->with('success', 'Journal Voucher created successfully.');
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        // }
    }

    // public function edit($id)
    // {
    //     $voucher = JournalVoucher::with('details')->findOrFail($id);
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
        $voucher = JournalVoucher::with('details')->findOrFail($id);
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
        $voucher = JournalVoucher::with('details.account.headAccounting', 'details.account.subheadAccounting')->findOrFail($id);
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
