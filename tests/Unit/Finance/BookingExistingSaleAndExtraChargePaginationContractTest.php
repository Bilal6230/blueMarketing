<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class BookingExistingSaleAndExtraChargePaginationContractTest extends TestCase
{
    public function test_existing_booking_amendment_reports_exact_source_reconciliation_failure(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/BookingController.php');
        $service=file_get_contents($root.'/app/Services/Booking/BookingAmendmentService.php');
        self::assertIsString($controller);
        self::assertIsString($service);

        foreach ([
            'BOOKING_SV_SOURCE_MISSING',
            'BOOKING_SV_SOURCE_AMBIGUOUS',
            'BOOKING_BO_SOURCE_AMBIGUOUS',
            'BOOKING_SV_DETAIL_MISMATCH',
            'BOOKING_SV_UNBALANCED',
        ] as $reason) {
            self::assertStringContainsString("'".$reason."' =>", $controller);
            self::assertStringContainsString($reason, $service);
        }
        self::assertStringContainsString('Booking amendment rejected by accounting validation.', $controller);
        self::assertStringContainsString("'reason' => \$exception->getMessage()", $controller);
        self::assertStringContainsString('$this->syncOriginalSale(', $service);
    }

    public function test_legacy_booking_sv_amount_is_resolved_from_balanced_posted_legs(): void
    {
        $service=file_get_contents(dirname(__DIR__,3).'/app/Services/Booking/BookingAmendmentService.php');
        self::assertIsString($service);
        self::assertStringContainsString("if(\$vouchers->count()!==1)", $service);
        self::assertStringContainsString("bccomp((string)\$legs->sum('amount_in'),(string)\$legs->sum('amount_out'),2)===0", $service);
        self::assertStringContainsString("\$postedTotal=(string)\$legs->sum('amount_in');", $service);
        self::assertStringContainsString("bccomp((string)\$bo->amount_in,\$postedTotal,2)", $service);
        self::assertStringContainsString("\$targetTotal=\$amountChanged?\$newTotal:\$postedTotal;", $service);
        self::assertStringContainsString("\$pricingChanged && bccomp(\$oldTotal,\$newTotal,2)!==0", $service);
    }

    public function test_extra_charge_table_keeps_only_final_bottom_pagination(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/booking/extra_charge.blade.php');
        self::assertIsString($view);
        self::assertStringContainsString('keepExtraChargeBottomPager', $view);
        self::assertStringContainsString("find('.dataTables_paginate,.dt-paging')", $view);
        self::assertStringContainsString('$pagers.slice(0,-1).remove()', $view);
        self::assertStringContainsString("$(document).on('init.dt draw.dt'", $view);
        self::assertStringContainsString('keepExtraChargeBottomPager();', $view);
        self::assertStringContainsString('id="example1"', $view);
    }
}
