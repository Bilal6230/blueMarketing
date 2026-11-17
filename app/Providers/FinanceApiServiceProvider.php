<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Standard finance permissions for the unified application. */
final class FinanceApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('finance-read', static fn ($user): bool =>
            $user->hasRole('superadmin') || $user->can('read voucher'));
        Gate::define('finance-post', static fn ($user): bool =>
            $user->hasRole('superadmin') || $user->can('create voucher'));
        Gate::define('approve-stock-finance', static fn ($user): bool =>
            $user->hasRole('superadmin') || $user->can('can approve'));
    }
}
