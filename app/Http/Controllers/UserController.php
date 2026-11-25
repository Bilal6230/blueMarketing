<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Role;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index()
    {
        $x['title']     = 'User';
        $x['data']      = User::query()->with('roles')->get();
        $x['role']      = Role::query()->get();

        $projects = Project::get();
        $x['projects'] = $projects;

        return view('admin.user', $x);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'      => ['required', 'string'],
            'nic_number'    => ['required', 'numeric'],
            'phone_number'  => ['required', 'numeric'],
            'avatar'        => ['required', 'image', 'mimes:jpg,png,jpeg,gif,svg', 'max:2048'],
            'project_id'    => ['required'],
            'status_id'     => ['required'],
            'gender'        => ['required', 'in:male,female,other'],
            'role'          => ['required', 'exists:roles,name'],
        ]);

        if ($validator->fails()) {
            return $request->expectsJson() ? response()->json(['message' => 'Please correct the form fields.', 'errors' => $validator->errors()], 422) : back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // Save avatar file
            $image_path = $request->file('avatar')->store('avatar', 'public');

            // Create the user
            $user = User::create([
                'name'          => $request->name,
                'email'         => $request->email,
                'gender'        => $request->gender,
                'nic_number'    => $request->nic_number,
                'phone_number'  => $request->phone_number,
                'department'    => $request->department ?? 'N/A',
                'address'       => $request->address ?? 'N/A',
                'avatar'        => $image_path,
                'project_id'    => $request->project_id,
                'status_id'     => $request->status_id,
                'password'      => bcrypt($request->password),
            ]);

            // Assign role
            $user->assignRole($request->role);

            DB::commit();

            // Redirect with success message
            return $request->expectsJson() ? response()->json(['success' => true, 'message' => 'User created successfully.']) : redirect()->route('users.index')->with('success', 'User created successfully!');
        } catch (\Throwable $th) {
            DB::rollback();
            //Log::error('User creation failed: ' . $th->getMessage(), ['trace' => $th->getTraceAsString()]);
            return $request->expectsJson() ? response()->json(['message' => 'Unable to create user.'], 422) : back()->with('error', 'Unable to create user.');
        }
    }


    public function show(Request $request)
    {
        $user = UserResource::collection(User::where(['id' => $request->id])->get());
        //return $user[0];
        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data user by id',
            'data'      => $user[0]
        ], Response::HTTP_OK);
    }
    public function toggleStatus(Request $request)
    {
        $user = User::findOrFail($request->id);
        $user->status_id = $request->status_id;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->status_id ? 'User Activated Successfully' : 'User Deactivated Successfully'
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'name'      => ['required', 'string', 'max:255'],
            'password'  => ['nullable', 'string'],
            'role'      => ['required'],
            'project_id' => ['required'],
            'status_id'  => ['required'],
            'avatar'    => ['nullable', 'image', 'mimes:jpg,png,jpeg,gif,svg', 'max:2048'],
        ];

        if ($request->email != $request->old_email) {
            $rules['email'] = ['required', 'string', 'email', 'max:255', 'unique:users'];
        } else {
            $rules['email'] = ['required', 'string', 'email', 'max:255'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $request->expectsJson() ? response()->json(['message' => 'Please correct the form fields.', 'errors' => $validator->errors()], 422) : back()->withErrors($validator)->withInput();
        }

        $data = [
            'name'          => $request->name,
            'email'         => $request->email,
            'gender'        => $request->gender,
            'nic_number'    => $request->nic_number,
            'phone_number'  => $request->phone_number,
            'department'    => $request->department,
            'address'       => $request->address,
            'description'   => $request->description,
            'project_id'    => $request->project_id,
            'status_id'     => $request->status_id,
        ];

        if (!empty($request->password)) {
            $data['password'] = bcrypt($request->password);
        }

        DB::beginTransaction();
        try {
            $user = User::findOrFail($request->id);

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                //dd("asdasd");
                // Delete the old avatar if it exists
                // if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                //     Storage::disk('public')->delete($user->avatar);
                // }

                // Store the new avatar
                $data['avatar'] = $request->file('avatar')->store('avatar', 'public');
            }

            $user->update($data);
            $user->syncRoles($request->role);

            DB::commit();
            if (!$request->expectsJson()) Alert::success('Notification', 'User updated.')->toToast();
        } catch (\Throwable $th) {
            DB::rollback();
            if ($request->expectsJson()) return response()->json(['message' => 'Unable to update user.'], 422);
            Alert::error('Notification', 'Unable to update user.')->toToast();
        }

        return $request->expectsJson() ? response()->json(['success' => true, 'message' => 'User updated.']) : back();
    }


    public function destroy(Request $request)
    {
        try {
            $user = User::find($request->id);
            $user->delete();
            if (!$request->expectsJson()) Alert::success('Notification', 'User deleted.')->toToast();
        } catch (\Throwable $th) {
            if ($request->expectsJson()) return response()->json(['message' => 'Unable to delete user.'], 422);
            Alert::error('Notification', 'Unable to delete user.')->toToast();
        }
        return $request->expectsJson() ? response()->json(['success' => true, 'message' => 'User deleted.']) : back();
    }
}
