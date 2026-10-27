<?php

namespace App\Services\Finance;

/**
 * Read-only manual evidence workflow. Fingerprints detect stale review packets;
 * a reviewer statement is never transformed into ledger ownership.
 */
final class LegacyEvidenceReviewWorksheet
{
    public function template(array $batch): array
    {
        $rows = [];
        foreach ($batch['packets'] as $packet) {
            $ledger = $packet['ledger'];
            $snapshot = [
                'project_id' => $batch['project_id'],
                'ledger' => $ledger,
                'candidate_category' => $packet['candidate_category'],
                'candidate_jv_header' => $packet['candidate_jv_header'],
                'candidate_cv_header' => $packet['candidate_cv_header'],
            ];
            $rows[] = [
                'ledger_id' => $ledger['id'],
                'snapshot_sha256' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                'proposed_source_kind' => null,
                'proposed_header_id' => null,
                'evidence_reference' => null,
                'reviewer' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ];
        }
        return [
            'schema_version' => 1,
            'project_id' => $batch['project_id'],
            'source_verified' => false,
            'workflow_status' => 'manual_evidence_required',
            'rows' => $rows,
            'instructions' => 'Fill independent evidence and reviewer fields, then validate against a fresh snapshot. A valid worksheet is NOT posting authorization.',
        ];
    }

    /**
     * Require a complete one-to-one submission: no missing, extra or duplicate
     * IDs; no silent substitution of a changed financial snapshot.
     */
    public function assess(array $batch, array $submission): array
    {
        $template = $this->template($batch);
        $issues = [];
        $expected = [];
        foreach ($template['rows'] as $row) {
            $expected[$row['ledger_id']] = $row;
        }
        if (($submission['schema_version'] ?? null) !== 1 ||
            ($submission['project_id'] ?? null) !== $batch['project_id'] ||
            !isset($submission['rows']) || !is_array($submission['rows'])) {
            return ['status' => 'blocked', 'source_verified' => false,
                'issues' => [['code' => 'invalid_worksheet_envelope']]];
        }
        $seen = [];
        foreach ($submission['rows'] as $row) {
            if (!is_array($row) || !isset($row['ledger_id']) || !is_int($row['ledger_id']) ||
                !isset($expected[$row['ledger_id']]) || isset($seen[$row['ledger_id']])) {
                $issues[] = ['code' => 'invalid_or_duplicate_ledger_id'];
                continue;
            }
            $id = $row['ledger_id'];
            $seen[$id] = true;
            if (!is_string($row['snapshot_sha256'] ?? null) ||
                !hash_equals($expected[$id]['snapshot_sha256'], $row['snapshot_sha256'])) {
                $issues[] = ['ledger_id' => $id, 'code' => 'stale_snapshot'];
            }
            $kind = $row['proposed_source_kind'] ?? null;
            $header = $row['proposed_header_id'] ?? null;
            if (!in_array($kind, ['journal_voucher', 'commision_voucher', 'unresolved'], true) ||
                ($kind !== 'unresolved' && (!is_int($header) || $header < 1)) ||
                ($kind === 'unresolved' && $header !== null)) {
                $issues[] = ['ledger_id' => $id, 'code' => 'invalid_proposed_source'];
            }
            foreach (['evidence_reference', 'reviewer', 'reviewed_at', 'review_note'] as $field) {
                $value = $row[$field] ?? null;
                if (!is_string($value) || trim($value) === '' || strlen($value) > 2000) {
                    $issues[] = ['ledger_id' => $id, 'code' => 'missing_or_invalid_'.$field];
                }
            }
            // Header candidates are ONLY hints. A reviewer must explicitly flag
            // divergent claims for further manual investigation.
            if ($kind !== 'unresolved' && is_int($header)) {
                $packet = null;
                foreach ($batch['packets'] as $candidate) {
                    if ($candidate['ledger']['id'] === $id) {
                        $packet = $candidate;
                        break;
                    }
                }
                $candidate = $kind === 'journal_voucher'
                    ? $packet['candidate_jv_header'] : $packet['candidate_cv_header'];
                if (!$candidate || $candidate['id'] !== $header) {
                    $issues[] = ['ledger_id' => $id, 'code' => 'claimed_header_not_in_snapshot'];
                }
            }
        }
        foreach (array_keys($expected) as $id) {
            if (!isset($seen[$id])) {
                $issues[] = ['ledger_id' => $id, 'code' => 'missing_review_row'];
            }
        }
        return [
            'status' => $issues ? 'blocked' : 'ready_for_independent_approval',
            'source_verified' => false,
            'project_id' => $batch['project_id'],
            'reviewed_rows' => count($seen),
            'expected_rows' => count($expected),
            'issues' => $issues,
            'limitations' => 'No provenance certified, no metadata write, and no automatic approval or posting.',
        ];
    }
}
