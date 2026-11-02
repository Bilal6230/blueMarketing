<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveMobileUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || (int) ($user->status_id ?? 1) === 0) {
            return response()->json([
                'status' => false,
                'error_key' => 'account_inactive',
                'message' => 'Your account is inactive or unauthenticated.',
            ], 403);
        }

        return $next($request);
    }
}
