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
        $movements = [];
        if ($accounts->isNotEmpty()) {
            $legacy = DB::table('ledgers')
                ->whereIn('project_head_subheads_id', $accounts->keys())
                ->where('is_active', 1)->whereDate('date', '<=', $to)
                ->groupBy('project_head_subheads_id')
                ->get([
                    'project_head_subheads_id',
                    DB::raw("SUM(CASE WHEN date < '{$from}' THEN amount_in ELSE 0 END) AS previous_debit"),
                    DB::raw("SUM(CASE WHEN date < '{$from}' THEN amount_out ELSE 0 END) AS previous_credit"),
                    DB::raw("SUM(CASE WHEN date >= '{$from}' AND date <= '{$to}' THEN amount_in ELSE 0 END) AS period_debit"),
                    DB::raw("SUM(CASE WHEN date >= '{$from}' AND date <= '{$to}' THEN amount_out ELSE 0 END) AS period_credit"),
                ]);
            foreach ($legacy as $row) {
                $balances[(int) $row->project_head_subheads_id] = [
                    'previous_debit' => $this->cents($row->previous_debit),
                    'previous_credit' => $this->cents($row->previous_credit),
                    'period_debit' => $this->cents($row->period_debit),
                    'period_credit' => $this->cents($row->period_credit),
                ];
            }
            foreach (DB::table('ledgers')->whereIn('project_head_subheads_id', $accounts->keys())
                ->where('is_active', 1)->whereBetween('date', [$from, $to])
                ->orderBy('date')->orderBy('id')
                ->get(['id', 'project_head_subheads_id', 'date', 'type', 'voucher_number', 'detail', 'amount_in', 'amount_out']) as $row) {
                $movements[(int) $row->project_head_subheads_id][] = [
                    'date' => substr((string) $row->date, 0, 10),
                    'voucher' => trim((string) $row->type.'-'.(string) $row->voucher_number, '-'),
                    'detail' => (string) ($row->detail ?? ''),
                    'debit' => $this->cents($row->amount_in), 'credit' => $this->cents($row->amount_out),
                    'order' => 'L'.str_pad((string) $row->id, 20, '0', STR_PAD_LEFT),
                ];
            }
        }

        if (Schema::hasTable('finance_posting_lines') && Schema::hasTable('finance_posting_batches')) {
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
                if ($line['effective_date'] >= $from) {
                    $movements[$id][] = [
                        'date' => $line['effective_date'],
                        'voucher' => trim((string) $line['voucher_series'].'-'.(string) ($line['voucher_number'] ?? $line['posting_batch_id']), '-'),
                        'detail' => (string) ($line['description'] ?? ''),
                        'debit' => $this->cents($line['debit']), 'credit' => $this->cents($line['credit']),
                        'order' => 'V'.str_pad((string) $line['posting_line_id'], 20, '0', STR_PAD_LEFT),
                    ];
                }
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
            $entries = [];
            $running = $openingNet;
            if ($openingNet !== 0) {
                $entries[] = [
                    'date' => '', 'voucher' => '', 'detail' => 'Opening Balance',
                    'amounts' => [
                        'previous_debit' => max($openingNet, 0), 'previous_credit' => max(-$openingNet, 0),
                        'period_debit' => 0, 'period_credit' => 0,
                        'closing_debit' => max($openingNet, 0), 'closing_credit' => max(-$openingNet, 0),
                    ],
                ];
            }
            $accountMovements = $movements[(int) $id] ?? [];
            usort($accountMovements, fn ($a, $b) => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);
            foreach ($accountMovements as $movement) {
                $running += $movement['debit'] - $movement['credit'];
                $entries[] = [
                    'date' => $movement['date'], 'voucher' => $movement['voucher'], 'detail' => $movement['detail'],
                    'amounts' => [
                        'previous_debit' => 0, 'previous_credit' => 0,
                        'period_debit' => $movement['debit'], 'period_credit' => $movement['credit'],
                        'closing_debit' => max($running, 0), 'closing_credit' => max(-$running, 0),
                    ],
                ];
            }

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
                'entries' => $entries,
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
        foreach ($tree as $type) {
            foreach ($type['heads'] as $headId => $head) {
                foreach ($head['subheads'] as $subheadId => $subhead) {
                    foreach ($subhead['accounts'] as $account) {
                        foreach ($account['entries'] as $entry) {
                            $row = $account;
                            $row['entries'] = [$entry];
                            $rows[] = $row + [
                                'head_id' => (int) $headId, 'head_name' => $head['name'],
                                'subhead_id' => (int) $subheadId, 'subhead_name' => $subhead['name'],
                            ];
                        }
                    }
                }
            }
        }
        return $rows;
    }

    public function hierarchy(array $accounts): array
    {
        $heads = [];
        foreach ($accounts as $account) {
            $headId = $account['head_id']; $subheadId = $account['subhead_id'];
            $heads[$headId]['name'] = $account['head_name'];
            $heads[$headId]['subheads'][$subheadId]['name'] = $account['subhead_name'];
            $accountId = $account['id'];
            if (!isset($heads[$headId]['subheads'][$subheadId]['accounts'][$accountId])) {
                $heads[$headId]['subheads'][$subheadId]['accounts'][$accountId] = $account + ['entries' => []];
                $heads[$headId]['subheads'][$subheadId]['accounts'][$accountId]['entries'] = [];
            }
            array_push($heads[$headId]['subheads'][$subheadId]['accounts'][$accountId]['entries'], ...$account['entries']);
        }
        foreach ($heads as &$head) {
            foreach ($head['subheads'] as &$subhead) {
                foreach ($subhead['accounts'] as &$account) {
                    $account['amounts'] = $this->entryTotals($account['entries']);
                }
                unset($account);
                $subhead['accounts'] = array_values($subhead['accounts']);
                $subhead['totals'] = $this->totals($subhead['accounts']);
            }
            unset($subhead);
            $head['totals'] = $this->totals($head['subheads']);
        }
        unset($head);
        return ['heads' => $heads, 'totals' => $this->totals($heads)];
    }

    private function entryTotals(array $entries): array
    {
        $totals = array_fill_keys(['previous_debit', 'previous_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'], 0);
        foreach ($entries as $entry) {
            foreach (['previous_debit', 'previous_credit', 'period_debit', 'period_credit'] as $key) {
                $totals[$key] += (int) $entry['amounts'][$key];
            }
            $totals['closing_debit'] = (int) $entry['amounts']['closing_debit'];
            $totals['closing_credit'] = (int) $entry['amounts']['closing_credit'];
        }
        return $totals;
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
