<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;


class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $x['title']     = 'Area List';
        $x['data']      = Area::get();
        $x['role']      = Role::get();
        return view('admin.area.index', $x);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:255' ,'unique:areas'],

        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }

        try {
            $data = Area::create([
                'name'          => $request->name,
                'is_active'     => $request->is_active,
                'create_by'     =>Auth::user()->id

            ]);
            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $data_list = Area::where(['id' => $request->id])->first();


        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data Area by id',
            'data'      => $data_list
        ], Response::HTTP_OK);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function edit(Area $area)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Area $area)
    {
        if ($request->name != $request->old_name) {
            $rules['name'] = ['required', 'string', 'max:255', 'unique:areas'];
            $validator = Validator::make($request->all(), $rules);
        } else {
            $rules['name'] = ['required', 'string', 'max:255'];
            $validator = Validator::make($request->all(), $rules);
        }

        $validator = Validator::make($request->all(), $rules);



        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        $data = [
            'name'          => $request->name,
            'is_active'     => $request->is_active,
            'create_by'     =>Auth::user()->id
        ];


        try {
            $project = Area::find($request->id);
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
     * @param  \App\Models\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function destroy(Area $area)
    {
        //
    }
}
