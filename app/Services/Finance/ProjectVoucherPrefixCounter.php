<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issued display identities are immutable. Underlying source number, posting,
 * approval and ledger identities remain independent.
 */
final class ProjectVoucherPrefixCounter
{
    public function currentPrefix(int $projectId, string $series): string
    {
        if (!in_array($series, ProjectVoucherDisplay::SERIES, true)) {
            throw new \DomainException('INVALID_DISPLAY_SERIES');
        }
        $default = $series === 'CP' ? 'DR' : $series;
        if (!Schema::hasTable('project_voucher_display_settings')) return $default;
        return (string)(DB::table('project_voucher_display_settings')
            ->where('project_id',$projectId)->where('series',$series)
            ->value('prefix') ?: $default);
    }

    private function historicalMax(int $projectId, string $prefix): int
    {
        if (!Schema::hasTable('project_voucher_display_numbers')) return 0;
        $max = 0;
        foreach (DB::table('project_voucher_display_numbers')
            ->where('project_id',$projectId)
            ->where('display_number','like',$prefix.'-%')
            ->pluck('display_number') as $display) {
            if (preg_match('/^'.preg_quote($prefix,'/').'-([1-9][0-9]*)$/D',(string)$display,$m)) {
                $max = max($max,(int)$m[1]);
            }
        }
        return $max;
    }

    private function baseline(int $projectId, string $series, string $prefix): int
    {
        $historical = $this->historicalMax($projectId,$prefix);
        if ($prefix === $series || ($series === 'DR' && $prefix === 'DR')) {
            // Imported canonical numbers may not have display identity rows.
            $original = app(ProjectVoucherAllocator::class)->preview($projectId,$series);
            if ($series === 'DR' && Schema::hasTable('customer_ledger')) {
                $original = max($original,1+(int)DB::table('customer_ledger')
                    ->where('project_id',$projectId)->where('transaction_type','CP')->max('type_id'));
            }
            return max(1,$historical+1,$original);
        }
        return max(1,$historical+1);
    }

    /**
     * Read-only status for the Settings AJAX editor.
     * Existing means issued display numbers or legacy canonical numbers,
     * not merely that an unused counter was configured.
     */
    public function status(int $projectId, string $series, string $prefix): array
    {
        $this->validateScope($projectId,$series,$prefix);
        if (!Schema::hasTable('project_voucher_prefix_counters')) {
            throw new \DomainException('VOUCHER_PREFIX_MIGRATION_REQUIRED');
        }
        $row=DB::table('project_voucher_prefix_counters')
            ->where('project_id',$projectId)->where('series',$series)
            ->where('prefix',$prefix)->first();
        $next=max((int)($row->next_number??1),$this->baseline($projectId,$series,$prefix));
        $exists=($row && (int)$row->issued_count>0)
            || $this->historicalMax($projectId,$prefix)>0
            || ($prefix===$series && $this->baseline($projectId,$series,$prefix)>1)
            || ($series==='DR' && $prefix==='DR' && $this->baseline($projectId,$series,$prefix)>1);
        // Legacy CP and current DR are one voucher family.
        $aliases=in_array($series,['CP','DR'],true)?['CP','DR']:[$series];
        $conflict=DB::table('project_voucher_prefix_counters')
            ->where('project_id',$projectId)->where('prefix',$prefix)
            ->whereNotIn('series',$aliases)->exists();
        if (Schema::hasTable('project_voucher_display_numbers')) {
            $conflict=$conflict||DB::table('project_voucher_display_numbers')
                ->where('project_id',$projectId)->whereNotIn('series',$aliases)
                ->where('display_number','like',$prefix.'-%')->exists();
        }
        if (Schema::hasTable('project_voucher_display_settings')) {
            $conflict=$conflict||DB::table('project_voucher_display_settings')
                ->where('project_id',$projectId)->where('prefix',$prefix)
                ->where('series','!=',$series)->exists();
        }
        return ['prefix'=>$prefix,'next_number'=>$next,'existing'=>(bool)$exists,
            'conflict'=>(bool)$conflict];
    }

