<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Storage;
use App\Models\ProjectHeadSubhead;



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


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project'      => ['required', 'string', 'max:255' ,'unique:projects'],
            'address'     => ['required', 'string',  'max:255'],
            'logo'        => [ 'image', 'max:8192'], // Assuming max file size is 2MB
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $imageName = time().'.'.$request->logo->extension();  
            $request->logo->move(public_path('images/logo'), $imageName);

            $data = Project::create([
                'project'     => $request->project,
                'address'     => $request->address,
                'is_active'   => $request->is_active,
                'create_by'   => Auth::user()->id,
                'logo'        => $imageName, // Save the image name in the database
            ]);

            $pivot = ProjectHeadSubhead::create([
                'project_id' => $data->id,
                'head_accounting_id' => 16,
                'subhead_accounting_id' => 123,
            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> saved successfully.')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage() . '</b>')->toToast()->toHtml();
        }

        return back();
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
        $rules = [
            'address' => ['required', 'string', 'max:255'],
        ];

        // Add logo validation rules if a new logo is being uploaded
        if ($request->hasFile('logo')) {
            $rules['logo'] = ['required', 'image', 'max:8192']; // Assuming max file size is 2MB
        }

        if ($request->project != $request->old_project) {
            $rules['project'] = ['required', 'string', 'max:255', 'unique:projects'];
        } else {
            $rules['project'] = ['required', 'string', 'max:255'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = [
            'project'   => $request->project,
            'address'   => $request->address,
            'is_active' => $request->is_active,
            'create_by' => Auth::user()->id,
        ];

        // Handle logo update
        if ($request->hasFile('logo')) {
            $imageName = time() . '.' . $request->logo->extension();
            $request->logo->move(public_path('images/logo'), $imageName);
            $data['logo'] = $imageName;
        }

        try {
            $project->update($data);
            Alert::success('Notification', 'Data <b>' . $project->name . '</b> saved successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $project->name . '</b> failed to save : ' . $th->getMessage())->toToast()->toHtml();
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
        $x['data']      = Project::find(1)->zones();
        $x['role']      = Role::get();

        dd($x['data']);
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
