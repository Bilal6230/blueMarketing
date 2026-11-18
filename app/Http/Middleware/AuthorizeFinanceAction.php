<?php

namespace App\Http\Middleware;

use App\Support\Finance\FinanceActionPermission;
use Closure;
use Illuminate\Http\Request;

final class AuthorizeFinanceAction
{
    public function handle(Request $request, Closure $next)
    {
        $ability = FinanceActionPermission::forRequest($request);
        if ($ability !== null) {
            abort_unless($request->user() && $request->user()->can(
                FinanceActionPermission::name($ability[0],$ability[1])
            ), 403, 'You do not have permission for this finance action.');
        }
        return $next($request);
    }
}
