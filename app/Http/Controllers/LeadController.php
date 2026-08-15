<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\Work;
use Illuminate\Http\Request;
use App\Models\PendingUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Repository\Lead\LeadRepository as lead_repo;

class LeadController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Lead SetUp';
        $x['data'] = lead_repo::getLeadsList(Auth::user()->id, $request->filter);
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        return view('admin.crm.lead', $x);
    }
    public function requestEditBtn(Request $request)
    {
        $request->validate([
            'record_id' => 'required|integer',
        ]);

        $tableName = 'leads';
        $lead = Lead::query()
            ->whereKey($request->record_id)
            ->where('is_active', 1)
            ->firstOrFail();

        $existing = PendingUpdate::where('table_name', $tableName)
            ->where('record_id', $request->record_id)
            ->where('status','=', 'pending')
            ->latest()
            ->first();
        if ($existing) {
            return response()->json([
                'status' => 'exists',
                'message' => 'An edit request is already pending for this record.',
            ]);
        }

        $pending = new PendingUpdate();
        $pending->table_name = $tableName;
        $pending->record_id = $lead->id;
        $pending->submitted_by = auth()->id(); // optional: track who requested
        $pending->status = 'pending';
        $pending->old_values = json_encode([]); // ✅ Fix the SQL error
        $pending->new_values = json_encode([]); // ✅ Fix the SQL error
        $pending->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Edit request submitted successfully!',
            'data' => $pending,
        ]);
    }

    public function approvalIndex()
    {
        $user = Auth::user();
        $this->authorizeLeadApprovalAccess($user);

        $pendingUpdates = PendingUpdate::with('submittedBy')
            ->where('table_name', 'leads')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        $leadIds = $pendingUpdates->pluck('record_id')->unique()->values();
        $leadsById = Lead::query()
            ->with([
                'users:id,name',
                'comments' => function ($query) {
                    $query->select('id', 'lead_id', 'comment', 'created_at', 'user_id')
                        ->with('user:id,name')
                        ->orderByDesc('id');
                },
            ])
            ->select('id', 'first_name', 'last_name', 'is_active')
            ->whereIn('id', $leadIds)
            ->get()
            ->keyBy('id');

        $pendingUpdates->transform(function ($pending) use ($leadsById) {
            $lead = $leadsById->get($pending->record_id);
            $assignedUsers = $lead?->users
                ? $lead->users->pluck('name')->filter()->values()
                : collect();

            $pending->lead_name = $lead ? $this->formatLeadName($lead) : 'Deleted Lead';
            $pending->current_assigned_users = $assignedUsers->isNotEmpty() ? $assignedUsers->all() : ['Unassigned'];
            $pending->current_assigned_users_label = $assignedUsers->isNotEmpty()
                ? $assignedUsers->implode(', ')
                : 'Unassigned';
            $pending->requested_user_name = $pending->submittedBy->name ?? 'Unknown User';
            $pending->request_type = 'Lead Assignment / Ownership Request';
            $pending->comments_for_modal = $lead
                ? $lead->comments->map(function ($comment) {
                    return [
                        'comment' => $comment->comment,
                        'created_at' => optional($comment->created_at)->format('d M Y H:i'),
                        'user_name' => $comment->user->name ?? 'Unknown User',
                    ];
                })->values()->all()
                : [];

            return $pending;
        });

        return view('admin.pending-approve.lead_approvals_index', [
            'title' => 'Lead Assignment Requests',
            'pendingUpdates' => $pendingUpdates,
        ]);
    }

    public function approvePendingAssignment($pendingId, Request $request)
    {
        $user = Auth::user();
        $this->authorizeLeadApprovalAccess($user);

        $pending = $this->resolveLeadPendingUpdate((int) $pendingId);

        DB::transaction(function () use ($pending, $user) {
            $lockedPending = PendingUpdate::query()
                ->whereKey($pending->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPending->status === 'approved') {
                return;
            }

            if ($lockedPending->status !== 'pending') {
                abort(409, 'This request has already been processed.');
            }

            $lead = Lead::query()
                ->whereKey($lockedPending->record_id)
                ->where('is_active', 1)
                ->lockForUpdate()
                ->first();

            if (!$lead) {
                abort(404);
            }

            $requestedUserId = (int) $lockedPending->submitted_by;
            $requestedUser = User::query()->whereKey($requestedUserId)->first();

            if (!$requestedUser) {
                abort(404);
            }

            $leadAssignments = DB::table('lead_user')
                ->where('lead_id', $lead->id)
                ->lockForUpdate()
                ->get();

            $alreadyAssigned = $leadAssignments->contains(function ($assignment) use ($requestedUserId) {
                return (int) $assignment->user_id === $requestedUserId;
            });

            if (!$alreadyAssigned) {
                DB::table('lead_user')->insert([
                    'lead_id' => $lead->id,
                    'user_id' => $requestedUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $lockedPending->update([
                'status' => 'approved',
                'approved_by' => $user->id,
            ]);
        });

        return back()->with('success', 'Lead assignment request approved successfully.');
    }

    public function rejectPendingAssignment($pendingId, Request $request)
    {
        $user = Auth::user();
        $this->authorizeLeadApprovalAccess($user);

        $pending = $this->resolveLeadPendingUpdate((int) $pendingId);

        DB::transaction(function () use ($pending, $user) {
            $lockedPending = PendingUpdate::query()
                ->whereKey($pending->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPending->status === 'rejected') {
                return;
            }

            if ($lockedPending->status !== 'pending') {
                abort(409, 'This request has already been processed.');
            }

            $lockedPending->update([
                'status' => 'rejected',
                'approved_by' => $user->id,
            ]);
        });

        return back()->with('success', 'Lead assignment request rejected.');
    }


    public function store(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:25'],
            'last_name' => ['required', 'string', 'max:25'],
            'gender' => ['required'],
            //'nic_number'     => [ 'numeric'],
            'phone_number' => ['required', 'numeric', 'unique:leads'],
            'mobile_number' => ['nullable', 'digits:11', 'unique:leads,mobile_number'],
            'area_id' => ['required', 'numeric'],
            'type' => ['required', 'numeric'],
            'office_address' => ['required'],
            'assign_id' => ['required'],
            'follow_id' => ['required'],
        ], [
            'phone_number.unique' => 'This phone number is already registered with another lead.',
            'mobile_number.unique' => 'This second phone number is already registered with another lead.',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }


        try {
            $data = Lead::create([

                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'relate' => $request->relate,
                'father_name' => $request->father_name,
                'gender' => $request->gender,
                'nic_number' => $request->nic_number,
                'phone_number' => $request->phone_number,
                'mobile_number' => $request->mobile_number,
                'area_id' => $request->area_id,
                'type' => $request->type,
                'business' => $request->business,
                'zone_id' => $request->zone_id,
                'home_address' => $request->home_address,
                'office_address' => $request->office_address,
                'designation' => $request->designation,
                'is_active' => $request->is_active,
                'follow_id' => $request->follow_id,
                'is_active' => $request->is_active,

                'project_id' => getSelectedTown(),

                'create_by' => Auth::user()->id

            ]);

            $roleIds = $request->assign_id;
            $data->users()->sync($roleIds);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }



    public function show(Request $request)
    {
        $data_list = Lead::where('id', $request->id)->with('users:id')->firstOrFail();


        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Lead by id',
            'data' => $data_list,
            'assigned_user_ids' => $data_list->users
                ->pluck('id')
                ->map(fn($id) => (string) $id)
                ->values(),
        ], Response::HTTP_OK);
    }




    public function update(Request $request)
    {
        $rules = [
            'id' => ['required', 'integer', 'exists:leads,id'],
            'first_name' => ['required', 'string', 'max:25'],
            'last_name' => ['required', 'string', 'max:25'],
            'gender' => ['required'],
            //'nic_number'     => [ 'numeric'],
            'phone_number' => [
                'required',
                'numeric',
                Rule::unique('leads', 'phone_number')->ignore($request->id),
            ],
            'mobile_number' => [
                'nullable',
                'digits:11',
                Rule::unique('leads', 'mobile_number')->ignore($request->id),
            ],
            'area_id' => ['required', 'numeric'],
            'type' => ['required', 'numeric'],
            'office_address' => ['required'],
            'assign_id' => ['required'],
            'follow_id' => ['required'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'phone_number.unique' => 'This phone number is already registered with another lead.',
            'mobile_number.unique' => 'This second phone number is already registered with another lead.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }

        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'relate' => $request->relate,
            'father_name' => $request->father_name,
            'gender' => $request->gender,
            'nic_number' => $request->nic_number,
            'phone_number' => $request->phone_number,
            'mobile_number' => $request->mobile_number,
            'area_id' => $request->area_id,
            'type' => $request->type,
            'business' => $request->business,
            'zone_id' => $request->zone_id,
            'home_address' => $request->home_address,
            'office_address' => $request->office_address,
            'designation' => $request->designation,
            'is_active' => $request->is_active,
            'follow_id' => $request->follow_id,
            'is_active' => $request->is_active,
        ];

        //dd($data);

        DB::beginTransaction();
        try {
            $result = Lead::find($request->id);

            // Track changes for each field
            $changes = [];
            foreach ($data as $key => $newValue) {
                if ($result->$key != $newValue) {
                    $changes[$key] = [
                        'old' => $result->$key,
                        'new' => $newValue,
                    ];
                }
            }
            $result->update($data);

            // Get the authenticated user who made the changes
            $authUser = auth()->user();

            // Log changes (you can save it in a separate log table or in the same record)
            if (!empty($changes)) {
                $comment = "User {$authUser->name} updated fields: ";
                foreach ($changes as $field => $change) {
                    $comment .= "{$field} (New: {$change['new']})";
                }

                // Optional: Save the log to a database table, assuming you have a Work or Activity log table
                $newLog = new Work();
                $newLog->lead_id = $result->id;
                $newLog->comment = rtrim($comment, ', '); // Trim trailing comma
                $newLog->user_id = $authUser->id; // Store the user ID of the one who made the change
                $newLog->save();
            }

            // Sync related users (for roles or assignments)
            $roleIds = $request->assign_id;
            $result->users()->sync($roleIds);
            //$result->syncRoles($request->role);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> berhasil disimpan')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> gagal disimpan : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }


    public function destroy(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:leads,id'],
        ]);

        try {
            $lead = Lead::find($request->id);

            if (!$lead) {
                Alert::error('Notification', 'Lead not found.')->toToast()->toHtml();
                return back();
            }

            $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));

            $lead->is_active = 0;
            $lead->save();

            Alert::success('Notification', 'Lead deleted successfully.')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Failed to delete lead' . ($name ? ' <b>' . $name . '</b>' : '') . ': ' . $th->getMessage())
                ->toToast()
                ->toHtml();
        }
        return back();
    }

    public function assign(Request $request)
    {
        $data_list = Lead::where(['id' => $request->id])->first();


        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }

    public function details(Request $request)
    {
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
        //dd($user);


        $data = lead_repo::getActiveList($user_id, $status, $leadID, $power);
        if (count($data) == 0) {
            echo "No More Leads";
            exit;
        }



        $x['title'] = 'Start Work';
        $x['data'] = $data[0];
        $x['user'] = $user;
        $x['role'] = Role::get();
        $x['register_date'] = $data[0]->created_at;
        return view('admin.crm.details', $x);
    }

    public function logUpdate(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'call_status' => ['required'],
            'call_duration' => ['required', 'numeric'],
            'comment' => ['required'],
            'lead_id' => ['required', 'numeric'],
            'type' => ['required', 'numeric'],


        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }

        try {
            $data = Work::create([

                'lead_id' => $request->lead_id,
                'comment' => $request->comment,
                'call_duration' => $request->call_duration,
                'call_status' => $request->call_status,
                'type' => $request->type,
                'user_id' => Auth::user()->id,
                'follow_up' => $request->follow_up,

            ]);
            Lead::where('id', $request->lead_id)->update(['follow_status' => $request->call_status, 'follow_up' => $request->follow_up]);


            // $roleIds = $request->assign_id;
            // $data->users()->sync($roleIds);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function search(Request $request)
    {
        if (isset($request->number)) {
            $data = lead_repo::getLeadByNumber($request->number);
            if (!empty($data)) {
                $projectDetails = getProjectDetails($data->project_id);

                $result['active'] = "Already Register";
                $result['name'] = $data->first_name . ' ' . $data->last_name;
                $result['id'] = $data->id;
                $result['created_at'] = $data->created_at;
                $result['updated_at'] = $data->updated_at;
                $result['assignTo'] = $data->users;
                $result['project'] = $projectDetails['project'];
                $result['status'] = 'success';
                $result['follow_status'] = $data->follow_status;

                return response()->json([
                    'status' => Response::HTTP_OK,
                    'message' => 'Data Project by id',
                    'data' => $result
                ], Response::HTTP_OK);

            } else {

                return response()->json(['error' => 'No Record Found'], 404);
            }



        }

        # code...
    }

    protected function resolveLeadPendingUpdate(int $pendingId): PendingUpdate
    {
        $pending = PendingUpdate::with('submittedBy')->findOrFail($pendingId);

        if ($pending->table_name !== 'leads') {
            abort(404);
        }

        return $pending;
    }

    protected function authorizeLeadApprovalAccess(User $user): void
    {
        if (Permission::where('name', 'assign lead')->exists()) {
            abort_unless($user->can('assign lead'), 403);

            return;
        }

        $isPrivilegedRole = $user->hasAnyRole(['admin', 'superadmin', 'super-admin']);
        abort_unless($user->can('update lead') && $isPrivilegedRole, 403);
    }

    protected function formatLeadName(Lead $lead): string
    {
        $leadName = trim(collect([$lead->first_name, $lead->last_name])->filter()->implode(' '));

        return $leadName !== '' ? $leadName : 'Unnamed Lead';
    }

}
