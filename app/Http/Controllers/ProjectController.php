<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use App\Models\HeadAccounting;
use App\Models\SubheadAccounting;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;



class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $x['title']     = 'Project List';
        $x['data']      = Project::get();
        $x['role']      = Role::get();
        //dd($x['data']);
        return view('admin.projects.index', $x);
    }

    private function requireProjectSetupSchema(): void
    {
        foreach (['project_code', 'location', 'opening_date', 'closing_date', 'corner_charge_percent', 'facing_charge_percent'] as $column) {
            abort_unless(Schema::hasColumn('projects', $column), 409, 'Run the project setup migration before editing project definitions.');
        }
        abort_unless(Schema::hasTable('project_charge_revisions'), 409, 'Run the project setup migration before editing project definitions.');
    }


    public function store(Request $request)
    {
        $this->requireProjectSetupSchema();
        $validator = Validator::make($request->all(), [
            'project' => ['required', 'string', 'max:255', 'unique:projects,project'],
            'address' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'opening_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:opening_date'],
            'corner_charge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'facing_charge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'logo' => ['nullable', 'image', 'max:8192'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $imageName = null;
            if ($request->hasFile('logo')) {
                $imageName = time() . '.' . $request->logo->extension();
                $request->logo->move(public_path('images/logo'), $imageName);
            }
            $validated = $validator->validated();
            $data = DB::transaction(function () use ($validated, $imageName) {
                $attributes = [
                    'project' => $validated['project'], 'address' => $validated['address'],
                    'is_active' => $validated['is_active'], 'create_by' => Auth::id(),
                    'location' => $validated['location'] ?? null,
                    'opening_date' => $validated['opening_date'] ?? null,
                    'closing_date' => $validated['closing_date'] ?? null,
                    'corner_charge_percent' => $validated['corner_charge_percent'] ?? 0,
                    'facing_charge_percent' => $validated['facing_charge_percent'] ?? 0,
                ];
                if ($imageName !== null && Schema::hasColumn('projects', 'logo')) $attributes['logo'] = $imageName;
                $project = Project::create($attributes);
                if (Schema::hasColumn('projects', 'project_code')) {
                    $project->project_code = sprintf('PRJ-%06d', $project->id);
                    $project->save();
                }
                $defaultHead = HeadAccounting::query()->whereKey(16)->where('is_active', 1)->firstOrFail();
                $defaultSubhead = SubheadAccounting::query()->whereKey(123)->where('is_active', 1)->firstOrFail();
                $defaultMapping = ProjectHeadSubhead::firstOrCreate([
                    'project_id' => $project->id,
                    'head_accounting_id' => $defaultHead->id,
                    'subhead_accounting_id' => $defaultSubhead->id,
                    'plot_id' => null,
                    'customer_id' => null,
                ]);
                if (!ProjectHeadSubhead::availableForPosting()->whereKey($defaultMapping->id)->exists()) {
                    throw new \DomainException('PROJECT_SYSTEM_ACCOUNT_INACTIVE');
                }
                foreach ([
                    ['Labour', 'Labour Party'],
                    ['Project Sale', 'Deduction'],
                    ['Project Sale', 'Resale Profit'],
                    ['Project Sale', 'Party Profit'],
                    ['Old Project Sale', null],
                ] as [$head, $subhead]) {
                    if ($subhead !== null) $this->systemHeadSubheadAccountId($project->id, $head, $subhead, true);
                }
                if (Schema::hasTable('project_charge_revisions')) {
                    DB::table('project_charge_revisions')->insert([
                        'project_id' => $project->id,
                        'corner_charge_percent' => $validated['corner_charge_percent'] ?? 0,
                        'facing_charge_percent' => $validated['facing_charge_percent'] ?? 0,
                        'effective_from' => $validated['opening_date'] ?? now()->toDateString(),
                        'changed_by' => Auth::id(), 'created_at' => now(),
                    ]);
                }
                return $project;
            });

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> saved successfully.')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Data <b>' .  $th->getMessage() . '</b>')->toToast()->toHtml();
        }

        return back();
    }
    private function systemHeadSubheadAccountId(int $projectId, string $headName, string $subheadName, bool $createIfMissing = false): int
    {
        $head = HeadAccounting::where('name', $headName)->first();
        $subhead = SubheadAccounting::where('name', $subheadName)->first();

        if (!$head && $createIfMissing) {
            $head = HeadAccounting::create([
                'name' => $headName,
                'is_active' => 1,
                'acct_type' => 0,
                'create_by' => Auth::id(),
            ]);
        }
        if (!$subhead && $createIfMissing) {
            $subhead = SubheadAccounting::create([
                'name' => $subheadName,
                'is_active' => 1,
                'create_by' => Auth::id(),
            ]);
        }

        if (!$head || !$subhead) {
            throw new \Exception("System subhead not found: {$subheadName}");
        }
        if ((int) $head->is_active !== 1 || (int) $subhead->is_active !== 1) {
            throw new \DomainException('PROJECT_SYSTEM_ACCOUNT_INACTIVE');
        }

        $mapping = ProjectHeadSubhead::firstOrCreate(
            [
                'project_id' => $projectId,
                'head_accounting_id' => $head->id,
                'subhead_accounting_id' => $subhead->id,
                'plot_id' => null,
                'customer_id' => null,
            ]
        );

        if (!ProjectHeadSubhead::availableForPosting()->whereKey($mapping->id)->exists()) {
            throw new \DomainException('PROJECT_SYSTEM_ACCOUNT_INACTIVE');
        }

        return (int) $mapping->id;
    }

    public function show(Request $request)
    {
        $data_list = Project::where(['id' => $request->id])->first(); // UserResource::collection(User::where(['id' => $request->id])->get());

        // foreach($data_list as $object)
        // {
        //     $arrays = $object->toArray();
        // }

        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data Project by id',
            'data'      => $data_list
        ], Response::HTTP_OK);
    }



    public function update(Request $request, Project $project)
    {
        $this->requireProjectSetupSchema();
        $rules = [
            'address' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'opening_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:opening_date'],
            'corner_charge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'facing_charge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'charge_effective_from' => ['nullable', 'date'],
            'id' => ['required', 'integer', Rule::in([(int) $project->id])],
        ];

        // Add logo validation rules if a new logo is being uploaded
        if ($request->hasFile('logo')) {
            $rules['logo'] = ['required', 'image', 'max:8192']; // Assuming max file size is 2MB
        }

        $rules['project'] = ['required', 'string', 'max:255', Rule::unique('projects', 'project')->ignore($project->id)];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $chargeChanged = (float) $project->corner_charge_percent !== (float) $validated['corner_charge_percent']
            || (float) $project->facing_charge_percent !== (float) $validated['facing_charge_percent'];
        if ($chargeChanged && empty($validated['charge_effective_from'])) {
            return back()->withErrors(['charge_effective_from' => 'Select when the new charges take effect.'])->withInput();
        }

        $data = [
            'project'   => $request->project,
            'address'   => $request->address,
            'location' => $validated['location'] ?? null,
            'opening_date' => $validated['opening_date'] ?? null,
            'closing_date' => $validated['closing_date'] ?? null,
            'corner_charge_percent' => $validated['corner_charge_percent'],
            'facing_charge_percent' => $validated['facing_charge_percent'],
            'is_active' => $request->is_active,
        ];

        // Handle logo update
        if ($request->hasFile('logo')) {
            $imageName = time() . '.' . $request->logo->extension();
            $request->logo->move(public_path('images/logo'), $imageName);
            $data['logo'] = $imageName;
        }

        try {
            DB::transaction(function () use ($project, $data, $chargeChanged, $validated) {
                $project->update($data);
                if (Schema::hasColumn('projects', 'project_code') && empty($project->project_code)) {
                    $project->project_code = sprintf('PRJ-%06d', $project->id);
                    $project->save();
                }
                if ($chargeChanged && Schema::hasTable('project_charge_revisions')) {
                    $effectiveDate = $validated['charge_effective_from'];
                    $exists = DB::table('project_charge_revisions')->where('project_id', $project->id)
                        ->where('effective_from', $effectiveDate)->exists();
                    if ($exists) throw new \DomainException('A charge revision already exists for that effective date.');
                    DB::table('project_charge_revisions')->insert([
                        'project_id' => $project->id,
                        'corner_charge_percent' => $data['corner_charge_percent'],
                        'facing_charge_percent' => $data['facing_charge_percent'],
                        'effective_from' => $effectiveDate,
                        'changed_by' => Auth::id(), 'created_at' => now(),
                    ]);
                }
            });
            Alert::success('Notification', 'Data <b>' . $project->name . '</b> saved successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Data <b>' . $project->project . '</b> failed to save : ' . $th->getMessage())->toToast()->toHtml();
        }

        return back();
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\Response
     */
    public function destroy(Project $project)
    {
        //
    }

    public function add_zone()
    {
        $x['title']     = 'Add Project Zone';
        $x['data']      = Project::findOrFail((int) getSelectedTown())->zones()->get();
        $x['role']      = Role::get();

        return view('admin.projects.add_zone', $x);
    }

    public function selectTown(Request $request)
    {
        $action = $request->input('action');
        // Cache::put('selected_action', $action); // Store the selected action in cache
        // return response()->json(['success' => true]);

        // Create a cookie with the 'selected_action' key and set it for 30 days (43,200 minutes)
        $cookie = cookie('selected_action', $action, 43200); // Cookie valid for 30 days

        return response()->json(['success' => true])->withCookie($cookie);
    }




}
