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
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\HeadAccounting;
use App\Models\AccountingExpense;
use App\Models\SubheadAccounting;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\Lead\LeadRepository as lead_repo;

class VoucherController extends Controller
{
    private array $allowedPendingUpdateTables = ['ledgers', 'draft_ledgers', 'customer_ledger', 'leads'];
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
        $user = Auth::user();
        $power = $user->roles[0]->name ?? 'User';

        $x['title'] = 'Pending Admin Approvals';
        $x['power'] = $power;
        $x['users'] = User::get();

        // Fetch only pending updates (you can show approved/rejected with filters later)
        $pendingUpdates = PendingUpdate::with(['submittedBy', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->where('status', 'pending')
            ->get();

        $leadCommentsByRecord = collect();
        $leadIds = $pendingUpdates->where('table_name', 'leads')
            ->pluck('record_id')
            ->unique()
            ->values();

        $leadOwnerNamesByRecord = collect();

        if ($leadIds->isNotEmpty()) {
            $leadCommentsByRecord = Lead::with(['comments' => function ($query) {
                $query->select('id', 'lead_id', 'comment', 'created_at')
                    ->orderBy('id', 'desc');
            }])
                ->whereIn('id', $leadIds)
                ->get()
                ->mapWithKeys(function ($lead) {
                    $comments = $lead->comments->map(function ($comment) {
                        return [
                            'comment' => $comment->comment,
                            'created_at' => optional($comment->created_at)->format('Y-m-d H:i'),
                        ];
                    })->values();

                    return [$lead->id => $comments];
                });

            $existingLeadIds = Lead::whereIn('id', $leadIds)->pluck('id');

            $latestLeadOwners = DB::table('lead_user as lu')
                ->joinSub(
                    DB::table('lead_user')
                        ->selectRaw('lead_id, MAX(id) as latest_id')
                        ->whereIn('lead_id', $leadIds)
                        ->groupBy('lead_id'),
                    'latest',
                    function ($join) {
                        $join->on('latest.latest_id', '=', 'lu.id');
                    }
                )
                ->leftJoin('users', 'users.id', '=', 'lu.user_id')
                ->select('lu.lead_id', 'users.name')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [$row->lead_id => $row->name ?: 'Unassigned'];
                });

            $leadOwnerNamesByRecord = $leadIds->mapWithKeys(function ($leadId) use ($existingLeadIds, $latestLeadOwners) {
                if (!$existingLeadIds->contains($leadId)) {
                    return [$leadId => 'Deleted Lead'];
                }

                return [$leadId => $latestLeadOwners->get($leadId, 'Unassigned')];
            });
        }

        $pendingUpdates->transform(function ($update) use ($leadOwnerNamesByRecord) {
            $update->current_owner_name = $update->table_name === 'leads'
                ? $leadOwnerNamesByRecord->get($update->record_id, 'Deleted Lead')
                : '—';

            return $update;
        });

        $x['pendingUpdates'] = $pendingUpdates;
        $x['leadCommentsByRecord'] = $leadCommentsByRecord;

