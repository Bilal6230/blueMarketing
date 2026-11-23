<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared locked, read-only bank custody verification for all seven voucher
 * families. This service does NOT reserve a balance or create a voucher:
 * callers must write their typed source claim inside the SAME transaction,
 * under the same project lock, before advertising a successful posting.
 */
final class VerifiedBankInstrument
{
    public const DIRECTION_BY_SERIES = [
        'CR' => 'receipt', 'DR' => 'payment',
        'BOR' => 'receipt', 'BOP' => 'payment',
        'BD' => 'receipt', 'BW' => 'payment',
        'PPR' => 'receipt',
    ];

    public static function direction(string $series): string
    {
        return self::DIRECTION_BY_SERIES[$series]
            ?? throw new \DomainException('Unsupported verified bank voucher series.');
    }

    private static function cents(mixed $amount): int
    {
        $value = trim((string) $amount);
        if (!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) {
            throw new \DomainException('Bank source amount has an invalid decimal value.');
        }
        return (int)$parts[1]*100
            + (int)str_pad($parts[2]??'', 2, '0');
    }

    /**
     * @return array{source_id:int,method:string,series:string,direction:string,
     *   reference:string,passing_date:string,account_mapping_id:int,
     *   amount:string,remaining_amount:string}
     */
    public function inspectLocked(int $projectId, string $series, string $method,
        int $sourceId, string $requestedAmount): array
    {
        $direction = self::direction($series);
        if ($projectId < 1 || $sourceId < 1 || !in_array($method, ['online','cheque'], true)) {
            throw new \DomainException('Invalid verified bank instrument identity.');
        }
        if (DB::transactionLevel() < 1) {
            throw new \DomainException('Verified bank source must be selected within its posting transaction.');
        }
        foreach (['projects','project_head_subheads','finance_bank_voucher_allocations',
            'finance_bank_voucher_allocation_releases'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \DomainException('Required bank custody storage is missing.');
            }
        }
        $table = $method === 'online' ? 'finance_online_intakes' : 'finance_cheque_intakes';
        $events = $method === 'online' ? 'finance_online_confirmation_events'
            : 'finance_cheque_intake_actions';
        $claims = $method === 'online' ? 'finance_online_voucher_claims'
            : 'finance_cheque_voucher_claims';
        $claimKey = $method === 'online' ? 'online_intake_id' : 'cheque_intake_id';
        foreach ([$table,$events,$claims] as $required) {
            if (!Schema::hasTable($required)) {
                throw new \DomainException('Verified bank management source migrations are missing.');
            }
        }
        DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
            ?: throw new \DomainException('Bank source project does not exist.');
        $source = DB::table($table)->where('project_id',$projectId)->where('id',$sourceId)
            ->lockForUpdate()->first();
        if (!$source) {
            throw new \DomainException('Bank source does not belong to the selected project.');
        }
        if (Schema::hasTable('finance_bank_correction_requests')
            && DB::table('finance_bank_correction_requests')
                ->where('project_id',$projectId)->where('source_method',$method)
                ->where('source_id',$sourceId)->where('status','pending')->exists()) {
            throw new \DomainException('Bank source correction is pending approval.');
        }
        if (DB::table($claims)->where('project_id',$projectId)
            ->where($claimKey,$sourceId)->exists()) {
            throw new \DomainException('Bank source has an existing legacy full claim.');
        }

        if ($method === 'online') {
            $evidence = DB::table($events)->where('project_id',$projectId)
                ->where('online_intake_id',$sourceId)
                ->where('from_status','pending')->where('to_status','confirmed')
                ->where('transaction_type',$direction)
                ->orderByDesc('id')->first();
            if ($source->status !== 'confirmed' || $source->type !== $direction
                || !$evidence
                || !BankSourceCorrectionEvidence::matchesOnlineAmount(
                    $projectId,$sourceId,$evidence->original_amount,$source->amount)) {
                throw new \DomainException('Online transaction is not verified for this direction.');
            }
            $reference = (string) $source->reference;
            $passingDate = substr((string)$evidence->verification_date,0,10);
        } else {
            $evidence = DB::table($events)->where('project_id',$projectId)
                ->where('cheque_intake_id',$sourceId)->where('action','cleared')
                ->whereNotNull('verification_confirmed_at')
                ->orderByDesc('id')->first();
            if ($source->status !== 'cleared'
                || $source->direction !== ($direction === 'receipt' ? 'in' : 'out')
                || !$evidence) {
                throw new \DomainException('Cheque is not cleared for this direction.');
            }
            $reference = (string) $source->cheque_number;
            $passingDate = substr((string)$evidence->action_date,0,10);
        }
        $mapping=(int)$source->account_mapping_id;
        if (!$mapping || !DB::table('project_head_subheads')
            ->where('project_id',$projectId)->where('id',$mapping)->exists()) {
            throw new \DomainException('Verified bank source account mapping is invalid.');
        }
        $total=self::cents($source->amount);
        $requested=self::cents($requestedAmount);
        $allocated=DB::table('finance_bank_voucher_allocations as a')
            ->where('a.project_id',$projectId)->where('a.source_method',$method)
            ->where('a.source_id',$sourceId)
            ->whereNotExists(function($query){
                $query->selectRaw('1')->from('finance_bank_voucher_allocation_releases as r')
                    ->whereColumn('r.allocation_id','a.id');
            })->sum('a.amount');
        $remaining=$total-self::cents($allocated);
        if ($requested<1 || $remaining<$requested) {
            throw new \DomainException('Requested amount exceeds remaining verified bank balance.');
        }
        return [
            'source_id'=>$sourceId,'method'=>$method,'series'=>$series,
            'direction'=>$direction,'reference'=>$reference,'passing_date'=>$passingDate,
            'account_mapping_id'=>$mapping,
            'amount'=>number_format($total/100,2,'.',''),
            'remaining_amount'=>number_format($remaining/100,2,'.',''),
        ];
    }
}
