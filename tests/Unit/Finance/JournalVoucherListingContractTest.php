<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class JournalVoucherListingContractTest extends TestCase
{
    public function test_jv_and_sv_listing_use_explicit_voucher_types_without_uninitialized_is_sales(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        self::assertIsString($source);

        $indexStart = strpos($source, 'public function index(Request $request)');
        $salesStart = strpos($source, 'public function salesIndex(Request $request)');
        self::assertNotFalse($indexStart);
        self::assertNotFalse($salesStart);
        self::assertGreaterThan($indexStart, $salesStart);

        $journalIndex = substr($source, $indexStart, $salesStart - $indexStart);
        self::assertStringNotContainsString('$isSales', $journalIndex);
        self::assertStringContainsString("\$x['type'] = 'JV';", $journalIndex);
        self::assertStringContainsString("\$x['class'] = 'Journal voucher';", $journalIndex);

        $createStart = strpos($source, 'public function create()');
        self::assertNotFalse($createStart);
        $salesIndex = substr($source, $salesStart, $createStart - $salesStart);
        self::assertStringContainsString("\$x['type'] = 'SV';", $salesIndex);
    }
}
