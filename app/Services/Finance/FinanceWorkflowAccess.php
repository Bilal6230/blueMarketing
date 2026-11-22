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

    /** Unified finance service access. */
    public static function mergedWriterAllowed(): bool
    {
        return FinanceConnectionGuard::isAllowed();
    }

    /** No separate finance mode is selectable. */
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
        abort_unless($user && $projectId > 0, 403);
        if (Gate::has('finance-post')) {
            Gate::authorize('finance-post');
        } else {
            // Finance actions are authorized by their existing granular route
            // permissions. Do not block them on an undefined legacy gate.
            $ability = \App\Support\Finance\FinanceActionPermission::forRequest(request());
            abort_unless($ability && $user->can(
                \App\Support\Finance\FinanceActionPermission::name($ability[0], $ability[1])
            ), 403);
        }
        // This ERP has a project selector. A permitted finance user may work in
        // any selected active project; the route's granular permission remains
        // mandatory, and every finance query/write is scoped to $projectId.
        abort_unless((int) getSelectedTown() === $projectId, 403);
        abort_unless(DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 403);
    }

    public static function authorizeRead(int $projectId): void
    {
        abort_unless(self::mergedWriterAllowed(), 409, 'Finance connection is not configured.');
        FinanceConnectionGuard::assertAllowed();
        $user = Auth::user();
        abort_unless($user && $projectId > 0, 403);
        if (Gate::has('finance-read')) {
            Gate::authorize('finance-read');
        } else {
            // Respect the per-voucher route permission when no finance-read
            // gate is registered. Project ownership is checked below.
            $ability = \App\Support\Finance\FinanceActionPermission::forRequest(request());
            abort_unless($ability && $user->can(
                \App\Support\Finance\FinanceActionPermission::name($ability[0], $ability[1])
            ), 403);
        }
        // This ERP has a project selector. A permitted finance user may work in
        // any selected active project; the route's granular permission remains
        // mandatory, and every finance query/write is scoped to $projectId.
        abort_unless((int) getSelectedTown() === $projectId, 403);
        abort_unless(DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 403);
    }
}
