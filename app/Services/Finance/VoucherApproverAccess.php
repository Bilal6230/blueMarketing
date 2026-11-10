<?php

namespace App\Services\Finance;

use App\Models\User;

/**
 * Access policy used by the voucher approval UI, assignment and locked decision.
 * The superadmin role is privileged but NOT an automatic posting approval.
 */
final class VoucherApproverAccess
{
    public static function isSuperAdmin(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(['superadmin', 'super-admin']);
    }

    public static function mayApprove(?User $user): bool
    {
        return $user !== null
            && (self::isSuperAdmin($user) || $user->can('voucher approver'));
    }

    public static function eligibleForAssignment(?User $user, int $projectId): bool
    {
        return $user !== null && (int) $user->project_id === $projectId
            && (int) $user->status_id === 1
            && self::mayApprove($user);
    }

    public static function mayDecide(?User $user, int $projectId, int $assignedId, int $submitterId): bool
    {
        if (!$user || !self::mayApprove($user)) return false;
        if ((int) $user->status_id !== 1) return false;
        if (!self::isSuperAdmin($user) && (int) $user->project_id !== $projectId) return false;
        // Explicit superadmin override, including own requests, with full audit.
        if (self::isSuperAdmin($user)) return true;
        return (int) $user->id === $assignedId && (int) $user->id !== $submitterId;
    }
}
