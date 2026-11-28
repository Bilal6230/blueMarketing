<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherDisplayIdentity
{
    public function lookup(int $projectId, string $sourceKind, int $sourceId): ?string
    {
        if ($projectId < 1 || $sourceId < 1 || !Schema::hasTable('project_voucher_display_numbers')) return null;
        $identity = DB::table('project_voucher_display_numbers')->where('project_id', $projectId)
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->first(['series','display_number']);
        // Issued display numbers never change when the active prefix changes.
        return $identity ? (string)$identity->display_number : null;
    }

    public function series(int $projectId, string $sourceKind, int $sourceId): ?string
    {
        if ($projectId < 1 || $sourceId < 1 || !Schema::hasTable('project_voucher_display_numbers')) return null;
        return DB::table('project_voucher_display_numbers')->where('project_id', $projectId)
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->value('series') ?: null;
    }

    public function assign(int $projectId, string $sourceKind, int $sourceId, string $series, string $date, ?int $bankSequence = null): ?string
    {
        if (DB::transactionLevel() < 1) throw new \DomainException('VOUCHER_DISPLAY_REQUIRES_TRANSACTION');
        if (!in_array($sourceKind, ['customer_ledger', 'journal_voucher', 'commission_voucher', 'booking_voucher', 'ledger'], true)
            || $sourceId < 1 || !in_array($series, ProjectVoucherDisplay::SERIES, true)) {
            throw new \DomainException('INVALID_VOUCHER_DISPLAY_SOURCE');
        }
        if (!Schema::hasTable('project_voucher_display_numbers')) return null;

        $existing = DB::table('project_voucher_display_numbers')->where('project_id', $projectId)
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->series !== $series) throw new \DomainException('VOUCHER_DISPLAY_SOURCE_CONFLICT');
            return (string) $existing->display_number;
        }

        if ($sourceKind === 'customer_ledger' && in_array($series, ['CR','DR','CP','CCR','CDR','BOR','BOP'], true)) {
                $source = DB::table('customer_ledger')->where('id',$sourceId)
                    ->where('project_id',$projectId)->lockForUpdate()->first(['transaction_type','type_id']);
                if (!$source || (int)$source->type_id<1
                    || !((string)$source->transaction_type===$series
                        || ($series==='DR' && (string)$source->transaction_type==='CP'))) {
                    throw new \DomainException('VOUCHER_SOURCE_IDENTITY_MISMATCH');
                }
            }
            return app(ProjectVoucherPrefixCounter::class)
                ->issue($projectId,$series,$sourceKind,$sourceId,$date,$display);
        }

        if ($sourceKind === 'customer_ledger' && in_array($series, ['CR','DR','CP','CCR','CDR'], true)) {
            $source = DB::table('customer_ledger')->where('id', $sourceId)
                ->where('project_id', $projectId)->lockForUpdate()
                ->first(['transaction_type','type_id']);
            // Only old CP rows may be presented as DR. New sources must
            // retain their actual, independent type and serial.
            $matches = $source && (
                (string)$source->transaction_type === $series
                || ($series === 'DR' && (string)$source->transaction_type === 'CP')
            );
            if (!$matches || (int)$source->type_id <= 0) {
                throw new \DomainException('VOUCHER_SOURCE_IDENTITY_MISMATCH');
            }
            $display = $series.'-'.(int)$source->type_id;
        } elseif ($sourceKind === 'customer_ledger' && in_array($series, ['BOR','BOP'], true) && $bankSequence !== null) {
            // This is a real, retained BOR/BOP display-series identity, separate
            // from the legacy CR/CP accounting type and number. The caller has
            // already reserved the bank-series number inside its project lock.
            if ($bankSequence === null || $bankSequence < 1) {
                throw new \DomainException('BANK_VOUCHER_SEQUENCE_REQUIRED');
            }
            $source = DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->lockForUpdate()
                ->first(['transaction_type','type_id']);
            if (!$source || (string)$source->transaction_type !== $series
                || (int)$source->type_id !== $bankSequence) {
                throw new \DomainException('BANK_VOUCHER_SOURCE_SERIES_MISMATCH');
            }
            $display = $series . '-' . $bankSequence;
            $alreadyUsed = DB::table('project_voucher_display_numbers')
                ->where('project_id', $projectId)->where('source_kind', 'customer_ledger')
                ->where('series', $series)->where('display_number', $display)
                ->lockForUpdate()->exists();
            if ($alreadyUsed) throw new \DomainException('BANK_VOUCHER_NUMBER_ALREADY_RESERVED');
        } else {
            [$table,$column] = match ($sourceKind) {
                'journal_voucher' => ['journal_vouchers','voucher_number'],
                'commission_voucher' => ['commision_vouchers','voucher_number'],
                'booking_voucher' => ['booking_vouchers','voucher_number'],
                'customer_ledger' => ['customer_ledger','type_id'],
                default => ['ledgers','voucher_number'],
            };
            $query = DB::table($table)->where('id',$sourceId);
            if ($sourceKind !== 'ledger') $query->where('project_id',$projectId);
            $raw = (string)$query->value($column);
            if (!preg_match('/^(?:'.preg_quote($series,'/').'-)?([0-9]+)$/D',$raw,$match) || (int)$match[1]<1)
                throw new \DomainException('VOUCHER_SOURCE_NUMBER_INVALID');
            $display = match ($series) {
                'JV' => 'JV-'.str_pad((string)(int)$match[1],7,'0',STR_PAD_LEFT),
                'CV' => CommissionVoucherNumber::display((int)$match[1]),
                default => $series.'-'.(int)$match[1],
            };
        }
        // First verify the original source and canonical number using all of
        // the legacy integrity branches above, then issue a separate display ID.
        if (Schema::hasTable('project_voucher_prefix_counters')) {
            return app(ProjectVoucherPrefixCounter::class)
                ->issue($projectId,$series,$sourceKind,$sourceId,$date);
        }
        DB::table('project_voucher_display_numbers')->insert([
            'project_id' => $projectId, 'source_kind' => $sourceKind, 'source_id' => $sourceId,
            'series' => $series, 'financial_year' => (int) substr($date, 0, 4),
            'display_number' => $display, 'created_at' => now(),
        ]);
        return $display;
    }
}
