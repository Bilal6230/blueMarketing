<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Immutable-in-memory review packet for a legacy ledger; never derives an
 * authoritative voucher owner from the numerical type_id.
 */
final class LegacyVoucherEvidencePacket
{
    /** Classify an internally generated packet without promoting hints to proof. */
    public function triageCategory(array $packet): string
    {
        if ($packet['candidate_category'] === 'both_headers') {
            return 'ambiguous_candidates';
        }
        if ($packet['candidate_category'] === 'neither_header') {
            return 'missing_candidate_header';
        }
        $header = match ($packet['candidate_category']) {
            'jv_only' => $packet['candidate_jv_header'],
            'cv_only' => $packet['candidate_cv_header'],
            default => throw new \InvalidArgumentException('Unknown packet candidate category.'),
        };
        $ledgerReference = $packet['ledger']['original_voucher_number'];
        $headerReference = $header['voucher_number'];
        if (trim($ledgerReference) === '' || trim($headerReference) === '') {
            return 'missing_voucher_reference';
        }
        // Exact string comparison deliberately preserves leading zeros/format.
        return $ledgerReference === $headerReference
            ? 'matching_reference_requires_manual_review' : 'candidate_reference_disagreement';
    }

    public function inspect(int $projectId, int $ledgerId): array
    {
        if ($projectId < 1 || $ledgerId < 1 ||
            !DB::table('projects')->where('id', $projectId)->exists()) {
            throw new \DomainException('Valid existing project and ledger required.');
        }
        $ledger = DB::table('ledgers')->where('id', $ledgerId)
            ->where('type', 'JV')
            ->whereIn('project_head_subheads_id', function ($q) use ($projectId) {
                $q->select('id')->from('project_head_subheads')->where('project_id', $projectId);
            })->first(['id','type','type_id','voucher_number','project_head_subheads_id']);
        if (!$ledger) {
            throw new \DomainException('JV-type ledger is absent from requested project.');
        }
        $jv = DB::table('journal_vouchers')->where('project_id', $projectId)
            ->where('id', $ledger->type_id)->first(['id','voucher_number']);
        $cv = DB::table('commision_vouchers')->where('project_id', $projectId)
            ->where('id', $ledger->type_id)->first(['id','voucher_number']);
        $category = $jv ? ($cv ? 'both_headers' : 'jv_only')
            : ($cv ? 'cv_only' : 'neither_header');
        return [
            'project_id'=>$projectId,
            'ledger'=>[
                'id'=>(int)$ledger->id,'type'=>$ledger->type,
                'legacy_type_id'=>(int)$ledger->type_id,
                'original_voucher_number'=>(string)$ledger->voucher_number,
                'project_account_id'=>(int)$ledger->project_head_subheads_id,
            ],
            'candidate_category'=>$category,
            'candidate_jv_header'=>$jv ? ['id'=>(int)$jv->id,'voucher_number'=>(string)$jv->voucher_number] : null,
            'candidate_cv_header'=>$cv ? ['id'=>(int)$cv->id,'voucher_number'=>(string)$cv->voucher_number] : null,
            'source_verified'=>false,
            'review_status'=>'requires_external_evidence',
            'limitations'=>'Numerical header match is not provenance; no history or attribution is modified.',
        ];
    }
    /**
     * Multi-case evidence review, strictly read-only. Invalid/cross-project
     * records abort instead of being silently omitted from a review packet.
     */
    public function inspectBatch(int $projectId, array $ledgerIds): array
    {
        if ($projectId < 1 || !$ledgerIds || count($ledgerIds) > 100 ||
            count(array_unique($ledgerIds)) !== count($ledgerIds)) {
            throw new \InvalidArgumentException('Provide 1-100 distinct ledger IDs.');
        }
        $packets = [];
        foreach ($ledgerIds as $id) {
            if (!is_int($id) || $id < 1) {
                throw new \InvalidArgumentException('Positive numeric ledger IDs required.');
            }
            $packets[] = $this->inspect($projectId, $id);
        }
        $categories = ['both_headers'=>0,'jv_only'=>0,'cv_only'=>0,'neither_header'=>0];
        foreach ($packets as $packet) {
            $categories[$packet['candidate_category']]++;
        }
        return ['project_id'=>$projectId,'scanned'=>count($packets),
            'candidate_categories'=>$categories,'packets'=>$packets,
            'source_verified'=>false,'review_status'=>'requires_external_evidence'];
    }

}
