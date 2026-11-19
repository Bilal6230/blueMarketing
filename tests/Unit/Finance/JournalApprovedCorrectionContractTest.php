<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class JournalApprovedCorrectionContractTest extends TestCase
{
    public function test_posted_jv_and_sv_edit_routes_use_same_batch_fk_safe_editor(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/JournalVoucherController.php');
        $editor=file_get_contents($root.'/app/Services/Finance/JournalApprovedVoucherEditor.php');
        $writer=file_get_contents($root.'/app/Services/Finance/JournalPostingWriter.php');
        $report=file_get_contents($root.'/app/Services/Finance/PostingReportReader.php');
        foreach([$controller,$editor,$writer,$report] as $text)self::assertIsString($text);
        self::assertStringContainsString('JournalApprovedVoucherEditor::class',$controller);
        self::assertStringContainsString("whereIn('voucher.type', ['JV','SV'])",$controller);
        self::assertStringContainsString('finance_journal_line_sources',$editor);
        self::assertStringContainsString('journal_voucher_detail_id',$editor);
        self::assertStringContainsString("where('line.is_active',1)",$editor);
        self::assertStringContainsString("'is_active'=>0",$editor);
        self::assertStringContainsString("'is_active'=>1",$editor);
        self::assertStringContainsString("'payload_sha256'=>\$newHash",$editor);
        self::assertStringNotContainsString('PostingReversalWriter',$editor);
        self::assertStringContainsString("where('line.is_active',1)",$writer);
        self::assertStringContainsString("where('line.is_active',1)",$report);
    }
}
