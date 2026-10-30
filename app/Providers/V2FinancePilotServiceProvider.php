<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Opt-in finance V2 pilot authorization ONLY in isolated local/testing.
 * No V1 role or permission is altered; no production Gate ability exists.
 */
final class V2FinancePilotServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!in_array(config('app.env'), ['local', 'testing'], true)
            || !config('finance.v2_pilot_api_enabled', false)) {
            return;
        }

        Gate::define('v2-finance-read', static function ($user): bool {
            return $user->hasRole('superadmin');
        });
        Gate::define('v2-finance-post', static function ($user): bool {
            return (bool) config('finance.v2_pilot_write_enabled', false)
                && $user->hasRole('superadmin');
        });
        Gate::define('approve-v2-stock-finance', static function ($user): bool {
            return (bool) config('finance.v2_pilot_write_enabled', false)
                && $user->hasRole('superadmin');
        });
    }
}
