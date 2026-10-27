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
}
