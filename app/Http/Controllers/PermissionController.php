<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Nwidart\Modules\Facades\Module;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class PermissionController extends Controller
{
    public function index()
    {
        $x['title']     = 'Permission';
        $x['data']      = Permission::get();
        return view('admin.permission', $x);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => ['required'],
            'guard_name'    => ['required'],
        ]);
        if ($validator->fails()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Please correct the form fields.', 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator)->withInput();
        }
        try {
            $permission = Permission::create([
                'name'          => $request->name,
                'guard_name'    => $request->guard_name,
            ]);
            if (!$request->expectsJson()) Alert::success('Notification', 'Permission created.')->toToast();
        } catch (\Throwable $th) {
            if ($request->expectsJson()) return response()->json(['message' => 'Unable to create permission.'], 422);
            Alert::error('Notification', 'Unable to create permission.')->toToast();
        }
        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Permission created.'])
            : back();
    }

    public function show(Request $request)
    {
        $permission = Permission::find($request->id);
        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data permission by id',
            'data'      => $permission
        ], Response::HTTP_OK);
    }
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => ['required'],
            'guard_name'    => ['required'],
        ]);
        if ($validator->fails()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Please correct the form fields.', 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator)->withInput();
        }
        try {
            $permission = Permission::find($request->id);
            $permission->update([
                'name'          => $request->name,
                'guard_name'    => $request->guard_name,
            ]);
            if (!$request->expectsJson()) Alert::success('Notification', 'Permission updated.')->toToast();
        } catch (\Throwable $th) {
            if ($request->expectsJson()) return response()->json(['message' => 'Unable to update permission.'], 422);
            Alert::error('Notification', 'Unable to update permission.')->toToast();
        }
        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Permission updated.'])
            : back();
    }

    public function destroy(Request $request)
    {
        try {
            $permission = Permission::find($request->id);
            $permission->delete();
            if (!$request->expectsJson()) Alert::success('Notification', 'Permission deleted.')->toToast();
        } catch (\Throwable $th) {
            if ($request->expectsJson()) return response()->json(['message' => 'Unable to delete permission.'], 422);
            Alert::error('Notification', 'Unable to delete permission.')->toToast();
        }
        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Permission deleted.'])
            : back();
    }
    public function reloadPermission()
    {
        try {
            $this->initModules();
            if (!$request->expectsJson()) Alert::success('Notification', 'Permissions refreshed.')->toToast();
        } catch (\Throwable $th) {
            if ($request->expectsJson()) return response()->json(['message' => 'Permission reload failed.'], 422);
            Alert::error('Notification', 'Permission reload failed.')->toToast();
        }
        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Permissions refreshed.'])
            : back();
    }

    private function initModules()
    {
        $modules = Module::getOrdered();

        foreach ($modules as $module) {
            $moduleJson = json_decode(file_get_contents($module->getPath() . '/module.json', true));
            $permissions = $moduleJson->permissions;
            for ($i = 0; $i < count($permissions); $i++) {
                $permissionMappings = ['delete', 'update', 'read', 'create'];
                foreach ($permissionMappings as $permissionMapping) {
                    $name = $permissionMapping . ' ' . $permissions[$i];
                    $permission = Permission::where(['name' => $name])->count();
                    if ($permission == 0) {
                        Permission::create(['name' => $name]);
                    }
                }
            }
        }
    }
}
