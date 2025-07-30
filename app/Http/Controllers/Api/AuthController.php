<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'projects_id' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$user->hasRole('superadmin') && $user->project_id != $request->projects_id) {
            return response()->json([
                'message' => 'Unauthorized project access',
            ], 403);
        }

        // ❌ Delete all previous tokens for this user
        $user->tokens()->update(['deleted_at' => now()]);

        $projects = Project::where('is_active', 1 )->get();


        // ✅ Create new token
        $token = $user->createToken('flutter_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user_id' => $user->id,
            // 'project_id' => $user->project_id,
            'status' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'gender' => $user->gender,
                'nic_number' => $user->nic_number,
                'phone_number' => $user->phone_number,
                'department' => $user->department,
                'status_id' => $user->status_id,
                'project_id' => $user->project_id,
                'address' => $user->address,
                'avatar' => $user->avatar,
                'roles' => $user->getRoleNames(),
            ],
            // 'projectList' => $projects,
        ]);
    }



    public function logout(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'token' => 'required',
        ]);

        $user = User::find($request->user_id);

        // Check if token exists
        $token = $user->tokens()->where('token', hash('sha256', $request->token))->first();

        if (!$token) {
            return response()->json([
                'message' => 'Invalid token',
            ], 401);
        }

        // Delete token (logout)
        $token->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

}