        return view('admin.pending-approve.pending_updates_index', $x);
    }


    public function cash_in()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Cash In Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['accountTypes'] = AccountType::where('status', 1)->orderBy('id')->get();
        $x['type'] = 'CR';
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
        $x['data'] = [];

        $x['headaccounts'] = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();
        $x['partyaccounts'] = ProjectHeadSubhead::with('subheadAccounting')
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
        $x['table_data_route'] = route('voucher.cash_in.data');
        $numbers = Ledger::where('type', 'CR')
            ->where('voucher_number', '>', 0)
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId))
            ->orderBy('voucher_number')
            ->pluck('voucher_number')
            ->toArray();

        $nextNumber = 1; // default starting number

        if (!empty($numbers)) {
            $allNumbers = range(min($numbers), max($numbers));
            $missing = array_diff($allNumbers, $numbers);
            if (!empty($missing)) {
                // Get the smallest missing number
                $nextNumber = min($missing);
            } else {
                // If no missing numbers, continue from max
                $nextNumber = max($numbers) + 1;
            }
        }

        $x['latest_voucher_number'] = $nextNumber;

        return view('admin.finance.voucher.cash_voucher', $x);
    }

    public function print($id)
    {
        $x['voucher'] = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')->findOrFail($id);
        return view('admin.finance.voucher.print-eng', $x);
    }
    public function approve($id, Request $request)
    {
        if (!in_array($request->table, $this->allowedPendingUpdateTables, true)) {
            abort(400, 'Invalid table name.');
        }

        // Fetch pending update record
        $pending = PendingUpdate::where('record_id', $id)
            ->where('table_name', $request->table)
            ->where('status', 'pending')
            ->firstOrFail();

        // Authorization check
        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }


        $pendingNewChanges = json_decode($pending->new_values, true);
        if (isset($pendingNewChanges['is_active']) && $pending->new_values == $pendingNewChanges['is_active']) {
            DB::transaction(function () use ($pending, $pendingNewChanges, $request) {
                $result = DB::table($request->table)::findOrFail($pending->record_id);
                if ($request->table == 'ledgers') {
                    $customerLedger = $result->customerLedger;
                    if ($customerLedger) {
                        $customerLedger->update($pendingNewChanges);
                    }
                }
                $result->update($pendingNewChanges);
            });
        }

        DB::transaction(function () use ($pending, $pendingNewChanges, $request) {
            // Check if record exists
            $record = DB::table($request->table)
                ->where('id', $pending->record_id)
                ->first();

            if (!$record) {
                abort(404, 'Record not found.');
            }

            // Update record dynamically
            DB::table($request->table)
                ->where('id', $pending->record_id)
                ->update($pendingNewChanges);

            // Mark pending as approved
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

        $pending = PendingUpdate::where('record_id', $id)
            ->where('table_name', $request->table)
            ->where('status', 'pending')
            ->firstOrFail();

        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }

        $pending->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

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
        if (!in_array($request->table, $this->allowedPendingUpdateTables, true)) {
            abort(400, 'Invalid table name.');
        }

        // Fetch pending update record
        $pending = PendingUpdate::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        // Authorization check
        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }
        if ($request->table == 'leads') {
            DB::table('lead_user')->where('lead_id', $pending->record_id)->update(['user_id' => $pending->submitted_by, 'created_at' => now(), 'updated_at' => now()]);
            $pending->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);
            return back()->with('success', 'Record approved successfully.');
        }


        $pendingNewChanges = json_decode($pending->new_values, true);
        if (isset($pendingNewChanges['is_active']) && $pending->new_values == $pendingNewChanges['is_active']) {
            DB::transaction(function () use ($pending, $pendingNewChanges, $request) {
                $result = DB::table($request->table)::findOrFail($pending->record_id);
                if ($request->table == 'ledgers') {
                    $customerLedger = $result->customerLedger;
                    if ($customerLedger) {
                        $customerLedger->update($pendingNewChanges);
                    }
                }
                $result->update($pendingNewChanges);
            });
        }

        DB::transaction(function () use ($pending, $pendingNewChanges, $request) {
            // Check if record exists
            $record = DB::table($request->table)
                ->where('id', $pending->record_id)
                ->first();

            if (!$record) {
                abort(404, 'Record not found.');
            }

            // Update record dynamically
            DB::table($request->table)
                ->where('id', $pending->record_id)
                ->update($pendingNewChanges);

            // Mark pending as approved
            $pending->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Record approved successfully.');
    }


    public function rejectAdmin($id, Request $request)
    {
        if (!in_array($request->table, $this->allowedPendingUpdateTables, true)) {
            abort(400, 'Invalid table name.');
        }

        $pending = PendingUpdate::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }

        $pending->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Voucher rejected.');
    }


    public function cash_out()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Cash Out Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['accountTypes'] = AccountType::where('status', 1)->orderBy('id')->get();
        $x['type'] = 'CP';
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
        $x['headaccounts'] = ProjectHeadSubhead::query()->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with(['headAccounting:id,name'])
            ->where('project_id', $selectedProjectId)
            ->get();
        $x['partyaccounts'] = ProjectHeadSubhead::query()
            ->select('project_id', 'head_accounting_id', 'subhead_accounting_id')
            ->with(['subheadAccounting:id,name'])
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
        $x['table_data_route'] = route('voucher.cash_out.data');
        $numbers = Ledger::where('type', 'CP')
            ->where('voucher_number', '>', 0)
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId))
            ->orderBy('voucher_number')
            ->pluck('voucher_number')
            ->toArray();

        $nextNumber = 1; // default starting number

        if (!empty($numbers)) {
            $allNumbers = range(min($numbers), max($numbers));
            $missing = array_diff($allNumbers, $numbers);
            if (!empty($missing)) {
                // Get the smallest missing number
                $nextNumber = min($missing);
            } else {
                // If no missing numbers, continue from max
                $nextNumber = max($numbers) + 1;
            }
        }

        $x['latest_voucher_number'] = $nextNumber;

        return view('admin.finance.voucher.cash_voucher', $x);
    }

    public function cash_out_data(Request $request)
    {
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
            ->whereIn('type', ['CP', 'BO'])
            ->whereHas('projectHeadSubhead', function ($q) use ($selectedProjectId) {
                $q->where('project_id', $selectedProjectId);
            });
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
        if ($request->has('voucher_number') && $request->voucher_number) {
            $q->where('voucher_number', 'like', '%' . $request->voucher_number . '%');
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
        $data = $rows->map(function ($i) {
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
                'voucher_number' => $i->type . '-' . $i->voucher_number,
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
        $data = $rows->map(function ($i) {
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
        $selectedProjectId = getSelectedTown();

        $query = CustomerLedger::with([
            'customer_list:id,first_name,last_name,phone_number',
            'plot_list:id,name,type',
            'ledger.projectHeadSubhead.subheadAccounting:id,name',
        ])
            ->where('project_id', $selectedProjectId)
            ->where('is_active', 1)
            ->whereIn('payment_type', [2, 3])
            ->where('passing_status', 0)
            ->orderByDesc('id');

        if ($request->filled('payment_type') && in_array((int) $request->input('payment_type'), [2, 3], true)) {
            $query->where('payment_type', (int) $request->input('payment_type'));
        }

        $pendingRows = $query->get()
            ->map(function (CustomerLedger $item) {
                return [
                    'id' => $item->id,
                    'date' => $item->date,
                    'transaction_type' => $item->transaction_type,
                    'reference' => $item->reference,
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
                ];
            });

        return response()->json([
            'status' => 'success',
            'count' => $pendingRows->count(),
            'data' => $pendingRows,
        ]);
    }

    public function updatePendingBankPaymentStatus(Request $request, $customerLedger)
    {
        $selectedProjectId = getSelectedTown();
        $validated = $request->validate([
            'passing_status' => 'required|in:1,2,3',
            'note' => 'required|string|max:1000',
            'bank_post_at' => 'nullable|date',
            'accounts_id' => 'required_if:passing_status,1',
            'subaccounts_id' => 'required_if:passing_status,1',
        ]);

        try {
            DB::transaction(function () use ($validated, $customerLedger, $selectedProjectId) {
                $lockedLedger = CustomerLedger::where('project_id', $selectedProjectId)
                    ->where('id', $customerLedger)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lockedLedger->passing_status !== 0) {
                    throw new \RuntimeException('This payment is already processed.');
                }

                $creditAccountId = null;
                if ((int) $validated['passing_status'] === 1) {
                    $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', $validated['accounts_id'])
                        ->where('subhead_accounting_id', $validated['subaccounts_id'])
                        ->where('project_id', $selectedProjectId)
                        ->value('id');

                    if (!$creditAccountId) {
                        throw new \RuntimeException('Selected bank account is invalid for this project.');
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
                    'user_id' => Auth::id(),
                ]);

                if ($status === 1) {
                    $clearanceReference = 'BANK_CLEARANCE#' . $lockedLedger->id;
                    $alreadyPosted = Ledger::where('customer_ledger_id', $lockedLedger->id)
                        ->where('type', 'BR')
                        ->where('reference', $clearanceReference)
                        ->exists();

                    if (!$alreadyPosted) {
                        $voucherNumber = getVocuherNumber('BR');
                        $lastId = getLastLedgerIdByType('BR');

                        Ledger::create([
                            'customer_ledger_id' => $lockedLedger->id,
                            'type' => 'BR',
                            'voucher_number' => $voucherNumber,
                            'type_id' => ((int) $lastId) + 1,
                            'project_head_subheads_id' => $creditAccountId,
                            'reference' => $clearanceReference,
                            'amount_in' => 0.00,
                            'amount_out' => $lockedLedger->amount_out,
                            'is_active' => 1,
                            'date' => $validated['bank_post_at'] ?? now()->toDateString(),
                            'detail' => $note,
                            'update_by' => Auth::id(),
                            'create_by' => Auth::id(),
                            'status' => 0,
                        ]);
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

    public function cash_in_data(Request $request)
    {
        $selectedProjectId = getSelectedTown();

        $q = Ledger::with([
            'projectHeadSubhead.headAccounting:id,name',
            'projectHeadSubhead.subheadAccounting:id,name',
            'projectHeadSubhead.project:id,project'
        ])
            ->where('is_active', 1)
            ->where('type', 'CR')
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId));

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

        // ----------------------------
        // Voucher Number filter (optional)
        // ----------------------------
        // if ($request->filled('voucher_number')) {
        //     $q->where('voucher_number', 'like', '%' . $request->voucher_number . '%');
        // }

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
        $data = $rows->map(function ($i) {
            $amount = $i->type === 'CP' ? (float) $i->amount_out : (float) $i->amount_in;

            $p = $i->latestPendingUpdate ?? PendingUpdate::where('table_name', 'ledgers')
                ->where('record_id', $i->id)
                ->latest()
                ->first();

            return [
                'date' => $i->date,
                'voucher_number' => $i->type . '-' . $i->voucher_number,
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

        $x['headaccounts'] = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();

        $x['partyaccounts'] = ProjectHeadSubhead::with('subheadAccounting')
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
        $selectedProjectId = getSelectedTown();
        // dd($request->all());
        $type = $request->type;
        $voucherNumber = (int) $request->number;
        // Check if voucher number already exists
        $exists = Ledger::where('type', $type)
            ->where('voucher_number', '>', 0)
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId))
            ->where('voucher_number', $voucherNumber)
            ->exists();

        if ($exists) {
            // Find next available number
            $nextNumber = $voucherNumber + 1;

            // Keep incrementing until a free number is found
            while (
                Ledger::where('type', $type)
                    ->where('voucher_number', $nextNumber)
                    ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId))
                    ->exists()
            ) {
                $nextNumber++;
            }

            $voucherNumber = $nextNumber;
        }

        // ✅ Now $voucherNumber is guaranteed unique
        $x['latest_voucher_number'] = $voucherNumber;
        return response()->json($x);
    }
    public function updateVoucherNumber()
    {
        Ledger::with('projectHeadSubhead:id,project_id')
            ->orderBy('type')
            ->orderBy('id')
            ->chunk(500, function ($ledgers) use (&$counters) {

                foreach ($ledgers as $ledger) {
                    $projectId = $ledger->projectHeadSubhead->project_id;
                    $type = $ledger->type;

                    $counters[$projectId][$type] ??= 1;

                    $ledger->update([
                        'voucher_number' => $counters[$projectId][$type]++
                    ]);
                }
            });
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
}
