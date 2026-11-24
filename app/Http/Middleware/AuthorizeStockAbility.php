<?php

namespace App\Http\Middleware;

use App\Support\Stock\StockAbility;
use Closure;
use Illuminate\Http\Request;

final class AuthorizeStockAbility
{
    public function handle(Request $request, Closure $next, string $module, string $action)
    {
        StockAbility::authorize($module,$action);
        return $next($request);
    }
}
