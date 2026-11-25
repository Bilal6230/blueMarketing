<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class JournalDraftDifferentialIdentityContractTest extends TestCase
{
    private function source(string $path): string
    {
        $text = file_get_contents(dirname(__DIR__, 3).'/'.$path);
        self::assertIsString($text, $path);
        return $text;
    }

    public function test_unposted_journal_updates_are_identity_preserving_and_transactional(): void
    {
        $code = $this->source('app/Services/Finance/JournalDraftWorkflow.php');
        self::assertStringContainsString('DB::transaction(function () use ($projectId, $sourceId, $date, $lines)', $code);
        self::assertStringContainsString("->where('journal_voucher_id', $sourceId)->whereNull('deleted_at')", $code);
        self::assertStringContainsString('->lockForUpdate()->get()->keyBy(', $code);
        self::assertStringContainsString("isset($submittedIds[$detailId]) || !$current->has($detailId)", $code);
        self::assertStringContainsString('JV_DRAFT_DETAIL_NOT_OWNED_OR_DUPLICATE', $code);
        self::assertStringContainsString("if (isset($submittedIds[$detailId])) continue;", $code);
        self::assertStringContainsString("->update(['deleted_at' => now(), 'updated_at' => now()]);", $code);
        self::assertStringContainsString("if (!$detailId) {", $code);
        self::assertStringContainsString("DB::table('journal_voucher_details')->insert($line + [", $code);
        self::assertStringContainsString("if ((int)$old->account_id === (int)$line['account_id']", $code);
        self::assertStringContainsString("->where('journal_voucher_id', $sourceId)->whereNull('deleted_at')", $code);
        self::assertStringNotContainsString("->where('journal_voucher_id', $sourceId)->whereNull('deleted_at')\n                ->update(['deleted_at' => now()]);", $code);
        self::assertStringContainsString('V2_JV_DRAFT_IMMUTABLE', $code);
    }

    public function test_posted_journal_still_archives_changed_lines_with_explicit_source_links(): void
    {
        $posted = $this->source('app/Services/Finance/JournalApprovedVoucherEditor.php');
        foreach ([
            'finance_posting_journal_sources',
            'finance_journal_line_sources',
            'journal_voucher_detail_id',
            "where('line.is_active',1)",
            "where('id',$posted[$id][0]->id)",
            "where('id',$legacy[$id][0]->id)",
            'insertGetId(',
        ] as $needle) {
            self::assertStringContainsString($needle, $posted);
        }
    }

    public function test_real_fk_enforcement_exists_and_type_id_is_not_a_foreign_key(): void
    {
        $migration = $this->source('database/migrations/2026_11_18_150000_enforce_voucher_source_foreign_keys.php');
        foreach ([
            "'ledgers','journal_voucher_detail_id','journal_voucher_details'",
            "'journal_voucher_details','journal_voucher_id','journal_vouchers'",
            "'commision_voucher_details','commision_voucher_id','commision_vouchers'",
            "'journal_voucher_details','account_id','project_head_subheads'",
            "'commision_voucher_details','account_id','project_head_subheads'",
            'restrictOnDelete()->restrictOnUpdate()',
            'Polymorphic ledgers.type + ledgers.type_id are NOT eligible for an FK.',
        ] as $needle) {
            self::assertStringContainsString($needle, $migration);
        }
    }
}
