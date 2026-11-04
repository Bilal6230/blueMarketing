<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccountType;
use App\Models\AccountGroup;
use App\Models\HeadAccounting;
use App\Models\ProjectHeadSubhead;
use App\Models\SubheadAccounting;
use App\Models\AccountingExpense;
use App\Models\Ledger;
use App\Models\Project;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingController extends Controller
{


    public function index(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project first.');
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Accounts List';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        // $x['data']      = AccountingExpense::get();


        // $x['data'] = AccountingExpense::where(['is_active' => 1])->get();


        $x['data'] = AccountingExpense::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->whereHas('projectHeadSubhead', fn ($query) => $query->where('project_id', $projectId))
            ->where('is_active', 1)->get();
        // dd($x['data']);


        $projects = Project::whereKey($projectId)->get();
        // $headaccounts = HeadAccounting::get();
        // $subheadaccounts = SubheadAccounting::get();

        $x['projects'] = $projects;
        // $x['headaccounts'] = $headaccounts;
        // $x['subheadaccounts'] = $subheadaccounts;
        return view('admin.finance.voucher.accounting_index', $x);
    }


    public function store(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project first.');
        $validator = Validator::make($request->all(), [
            'price' => ['required', 'numeric', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'detail' => ['required'],
            'accounts_id' => ['required'],
            'subaccounts_id' => ['required'],
            'projects_id' => ['required'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        abort_unless((int) $request->input('projects_id') === $projectId, 403,
            'The account must belong to the selected project.');

        $data = DB::transaction(function () use ($request, $projectId): AccountingExpense {
            $projectHeadSubhead = ProjectHeadSubhead::availableForPosting()
                ->where('head_accounting_id', $request->accounts_id)
                ->where('subhead_accounting_id', $request->subaccounts_id)
                ->where('project_id', $projectId)->lockForUpdate()
                ->first();
            if (!$projectHeadSubhead) {
                throw ValidationException::withMessages([
                    'subaccounts_id' => 'Select an active account from the selected project.',
                ]);
            }
            return AccountingExpense::create([
                'price' => $request->price,
                'detail' => $request->detail,
                'cash_in' => $request->boolean('cash_in'),
                'project_head_subheads_id' => $projectHeadSubhead->id,
                'create_by' => Auth::user()->id
            ]);
        });

        Alert::success('Notification', 'Accounting entry saved successfully.')->toToast()->toHtml();
        return back();
    }


    public function get_accounts_by_project(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project first.');
        if ($request->filled('projectID')) {
            abort_unless((int) $request->input('projectID') === $projectId, 403, 'Account mappings must belong to the selected project.');
        }
        $data_list = ProjectHeadSubhead::availableForPosting()
            ->with('headAccounting')->where('project_id', $projectId)->get();
        // dd($data_list);


        return $data_list;
    }
    public function Accountant(Request $request)
    {
        $x['title'] = 'Accountants List';
        $selectedProjectId = getSelectedTown();
        $accountants = User::query()
            // include users in the project OR users with any ledger in the project
            ->where(function ($q) use ($selectedProjectId) {
                $q->where('users.project_id', $selectedProjectId)
                    ->orWhereHas('ledgers.projectHeadSubhead', function ($qq) use ($selectedProjectId) {
                        $qq->where('project_id', $selectedProjectId);
                    });
            })
            // pick columns first (so withCount isn’t wiped)
            ->select([
                'users.id',
                'users.name',
                'users.phone_number',
                'users.email',
                'users.nic_number',
                'users.project_id',
            ])
            // count only ledgers for this project
            ->withCount([
                'ledgers as matching_ledgers_count' => function ($q) use ($selectedProjectId) {
                    $q->whereHas('projectHeadSubhead', function ($e) use ($selectedProjectId) {
                        $e->where('project_id', $selectedProjectId);
                    });
                }
            ])
            ->orderByDesc('matching_ledgers_count')
            ->orderBy('users.name')
            ->get();
        $x['accountants'] = $accountants;
        return view('admin.finance.accountant.index', $x);
    }
    public function AccountantLedgers(User $user, Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project first.');
        if ($request->filled('project_id')) {
            abort_unless($request->integer('project_id') === $projectId, 403,
                'Ledger records must belong to the selected project.');
        }

        $ledgers = Ledger::query()
            ->where('create_by', $user->id) // or ->where('user_id', $user->id) depending on your schema
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $projectId))
            ->with(['projectHeadSubhead:id,project_id,head_accounting_id,subhead_accounting_id,plot_id,customer_id'])
            ->orderBy('date', 'asc')
            ->get([
                'id',
                'type',
                'type_id',
                'project_head_subheads_id',
                'reference',
                'amount_in',
                'amount_out',
                'detail',
                'create_by',
                'date',
                'created_at'
            ]);

        return response()->json(['ledgers' => $ledgers]);
    }

    public function get_account(Request $request)
    {

        $action = $request->input('action');
        $selectedProjectId = getSelectedTown();
        try {

            switch ($action) {

                case 'get_head':

                    $query = ProjectHeadSubhead::availableForPosting();
                    $data_list = $query->select('project_id', 'head_accounting_id')
                        ->distinct()
                        ->whereHas('headAccounting', function ($query) use ($request) {
                            $query->where('acct_type', $request->acct_type);
                        })
                        ->where(['project_id' => $selectedProjectId])
                        ->with('headAccounting')
                        ->get();

                    $response = $data_list;
                    break;

                case 'get_child':

                    $query = ProjectHeadSubhead::availableForPosting();
                    $data_list = $query->with(['subheadAccounting', 'headAccounting'])
                        ->withSum('ledgers as total_in', 'amount_in')
                        ->withSum('ledgers as total_out', 'amount_out')
                        ->where(['head_accounting_id' => $request->accountID])
                        ->where(['project_id' => $selectedProjectId])
                        ->get();
                    $data_list = $data_list->map(function ($item) {
                        $item->balance = ($item->total_in ?? 0) - ($item->total_out ?? 0);
                        return $item;
                    });


                    $response = $data_list;
                    break;
            }
            return $response;
        } catch (Exception $ex) {
            // $response['msg'] = AppRepo::create_error_log("Tag", "get_slide_html_($action)_by_$app_user_id", $ex, $app_user_id);
        }
    }


    public function get_subaccounts_by_project(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project first.');
        abort_unless((int) $request->input('projectID') === $projectId, 403, 'Account mappings must belong to the selected project.');
        $query = ProjectHeadSubhead::availableForPosting()->with('subheadAccounting');
        $data_list = $query->where('head_accounting_id', $request->accountID)->where('project_id', $projectId)->get();
        // dd($data_list);


        return $data_list;
    }


    public function head_index(Request $request)
    {

        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Main Accounts';
        $x['data'] = HeadAccounting::get();
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['accountTypes'] = AccountType::where('status', 1)->orderBy('id')->get();
        return view('admin.finance.accounting.head_accounts', $x);
    }

    public function head_store(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:25'],
            'is_active' => ['required', 'boolean'],
            'acct_type' => ['required', 'integer', 'exists:account_types,id'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }


        try {
            $data = HeadAccounting::create([

                'name' => $request->name,
                'is_active' => $request->is_active,
                'acct_type' => $request->acct_type,
                'create_by' => Auth::user()->id

            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function head_show(Request $request)
    {
        $data_list = HeadAccounting::where(['id' => $request->id])->first();


        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }

    public function head_update(Request $request)
    {
        $rules = [
            'id' => ['required', 'integer', 'exists:head_accountings,id'],
            'name' => ['required', 'string', 'max:25'],
            'acct_type' => ['required', 'integer', 'exists:account_types,id'],
            'is_active' => ['required', 'boolean'],
            'status_change_reason' => ['nullable', 'string', 'max:500'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $result = HeadAccounting::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $oldStatus = (int) $result->is_active;
            $newStatus = (int) $request->is_active;
            if ($oldStatus !== $newStatus) {
                Validator::make($request->all(), [
                    'status_change_reason' => ['required', 'string', 'max:500'],
                ])->validate();
            }

            $result->update([
                'name' => $request->name,
                'is_active' => $request->is_active,
                'acct_type' => $request->acct_type, // ✅ added this
            ]);
            if ($oldStatus !== $newStatus) {
                app(\App\Services\Finance\AccountDefinitionStatusAudit::class)->record(
                    'head', (int) $result->id, $oldStatus, $newStatus,
                    (string) $request->status_change_reason, Auth::id()
                );
            }

            DB::commit();

            Alert::success('Notification', 'Data <b>' . $result->name . '</b> updated successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            if ($th instanceof \Illuminate\Validation\ValidationException) {
                throw $th;
            }
            Alert::error('Notification', 'Update failed: <b>' . $th->getMessage() . '</b>')->toToast()->toHtml();
        }

        return back();
    }



    public function head_destroy(Request $request)
    {
    }








    public function subhead_index(Request $request)
    {

        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Party Accounts';
        $x['data'] = SubheadAccounting::get();
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        return view('admin.finance.accounting.child_accounts', $x);
    }


    public function subhead_store(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:25'],
            'is_active' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }


        try {
            $data = SubheadAccounting::create([
                'name' => $request->name,
                'urdu_name' => $request->urdu_name,
                'is_active' => $request->is_active,
                'cnic' => $request->cnic,
                'phone' => $request->phone,
                'create_by' => Auth::user()->id

            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            report($th);
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }



    public function subhead_show(Request $request)
    {
        $data_list = SubheadAccounting::where(['id' => $request->id])->first();


        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }




    public function subhead_update(Request $request)
    {
        $rules = [
            'id' => ['required', 'integer', 'exists:subhead_accountings,id'],
            'name' => ['required', 'string', 'max:25'],
            'is_active' => ['required', 'boolean'],
            'status_change_reason' => ['nullable', 'string', 'max:500'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        $data = [
            'name' => $request->name,
            'urdu_name' => $request->urdu_name,
            'cnic' => $request->cnic,
            'phone' => $request->phone,
            'is_active' => $request->is_active,
        ];

        DB::beginTransaction();
        try {
            $result = SubheadAccounting::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $oldStatus = (int) $result->is_active;
            $newStatus = (int) $request->is_active;
            if ($oldStatus !== $newStatus) {
                Validator::make($request->all(), [
                    'status_change_reason' => ['required', 'string', 'max:500'],
                ])->validate();
            }
            $result->update($data);
            if ($oldStatus !== $newStatus) {
                app(\App\Services\Finance\AccountDefinitionStatusAudit::class)->record(
                    'subhead', (int) $result->id, $oldStatus, $newStatus,
                    (string) $request->status_change_reason, Auth::id()
                );
            }
            //$result->syncRoles($request->role);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> berhasil disimpan')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            if ($th instanceof \Illuminate\Validation\ValidationException) {
                throw $th;
            }
            Alert::error('Notification', 'Update failed: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }


    public function subhead_destroy(Request $request)
    {
    }

    public function account_groups_index(Request $request)
    {
        $storageReady = $this->accountGroupsStorageReady();
        $projectId = (int) getSelectedTown();
        $preview = null;
        if ($storageReady && ($request->filled('source_group_id') || $request->filled('target_group_id'))) {
            $data = $request->validate([
                'source_group_id' => ['required', 'integer', 'exists:account_groups,id'],
                'target_group_id' => ['required', 'integer', 'exists:account_groups,id', 'different:source_group_id'],
            ]);
            $preview = app(\App\Services\Finance\AccountGroupMergePreview::class)
                ->build($projectId, (int) $data['source_group_id'], (int) $data['target_group_id']);
        }
        return view('admin.finance.accounting.account_groups', [
            'title' => 'Account Groups',
            'storageReady' => $storageReady,
            'projectId' => $projectId,
            'groups' => $storageReady ? AccountGroup::with(['parent', 'heads' => fn ($query) => $query->wherePivot('project_id', $projectId)->orderBy('name')])
                ->where('project_id', $projectId)->orderBy('name')->get() : collect(),
            'parents' => $storageReady ? AccountGroup::where('project_id', $projectId)->where('is_active', 1)->orderBy('name')->get() : collect(),
            'unassignedHeads' => $storageReady ? HeadAccounting::where('is_active', 1)
                ->whereHas('projects', fn ($query) => $query->where('projects.id', $projectId))
                ->whereNotIn('id', DB::table('project_head_account_groups')->select('head_accounting_id')->where('project_id', $projectId))
                ->orderBy('name')->get() : collect(),
            'preview' => $preview,
        ]);
    }

    public function account_group_store(Request $request)
    {
        abort_unless($this->accountGroupsStorageReady(), 503, 'Account Group migrations must be applied before editing groups.');
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 422, 'Select a project before creating account groups.');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('account_groups', 'code')->where(fn ($query) => $query->where('project_id', $projectId))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:account_groups,id'],
        ]);
        DB::transaction(function () use ($data, $projectId) {
            if (!empty($data['parent_id'])) {
                $parent = AccountGroup::where('project_id', $projectId)->whereKey($data['parent_id'])->lockForUpdate()->firstOrFail();
                abort_unless($parent->is_active, 422, 'Select an active parent group.');
            }
            $group = AccountGroup::create($data + ['project_id' => $projectId, 'is_active' => 1, 'create_by' => Auth::id()]);
            app(\App\Services\Finance\AccountGroupAudit::class)->record(
                $projectId, (int) $group->id, 'created', Auth::id(), null, null, $group->only(['parent_id', 'code', 'name', 'description', 'is_active'])
            );
        });
        return back()->with('success', 'Account group created.');
    }

    public function account_group_update(Request $request, AccountGroup $group)
    {
        abort_unless($this->accountGroupsStorageReady(), 503, 'Account Group migrations must be applied before editing groups.');
        $projectId = (int) getSelectedTown();
        abort_unless((int) $group->project_id === $projectId, 404);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('account_groups', 'code')->where(fn ($query) => $query->where('project_id', $projectId))->ignore($group->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:account_groups,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($group, $data, $projectId) {
            // Serialize hierarchy edits so concurrent requests cannot create a cycle.
            AccountGroup::where('project_id', $projectId)->orderBy('id')->lockForUpdate()->get(['id']);
            $locked = AccountGroup::where('project_id', $projectId)->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
            if ($parentId !== null) {
                if ($parentId === (int) $locked->id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'A group cannot be its own parent.']);
                }
                if (!AccountGroup::where('project_id', $projectId)->whereKey($parentId)->where('is_active', 1)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'Select an active parent group.']);
                }
                if ($this->accountGroupDescendants((int) $locked->id, $projectId)->contains($parentId)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'A group cannot be moved under one of its descendants.']);
                }
            }
            $before = $locked->only(['parent_id', 'code', 'name', 'description', 'is_active']);
            $locked->update(array_merge($data, ['parent_id' => $parentId]));
            app(\App\Services\Finance\AccountGroupAudit::class)->record(
                $projectId, (int) $locked->id, 'updated', Auth::id(), null, $before,
                $locked->fresh()->only(['parent_id', 'code', 'name', 'description', 'is_active'])
            );
        });

        return back()->with('success', 'Account group updated.');
    }

    public function account_group_assign_head(Request $request, AccountGroup $group)
    {
        abort_unless($this->accountGroupsStorageReady(), 503, 'Account Group migrations must be applied before assigning Heads.');
        $data = $request->validate(['head_id' => ['required', 'integer', 'exists:head_accountings,id']]);
        $projectId = (int) getSelectedTown();
        abort_unless((int) $group->project_id === $projectId, 404);
        DB::transaction(function () use ($group, $data, $projectId) {
            $lockedGroup = AccountGroup::where('project_id', $projectId)->whereKey($group->id)->where('is_active', 1)->lockForUpdate()->firstOrFail();
            $head = HeadAccounting::whereKey($data['head_id'])->where('is_active', 1)->lockForUpdate()->firstOrFail();
            abort_unless($head->projects()->where('projects.id', $projectId)->exists(), 422,
                'This Head is not mapped to the selected project.');
            abort_unless(!DB::table('project_head_account_groups')->where('project_id', $projectId)->where('head_accounting_id', $head->id)->exists(), 409,
                'This Head already belongs to a group. Use a reviewed group merge before changing its classification.');
            $before = ['account_group_id' => null, 'project_id' => $projectId];
            DB::table('project_head_account_groups')->insert([
                'project_id' => $projectId, 'head_accounting_id' => $head->id,
                'account_group_id' => $lockedGroup->id, 'create_by' => Auth::id(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(\App\Services\Finance\AccountGroupAudit::class)->record(
                $projectId, (int) $lockedGroup->id, 'head_assigned', Auth::id(), (int) $head->id, $before,
                ['account_group_id' => (int) $lockedGroup->id, 'project_id' => $projectId]
            );
        });

        return back()->with('success', 'Head assigned to the account group.');
    }

    public function account_group_merge_apply(Request $request, AccountGroup $group)
    {
        abort_unless($this->accountGroupsStorageReady(), 503, 'Account Group migrations must be applied before merging groups.');
        $data = $request->validate([
            'target_group_id' => ['required', 'integer', 'exists:account_groups,id', 'not_in:'.$group->id],
            'confirm_merge' => ['required', 'accepted'],
        ]);
        $projectId = (int) getSelectedTown();
        abort_unless((int) $group->project_id === $projectId, 404);

        DB::transaction(function () use ($group, $data, $projectId) {
            AccountGroup::where('project_id', $projectId)->orderBy('id')->lockForUpdate()->get(['id']);
            $source = AccountGroup::where('project_id', $projectId)->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $target = AccountGroup::where('project_id', $projectId)->whereKey($data['target_group_id'])->lockForUpdate()->firstOrFail();
            $preview = app(\App\Services\Finance\AccountGroupMergePreview::class)->build(
                $projectId, (int) $source->id, (int) $target->id
            );
            abort_unless($preview['can_apply'], 409, 'Merge blocked. Resolve every preview finding and rebuild the preview.');

            $sourceHeadIds = $preview['source_heads']->pluck('head_accounting_id')->map(fn ($id) => (int) $id)->all();
            $mappingIds = DB::table('project_head_subheads')->where('project_id', $projectId)
                ->whereIn('head_accounting_id', $sourceHeadIds)->orWhere(function ($query) use ($projectId, $preview) {
                    $targetHeadIds = $preview['target_heads']->pluck('head_accounting_id')->all();
                    $query->where('project_id', $projectId)->whereIn('head_accounting_id', $targetHeadIds);
                })->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $before = $this->accountGroupMergeLedgerSnapshot($mappingIds);

            $updated = DB::table('project_head_account_groups')->where('project_id', $projectId)
                ->where('account_group_id', $source->id)->update(['account_group_id' => $target->id, 'updated_at' => now()]);
            abort_unless($updated === count($sourceHeadIds), 409, 'The source Head assignments changed. Refresh the preview.');

            $after = $this->accountGroupMergeLedgerSnapshot($mappingIds);
            abort_unless($before === $after, 409, 'Ledger totals changed during merge. The operation was rolled back.');

            foreach ($sourceHeadIds as $headId) {
                app(\App\Services\Finance\AccountGroupAudit::class)->record(
                    $projectId, (int) $source->id, 'head_assigned', Auth::id(), $headId,
                    ['account_group_id' => (int) $source->id], ['account_group_id' => (int) $target->id]
                );
            }
            $sourceBefore = $source->only(['is_active', 'parent_id', 'code', 'name']);
            $source->update(['is_active' => 0]);
            app(\App\Services\Finance\AccountGroupAudit::class)->record(
                $projectId, (int) $source->id, 'merge_applied', Auth::id(), null, $sourceBefore,
                ['merged_into_group_id' => (int) $target->id, 'moved_head_ids' => $sourceHeadIds, 'ledger_snapshot' => $after]
            );
        });

        return redirect()->route('accounting.groups')->with('success', 'Group classification merged for this project. Ledger rows were not changed.');
    }

    private function accountGroupMergeLedgerSnapshot(array $mappingIds): array
    {
        if ($mappingIds === []) return ['ledger_rows' => 0, 'amount_in_cents' => 0, 'amount_out_cents' => 0];
        $totals = DB::table('ledgers')->whereIn('project_head_subheads_id', $mappingIds)->where('is_active', 1)
            ->selectRaw('COUNT(*) as ledger_rows, COALESCE(SUM(amount_in), 0) as amount_in, COALESCE(SUM(amount_out), 0) as amount_out')->first();
        $amountIn = (string) $totals->amount_in;
        $amountOut = (string) $totals->amount_out;
        $toCents = static function (string $amount): int {
            $negative = str_starts_with($amount, '-');
            $amount = ltrim($amount, '-');
            if (str_contains($amount, '.')) $amount = rtrim(rtrim($amount, '0'), '.');
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
                throw new \DomainException('ACCOUNT_GROUP_MERGE_INVALID_AMOUNT');
            }
            [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '');
            $cents = ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
            return $negative ? -$cents : $cents;
        };
        return [
            'ledger_rows' => (int) $totals->ledger_rows,
            'amount_in_cents' => $toCents($amountIn),
            'amount_out_cents' => $toCents($amountOut),
        ];
    }

    private function accountGroupDescendants(int $groupId, int $projectId): \Illuminate\Support\Collection
    {
        $seen = collect();
        $pending = collect([$groupId]);
        while ($pending->isNotEmpty()) {
            $parentId = (int) $pending->shift();
            $children = AccountGroup::where('project_id', $projectId)->where('parent_id', $parentId)->pluck('id')
                ->map(fn ($id) => (int) $id)->filter(fn ($id) => !$seen->contains($id));
            $seen = $seen->merge($children);
            $pending = $pending->merge($children);
        }
        return $seen;
    }

    private function accountGroupsStorageReady(): bool
    {
        return Schema::hasTable('account_groups')
            && Schema::hasTable('project_head_account_groups')
            && Schema::hasTable('account_group_audits');
    }




    public function details_index(Request $request)
    {
        $selectedProjectId = (int) getSelectedTown();
        abort_if($selectedProjectId < 1, 403, 'Select a project first.');
        if ($request->filled('projects_id')) {
            abort_unless((int) $request->input('projects_id') === $selectedProjectId, 403,
                'Accounting details must belong to the selected project.');
        }
        $x['title'] = 'Party Ledger';
        $power = Auth::user()->roles[0]->name;
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $query = AccountingExpense::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->whereHas('projectHeadSubhead', fn ($q) => $q->where('project_id', $selectedProjectId));

        if (isset($request->accounts_id)) {
            $query->whereHas('projectHeadSubhead.headAccounting', function ($q) use ($request) {
                $q->where('id', $request->accounts_id);
            });
        }

        if (isset($request->subaccounts_id)) {
            $query->whereHas('projectHeadSubhead.subheadAccounting', function ($q) use ($request) {
                $q->where('id', $request->subaccounts_id);
            });
        }

        if (isset($request->projects_id)) {
            $query->whereHas('projectHeadSubhead.project', function ($q) use ($request) {
                $q->where('id', $request->projects_id);
            });
        }

        $x['data'] = $query->where('is_active', 1)->where('cash_in', 0)->get();

        $totalPrice = 0;

        foreach ($x['data'] as $expense) {
            $totalPrice += $expense->price;
        }

        // dd($totalPrice);
        $x['total_price'] = $totalPrice;

        // $projects = Project::get();
        $projects = Project::where('id', $selectedProjectId)->get();

        $headSubHeads = ProjectHeadSubhead::with(['headAccounting', 'subheadAccounting'])
            ->where('project_id', $selectedProjectId)
            ->get();

        $headaccounts = $headSubHeads->pluck('headAccounting')->unique('id')->values();
        $subheadaccounts = $headSubHeads->pluck('subheadAccounting')->unique('id')->values();

        $x['projects'] = $projects;
        $x['headaccounts'] = $headaccounts;
        $x['subheadaccounts'] = $subheadaccounts;

        return view('admin.finance.reports.details_index', $x);
    }




    public function category_index(Request $request)
    {
        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Category Details';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;

        $selectedProjectId = getSelectedTown();
        $x['headaccounts'] = HeadAccounting::where('is_active', 1)->orderBy('name')->get();
        $x['subheadaccounts'] = SubheadAccounting::where('is_active', 1)->orderBy('name')->get();
        $x['projects'] = Project::whereKey($selectedProjectId)->get();

        $x['categoryMappings'] = ProjectHeadSubhead::query()
            ->leftJoin('projects', 'projects.id', '=', 'project_head_subheads.project_id')
            ->leftJoin('head_accountings', 'head_accountings.id', '=', 'project_head_subheads.head_accounting_id')
            ->leftJoin('subhead_accountings', 'subhead_accountings.id', '=', 'project_head_subheads.subhead_accounting_id')
            ->where('projects.id', $selectedProjectId)
            ->whereNull('project_head_subheads.plot_id')
            ->whereNull('project_head_subheads.customer_id')
            ->select(
                'project_head_subheads.id',
                'projects.project as project_name',
                'head_accountings.name as head_name',
                'subhead_accountings.name as subhead_name'
            )
            ->orderBy('project_head_subheads.id', 'desc')
            ->get();

        return view('admin.finance.accounting.category_index', $x);
    }




    public function category_store(Request $request)
    {
        $data = $request->validate([
            'accounts_id' => ['required', 'integer', 'min:1', 'exists:head_accountings,id'],
            'subaccounts_id' => ['required', 'integer', 'min:1', 'exists:subhead_accountings,id'],
        ]);
        $selectedProjectId = (int) getSelectedTown();
        abort_if($selectedProjectId < 1, 403, 'Select a project first.');
        try {
            DB::transaction(function () use ($data, $selectedProjectId): void {
                Project::whereKey($selectedProjectId)->lockForUpdate()->firstOrFail();
                $head = HeadAccounting::whereKey((int) $data['accounts_id'])->lockForUpdate()->firstOrFail();
                $subhead = SubheadAccounting::whereKey((int) $data['subaccounts_id'])->lockForUpdate()->firstOrFail();
                abort_unless((int) $head->is_active === 1 && (int) $subhead->is_active === 1, 422,
                    'Inactive account definitions cannot be assigned for new posting.');

                $exists = ProjectHeadSubhead::query()->where('project_id', $selectedProjectId)
                    ->where('head_accounting_id', $head->id)->where('subhead_accounting_id', $subhead->id)
                    ->whereNull('plot_id')->whereNull('customer_id')->exists();
                abort_if($exists, 409, 'This account mapping already exists in the selected project.');

                ProjectHeadSubhead::create([
                    'project_id' => $selectedProjectId, 'head_accounting_id' => $head->id,
                    'subhead_accounting_id' => $subhead->id, 'plot_id' => null, 'customer_id' => null,
                ]);
            }, 3);

            Alert::success('Notification', 'Account mapping saved successfully.')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Account mapping could not be saved. ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }
    public function category_edit($id)
    {
        // Get the record
        $record = DB::table('project_head_subheads as phs')
            ->leftJoin('subhead_accountings as sh', 'sh.id', '=', 'phs.subhead_accounting_id')
            ->select(
                'phs.id',
                'phs.project_id',
                'phs.subhead_accounting_id',
                'phs.head_accounting_id',
                'sh.name as subhead_name'
            )
            ->where('phs.project_id', getSelectedTown())
            ->whereNull('phs.plot_id')
            ->whereNull('phs.customer_id')
            ->where('phs.id', $id)
            ->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Record not found'
            ]);
        }

        // Offer active heads for new mappings, retaining the current head only
        // when it has since been deactivated so historical identity is visible.
        $allHeads = DB::table('head_accountings')
            ->select('id', 'name')
            ->where(function ($query) use ($record): void {
                $query->where('is_active', 1)->orWhere('id', $record->head_accounting_id);
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $record->id,
                'subhead_name' => $record->subhead_name,
                'selected_head_id' => $record->head_accounting_id, // just the id
            ],
            'heads' => $allHeads // array of {id, name}
        ]);
    }


    public function category_update(Request $request, $id)
    {
        $data = $request->validate([
            'head_ids' => ['required', 'array', 'min:1'],
            'head_ids.*' => ['required', 'integer', 'min:1', 'distinct', 'exists:head_accountings,id'],
        ]);

        // Get the base record
        $baseRecord = ProjectHeadSubhead::query()->where('id', $id)
            ->where('project_id', getSelectedTown())->whereNull('plot_id')->whereNull('customer_id')->first();
        if (!$baseRecord) {
            return response()->json(['status' => 'error', 'message' => 'Record not found']);
        }

        $projectId = $baseRecord->project_id;
        $subheadId = $baseRecord->subhead_accounting_id;
        $newHeadIds = array_values(array_unique(array_map('intval', $data['head_ids'])));

        DB::beginTransaction();
        try {
            // Serialize edits to this project's generic mappings so concurrent
            // requests cannot both pass the duplicate check and reassign the
            // same base row from a stale snapshot.
            Project::whereKey($projectId)->lockForUpdate()->firstOrFail();

            // Get existing head IDs for this project + subhead
            $existingHeadIds = ProjectHeadSubhead::query()
                ->where('project_id', $projectId)
                ->where('subhead_accounting_id', $subheadId)
                ->whereNull('plot_id')->whereNull('customer_id')
                ->orderBy('id')->lockForUpdate()
                ->pluck('head_accounting_id')
                ->toArray();

            $activeHeadIds = HeadAccounting::query()->whereIn('id', $newHeadIds)
                ->where('is_active', 1)->lockForUpdate()->pluck('id')->map(fn ($value) => (int) $value)->all();
            $inactiveNewHeadIds = array_diff($newHeadIds, $activeHeadIds, array_map('intval', $existingHeadIds));
            if ($inactiveNewHeadIds !== []) {
                throw new \InvalidArgumentException('Inactive head accounts cannot be assigned to a new mapping.');
            }

            // Filter new heads that don't already exist
            $filteredHeads = array_diff($newHeadIds, $existingHeadIds);

            if (empty($filteredHeads)) {
                // If all already exist, just return success (no change)
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'No new head accounts to update.'
                ]);
            }

            if (!SubheadAccounting::whereKey($subheadId)->where('is_active', 1)->exists()) {
                throw new \InvalidArgumentException('An inactive sub-head cannot receive a new account mapping.');
            }

            // Use first new head to update existing record
            $firstHeadId = array_shift($filteredHeads);

            ProjectHeadSubhead::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            app(\App\Services\Finance\AccountMappingHistoryGuard::class)->assertUnused((int) $id);

            ProjectHeadSubhead::query()
                ->where('id', $id)
                ->where('project_id', $projectId)
                ->whereNull('plot_id')->whereNull('customer_id')
                ->update([
                    'head_accounting_id' => $firstHeadId,
                    'updated_at' => now()
                ]);

            // Insert remaining heads (if any)
            foreach ($filteredHeads as $headId) {
                // Double-check again for duplicates (race condition safety)
                $exists = ProjectHeadSubhead::query()
                    ->where('project_id', $projectId)
                    ->where('subhead_accounting_id', $subheadId)
                    ->where('head_accounting_id', $headId)
                    ->whereNull('plot_id')->whereNull('customer_id')
                    ->exists();

                if (!$exists) {
                    ProjectHeadSubhead::query()->insert([
                        'project_id' => $projectId,
                        'subhead_accounting_id' => $subheadId,
                        'head_accounting_id' => $headId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Head Accounts updated successfully!'
            ]);

        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\DomainException $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Account has financial history and cannot be reassigned.'], 409);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating: ' . $e->getMessage()
            ]);
        }
    }

    public function category_delete($id)
    {
        try {
            $deleted = DB::transaction(function () use ($id): bool {
                $projectId = (int) getSelectedTown();
                Project::whereKey($projectId)->lockForUpdate()->first();
                $mapping = ProjectHeadSubhead::query()->whereKey($id)
                    ->where('project_id', $projectId)->whereNull('plot_id')->whereNull('customer_id')
                    ->lockForUpdate()->first();
                if (!$mapping) {
                    return false;
                }
                app(\App\Services\Finance\AccountMappingHistoryGuard::class)->assertUnused((int) $id);
                $mapping->delete();
                return true;
            });
            if (!$deleted) {
                return response()->json(['status' => 'error', 'message' => 'Record not found'], 404);
            }

            return response()->json(['status' => 'success', 'message' => 'Record deleted successfully!']);
        } catch (\DomainException $e) {
            return response()->json(['status' => 'error', 'message' => 'Account has financial history and cannot be deleted.'], 409);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Something went wrong!']);
        }
    }
    public function details_party_ledger(Request $request)
    {
        $projects_id = getSelectedTown();
        // Get ProjectHeadSubhead ID(s)
        $query = ProjectHeadSubhead::where('project_id', $projects_id);

        // Filter by head_accounting_id if provided
        if ($request->filled('accounts_id')) {
            $query->where('head_accounting_id', $request->accounts_id);
        }

        // Filter by subhead_accounting_id if provided
        if ($request->filled('subaccounts_id')) {
            $query->whereIn('subhead_accounting_id', $request->subaccounts_id);
        }

        // Fetch the matching ProjectHeadSubhead records
        $projectHeadSubheads = $query->pluck('id');
        if ($projectHeadSubheads->isEmpty()) {
            return redirect()->back()->with('error', 'No matching records found for the given criteria.');
        }

        $fdate = Carbon::createFromFormat('d-m-Y', $request->fdate)->format('Y-m-d');
        $tdate = Carbon::createFromFormat('d-m-Y', $request->tdate)->format('Y-m-d');

        // Calculate the opening balance
        $openingBalance = Ledger::whereIn('project_head_subheads_id', $projectHeadSubheads)
            ->whereDate('date', '<', $fdate)
            ->where('is_active', 1)
            ->selectRaw('COALESCE(SUM(amount_in),0) - COALESCE(SUM(amount_out),0) as opening_balance')
            ->value('opening_balance');
        // Fetch ledger details
        $ledgerDetails = Ledger::whereIn('project_head_subheads_id', $projectHeadSubheads)
            ->whereBetween('date', [$fdate, $tdate])
            ->where('is_active', 1)
            ->get();

        // Fetch party details
        $partyDetails = ProjectHeadSubhead::whereIn('id', $projectHeadSubheads)
            ->with(['project', 'headAccounting', 'subheadAccounting'])
            ->get()
            ->map(function ($item) {
                return [
                    'project_name' => $item->project->project ?? '',
                    'head_accounting' => $item->headAccounting->name ?? '',
                    'subhead_accounting' => $item->subheadAccounting->name ?? '',
                    'customer_id' => $item->customer_id ?? '',
                ];
            });

        // Get project name for the header
        $projectName = Project::find($projects_id)->project ?? 'N/A';
        // Return the Blade view with data
        return view('admin.finance.reports.party_ledger_report', [
            'reportTitle' => "Ledger Report",
            'fdate' => $request->fdate,
            'tdate' => $request->tdate,
            'projectName' => $projectName,
            'opening_balance' => $openingBalance ?? 0,
            'ledger_details' => $ledgerDetails,
            'party_details' => $partyDetails,
        ]);
    }
}
