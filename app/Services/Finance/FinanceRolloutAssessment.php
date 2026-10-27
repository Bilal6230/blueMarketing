<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Read-only bounded all-project finance rollout assessment.
 * No historical JV/CV ledger ownership is inferred or assigned.
 */
final class FinanceRolloutAssessment
{
    public function inspect(int $limit = 100): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Project scan limit must be 1-1000.');
        }

        $schema = app(FinanceSchemaPreflight::class)->inspect();
        $ids = DB::table('projects')->orderBy('id')->limit($limit + 1)->pluck('id');
        $truncated = $ids->count() > $limit;
        $projects = [];
        $totalCandidates = 0;
        $totalUnverified = 0;
        $blockedProjects = 0;
        foreach ($ids->take($limit) as $id) {
            $report = app(FinancialReadinessInspector::class)->inspect((int) $id);
            $projects[] = [
                'project_id' => (int) $id,
                'status' => $report['status'],
                'jv_headers' => $report['jv_headers'],
                'cv_headers' => $report['cv_headers'],
                'candidate_jv_type_ledgers' => $report['candidate_jv_type_ledgers'],
                'unverified_jv_type_ledgers' => $report['unverified_jv_type_ledgers'],
                'blockers' => $report['blockers'],
            ];
            $totalCandidates += $report['candidate_jv_type_ledgers'];
            $totalUnverified += $report['unverified_jv_type_ledgers'];
            if ($report['status'] !== 'preliminary_ready') {
                $blockedProjects++;
            }
        }
        // An empty project inventory must never be mistaken for a verified rollout.
        $ready = count($projects) > 0 && !$truncated
            && $schema['status'] === 'schema_verified' && $blockedProjects === 0;
        $globalBlockers = [];
        if ($projects === []) {
            $globalBlockers[] = 'no_projects_scanned';
        }
        if ($truncated) {
            $globalBlockers[] = 'project_scan_incomplete';
        }
        if ($schema['status'] !== 'schema_verified') {
            $globalBlockers[] = 'finance_metadata_schema_unsafe';
        }
        if ($blockedProjects > 0) {
            $globalBlockers[] = 'blocked_projects_present';
        }
        return [
            'status' => $ready ? 'preliminary_ready' : 'blocked',
            'schema' => $schema,
            'blockers' => $globalBlockers,
            'scanned_projects' => count($projects),
            'incomplete' => $truncated,
            'blocked_projects' => $blockedProjects,
            'candidate_jv_type_ledgers' => $totalCandidates,
            'unverified_jv_type_ledgers' => $totalUnverified,
            'projects' => $projects,
            'limitations' => 'Preliminary read-only inventory, not historical ledger reconciliation, backup verification, or MySQL concurrency approval.',
        ];
    }
}
