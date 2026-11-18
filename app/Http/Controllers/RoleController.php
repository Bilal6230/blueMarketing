<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Finance\RolePermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    private function rows(): array
    {
        $roles = Role::with('permissions:id,name,guard_name')->orderByDesc('updated_at')->get();
        $actors = [];
        if (Schema::hasColumn('roles', 'updated_by')) {
            $ids = $roles->pluck('updated_by')->filter()->unique()->all();
            $actors = User::whereIn('id', $ids)->pluck('name', 'id')->all();
        }
        return $roles->map(function ($role) use ($actors): array {
            return [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
                'guard' => (string) $role->guard_name,
                'updated' => optional($role->updated_at)->format('Y-m-d H:i:s'),
                'updated_by' => $actors[$role->updated_by ?? 0] ?? '—',
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'locked' => in_array(strtolower($role->name), ['superadmin','super-admin'], true),
            ];
        })->all();
    }

    public function index(Request $request)
    {
        $rows = $this->rows();
        if ($request->wantsJson()) {
            return response()->json(['status'=>'success','data'=>$rows]);
        }
        $permissions = Permission::orderBy('name')->get(['id','name','guard_name']);
        return view('admin.role', [
            'title'=>'Roles & Permissions',
            'roleRows'=>$rows,
            'permissionRows'=>$permissions->map(fn ($p)=>[
                'name'=>(string)$p->name,
                'guard'=>(string)$p->guard_name,
                'category'=>RolePermissionCatalog::category((string)$p->name),
            ])->values()->all(),
            'legacyCount'=>$permissions->filter(fn($p)=>RolePermissionCatalog::isLegacyVoucher((string)$p->name))->count(),
            'financeDefinitions'=>collect(\App\Support\Finance\FinanceActionPermission::MODULES)->map(fn($module)=>[
                'module'=>$module,
                'label'=>RolePermissionCatalog::moduleLabel($module),
                'actions'=>RolePermissionCatalog::moduleActions($module),
            ])->all(),
        ]);
    }

    private function validatePayload(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name'=>['required','string','max:255'],
            'guard_name'=>['required',Rule::in(['web','api'])],
            'permissions'=>['sometimes','array'],
            'permissions.*'=>['string','distinct','max:255'],
        ]);
        $name = trim($data['name']);
        $guard = $data['guard_name'];
        if ($role && $role->guard_name !== $guard) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'guard_name'=>'A role guard cannot be changed while editing. Create a new role for the other guard.',
            ]);
        }
        $duplicate = Role::where('name',$name)->where('guard_name',$guard)
            ->when($role,fn($q)=>$q->where('id','!=',$role->id))->exists();
        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name'=>'A role with this name and guard already exists.',
            ]);
        }
        $selected = array_values(array_unique($data['permissions'] ?? []));
        if (array_filter($selected, fn($name)=>RolePermissionCatalog::isLegacyVoucher($name))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'permissions'=>'Legacy voucher permissions cannot be added through the modern role editor.',
            ]);
        }
        $available = Permission::where('guard_name',$guard)->whereIn('name',$selected)
            ->pluck('name')->all();
        if (count($available) !== count($selected)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'permissions'=>'One or more permissions are not available for this guard.',
            ]);
        }
        // Hidden old grants are automatically preserved on edit, never newly added.
        // They cannot silently disappear while old non-finance routes still use them.
        if ($role) {
            $legacy = $role->permissions->pluck('name')->filter(
                fn($p)=>RolePermissionCatalog::isLegacyVoucher((string)$p)
            )->all();
            $available = array_values(array_unique(array_merge($available,$legacy)));
        }
        return ['name'=>$name,'guard_name'=>$guard,'permissions'=>$available];
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $role = DB::transaction(function () use ($data) {
            $role = Role::create(['name'=>$data['name'],'guard_name'=>$data['guard_name']]);
            $role->syncPermissions($data['permissions']);
            if (Schema::hasColumn('roles','updated_by')) {
                DB::table('roles')->where('id',$role->id)->update(['updated_by'=>auth()->id()]);
            }
            return $role;
        });
        return $request->wantsJson()
            ? response()->json(['status'=>'success','message'=>'Role created successfully.','id'=>$role->id],201)
            : redirect()->route('role.index')->with('success','Role created successfully.');
    }

    public function show(Request $request)
    {
        $data = $request->validate(['id'=>['required','integer','min:1']]);
        $role = Role::with('permissions')->findOrFail($data['id']);
        return response()->json(['status'=>Response::HTTP_OK,'message'=>'Role loaded','data'=>$role]);
    }

    public function update(Request $request)
    {
        $id = $request->validate(['id'=>['required','integer','min:1']])['id'];
        $role = Role::with('permissions')->findOrFail($id);
        abort_if(in_array(strtolower($role->name),['superadmin','super-admin'],true),403,'Protected system role.');
        $data = $this->validatePayload($request,$role);
        DB::transaction(function () use ($role,$data) {
            $role->update(['name'=>$data['name'],'guard_name'=>$data['guard_name']]);
            $role->syncPermissions($data['permissions']);
            if (Schema::hasColumn('roles','updated_by')) {
                DB::table('roles')->where('id',$role->id)->update(['updated_by'=>auth()->id()]);
            }
        });
        return $request->wantsJson()
            ? response()->json(['status'=>'success','message'=>'Role and permissions updated.'])
            : redirect()->route('role.index')->with('success','Role updated.');
    }

    public function destroy(Request $request)
    {
        $id = $request->validate(['id'=>['required','integer','min:1']])['id'];
        DB::transaction(function () use ($id) {
            $role = Role::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if(in_array(strtolower($role->name),['superadmin','super-admin'],true),403,'Protected system role.');
            $inUse = DB::table('model_has_roles')->where('role_id',$role->id)->exists();
            abort_if($inUse,409,'This role is assigned to users. Reassign them before archiving it.');
            // The Role model uses SoftDeletes. Never remove its grants or row physically.
            $role->delete();
        });
        return $request->wantsJson()
            ? response()->json(['status'=>'success','message'=>'Role archived successfully.'])
            : redirect()->route('role.index')->with('success','Role archived.');
    }
}
