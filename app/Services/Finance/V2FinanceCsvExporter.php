<?php

namespace App\Services\Finance;

/**
 * Pure CSV representation of previously authorized V2 report data.
 * Uses fixed columns, safely quotes all fields, and prevents spreadsheet
 * formula execution from any unexpected source strings.
 */
final class V2FinanceCsvExporter
{
    private function safe(mixed $value): string
    {
        $s = (string) $value;
        if (preg_match('/^\s*[=+@-]/u', $s) && !preg_match('/^-?\d+(?:\.\d+)?$/D', $s)) {
            return "'" . $s;
        }
        return $s;
    }

    private function table(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('V2_CSV_STREAM_UNAVAILABLE');
        }
        try {
            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                if (count($row) !== count($headers)) {
                    throw new \DomainException('V2_CSV_COLUMN_CONTRACT_INVALID');
                }
                fputcsv($stream, array_map(fn ($value): string => $this->safe($value), $row));
            }
            rewind($stream);
            $out = stream_get_contents($stream);
            if ($out === false) {
                throw new \RuntimeException('V2_CSV_STREAM_UNREADABLE');
            }
            return $out;
        } finally {
            fclose($stream);
        }
    }

    public function trialBalance(array $report): string
    {
        if (($report['scope'] ?? '') !== 'typed_v2_postings_only') {
            throw new \DomainException('V2_CSV_WRONG_REPORT_SCOPE');
        }
        $rows = [];
        foreach ($report['accounts'] as $account) {
            $rows[] = [
                $report['project_id'], $report['from'] ?? '',
                $report['through'] ?? '', $account['account_id'],
                $account['opening_balance'], $account['period_debit'],
                $account['period_credit'], $account['closing_balance'],
            ];
        }
        return $this->table([
            'project_id', 'from', 'through', 'account_id',
            'opening_balance', 'period_debit',
            'period_credit', 'closing_balance',
        ], $rows);
    }

    public function accountStatement(array $report): string
    {
        if (($report['scope'] ?? '') !== 'typed_v2_postings_only') {
            throw new \DomainException('V2_CSV_WRONG_REPORT_SCOPE');
        }
        $rows = [];
        foreach ($report['lines'] as $line) {
            $rows[] = [
                $report['project_id'], $report['account_id'],
                $line['effective_date'], $line['posting_batch_id'],
                $line['posting_line_id'], $line['source_kind'],
                $line['source_id'], $line['is_reversal'] ? '1' : '0',
                $line['debit'], $line['credit'], $line['running_balance'],
            ];
        }
        return $this->table([
            'project_id', 'account_id', 'date', 'posting_batch_id',
            'posting_line_id', 'source_kind', 'source_id',
            'is_reversal', 'debit', 'credit', 'running_balance',
        ], $rows);
    }
    /** Approved, typed V2 register for JV/CV/cash/booking/labour/stock. */
    public function voucherRegister(array $documents): string
    {
        $rows = [];
        foreach ($documents as $document) {
            $rows[] = [
                $document['posting_batch_id'],
                $document['source_kind'],
                $document['source_id'],
                $document['source_reference'],
                $document['voucher_series'],
                $document['effective_date'],
                $document['is_reversal'] ? '1' : '0',
                $document['reversal_of_batch_id'] ?? '',
                $document['line_count'],
                $document['total_debit'],
                $document['total_credit'],
            ];
        }
        return $this->table([
            'posting_batch_id', 'source_kind', 'source_id',
            'source_reference', 'voucher_series', 'effective_date',
            'is_reversal', 'reversal_of_batch_id', 'line_count',
            'total_debit', 'total_credit',
        ], $rows);
    }

}
