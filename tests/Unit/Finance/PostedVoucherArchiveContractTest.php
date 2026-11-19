<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class PostedVoucherArchiveContractTest extends TestCase
{
    public function test_journal_and_commission_posted_delete_archive_exact_source_and_active_legs(): void
    {
        $root=dirname(__DIR__,3);
        $archiver=file_get_contents($root.'/app/Services/Finance/PostedVoucherArchiver.php');
        $journal=file_get_contents($root.'/app/Http/Controllers/JournalVoucherController.php');
        $commission=file_get_contents($root.'/app/Http/Controllers/CommissionVoucherController.php');
        $report=file_get_contents($root.'/app/Services/Finance/PostingReportReader.php');
        $audit=file_get_contents($root.'/app/Services/Finance/PostingBatchIntegrityAudit.php');
        foreach([$archiver,$journal,$commission,$report,$audit] as $code)self::assertIsString($code);
        self::assertStringContainsString('finance_posting_journal_sources',$archiver);
        self::assertStringContainsString('finance_posting_commission_sources',$archiver);
        self::assertStringContainsString('finance_journal_line_sources',$archiver);
        self::assertStringContainsString('finance_commission_line_sources',$archiver);
        self::assertStringContainsString('journal_voucher_detail_id',$archiver);
        self::assertStringContainsString('commision_voucher_detail_id',$archiver);
        self::assertStringContainsString('assertEnforcedDetailForeignKey()',$archiver);
        self::assertStringContainsString("'is_active'=>0",$archiver);
        self::assertStringContainsString("'deleted_at'=>now()",$archiver);
        self::assertStringNotContainsString('PostingReversalWriter',$archiver);
        self::assertStringContainsString('PostedVoucherArchiver::class',$journal);
        self::assertStringContainsString("can('finance jv reverse')",$journal);
        self::assertStringContainsString("can('finance cv reverse')",$commission);
        self::assertStringContainsString('PostedVoucherArchiver::class',$commission);
        self::assertStringContainsString('ARCHIVED_VOUCHER_WITH_ACTIVE_POSTINGS',$audit);
        self::assertStringContainsString('archived_header.deleted_at',$report);
    }
}
