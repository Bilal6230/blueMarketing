<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TrialBalanceReport
{
    public function build(int $projectId, string $from, string $to): array
    {
        $hasGroups = Schema::hasTable('account_groups') && Schema::hasTable('project_head_account_groups');
        $accountQuery = DB::table('project_head_subheads as account')
            ->join('head_accountings as head', 'head.id', '=', 'account.head_accounting_id')
            ->join('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')
            ->leftJoin('account_types as account_type', 'account_type.id', '=', 'head.acct_type')
            ->leftJoin('leads as customer', 'customer.id', '=', 'account.customer_id')
            ->leftJoin('plots as plot', 'plot.id', '=', 'account.plot_id')
            ->where('account.project_id', $projectId);
        if ($hasGroups) {
            $accountQuery->leftJoin('project_head_account_groups as head_group', function ($join) {
                $join->on('head_group.head_accounting_id', '=', 'head.id')
                    ->on('head_group.project_id', '=', 'account.project_id');
            });
            $accountQuery->leftJoin('account_groups as account_group', 'account_group.id', '=', 'head_group.account_group_id');
        }
        $accountSelect = [
                'account.id', 'head.id as head_id', 'head.name as head_name',
                'sub.id as subhead_id', 'sub.name as subhead_name',
                'account_type.id as type_id', 'account_type.name as type_name',
                'customer.first_name', 'customer.last_name', 'customer.business',
                'plot.name as plot_name',
            ];
        if ($hasGroups) {
            $accountSelect[] = 'head_group.account_group_id';
            $accountSelect[] = 'account_group.name as account_group_name';
        }
        $accounts = $accountQuery->get($accountSelect)->keyBy('id');
        $groupsById = $hasGroups ? DB::table('account_groups')->get(['id', 'parent_id', 'name'])->keyBy('id') : collect();
        foreach ($accounts as $account) {
            $account->group_path = $this->groupPath($account, $groupsById);
        }

        $balances = [];
        if ($accounts->isNotEmpty()) {
            $legacy = DB::table('ledgers')
                ->whereIn('project_head_subheads_id', $accounts->keys())
                ->where('is_active', 1)->whereDate('date', '<=', $to)
                ->groupBy('project_head_subheads_id')
                ->get([
                    'project_head_subheads_id',
                    DB::raw("SUM(CASE WHEN date < '{$from}' THEN amount_out ELSE 0 END) AS previous_debit"),
                    DB::raw("SUM(CASE WHEN date < '{$from}' THEN amount_in ELSE 0 END) AS previous_credit"),
                    DB::raw("SUM(CASE WHEN date >= '{$from}' AND date <= '{$to}' THEN amount_out ELSE 0 END) AS period_debit"),
                    DB::raw("SUM(CASE WHEN date >= '{$from}' AND date <= '{$to}' THEN amount_in ELSE 0 END) AS period_credit"),
                ]);
            foreach ($legacy as $row) {
                $balances[(int) $row->project_head_subheads_id] = [
                    'previous_debit' => $this->cents($row->previous_debit),
                    'previous_credit' => $this->cents($row->previous_credit),
                    'period_debit' => $this->cents($row->period_debit),
                    'period_credit' => $this->cents($row->period_credit),
                ];
            }

        }

        // V2 posting tables are an isolated local/testing pilot. Production
        // reports must continue from the authoritative legacy ledgers and
        // must not invoke the deliberately production-blocked V2 reader.
        if (V2IsolatedConnectionGuard::isAllowed()
            && Schema::hasTable('finance_posting_lines')
            && Schema::hasTable('finance_posting_batches')) {
            foreach ((new V2PostingGateway())->postingLines($projectId, null, $to) as $line) {
                if ($line['legacy_ledger_id'] !== null) {
                    continue;
                }
                $id = (int) $line['project_head_subhead_id'];
                if (!$accounts->has($id)) {
                    throw new \DomainException('TRIAL_BALANCE_ACCOUNT_NOT_IN_PROJECT');
                }
                $balances[$id] ??= array_fill_keys([
                    'previous_debit', 'previous_credit', 'period_debit', 'period_credit'
                ], 0);
                $prefix = $line['effective_date'] < $from ? 'previous_' : 'period_';
                $balances[$id][$prefix.'debit'] += $this->cents($line['debit']);
                $balances[$id][$prefix.'credit'] += $this->cents($line['credit']);

            }
        }

        $tree = [];
        foreach ($accounts as $id => $account) {
            $amounts = $balances[(int) $id] ?? array_fill_keys([
                'previous_debit', 'previous_credit', 'period_debit', 'period_credit'
            ], 0);
            if (array_sum($amounts) === 0) {
                continue;
            }
            $openingNet = $amounts['previous_debit'] - $amounts['previous_credit'];
            $amounts['previous_debit'] = max($openingNet, 0);
            $amounts['previous_credit'] = max(-$openingNet, 0);
            $net = $openingNet + $amounts['period_debit'] - $amounts['period_credit'];
            $amounts['closing_debit'] = max($net, 0);
            $amounts['closing_credit'] = max(-$net, 0);
            $typeKey = (int) ($account->type_id ?? 0);
            $headKey = (int) $account->head_id;
            $subKey = (int) $account->subhead_id;
            $tree[$typeKey]['name'] = $account->type_name ?: 'Unclassified';
            $tree[$typeKey]['heads'][$headKey]['name'] = $account->head_name;
            $tree[$typeKey]['heads'][$headKey]['group_path'] = $account->group_path;
            $tree[$typeKey]['heads'][$headKey]['group_key'] = isset($account->account_group_id) ? (string) $account->account_group_id : null;
            $tree[$typeKey]['heads'][$headKey]['subheads'][$subKey]['name'] = $account->subhead_name;
            $tree[$typeKey]['heads'][$headKey]['subheads'][$subKey]['accounts'][] = [
                'id' => (int) $id,
                'name' => $this->accountName($account),
                'group_path' => $account->group_path,
                'amounts' => $amounts,
            ];
        }

        ksort($tree);
        foreach ($tree as &$type) {
            uasort($type['heads'], function ($a, $b) {
                if (($a['group_path'] ?? null) === ($b['group_path'] ?? null)) {
                    return strnatcasecmp($a['name'], $b['name']);
                }
                if (($a['group_path'] ?? null) === null) return 1;
                if (($b['group_path'] ?? null) === null) return -1;
                return strnatcasecmp($a['group_path'], $b['group_path']);
            });
            foreach ($type['heads'] as &$head) {
                ksort($head['subheads']);
                foreach ($head['subheads'] as &$subhead) {
                    usort($subhead['accounts'], fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
                    $subhead['totals'] = $this->totals($subhead['accounts']);
                }
                unset($subhead);
                $head['totals'] = $this->totals($head['subheads']);
            }
            unset($head);
            $groupTotals = [];
            foreach ($type['heads'] as $head) {
                if ($head['group_key'] !== null) {
                    $groupTotals[$head['group_key']] ??= array_fill_keys(array_keys($head['totals']), 0);
                    foreach ($head['totals'] as $key => $value) {
                        $groupTotals[$head['group_key']][$key] += $value;
                    }
                }
            }
            foreach ($type['heads'] as &$head) {
                $head['group_totals'] = $head['group_key'] !== null ? $groupTotals[$head['group_key']] : null;
            }
            unset($head);
            $type['totals'] = $this->totals($type['heads']);
        }
        unset($type);

        return ['tree' => $tree, 'totals' => $this->totals($tree)];
    }

    public function flatten(array $tree): array
    {
        $rows = [];
        foreach ($tree as $typeId => $type) {
            foreach ($type['heads'] as $headId => $head) {
                foreach ($head['subheads'] as $subheadId => $subhead) {
                    foreach ($subhead['accounts'] as $account) {
                        $rows[] = $account + [
                            'type_id' => (int) $typeId, 'type_name' => $type['name'],
                            'head_id' => (int) $headId, 'head_name' => $head['name'],
                            'group_path' => $head['group_path'] ?? null,
                            'group_key' => $head['group_key'] ?? null,
                            'subhead_id' => (int) $subheadId, 'subhead_name' => $subhead['name'],
                        ];
                    }
                }
            }
        }
        return $rows;
    }

    public function hierarchy(array $accounts): array
    {
        $types = [];
        foreach ($accounts as $account) {
            $typeId = $account['type_id'];
            $headId = $account['head_id'];
            $types[$typeId]['name'] = $account['type_name'];
            $types[$typeId]['heads'][$headId]['name'] = $account['head_name'];
            $types[$typeId]['heads'][$headId]['group_path'] = $account['group_path'] ?? null;
            $types[$typeId]['heads'][$headId]['group_key'] = $account['group_key'] ?? null;
            $types[$typeId]['heads'][$headId]['accounts'][] = $account;
        }
        foreach ($types as &$type) {
            uasort($type['heads'], function ($a, $b) {
                if (($a['group_path'] ?? null) === ($b['group_path'] ?? null)) {
                    return strnatcasecmp($a['name'], $b['name']);
                }
                if (($a['group_path'] ?? null) === null) return 1;
                if (($b['group_path'] ?? null) === null) return -1;
                return strnatcasecmp($a['group_path'], $b['group_path']);
            });
            foreach ($type['heads'] as &$head) {
                $head['totals'] = $this->totals($head['accounts']);
            }
            unset($head);
            $groupTotals = [];
            foreach ($type['heads'] as $head) {
                if ($head['group_key'] !== null) {
                    $groupTotals[$head['group_key']] ??= array_fill_keys(array_keys($head['totals']), 0);
                    foreach ($head['totals'] as $key => $value) {
                        $groupTotals[$head['group_key']][$key] += $value;
                    }
                }
            }
            foreach ($type['heads'] as &$head) {
                $head['group_totals'] = $head['group_key'] !== null ? $groupTotals[$head['group_key']] : null;
            }
            unset($head);
            $type['totals'] = $this->totals($type['heads']);
        }
        unset($type);
        return ['types' => $types, 'totals' => $this->totals($types)];
    }

    private function accountName(object $account): string
    {
        $person = trim(implode(' ', array_filter([$account->first_name, $account->last_name])));
        return $person ?: ($account->business ?: ($account->plot_name ?: $account->subhead_name));
    }

    private function groupPath(object $account, $groupsById): ?string
    {
        $groupId = (int) ($account->account_group_id ?? 0);
        if ($groupId < 1 || !$groupsById->has($groupId)) {
            return null;
        }

        $names = [];
        $seen = [];
        while ($groupId > 0 && $groupsById->has($groupId) && !isset($seen[$groupId])) {
            $seen[$groupId] = true;
            $group = $groupsById->get($groupId);
            $names[] = (string) $group->name;
            $groupId = (int) ($group->parent_id ?? 0);
        }

        return implode(' / ', array_reverse($names));
    }

    private function totals(array $items): array
    {
        $totals = array_fill_keys(['previous_debit', 'previous_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'], 0);
        foreach ($items as $item) {
            $values = $item['amounts'] ?? $item['totals'];
            foreach ($totals as $key => $_) {
                $totals[$key] += (int) ($values[$key] ?? 0);
            }
        }
        return $totals;
    }

    private function cents(mixed $amount): int
    {
        $value = (string) ($amount ?? '0');
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value, $match)) {
            throw new \DomainException('TRIAL_BALANCE_INVALID_AMOUNT');
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
        return $negative ? -$cents : $cents;
    }
}
