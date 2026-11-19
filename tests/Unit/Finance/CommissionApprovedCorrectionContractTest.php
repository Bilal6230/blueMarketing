<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class CommissionApprovedCorrectionContractTest extends TestCase
{
    public function test_approved_commission_edit_corrects_original_batch_without_customer_reversal_lines(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/CommissionVoucherController.php');
        $editor=file_get_contents($root.'/app/Services/Finance/CommissionApprovedVoucherEditor.php');
        $posting=file_get_contents($root.'/app/Services/Finance/CommissionPostingWriter.php');
        $report=file_get_contents($root.'/app/Services/Finance/PostingReportReader.php');
        foreach ([$controller,$editor,$posting,$report] as $source) self::assertIsString($source);

        self::assertStringContainsString('CommissionApprovedVoucherEditor::class',$controller);
        self::assertStringContainsString("status==='approved'",$controller);
        self::assertStringContainsString('finance_commission_line_sources',$editor);
        self::assertStringContainsString('commision_voucher_detail_id',$editor);
        self::assertStringContainsString('assertEnforcedDetailForeignKey()',$editor);
        self::assertStringContainsString('whereNull(\'deleted_at\')',$editor);
        self::assertStringContainsString("'is_active'=>0",$editor);
        self::assertStringContainsString("'is_active'=>1",$editor);
        self::assertStringContainsString("'payload_sha256'=>\$fingerprint",$editor);
        self::assertStringNotContainsString('reversePostedBatch(',$editor);
        self::assertStringNotContainsString('PostingReversalWriter',$editor);
        self::assertStringContainsString("where('l.is_active',1)",$posting);
        self::assertStringContainsString("where('line.is_active',1)",$report);
    }
}
