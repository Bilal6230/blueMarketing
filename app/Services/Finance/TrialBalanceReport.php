<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TrialBalanceReport
{
    public function build(int $projectId, string $from, string $to): array
    {
        $accounts = DB::table('project_head_subheads as account')
            ->join('head_accountings as head', 'head.id', '=', 'account.head_accounting_id')
            ->join('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')
            ->leftJoin('account_types as account_type', 'account_type.id', '=', 'head.acct_type')
            ->leftJoin('leads as customer', 'customer.id', '=', 'account.customer_id')
            ->leftJoin('plots as plot', 'plot.id', '=', 'account.plot_id')
            ->where('account.project_id', $projectId)
            ->get([
                'account.id', 'head.id as head_id', 'head.name as head_name',
                'sub.id as subhead_id', 'sub.name as subhead_name',
                'account_type.id as type_id', 'account_type.name as type_name',
                'customer.first_name', 'customer.last_name', 'customer.business',
                'plot.name as plot_name',
            ])->keyBy('id');

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
            $tree[$typeKey]['heads'][$headKey]['subheads'][$subKey]['name'] = $account->subhead_name;
            $tree[$typeKey]['heads'][$headKey]['subheads'][$subKey]['accounts'][] = [
                'id' => (int) $id,
                'name' => $this->accountName($account),
                'amounts' => $amounts,
            ];
        }

        ksort($tree);
        foreach ($tree as &$type) {
            ksort($type['heads']);
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
            $types[$typeId]['heads'][$headId]['accounts'][] = $account;
        }
        foreach ($types as &$type) {
            foreach ($type['heads'] as &$head) {
                $head['totals'] = $this->totals($head['accounts']);
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
