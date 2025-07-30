<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the user is logged in and has a status_id of 0
        if (Auth::check() && Auth::user()->status_id == 0) {
            Auth::logout(); // Log the user out
            return redirect()->route('login')->withErrors(['status_id' => 'Your account has been deactivated.']);
        }

        return $next($request);
    }
}
