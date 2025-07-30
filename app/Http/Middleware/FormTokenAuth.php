<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class FormTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $app_user = $request->input('app_user');
        $token = $request->input('token');

        if (!$app_user || !$token) {
            return response()->json([
                'status' => false,
                'message' => 'App User ID and token are required.',
            ], 400);
        }

        $user = User::find($app_user);

        if (!$user || !$user->tokens()
            ->where('token', hash('sha256', $token))
            ->whereNull('deleted_at')
            ->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        // Attach user to request for future use
        $request->merge(['authenticated_user' => $user]);

        return $next($request);
    }
}
