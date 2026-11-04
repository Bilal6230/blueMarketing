<?php

namespace App\Services\Finance;

use App\Models\AccountGroup;
use Illuminate\Support\Facades\DB;

final class AccountGroupMergePreview
{
    public function build(int $projectId, int $sourceId, int $targetId): array
    {
        if ($projectId < 1 || $sourceId === $targetId) {
            throw new \InvalidArgumentException('Choose two different groups.');
        }

        $groups = AccountGroup::where('project_id', $projectId)->whereIn('id', [$sourceId, $targetId])->get()->keyBy('id');
        abort_unless($groups->count() === 2, 404, 'One of the selected groups no longer exists.');
        $source = $groups->get($sourceId);
        $target = $groups->get($targetId);
        $headLinks = DB::table('project_head_account_groups as link')
            ->join('head_accountings as head', 'head.id', '=', 'link.head_accounting_id')
            ->where('link.project_id', $projectId)->whereIn('link.account_group_id', [$sourceId, $targetId])
            ->get(['link.head_accounting_id', 'link.account_group_id', 'head.name', 'head.acct_type']);
        $sourceHeadIds = $headLinks->where('account_group_id', $sourceId)->pluck('head_accounting_id')->map(fn ($id) => (int) $id)->all();
        $targetHeadIds = $headLinks->where('account_group_id', $targetId)->pluck('head_accounting_id')->map(fn ($id) => (int) $id)->all();
        $headIds = array_values(array_unique(array_merge($sourceHeadIds, $targetHeadIds)));

        $mappings = $headIds === [] ? collect() : DB::table('project_head_subheads as mapping')
            ->leftJoin('projects', 'projects.id', '=', 'mapping.project_id')
            ->where('mapping.project_id', $projectId)->whereIn('mapping.head_accounting_id', $headIds)
            ->get([
                'mapping.id', 'mapping.project_id', 'mapping.head_accounting_id',
                'mapping.subhead_accounting_id', 'projects.name as project_name',
            ]);

        $mappingIds = $mappings->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ledgerTotals = $mappingIds === [] ? collect() : DB::table('ledgers')
            ->whereIn('project_head_subheads_id', $mappingIds)
            ->where('is_active', 1)
            ->groupBy('project_head_subheads_id')
            ->get([
                'project_head_subheads_id',
                DB::raw('COUNT(*) as ledger_count'),
                DB::raw('SUM(amount_in) as amount_in'),
                DB::raw('SUM(amount_out) as amount_out'),
            ])->keyBy('project_head_subheads_id');

        $projects = [];
        $global = ['mappings' => 0, 'ledger_rows' => 0, 'amount_in_cents' => 0, 'amount_out_cents' => 0];
        foreach ($mappings as $mapping) {
            $projectId = (int) $mapping->project_id;
            $project = $projects[$projectId] ??= [
                'project_id' => $projectId,
                'project_name' => $mapping->project_name ?: "Project {$projectId}",
                'source_heads' => [], 'target_heads' => [],
                'mappings' => 0, 'ledger_rows' => 0, 'amount_in_cents' => 0, 'amount_out_cents' => 0,
            ];
            $headId = (int) $mapping->head_accounting_id;
            $side = in_array($headId, $sourceHeadIds, true) ? 'source_heads' : 'target_heads';
            $project[$side][$headId] = true;
            $project['mappings']++;
            $global['mappings']++;
            $ledger = $ledgerTotals->get($mapping->id);
            if ($ledger) {
                $count = (int) $ledger->ledger_count;
                $in = $this->cents($ledger->amount_in);
                $out = $this->cents($ledger->amount_out);
                $project['ledger_rows'] += $count;
                $project['amount_in_cents'] += $in;
                $project['amount_out_cents'] += $out;
                $global['ledger_rows'] += $count;
                $global['amount_in_cents'] += $in;
                $global['amount_out_cents'] += $out;
            }
            $projects[$projectId] = $project;
        }

        foreach ($projects as &$project) {
            $project['source_heads'] = count($project['source_heads']);
            $project['target_heads'] = count($project['target_heads']);
        }
        unset($project);

        $duplicates = $mappingIds === [] ? collect() : DB::table('project_head_subheads')
            ->whereIn('id', $mappingIds)
            ->select('project_id', 'head_accounting_id', 'subhead_accounting_id', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('project_id', 'head_accounting_id', 'subhead_accounting_id')
            ->havingRaw('COUNT(*) > 1')->get();

        $reasons = [];
        if (!$source->is_active) $reasons[] = 'The source group is inactive.';
        if (!$target->is_active) $reasons[] = 'The target group is inactive.';
        if ($source->children()->where('project_id', $projectId)->where('is_active', 1)->exists()) $reasons[] = 'The source has active child groups; merge leaves only until descendants are reviewed.';
        $sourceTypes = $headLinks->where('account_group_id', $sourceId)->pluck('acct_type')->unique()->values();
        $targetTypes = $headLinks->where('account_group_id', $targetId)->pluck('acct_type')->unique()->values();
        if ($sourceTypes->merge($targetTypes)->unique()->count() > 1) {
            $reasons[] = 'The selected groups contain Heads from different account types.';
        }
        if ($duplicates->isNotEmpty()) $reasons[] = 'Existing duplicate account mappings need review before any merge.';
        if ($sourceHeadIds === []) $reasons[] = 'The source group has no Heads to merge.';
        return [
            'project_id' => $projectId,
            'source' => $source,
            'target' => $target,
            'source_heads' => $headLinks->where('account_group_id', $sourceId)->values(),
            'target_heads' => $headLinks->where('account_group_id', $targetId)->values(),
            'projects' => array_values($projects),
            'project_totals' => $global,
            'duplicates' => $duplicates,
            'reasons' => $reasons,
            'can_apply' => $reasons === [],
        ];
    }

    private function cents(mixed $amount): int
    {
        $value = (string) ($amount ?? '0');
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new \DomainException('ACCOUNT_GROUP_MERGE_INVALID_AMOUNT');
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $cents = (int) $whole * 100 + (int) str_pad($decimal, 2, '0');
        return $negative ? -$cents : $cents;
    }
}
