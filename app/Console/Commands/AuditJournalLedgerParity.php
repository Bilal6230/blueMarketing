<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AuditJournalLedgerParity extends Command
{
    protected $signature = 'finance:audit-jv-ledger-parity {--database= : Explicit DB name} {--id= : Optional journal_vouchers.id} {--limit=25 : Maximum detailed examples per category}';
    protected $description = 'Read-only account/amount multiset reconciliation of JV details against ledger legs, scoped by project and voucher number';

    public function handle(): int
    {
        $db = (string) DB::connection()->getDatabaseName();
        if ($this->option('database') !== $db || $db === '') {
            $this->error('Specify --database='.$db.' explicitly.');
            return self::INVALID;
        }
        $id = $this->option('id');
        if ($id !== null && (!ctype_digit((string)$id) || (int)$id < 1)) {
            $this->error('--id requires positive JV header ID');
            return self::INVALID;
        }
        $limit = max(1, min(500, (int) $this->option('limit')));
        $result = [
            'database' => $db,
            'mode' => 'read_only',
            'scope' => $id === null ? 'all_active_jv_headers' : 'jv_header_'.$id,
            'headers_checked' => 0, 'balanced_headers' => 0,
            'headers_with_missing_legs' => 0, 'headers_with_extra_legs' => 0,
            'missing_legs' => 0, 'extra_legs' => 0,
            'missing_debit_cents' => 0, 'missing_credit_cents' => 0,
            'extra_debit_cents' => 0, 'extra_credit_cents' => 0,
            'examples' => []
        ];
        $headers = DB::table('journal_vouchers')
            ->where('type', 'JV')->whereNull('deleted_at')
            ->when($id !== null, fn ($q) => $q->where('id', (int) $id))
            ->orderBy('id')->get();
        foreach ($headers as $h) {
            $result['headers_checked']++;
            $details = DB::table('journal_voucher_details')->where('journal_voucher_id', $h->id)
                ->whereNull('deleted_at')->orderBy('id')
                ->get(['id','account_id','debit','credit','created_at']);
            $ledger = DB::table('ledgers as l')
                ->join('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
                ->where('a.project_id', $h->project_id)
                ->where('l.type', 'JV')->where('l.type_id', $h->id)
                ->where('l.voucher_number', $h->voucher_number)
                ->orderBy('l.id')
                ->get(['l.id','l.project_head_subheads_id','l.amount_in','l.amount_out','l.is_active','l.date','l.created_at']);
            $keys = [];
            foreach ($ledger as $row) {
                $key = $this->key($row->project_head_subheads_id, $row->amount_in, $row->amount_out);
                $keys[$key][] = $row;
            }
            $missing = []; $extra = [];
            foreach ($details as $d) {
                $key = $this->key($d->account_id, $d->debit, $d->credit);
                if (!empty($keys[$key])) {
                    array_shift($keys[$key]);
                } else {
                    $missing[] = ['detail_id' => $d->id, 'account_id' => $d->account_id,
                        'debit' => $d->debit, 'credit' => $d->credit];
                    $result['missing_debit_cents'] += $this->cents($d->debit);
                    $result['missing_credit_cents'] += $this->cents($d->credit);
                }
            }
            foreach ($keys as $rows) {
                foreach ($rows as $row) {
                    $extra[] = ['ledger_id' => $row->id, 'account_id' => $row->project_head_subheads_id,
                        'debit' => $row->amount_in, 'credit' => $row->amount_out,
                        'is_active' => $row->is_active, 'date' => $row->date,
                        'created_at' => $row->created_at];
                    $result['extra_debit_cents'] += $this->cents($row->amount_in);
                    $result['extra_credit_cents'] += $this->cents($row->amount_out);
                }
            }
            if ($missing) $result['headers_with_missing_legs']++;
            if ($extra) $result['headers_with_extra_legs']++;
            if (!$missing && !$extra) $result['balanced_headers']++;
            $result['missing_legs'] += count($missing);
            $result['extra_legs'] += count($extra);
            if (($missing || $extra) && count($result['examples']) < $limit) {
                $result['examples'][] = ['jv_id' => $h->id,
                    'project_id' => $h->project_id, 'voucher_number' => $h->voucher_number,
                    'detail_count' => $details->count(), 'ledger_count' => $ledger->count(),
                    'missing' => $missing, 'extra' => $extra];
            }
        }
        // Further cross-project or different-number rows with the same polymorphic type_id
        // are NOT classified as journal-owned extras: they may belong to stock/labour.
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        return self::SUCCESS;
    }

    private function key($account, $debit, $credit): string
    {
        return (string)$account . ':' . $this->cents($debit) . ':' . $this->cents($credit);
    }

    private function cents($value): int
    {
        return (int) round((float)$value * 100);
    }
}
