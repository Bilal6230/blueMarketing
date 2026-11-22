<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Original booking deposits retain create-plot; Extra Charges use their own
 * independently assignable finance action permission instead.
 */
final class AuthorizeBookingDeposit
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->input('action') === 'extra_charge') {
            abort_unless($request->user()?->can('finance extra-charge create'),403);
        } elseif ($request->input('action') === 'deposit') {
            abort_unless($request->user()?->can('create plot')
                || $request->user()?->can('finance plot-receipt create')
                || $request->user()?->can('add slip'),403);
        } else {
            abort_unless($request->user()?->can('create plot'),403);
        }
        return $next($request);
    }
}
