<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\LegacyEvidenceReviewWorksheet;
use PHPUnit\Framework\TestCase;

final class LegacyEvidenceReviewWorksheetTest extends TestCase
{
    private function batch(): array
    {
        return ['project_id' => 6, 'packets' => [[
            'ledger' => ['id'=>12179, 'type'=>'JV', 'legacy_type_id'=>1310,
                'original_voucher_number'=>'249', 'project_account_id'=>428],
            'candidate_category'=>'neither_header',
            'candidate_jv_header'=>null,
            'candidate_cv_header'=>null,
        ]]];
    }

    public function test_template_keeps_source_unverified_and_generates_stable_fingerprint(): void
    {
        $service = new LegacyEvidenceReviewWorksheet();
        $first = $service->template($this->batch());
        $this->assertSame($first, $service->template($this->batch()));
        $this->assertFalse($first['source_verified']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['rows'][0]['snapshot_sha256']);
        $this->assertNull($first['rows'][0]['proposed_source_kind']);
    }

    public function test_complete_unresolved_review_stays_unverified(): void
    {
        $service = new LegacyEvidenceReviewWorksheet();
        $review = $service->template($this->batch());
        $review['rows'][0] = array_merge($review['rows'][0], [
            'proposed_source_kind'=>'unresolved', 'evidence_reference'=>'archive-box-7',
            'reviewer'=>'Finance reviewer', 'reviewed_at'=>'2026-10-27',
            'review_note'=>'No independent voucher found.',
        ]);
        $result = $service->assess($this->batch(), $review);
        $this->assertSame('ready_for_independent_approval', $result['status']);
        $this->assertFalse($result['source_verified']);
        $this->assertSame([], $result['issues']);
    }

    public function test_stale_fingerprint_blocks_submitted_review(): void
    {
        $service = new LegacyEvidenceReviewWorksheet();
        $review = $service->template($this->batch());
        $review['rows'][0]['snapshot_sha256'] = str_repeat('0', 64);
        $result = $service->assess($this->batch(), $review);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('stale_snapshot', array_column($result['issues'], 'code'));
    }

    public function test_numeric_claim_without_independent_review_and_header_is_blocked(): void
    {
        $service = new LegacyEvidenceReviewWorksheet();
        $review = $service->template($this->batch());
        $review['rows'][0]['proposed_source_kind'] = 'journal_voucher';
        $review['rows'][0]['proposed_header_id'] = 1310;
        $result = $service->assess($this->batch(), $review);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('claimed_header_not_in_snapshot', array_column($result['issues'], 'code'));
        $this->assertContains('missing_or_invalid_evidence_reference', array_column($result['issues'], 'code'));
    }

    public function test_missing_duplicate_and_extra_review_rows_fail_closed(): void
    {
        $service = new LegacyEvidenceReviewWorksheet();
        $review = $service->template($this->batch());
        $review['rows'][] = $review['rows'][0];
        $review['rows'][] = array_merge($review['rows'][0], ['ledger_id'=>999]);
        $result = $service->assess($this->batch(), $review);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('invalid_or_duplicate_ledger_id', array_column($result['issues'], 'code'));
        $review['rows'] = [];
        $result = $service->assess($this->batch(), $review);
        $this->assertContains('missing_review_row', array_column($result['issues'], 'code'));
    }

    public function test_project_mismatch_is_rejected(): void
    {
        $service = new LegacyEvidenceReviewWorksheet();
        $review = $service->template($this->batch());
        $review['project_id'] = 7;
        $this->assertSame('invalid_worksheet_envelope',
            $service->assess($this->batch(), $review)['issues'][0]['code']);
    }
}
