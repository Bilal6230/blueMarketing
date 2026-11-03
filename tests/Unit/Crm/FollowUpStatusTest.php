<?php

namespace Tests\Unit\Crm;

use App\Services\Crm\FollowUpStatus;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FollowUpStatusTest extends TestCase
{
    public function test_only_actionable_crm_statuses_are_open(): void
    {
        foreach ([2, 3, 6, 7, 8] as $status) {
            $this->assertTrue(FollowUpStatus::isOpen($status));
        }
        foreach ([1, 4, 5] as $status) {
            $this->assertFalse(FollowUpStatus::isOpen($status));
        }
    }

    public function test_status_label_distinguishes_closed_due_overdue_and_scheduled(): void
    {
        Carbon::setTestNow('2026-11-02 12:00:00');
        $this->assertSame('Completed / Closed', FollowUpStatus::label(5, Carbon::tomorrow()));
        $this->assertSame('Pending (unscheduled)', FollowUpStatus::label(6, null));
        $this->assertSame('Due Today', FollowUpStatus::label(2, Carbon::today()->setTime(8, 0)));
        $this->assertSame('Overdue', FollowUpStatus::label(3, Carbon::yesterday()));
        $this->assertSame('Scheduled', FollowUpStatus::label(7, Carbon::tomorrow()));
        Carbon::setTestNow();
    }
}
