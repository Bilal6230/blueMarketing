<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\{Auth, DB, Gate};

final class V2OriginalWorkflowAccess
{
    /**
     * A local-only standard ERP mode is explicitly requested by the operator
     * when testing ordinary voucher screens. Isolated V2 databases remain
     * in the experimental workflow and can never be switched to legacy writes.
     */
    public static function normalErpMode(): bool
    {
        $connection = config('database.default');
        $database = (string) config('database.connections.'.$connection.'.database');
        return config('app.env') === 'local'
            && !str_starts_with($database, 'bluemarketing_v2_')
            && (bool) config('finance.normal_erp_mode', false);
    }

    public static function selected(): bool
    {
        if (self::normalErpMode()) return false;
        $connection = config('database.default');
        return str_starts_with((string) config('database.connections.'.$connection.'.database'), 'bluemarketing_v2_')
            || (in_array(config('app.env'), ['local', 'testing'], true) && config('finance.v2_pilot_api_enabled'));
    }

    public static function enabled(): bool
    {
        if (self::normalErpMode()) return false;
        return in_array(config('app.env'), ['local', 'testing'], true)
            && config('finance.v2_pilot_api_enabled') && config('finance.v2_pilot_write_enabled');
    }

    public static function authorize(int $projectId): void
    {
        abort_unless(self::enabled(), 409, 'Isolated financial workflow is disabled.');
        V2IsolatedConnectionGuard::assertAllowed();
        $user = Auth::user();
        abort_unless($user && $projectId > 0 && Gate::has('v2-finance-post'), 403);
        Gate::authorize('v2-finance-post');
        abort_unless($user->hasRole('superadmin') || (int) $user->project_id === $projectId, 403);
        abort_unless(DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 403);
    }

    public static function authorizeRead(int $projectId): void
    {
        abort_unless(
            in_array(config('app.env'), ['local', 'testing'], true)
                && config('finance.v2_pilot_api_enabled'),
            409,
            'New voucher workspaces are disabled until an isolated local V2 finance database is configured.'
        );
        V2IsolatedConnectionGuard::assertAllowed();
        $user = Auth::user();
        abort_unless($user && $projectId > 0 && Gate::has('v2-finance-read'), 403);
        Gate::authorize('v2-finance-read');
        abort_unless($user->hasRole('superadmin') || (int) $user->project_id === $projectId, 403);
        abort_unless(DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 403);
    }
}
