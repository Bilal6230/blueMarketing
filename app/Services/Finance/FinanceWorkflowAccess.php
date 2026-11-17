<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\{Auth, DB, Gate};

final class FinanceWorkflowAccess
{
    /** All environments use the normal unified ERP finance workflow. */
    public static function normalErpMode(): bool
    {
        return true;
    }

    /** Compatibility API for existing finance services; no pilot or DB selector. */
    public static function mergedWriterAllowed(): bool
    {
        return FinanceConnectionGuard::isAllowed();
    }

    /** No isolated finance mode may be selected by configuration. */
    public static function selected(): bool
    {
        return false;
    }

    public static function enabled(): bool
    {
        return self::mergedWriterAllowed();
    }

    public static function authorize(int $projectId): void
    {
        abort_unless(self::mergedWriterAllowed(), 409, 'Finance connection is not configured.');
        FinanceConnectionGuard::assertAllowed();
        $user = Auth::user();
        abort_unless($user && $projectId > 0 && Gate::has('v2-finance-post'), 403);
        Gate::authorize('v2-finance-post');
        abort_unless($user->hasRole('superadmin') || (int) $user->project_id === $projectId, 403);
        abort_unless(DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 403);
    }

    public static function authorizeRead(int $projectId): void
    {
        abort_unless(self::mergedWriterAllowed(), 409, 'Finance connection is not configured.');
        FinanceConnectionGuard::assertAllowed();
        $user = Auth::user();
        abort_unless($user && $projectId > 0 && Gate::has('v2-finance-read'), 403);
        Gate::authorize('v2-finance-read');
        abort_unless($user->hasRole('superadmin') || (int) $user->project_id === $projectId, 403);
        abort_unless(DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 403);
    }
}