    private function validateScope(int $projectId, string $series, string $prefix): void
    {
        if ($projectId<1 || !in_array($series,ProjectVoucherDisplay::SERIES,true)
            || !preg_match('/^[A-Z][A-Z0-9]{1,11}$/D',$prefix)) {
            throw new \DomainException('INVALID_VOUCHER_PREFIX_SCOPE');
        }
    }

    /** Caller must hold the project row lock in the same transaction. */
    public function configure(int $projectId,string $series,string $prefix,?int $start): array
    {
        if (DB::transactionLevel()<1) throw new \DomainException('VOUCHER_PREFIX_TRANSACTION_REQUIRED');
        $this->validateScope($projectId,$series,$prefix);
        $state=$this->status($projectId,$series,$prefix);
        if ($state['conflict']) throw \Illuminate\Validation\ValidationException::withMessages([
            'prefix'=>['This prefix belongs to another voucher family in the selected project.'],
        ]);
        if ($state['existing'] && $start!==null && $start!==$state['next_number']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_number'=>['An existing prefix must resume at its next available number.'],
            ]);
        }
        if ($start!==null && ($start<1 || $start>999999999)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_number'=>['Starting number must be between 1 and 999999999.'],
            ]);
        }
        $query=DB::table('project_voucher_prefix_counters')->where('project_id',$projectId)
            ->where('series',$series)->where('prefix',$prefix);
        $row=$query->lockForUpdate()->first();
        $next=$state['existing']?$state['next_number']:($start??$state['next_number']);
        if ($row) {
            if (!(int)$row->issued_count && !$state['existing']) {
                $query->update(['next_number'=>$next,'updated_at'=>now()]);
            }
        } else {
            $query->insert(['project_id'=>$projectId,'series'=>$series,'prefix'=>$prefix,
                'next_number'=>$next,'issued_count'=>0,'created_at'=>now(),'updated_at'=>now()]);
        }
        return ['prefix'=>$prefix,'next_number'=>$next,'existing'=>$state['existing']];
    }

    /** Allocates only when the voucher source already exists inside its posting transaction. */
    public function issue(int $projectId,string $series,string $sourceKind,int $sourceId,string $date): string
    {
        if (DB::transactionLevel()<1) throw new \DomainException('VOUCHER_PREFIX_TRANSACTION_REQUIRED');
        if (!Schema::hasTable('project_voucher_prefix_counters')) {
            throw new \DomainException('VOUCHER_PREFIX_MIGRATION_REQUIRED');
        }
        // A single project lock serializes all families and their prefix changes.
        DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
        $prefix=$this->currentPrefix($projectId,$series);
        $state=$this->status($projectId,$series,$prefix);
        if ($state['conflict']) throw new \DomainException('VOUCHER_PREFIX_PROJECT_COLLISION');
        $counter=DB::table('project_voucher_prefix_counters')->where('project_id',$projectId)
            ->where('series',$series)->where('prefix',$prefix);
        $row=$counter->lockForUpdate()->first();
        $next=max((int)($row->next_number??1),$state['next_number']);
        $display=$prefix.'-'.$next;
        if (DB::table('project_voucher_display_numbers')->where('project_id',$projectId)
            ->where('display_number',$display)->exists()) {
            throw new \DomainException('VOUCHER_DISPLAY_NUMBER_COLLISION');
        }
        DB::table('project_voucher_display_numbers')->insert([
            'project_id'=>$projectId,'source_kind'=>$sourceKind,'source_id'=>$sourceId,
            'series'=>$series,'financial_year'=>(int)substr($date,0,4),
            'display_number'=>$display,'created_at'=>now(),
        ]);
        if ($row) $counter->update(['next_number'=>$next+1,'issued_count'=>(int)$row->issued_count+1,'updated_at'=>now()]);
        else $counter->insert(['project_id'=>$projectId,'series'=>$series,'prefix'=>$prefix,
            'next_number'=>$next+1,'issued_count'=>1,'created_at'=>now(),'updated_at'=>now()]);
        return $display;
    }
}
