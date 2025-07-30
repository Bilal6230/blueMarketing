<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;


use Spatie\Permission\Models\Role;

class ZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $x['title']     = 'Sectors List';
        $x['data']      = Zone::get();
        $x['role']      = Role::get();
        return view('admin.zone.index', $x);
    }



    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_name'      => ['required', 'string', 'max:255' ,'unique:zones'],

        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }

        try {
            $data = Zone::create([
                'zone_name'          => $request->zone_name,
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


    public function show(Request $request)
    {
        $data_list = Zone::where(['id' => $request->id])->first();


        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data Project by id',
            'data'      => $data_list
        ], Response::HTTP_OK);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Zone  $zone
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, Zone $zone)
    {

        if ($request->zone_name != $request->old_zone) {
            $rules['zone_name'] = ['required', 'string', 'max:255', 'unique:zones'];
            $validator = Validator::make($request->all(), $rules);
        } else {
            $rules['zone_name'] = ['required', 'string', 'max:255'];
            $validator = Validator::make($request->all(), $rules);
        }

        $validator = Validator::make($request->all(), $rules);



        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        $data = [
            'zone_name'          => $request->zone_name,
            'is_active'     => $request->is_active,
            'create_by'     =>Auth::user()->id

        ];


        try {
            $project = Zone::find($request->id);
            $project->update($data);
            Alert::success('Notification', 'Data <b>' . $project->name . '</b> saved successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $project->name . '</b> failed to save : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }


    public function destroy(Zone $zone)
    {
        //
    }
}
