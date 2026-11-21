<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class OriginalJournalWorkflow
{
    private function money(mixed $value): string
    {
        $value = (string) $value;
        if (!preg_match('/^\d{1,9}(?:\.(\d{1,2}))?$/D', $value)) {
            throw new \DomainException('V2_JV_INVALID_MONEY');
        }
        $parts = explode('.', $value);
        return (string) (int) $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');
    }

    private function postSeries(string $series,int $projectId,int $id,
        string $key,?int $actorId): int
    {
        $gateway=new PostingGateway();
        return $series==='SV'
            ? $gateway->postSalesVoucher($projectId,$id,$key,$actorId)
            : $gateway->postJournal($projectId,$id,$key,$actorId);
    }

    public function createAndPost(int $projectId, string $key, array $input, ?int $actorId, string $series = 'JV'): int
    {
        FinanceConnectionGuard::assertAllowed();
        if (!in_array($series,['JV','SV'],true)) {
            throw new \DomainException('Unsupported Journal/Sales voucher series.');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $key)) {
            throw new \DomainException('V2_JV_REQUEST_KEY_REQUIRED');
        }
        return DB::transaction(function () use ($projectId, $key, $input, $actorId, $series): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $accounts = \App\Models\ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
                ->whereIn('id', $input['sub_accounts'])->orderBy('id')->lockForUpdate()
                ->get(['id', 'head_accounting_id'])->keyBy('id');
            foreach ($input['sub_accounts'] as $i => $accountId) {
                if (!$accounts->has($accountId)
                    || (int) $accounts->get($accountId)->head_accounting_id !== (int) $input['accounts'][$i]) {
                    throw new \DomainException('V2_JV_ACCOUNT_PAIR_CHANGED');
                }
            }
            $lines = [];
            $debit = $credit = 0;
            foreach ($input['sub_accounts'] as $i => $accountId) {
                $d = $this->money($input['debit'][$i]);
                $c = $this->money($input['credit'][$i]);
                $debit += (int) str_replace('.', '', $d);
                $credit += (int) str_replace('.', '', $c);
                $lines[] = ['account_id' => (int) $accountId, 'debit' => $d, 'credit' => $c,
                    'description' => (string) ($input['line_description'][$i] ?? '')];
            }
            $existing = DB::table('finance_posting_batches')->where('project_id', $projectId)
                ->where('request_key', $key)->lockForUpdate()->first();
            if ($existing) {
                $sourceId = DB::table('finance_posting_journal_sources')
                    ->where('posting_batch_id', $existing->id)->value('journal_voucher_id');
                $header = $sourceId ? DB::table('journal_vouchers')->where('id', $sourceId)
                    ->where('project_id', $projectId)->where('type', $series)->whereNull('deleted_at')->first() : null;
                $stored = $header ? DB::table('journal_voucher_details')->where('journal_voucher_id', $sourceId)
                    ->whereNull('deleted_at')->orderBy('id')->get()->map(fn ($row) => [
                        'account_id' => (int) $row->account_id, 'debit' => $this->money($row->debit),
                        'credit' => $this->money($row->credit), 'description' => (string) ($row->description ?? ''),
                    ])->all() : [];
                if (!$header || (string) $header->date !== $input['date']
                    || (string) ($header->reference ?? '') !== (string) ($input['reference'] ?? '')
                    || (string) $header->description !== $input['description']
                    || (int) ($header->contractor_id ?? 0) !== (int) ($input['contractor_id'] ?? 0)
                    || $stored !== $lines) {
                    throw new \DomainException('V2_JV_REQUEST_CONFLICT');
                }
                $this->postSeries($series,$projectId,(int)$sourceId,$key,$actorId);
                return (int) $sourceId;
            }
            $id = DB::table('journal_vouchers')->insertGetId([
                'project_id' => $projectId, 'type' => $series,
                'voucher_number' => $series==='SV'
                    ? (new ProjectVoucherAllocator())->nextSalesVoucher($projectId)
                    : (new ProjectVoucherAllocator())->nextJournal($projectId),
                'date' => $input['date'], 'reference' => $input['reference'] ?? null,
                'description' => $input['description'],
                'total_debit' => intdiv($debit, 100).'.'.str_pad((string) ($debit % 100), 2, '0', STR_PAD_LEFT),
                'total_credit' => intdiv($credit, 100).'.'.str_pad((string) ($credit % 100), 2, '0', STR_PAD_LEFT),
                'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (array_key_exists('contractor_id', $input)) {
                DB::table('journal_vouchers')->where('id', $id)->update(['contractor_id' => $input['contractor_id']]);
            }
            foreach ($lines as $line) {
                DB::table('journal_voucher_details')->insert($line + [
                    'journal_voucher_id' => $id, 'created_by' => $actorId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            app(ProjectVoucherDisplayIdentity::class)->assign(
                $projectId,'journal_voucher',(int)$id,$series,(string)$input['date']
            );
            $this->postSeries($series,$projectId,(int)$id,$key,$actorId);
            return (int) $id;
        }, 3);
    }
    /**
     * Save a Sales Voucher for approval without creating any active posting.
     * The project row serializes concurrent saves and approval-event metadata
     * makes retries with the same request key idempotent.
     */
    public function createPendingSales(int $projectId,string $key,array $input,int $actorId): int
    {
        FinanceConnectionGuard::assertAllowed();
        if(!preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key)){
            throw new \DomainException('SV_REQUEST_KEY_REQUIRED');
        }
        return DB::transaction(function()use($projectId,$key,$input,$actorId):int{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $entries=[];
            $debitCents=0;$creditCents=0;
            $accounts=\App\Models\ProjectHeadSubhead::availableForPosting()
                ->where('project_id',$projectId)->whereIn('id',$input['sub_accounts'])
                ->lockForUpdate()->get(['id','head_accounting_id'])->keyBy('id');
            foreach($input['sub_accounts'] as $i=>$accountId){
                if(!$accounts->has($accountId)||
                    (int)$accounts->get($accountId)->head_accounting_id!==(int)$input['accounts'][$i]){
                    throw new \DomainException('SV_ACCOUNT_PAIR_CHANGED');
                }
                $debit=$this->money($input['debit'][$i]);
                $credit=$this->money($input['credit'][$i]);
                $debitCents+=(int)str_replace('.','',$debit);
                $creditCents+=(int)str_replace('.','',$credit);
                $entries[]=['account_id'=>(int)$accountId,'debit'=>$debit,
                    'credit'=>$credit,'description'=>(string)($input['line_description'][$i]??'')];
            }
            if(count($entries)<2||$debitCents<1||$debitCents!==$creditCents){
                throw new \DomainException('SV_AMOUNT_IMBALANCE');
            }
            $identity=[
                'date'=>(string)$input['date'],
                'reference'=>(string)($input['reference']??''),
                'description'=>(string)$input['description'],
                'lines'=>$entries,
            ];
            $fingerprint=hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
            $event='svk_'.substr(hash('sha256',$key),0,20);
            $prior=DB::table('daily_voucher_approval_events as e')
                ->join('daily_voucher_approvals as a','a.id','=','e.approval_id')
                ->where('a.project_id',$projectId)->where('a.voucher_type','SV')
                ->where('e.event',$event)->lockForUpdate()
                ->first(['a.source_id','a.source_table','e.remarks']);
            if($prior){
                $meta=json_decode((string)$prior->remarks,true);
                if(($meta['key']??null)!==$key||($meta['fingerprint']??null)!==$fingerprint
                    ||$prior->source_table!=='journal_vouchers'
                    ||!DB::table('journal_vouchers')->where('id',$prior->source_id)
                        ->where('project_id',$projectId)->where('type','SV')
                        ->whereNull('deleted_at')->exists()){
                    throw new \DomainException('SV_REQUEST_KEY_CONFLICT');
                }
                return (int)$prior->source_id;
            }
            $formatted=intdiv($debitCents,100).'.'.str_pad((string)($debitCents%100),2,'0',STR_PAD_LEFT);
            $id=DB::table('journal_vouchers')->insertGetId([
                'project_id'=>$projectId,'type'=>'SV',
                'voucher_number'=>(new ProjectVoucherAllocator())->nextSalesVoucher($projectId),
                'date'=>$input['date'],'reference'=>$input['reference']??null,
                'description'=>$input['description'],'total_debit'=>$formatted,
                'total_credit'=>$formatted,'status'=>'pending',
                'created_by'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach($entries as $line){
                DB::table('journal_voucher_details')->insert($line+[
                    'journal_voucher_id'=>$id,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            app(ProjectVoucherDisplayIdentity::class)->assign(
                $projectId,'journal_voucher',(int)$id,'SV',(string)$input['date']);
            if(!app(DailyVoucherApprovalWorkflow::class)->submitIfRequired(
                $projectId,'SV','journal_vouchers',(int)$id,$actorId)){
                throw new \DomainException('SV_APPROVAL_POLICY_CHANGED');
            }
            $approvalId=DB::table('daily_voucher_approvals')
                ->where('project_id',$projectId)->where('voucher_type','SV')
                ->where('source_table','journal_vouchers')->where('source_id',$id)->value('id');
            DB::table('daily_voucher_approval_events')->insert([
                'approval_id'=>$approvalId,'event'=>$event,'actor_id'=>$actorId,
                'remarks'=>json_encode(['key'=>$key,'fingerprint'=>$fingerprint],JSON_THROW_ON_ERROR),
                'created_at'=>now(),
            ]);
            return (int)$id;
        },3);
    }

}
