<?php

namespace App\Services\Crm;

use Carbon\CarbonInterface;

final class FollowUpStatus
{
    /** Interested, Schedule Later, New Lead, Town Visit, Outside Meeting. */
    public const OPEN = [2, 3, 6, 7, 8];

    public static function isOpen(int $status): bool
    {
        return in_array($status, self::OPEN, true);
    }

    public static function label(int $status, ?CarbonInterface $next): string
    {
        if (!self::isOpen($status)) {
            return 'Completed / Closed';
        }
        if ($next === null) {
            return 'Pending (unscheduled)';
        }
        if ($next->isToday()) {
            return 'Due Today';
        }
        return $next->isPast() ? 'Overdue' : 'Scheduled';
    }
}
