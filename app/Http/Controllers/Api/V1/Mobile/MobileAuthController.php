<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\ResolvesMobileProjectAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends BaseMobileController
{
    use ResolvesMobileProjectAccess;

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('invalid_credentials', 'Invalid email or password.', 401);
        }

        if ((int) ($user->status_id ?? 1) === 0) {
            return $this->errorResponse('forbidden', 'Your account is inactive.', 403);
        }

        $projects = $this->allowedProjects($user);
        $selectedProjectId = $this->resolveSelectedProjectId($request, $user);
        $token = $user->createToken('blue_marketing_android')->plainTextToken;

        return $this->successResponse('Login successful.', [
            'token' => $token,
            'user' => $this->transformUser($user),
            'role_names' => $this->roleNames($user),
            'permissions' => $this->permissionNames($user),
            'projects' => $this->transformProjects($projects),
            'selected_project_id' => $selectedProjectId,
        ]);
    }

    public function me(Request $request)
    {
        $request->validate([
            'project_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $projects = $this->allowedProjects($user);
        $selectedProjectId = $this->resolveSelectedProjectId($request, $user);

        return $this->successResponse('Current user loaded.', [
            'user' => $this->transformUser($user),
            'role_names' => $this->roleNames($user),
            'permissions' => $this->permissionNames($user),
            'projects' => $this->transformProjects($projects),
            'selected_project_id' => $selectedProjectId,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->successResponse('Logged out successfully.');
    }
}
