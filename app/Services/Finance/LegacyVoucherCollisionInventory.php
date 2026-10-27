<?php
namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only, bounded inventory of legacy JV-type rows requiring human review.
 * Numeric type_id overlap is explicitly ambiguous, never proof of ownership.
 */
final class LegacyVoucherCollisionInventory
{
    public function inspect(int $projectId, int $limit = 100): array
    {
        if ($projectId < 1 || $limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Valid project and limit (1-1000) required.');
        }
        if (!DB::table('projects')->where('id', $projectId)->exists()) {
            throw new \DomainException('Project does not exist.');
        }
        $scope = DB::table('ledgers')->where('type', 'JV')
            ->whereIn('project_head_subheads_id', function ($q) use ($projectId) {
                $q->select('id')->from('project_head_subheads')->where('project_id', $projectId);
            });
        if (Schema::hasTable('ledger_source_attributions')) {
            $scope->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('ledger_source_attributions as a')
                    ->whereColumn('a.ledger_id', 'ledgers.id');
            });
        }
        $available = (clone $scope)->count();
        $rows = (clone $scope)->orderBy('id')->limit($limit)->get(['id', 'type_id', 'voucher_number']);
        $cases = [];
        $categories = ['both_headers' => 0, 'jv_only' => 0, 'cv_only' => 0, 'neither_header' => 0];
        foreach ($rows as $ledger) {
            $jv = DB::table('journal_vouchers')->where('project_id', $projectId)
                ->where('id', $ledger->type_id)->exists();
            $cv = DB::table('commision_vouchers')->where('project_id', $projectId)
                ->where('id', $ledger->type_id)->exists();
            $category = $jv ? ($cv ? 'both_headers' : 'jv_only') : ($cv ? 'cv_only' : 'neither_header');
            $categories[$category]++;
            $cases[] = [
                'ledger_id' => (int) $ledger->id,
                'legacy_type_id' => (int) $ledger->type_id,
                'original_voucher_number' => (string) $ledger->voucher_number,
                'candidate_category' => $category,
                'source_verified' => false,
            ];
        }
        return [
            'project_id' => $projectId, 'available' => $available, 'scanned' => $rows->count(),
            'incomplete' => $available > $rows->count(),
            'categories_in_scanned_rows' => $categories, 'cases' => $cases,
            'status' => $available > $rows->count() ? 'incomplete' : 'review_required',
            'limitations' => 'Header existence is not source proof. No historical ledger attribution is written.',
        ];
    }
    /**
     * Complete bounded project classification without a large per-ledger JSON dump.
     * Header matches are candidates ONLY; no legacy provenance is verified.
     */
    public function summarize(int $projectId, int $maxRows = 5000): array
    {
        if ($projectId < 1 || $maxRows < 1 || $maxRows > 10000) {
            throw new \InvalidArgumentException('Valid project and maxRows (1-10000) required.');
        }
        if (!DB::table('projects')->where('id', $projectId)->exists()) {
            throw new \DomainException('Project does not exist.');
        }
        $query = DB::table('ledgers')->where('type', 'JV')
            ->whereIn('project_head_subheads_id', function ($q) use ($projectId) {
                $q->select('id')->from('project_head_subheads')->where('project_id', $projectId);
            });
        if (Schema::hasTable('ledger_source_attributions')) {
            $query->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('ledger_source_attributions as a')
                    ->whereColumn('a.ledger_id', 'ledgers.id');
            });
        }
        $available = (clone $query)->count();
        $categories = ['both_headers' => 0, 'jv_only' => 0, 'cv_only' => 0, 'neither_header' => 0];
        $examples = ['both_headers' => [], 'jv_only' => [], 'cv_only' => [], 'neither_header' => []];
        // Retrieve no more than 10k IDs, then bulk-resolve header presence.
        // Avoid 2 DB queries per ledger (legacy projects can contain thousands).
        $rows = (clone $query)->orderBy('id')->limit($maxRows)->get(['id', 'type_id']);
        foreach ($rows->chunk(500) as $batch) {
            $ids = $batch->pluck('type_id')->unique()->all();
            $jvs = DB::table('journal_vouchers')->where('project_id', $projectId)
                ->whereIn('id', $ids)->pluck('id')->mapWithKeys(fn ($id) => [(string) $id => true])->all();
            $cvs = DB::table('commision_vouchers')->where('project_id', $projectId)
                ->whereIn('id', $ids)->pluck('id')->mapWithKeys(fn ($id) => [(string) $id => true])->all();
            foreach ($batch as $ledger) {
                $jv = isset($jvs[(string) $ledger->type_id]);
                $cv = isset($cvs[(string) $ledger->type_id]);
                $category = $jv ? ($cv ? 'both_headers' : 'jv_only')
                    : ($cv ? 'cv_only' : 'neither_header');
                $categories[$category]++;
                if (count($examples[$category]) < 5) {
                    $examples[$category][] = (int) $ledger->id;
                }
            }
        }
        $scanned = $rows->count();
        return [
            'project_id' => $projectId, 'available' => $available,
            'scanned' => $scanned, 'incomplete' => $available > $scanned,
            'categories_in_scanned_rows' => $categories,
            'review_examples_ledger_ids' => $examples,
            'status' => $available > $scanned ? 'incomplete' : 'review_required',
            'source_verified' => false,
            'limitations' => 'Read-only classification, not proof of JV/CV origin. Never bulk attribute.',
        ];
    }

}
