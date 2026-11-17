<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\{Auth, DB, Gate};

final class V2OriginalWorkflowAccess
{
    /**
     * The ordinary ERP is the default on a LOCAL, non-isolated database.
     * This choice no longer depends on FINANCE_NORMAL_ERP_MODE or pilot flags.
     * V2-prefixed isolated databases continue to use their own pilot routing.
     * Production remains on the original ERP routes pending source-by-source rollout.
     */
    public static function normalErpMode(): bool
    {
        $connection = config('database.default');
        $database = (string) config('database.connections.'.$connection.'.database');
        return config('app.env') === 'local'
            && $database !== ''
            && !str_starts_with($database, 'bluemarketing_v2_');
    }

    /**
     * Merge the existing typed writer into the normal LOCAL ERP workspaces.
     * This is a narrowly scoped bridge, NOT a global switch back to pilot mode.
     * A mixed project keeps legacy writers for legacy sources and typed writers
     * for typed sources; the DB connection guard still rejects live databases.
     */
    public static function mergedWriterAllowed(): bool
    {
        return self::normalErpMode()
            && V2IsolatedConnectionGuard::isAllowed();
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
        abort_unless(self::enabled() || self::mergedWriterAllowed(), 409, 'Typed financial workflow is unavailable for this database.');
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
                && (config('finance.v2_pilot_api_enabled') || self::mergedWriterAllowed()),
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
