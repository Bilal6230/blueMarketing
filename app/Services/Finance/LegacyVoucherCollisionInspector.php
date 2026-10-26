<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Read-only detection of numeric JV/CV ID overlap.
 * It reports candidate collisions, never assigns historical ledger ownership.
 */
final class LegacyVoucherCollisionInspector
{
    public function inspect(int $projectId, int $limit = 100): array
    {
        if ($projectId < 1 || $limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Project and limit must be positive (limit <= 1000).');
        }

        $jv = DB::table('journal_vouchers')->where('project_id', $projectId)->select('id');
        $cv = DB::table('commision_vouchers')->where('project_id', $projectId)->select('id');
        $overlaps = DB::query()->fromSub($jv, 'jv')
            ->joinSub($cv, 'cv', 'jv.id', '=', 'cv.id');
        $total = (clone $overlaps)->count();
        $ids = (clone $overlaps)->orderBy('jv.id')->limit($limit)->pluck('jv.id');
        $rows = [];
        foreach ($ids as $id) {
            $candidates = DB::table('ledgers')
                ->where('type', 'JV')->where('type_id', $id)
                ->whereIn('project_head_subheads_id', function ($query) use ($projectId) {
                    $query->select('id')->from('project_head_subheads')
                        ->where('project_id', $projectId);
                })->count();
            $rows[] = [
                'project_id' => $projectId,
                'overlapping_header_id' => (int) $id,
                'candidate_jv_ledger_rows' => $candidates,
                'source_identified' => false,
                'classification' => 'numeric_header_collision_only',
            ];
        }

        return [
            'project_id' => $projectId,
            'overlap_total' => $total,
            'reported_count' => count($rows),
            'incomplete' => $total > count($rows),
            'rows' => $rows,
            'ledger_provenance' => 'unknown',
        ];
    }
}
