<?php

namespace App\Http\Controllers;

use App\Models\PendingUpdate;
use Exception;
use App\Models\Plot;
use App\Models\User;
use App\Models\Ledger;
use App\Models\Project;

use App\Models\DraftLedger;

use Illuminate\Http\Request;
use App\Models\CustomerLedger;
use App\Models\HeadAccounting;
use App\Models\SubheadAccounting;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;


class LedgerController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required'],
            'detail' => ['required'],
            'plot_id' => 'required',
            'customer_id' => 'required',
            'accounts_id' => ['required'],
            'payment_type' => 'required',
            'subaccounts_id' => ['required'],
            'reference' => 'required',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        $selectedProjectId = getSelectedTown();
        $action = $request->input('action');
        $customer_id = $request->input('customer_id');
        $x['today'] = date("d-m-Y");
        $cleanAmount = str_replace(',', '', $request->amount);
        $voucherValue = $request->input('voucher');
        $firstTwoDigits = substr($voucherValue, 0, 2);
        $amount_in = $amount_out = 0;
        if ($firstTwoDigits === 'CR') {
            $amount_in = $cleanAmount;
        } elseif ($firstTwoDigits === 'CP') {
            $amount_out = $cleanAmount;
        }

        try {



            //
            $payment_type = $request->input('payment_type');
            if ($payment_type == 1) {
                $t_number = $bank_id = null;
            } else {
                $t_number = $request->input('t_number');
                $bank_id = $request->input('bank_id');
            }
            $plotName = Plot::where('id', $request->input('plot_id'))->value('name');
            $customerLedger = CustomerLedger::create([
                'customer_id' => $customer_id,
                'transaction_type' => $firstTwoDigits,
                'type_id' => get_new_typeID($firstTwoDigits),
                'reference' => $request->input('reference'),
                'project_id' => $selectedProjectId,
                'plot_id' => $request->input('plot_id'),
                'amount_in' => 0,
                'amount_out' => str_replace(',', '', $request->input('amount')),
                'description' => $request->input('detail'),
                'date' => $request->input('date'), // Assuming booking date is the transaction date
                'payment_type' => $request->input('payment_type'),
                't_number' => $t_number,
                'bank_id' => $bank_id,
                'is_active' => 1,
                'is_approve' => 0,
                'passing_date' => $request->input('passing_date'),
            ]);


            $lastId = getLastLedgerIdByType($firstTwoDigits);


            $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
                ->where('subhead_accounting_id', $request->subaccounts_id)
                ->where('project_id', $selectedProjectId)
                ->first();
            // dd($projectHeadSubhead->id);
            // $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
            //     ->where('subhead_accounting_id', $request->subaccounts_id)
            //     ->where('project_id', $selectedProjectId)
            //     ->where('plot_id', $request->input('plot_id'))
            //     ->where('customer_id', $customer_id)
            //     ->value('id');
            //     dd($creditAccountId);
            if (!$projectHeadSubhead) {
                throw new \Exception('Credit account ID not found.');
            }
            $data = Ledger::create([
                'customer_ledger_id' => $customerLedger->id,
                'type' => $firstTwoDigits,
                'type_id' => $lastId + 1,
                'project_head_subheads_id' => $projectHeadSubhead->id,
                'reference' => $request->reference,
                'amount_in' => $amount_in,
                'amount_out' => $amount_out,
                'is_active' => 1,
                'date' => $request->date,
                'detail' => $request->detail,
                'update_by' => Auth::user()->id,
                'create_by' => Auth::user()->id,
                'status' => 0,

            ]);
            $lastSubmitDate = $request->date; // Adjust this according to your actual form field
            session(['last_submit_date' => $lastSubmitDate]);

            if ($request->id) {
                DraftLedger::find($request->input('id'))->delete();
            }
            DB::commit();
            // Alert::success('Notification', 'Data <b></b> Save successfully ')->toToast()->toHtml();
            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }
    public function saveAsDraft(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $action = $request->input('action');
        $customer_id = $request->input('customer_id');
        $x['today'] = date("d-m-Y");
        $cleanAmount = $request->amount ? str_replace(',', '', $request->amount) : 0;
        $voucherValue = $request->input('voucher');
        $firstTwoDigits = substr($voucherValue, 0, 2);
        $amount_in = $amount_out = 0;
        if ($firstTwoDigits === 'CR') {
            $amount_in = $cleanAmount;
        } elseif ($firstTwoDigits === 'CP') {
            $amount_out = $cleanAmount;
        }
        try {
            $payment_type = $request->input('payment_type');
            if ($payment_type == 1) {
                $t_number = $bank_id = null;
            } else {
                $t_number = $request->input('t_number');
                $bank_id = $request->input('bank_id');
            }
            $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
                ->where('subhead_accounting_id', $request->subaccounts_id)
                ->where('project_id', $selectedProjectId)
                ->first();

            // if (!$projectHeadSubhead) {
            //     throw new \Exception('Credit account ID not found.');
            // }
            $draftLedger = DraftLedger::find($request->input('id'));

            $newValues = [
                // Customer Ledger Fields
                'customer_id' => $customer_id,
                'transaction_type' => $firstTwoDigits,
                'type_id' => get_new_typeID($firstTwoDigits),
                'reference' => $request->input('reference'),
                'project_id' => $selectedProjectId,
                'plot_id' => $request->input('plot_id'),
                'amount_in' => $amount_in ?? 0,
                'amount_out' => $amount_out ?? 0,
                'description' => $request->input('detail'),
                'date' => $request->input('date'),
                'payment_type' => $request->input('payment_type'),
                't_number' => $t_number,
                'bank_id' => $bank_id,
                'is_active' => 1, // As Draft
                'is_approve' => 0,
                'passing_date' => $request->input('passing_date'),
                'check_history' => $request->input('check_history'),
                'note' => $request->input('note'),
                'bank_post_at' => $request->input('bank_post_at'),

                // Ledger Fields
                'type' => $firstTwoDigits,
                'project_head_subheads_id' => $projectHeadSubhead?->id,
                'detail' => $request->input('detail'),
                'update_by' => Auth::user()->id,
                'status' => 'draft', // you can set string status
            ];

            if ($draftLedger) {
                $oldValues = $draftLedger->only(array_keys($newValues));
                if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update')) {
                    $draftLedger->update($newValues);
                    DB::commit();
                    return back()->with('success', 'Record updated successfully (direct update).');
                }
                $pendingUpdate = PendingUpdate::where('table_name', 'draft_ledgers')
                    ->where('record_id', $draftLedger->id)
                    ->where('status', 'pending')
                    ->first();

                if ($pendingUpdate) {
                    $pendingUpdate->update([
                        'old_values' => json_encode($oldValues),
                        'new_values' => json_encode($newValues),
                        'submitted_by' => Auth::id(),
                    ]);
                } else {
                    PendingUpdate::create([
                        'table_name' => 'draft_ledgers',
                        'record_id' => $draftLedger->id,
                        'old_values' => json_encode($oldValues),
                        'new_values' => json_encode($newValues),
                        'status' => 'pending',
                        'submitted_by' => Auth::id(),
                    ]);
                }

            } else {
                $data['create_by'] = Auth::user()->id;
                DraftLedger::create($newValues);
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
        return response()->json(['message' => 'Draft saved successfully.']);
    }

    public function getSubaccountDetails(Request $request)
    {
        $subaccountId = $request->input('subaccounts_id');
        $selectedProjectId = getSelectedTown();
        $subaccount = ProjectHeadSubhead::with(['subheadAccounting', 'headAccounting'])
            ->withSum('ledgers as total_in', 'amount_in')
            ->withSum('ledgers as total_out', 'amount_out')
            ->where(['subhead_accounting_id' => $subaccountId])
            ->where(['project_id' => $selectedProjectId])
            ->get()
            ->map(function ($item) {
                $item->balance = ($item->total_in ?? 0) - ($item->total_out ?? 0);
                return $item;
            });
        // dd($subaccount);
        return response()->json([
            'headId' => $subaccount[0]->headAccounting->id,
            'headName' => $subaccount[0]->headAccounting->name,
            'acct_type' => $subaccount[0]->headAccounting->acct_type,
            'balance' => $subaccount[0]->balance,
            'cnic' => $subaccount[0]->subheadAccounting->cnic,
            'phone' => $subaccount[0]->subheadAccounting->phone,
        ]);
    }

    public function show(Request $request)
    {
        $data_list = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project', 'customerLedger')->where(['id' => $request->id])->first();
        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }
    public function draftShow(Request $request)
    {
        $data_list = DraftLedger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')->where(['id' => $request->id])->first();
        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }

    public function show_ledger()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Cash Book';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CR';
        $x['class'] = 'cash-in';

        $selectedProjectId = getSelectedTown();

        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('is_active', 1) // Add this condition to filter by is_active
            ->where('type', 'CR')
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            })
            ->get();
        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
            return $item;
        });
        $x['data'] = $data;


        $projects = Project::where('id', $selectedProjectId)->get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.show_ledger', $x);
    }

    public function destroy(Request $request)
    {
        $data = [
            'delete_reason' => $request->delete_reason,
            'is_active' => "0",
        ];
        DB::beginTransaction();
        try {
            $ledger = Ledger::findOrFail($request->id);

            if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update')) {
                $ledger->update(['is_active' => 0]);
                DB::commit();

                Alert::success('Notification', 'Voucher <b>' . $ledger->detail . '</b> deleted successfully.')
                    ->toToast()->toHtml();
                return back();
            }

            PendingUpdate::create([
                'table_name' => 'ledgers',
                'record_id' => $ledger->id,
                'old_values' => json_encode($ledger->toArray()),
                'new_values' => json_encode($data),
                'status' => 'pending',
                'submitted_by' => Auth::id(),
            ]);
            DB::commit();
            Alert::info('Notification', 'Delete request for <b>' . $ledger->detail . '</b> is pending admin approval.')
                ->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollBack();
            Alert::error('Notification', 'Failed to delete voucher: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function draftDestroy(Request $request)
    {
        $data = [
            'is_active' => "0",
        ];
        DB::beginTransaction();
        try {
            $result = DraftLedger::find($request->id);
            if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update')) {
                $result->update(['is_active' => 0]);
                DB::commit();

                Alert::success('Notification', 'Voucher <b>' . $result->detail . '</b> deleted successfully.')
                    ->toToast()->toHtml();
                return back();
            }

            PendingUpdate::create([
                'table_name' => 'draft_ledgers',
                'record_id' => $result->id,
                'old_values' => json_encode($result->toArray()),
                'new_values' => json_encode($data),
                'status' => 'pending',
                'submitted_by' => Auth::id(),
            ]);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> Deleted')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> failed to delete: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function update(Request $request)
    {
        $rules = [
            'amount' => ['required'],
            'detail' => ['required'],
            'accounts_id' => ['required'],
            'subaccounts_id' => ['required'],
        ];

        $selectedProjectId = getSelectedTown();
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $cleanAmount = str_replace(',', '', $request->amount);
        $voucherValue = $request->input('voucher');
        $firstTwoDigits = substr($voucherValue, 0, 2);
        $amount_in = $amount_out = 0;

        if ($firstTwoDigits === 'CR') {
            $amount_in = $cleanAmount;
        } elseif ($firstTwoDigits === 'CP') {
            $amount_out = $cleanAmount;
        }

        $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
            ->where('subhead_accounting_id', $request->subaccounts_id)
            ->where('project_id', $selectedProjectId)
            ->firstOrFail();

        $newValues = [
            'project_head_subheads_id' => $projectHeadSubhead->id,
            'reference' => $request->reference,
            'amount_in' => $amount_in,
            'amount_out' => $amount_out,
            'is_active' => 1,
            'date' => $request->date,
            'detail' => $request->detail,
        ];

        DB::beginTransaction();
        try {
            $ledger = Ledger::findOrFail($request->id);

            $oldValues = $ledger->only(array_keys($newValues));
            // ✅ Check permissions: Super Admin or direct-update
            if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update')) {
                // 🔓 Directly update the ledger
                $ledger->update($newValues);

                DB::commit();
                return back()->with('success', 'Record updated successfully (direct update).');
            }
            // 🔒 For others: Create or update a pending update
            $pendingUpdate = PendingUpdate::where('table_name', 'ledgers')
                ->where('record_id', $ledger->id)
                ->where('status', 'pending')
                ->first();

            if ($pendingUpdate) {
                $pendingUpdate->update([
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode($newValues),
                    'submitted_by' => Auth::id(),
                ]);
            } else {
                PendingUpdate::create([
                    'table_name' => 'ledgers',
                    'record_id' => $ledger->id,
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode($newValues),
                    'status' => 'pending',
                    'submitted_by' => Auth::id(),
                ]);
            }

            DB::commit();
            return back()->with('success', 'Your changes have been submitted and are pending admin approval.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->with('error', 'Failed to submit update: ' . $th->getMessage());
        }
    }




    public function fetch_data_url(Request $request)
    {
        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        $query = Ledger::with('projectHeadSubhead.project', 'projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'createdBy:id,name')->where('is_active', 1)->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
            $query->where('project_id', $selectedProjectId);
        });

        // If only start date → from start date to all
        if (!empty($request->start_date) && empty($request->end_date)) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        // If only end date → from start to end date
        if (empty($request->start_date) && !empty($request->end_date)) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        // If both → between start and end
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // $data = $query->get();

        return DataTables::eloquent($query)
            ->addColumn('project_name', function ($ledger) {
                // Access the project name from the relationship
                return $ledger->projectHeadSubhead->project->project ?? '';
            })
            ->addColumn('accountent_name', function ($ledger) {
                // Access the head account name from the relationship
                return $ledger->createdBy->name ?? '';
            })
            ->addColumn('head_account_name', function ($ledger) {
                // Access the head account name from the relationship
                return $ledger->projectHeadSubhead->headAccounting->name ?? '';
            })
            ->addColumn('subhead_account_name', function ($ledger) {
                // Access the sub account name from the relationship
                return $ledger->projectHeadSubhead->subheadAccounting->name ?? '';
            })
            ->addColumn('action', function ($ledger) {
                // Add any additional columns or custom data here
                return '<button class="btn btn-info">Edit</button>';
            })
            ->toJson();
    }

    public function show_ledger_party()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Party wise ledger';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CR';
        $x['class'] = 'cash-in';


        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('is_active', 1) // Add this condition to filter by is_active
            ->where('type', 'CR')
            ->get();
        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
            return $item;
        });
        $x['data'] = $data;


        $projects = Project::get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.show_ledger_party', $x);
    }

    public function show_ledger_head()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = request('name') ?? 'Head wise ledger';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CR';
        $x['account_type'] = request('type') ?? 'head';
        $x['class'] = 'cash-in';


        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('is_active', 1) // Add this condition to filter by is_active
            ->where('type', 'CR')
            ->get();
        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
            return $item;
        });
        $x['data'] = $data;


        $projects = Project::get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.head_ledger', $x);
    }

    public function fetch_data_by_party(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $data = Ledger::with('projectHeadSubhead.project', 'projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')
            ->where('is_active', 1)
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            })
            ->selectRaw('project_head_subheads_id, sum(amount_in) as total_amount_in, sum(amount_out) as total_amount_out')
            ->groupBy('project_head_subheads_id');

        return DataTables::eloquent($data)
            ->addColumn('project_name', function ($ledger) {
                // Access the project name from the relationship
                return $ledger->projectHeadSubhead->project->project ?? '';
            })
            ->addColumn('head_account_name', function ($ledger) {
                // Access the head account name from the relationship
                return $ledger->projectHeadSubhead->headAccounting->name ?? '';
            })
            ->addColumn('subhead_account_name', function ($ledger) {
                // Access the sub account name from the relationship
                return $ledger->projectHeadSubhead->subheadAccounting->name ?? '';
            })
            ->addColumn('total_amount_in', function ($ledger) {
                // Access the total amount in for the project_head_subheads_id
                return $ledger->total_amount_in ?? '';
            })
            ->addColumn('total_amount_out', function ($ledger) {
                // Access the total amount out for the project_head_subheads_id
                return $ledger->total_amount_out ?? '';
            })
            ->addColumn('balance_amount', function ($ledger) {
                // Calculate the balance by subtracting total_amount_out from total_amount_in
                return ($ledger->total_amount_in ?? 0) - ($ledger->total_amount_out ?? 0);
            })
            ->toJson();
    }

    public function fetch_data_by_head(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        if ($request->account_type == 'account') {
            $data = Ledger::query()
                ->join('project_head_subheads', 'ledgers.project_head_subheads_id', '=', 'project_head_subheads.id')
                ->join('head_accountings', 'project_head_subheads.head_accounting_id', '=', 'head_accountings.id')
                ->where('ledgers.is_active', 1)
                ->where('project_head_subheads.project_id', $selectedProjectId)
                ->selectRaw('
                head_accountings.acct_type as acct_type,
                SUM(ledgers.amount_in) as total_amount_in,
                SUM(ledgers.amount_out) as total_amount_out
            ')
                ->groupBy('head_accountings.acct_type'); // ✅ only group by acct_type

            return DataTables::of($data)
                ->addColumn('head_account_name', function ($ledger) {
                    return $this->getAccountName($ledger->acct_type); // 👈 map acct_type → readable name
                })
                ->addColumn('total_amount_in', fn($ledger) => $ledger->total_amount_in ?? 0)
                ->addColumn('total_amount_out', fn($ledger) => $ledger->total_amount_out ?? 0)
                ->addColumn('balance_amount', fn($ledger) => ($ledger->total_amount_in ?? 0) - ($ledger->total_amount_out ?? 0))
                ->toJson();

        }
        $data = Ledger::query()
            ->join('project_head_subheads', 'ledgers.project_head_subheads_id', '=', 'project_head_subheads.id')
            ->join('head_accountings', 'project_head_subheads.head_accounting_id', '=', 'head_accountings.id')
            ->where('ledgers.is_active', 1)
            ->where('project_head_subheads.project_id', $selectedProjectId)
            ->selectRaw('
            project_head_subheads.head_accounting_id,
            head_accountings.name as head_account_name,
            SUM(ledgers.amount_in) as total_amount_in,
            SUM(ledgers.amount_out) as total_amount_out
        ')
            ->groupBy('project_head_subheads.head_accounting_id', 'head_accountings.name'); // Ensure name is grouped too

        return DataTables::of($data)
            ->addColumn('total_amount_in', function ($ledger) {
                return $ledger->total_amount_in ?? '';
            })
            ->addColumn('total_amount_out', function ($ledger) {
                return $ledger->total_amount_out ?? '';
            })
            ->addColumn('balance_amount', function ($ledger) {
                return ($ledger->total_amount_in ?? 0) - ($ledger->total_amount_out ?? 0);
            })
            ->toJson();
    }
    private function getAccountName($typeId)
    {
        $map = [
            0 => 'Pleas Update',
            1 => 'Assets',
            2 => 'Owner',
            3 => 'Recovery',
            4 => 'Expense',
            5 => 'Amanat Payments',
        ];

        return $map[$typeId] ?? 'Unknown';
    }
}
