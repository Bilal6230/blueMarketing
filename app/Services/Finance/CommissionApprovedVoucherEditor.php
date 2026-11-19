<?php

namespace App\Services\Finance;

use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same-ID, non-reversal correction of a previously approved Commission voucher.
 *
 * Posting evidence is retained in archived posting/detail/ledger legs; only
 * currently active legs contribute to live reports. The original posting
 * batch and voucher number stay the same. No new financial batch is created.
 * The transaction locks the selected project, source, batch and related rows.
 */
final class CommissionApprovedVoucherEditor
{
    private function cents(mixed $value): int
    {
        $s=(string)$value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D',$s,$m)) {
            throw new \DomainException('Commission correction amount is invalid.');
        }
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }

    public function update(
        int $projectId,int $voucherId,string $date,array $submitted,
        array $metadata=[],?int $actorId=null
    ): void {
        FinanceConnectionGuard::assertAllowed();
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if ($projectId<1||$voucherId<1||!$parsed||$parsed->format('Y-m-d')!==$date) {
            throw new \DomainException('Commission correction date or identity is invalid.');
        }
        if (!Schema::hasColumn('finance_posting_lines','is_active')
            || !Schema::hasColumn('ledgers','commision_voucher_detail_id')) {
            throw new \DomainException('Commission correction requires the ledger and posting lifecycle migrations.');
        }
        app(CommissionLedgerSynchronizer::class)->assertEnforcedDetailForeignKey();
        DB::transaction(function()use($projectId,$voucherId,$date,$submitted,$metadata,$actorId):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $header=DB::table('commision_vouchers')->where('id',$voucherId)
                ->where('project_id',$projectId)->whereNull('deleted_at')
                ->lockForUpdate()->first();
            if (!$header||(string)$header->status!=='approved') {
                throw new \DomainException('Only an approved Commission voucher can use this correction.');
            }
            $source=DB::table('finance_posting_commission_sources as source')
                ->join('finance_posting_batches as batch','batch.id','=','source.posting_batch_id')
                ->where('source.commision_voucher_id',$voucherId)
                ->where('batch.project_id',$projectId)
                ->whereNull('batch.reversal_of_batch_id')
                ->orderBy('batch.id')->get(['batch.id']);
            if ($source->count()!==1) {
                throw new \DomainException('Commission voucher requires exactly one source posting batch.');
            }
            $batchId=(int)$source[0]->id;
            $batch=DB::table('finance_posting_batches')->where('id',$batchId)
                ->where('project_id',$projectId)->lockForUpdate()->first();
            if (!$batch||$batch->posted_at===null
                ||DB::table('finance_posting_batches')->where('reversal_of_batch_id',$batchId)->exists()
                ||!DB::table('finance_posting_source_claims')->where('posting_batch_id',$batchId)
                    ->where('source_kind','commission')->exists()) {
                throw new \DomainException('Reversed or unclaimed Commission voucher cannot be corrected.');
            }
            $old=DB::table('commision_voucher_details')
                ->where('commision_voucher_id',$voucherId)->whereNull('deleted_at')
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($old->count()<2||count($submitted)<2||count($submitted)>100) {
                throw new \DomainException('Commission correction requires 2–100 rows.');
            }
            $ledgerRows=DB::table('ledgers')->where('type','CV')->where('type_id',$voucherId)
                ->where('is_active',1)->lockForUpdate()->get()->groupBy('commision_voucher_detail_id');
            $sourceRows=DB::table('finance_commission_line_sources as src')
                ->join('finance_posting_lines as line','line.id','=','src.posting_line_id')
                ->where('line.posting_batch_id',$batchId)->where('line.is_active',1)
                ->lockForUpdate()->get(['line.id','line.project_head_subhead_id',
                    'line.debit','line.credit','line.description','src.commision_voucher_detail_id'])
                ->groupBy('commision_voucher_detail_id');
            if ($ledgerRows->flatten(1)->count()!==$old->count()
                ||$sourceRows->flatten(1)->count()!==$old->count()
                ||DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
                    ->where('is_active',1)->count()!==$old->count()) {
                throw new \DomainException('Commission current detail, ledger and posting cardinalities do not match.');
            }
            foreach ($old as $detailId=>$detail) {
                $leg=$ledgerRows->get($detailId,collect());
                $posting=$sourceRows->get($detailId,collect());
                if ($leg->count()!==1||$posting->count()!==1
                    ||(int)$leg[0]->project_head_subheads_id!==(int)$detail->account_id
                    ||(int)$posting[0]->project_head_subhead_id!==(int)$detail->account_id
                    ||$this->cents($leg[0]->amount_in)!==$this->cents($detail->debit)
                    ||$this->cents($leg[0]->amount_out)!==$this->cents($detail->credit)
                    ||$this->cents($posting[0]->debit)!==$this->cents($detail->debit)
                    ||$this->cents($posting[0]->credit)!==$this->cents($detail->credit)) {
                    throw new \DomainException('Commission correction source-to-ledger FK mismatch.');
                }
            }
            $desired=[];$used=[];$debit=0;$credit=0;
            foreach($submitted as $row) {
                $id=(int)($row['detail_id']??0);
                if ($id&&(isset($used[$id])||!$old->has($id))) {
                    throw new \DomainException('Commission detail ID is duplicated or belongs to another voucher.');
                }
                if ($id)$used[$id]=true;
                $account=(int)($row['account_id']??0);
                if (!DB::table('project_head_subheads')->where('id',$account)
                    ->where('project_id',$projectId)->exists()) {
                    throw new \DomainException('Commission correction account belongs to another project.');
                }
                $dr=$this->cents($row['debit']??'0');
                $cr=$this->cents($row['credit']??'0');
                if (($dr>0)===($cr>0)) {
                    throw new \DomainException('Commission correction requires either debit or credit per row.');
                }
                $debit+=$dr;$credit+=$cr;
                $desired[]=['detail_id'=>$id,'account_id'=>$account,
                    'debit'=>$this->money($dr),'credit'=>$this->money($cr),
                    'description'=>(string)($row['description']??'')];
            }
            if ($debit<1||$debit!==$credit) {
                throw new \DomainException('Commission correction debit and credit must balance.');
            }
            $reference=array_key_exists('reference',$metadata)?$metadata['reference']:$header->reference;
            $description=array_key_exists('description',$metadata)?$metadata['description']:$header->description;
            $headerChanged=(string)$header->date!==$date
                ||(string)($header->reference??'')!==(string)($reference??'')
                ||(string)($header->description??'')!==(string)($description??'');
            $rows=[];
            foreach($desired as $line) {
                $id=$line['detail_id'];$prior=$id?$old->get($id):null;
                $unchanged=$prior&&!$headerChanged
                    &&(int)$prior->account_id===$line['account_id']
                    &&$this->cents($prior->debit)===$this->cents($line['debit'])
                    &&$this->cents($prior->credit)===$this->cents($line['credit'])
                    &&(string)($prior->description??'')===$line['description'];
                $line['unchanged']=(bool)$unchanged;
                $rows[]=$line;
            }
            $removed=$old->keys()->filter(fn($id)=>!isset($used[(int)$id]))->all();
            if ($removed===[]&&!$headerChanged
                &&count($rows)===$old->count()
                &&collect($rows)->every(fn($line)=>$line['unchanged'])) {
                return; // No-op leaves source, ledger and posting PKs unchanged.
            }
            // Retire only changed or deleted original legs; evidence and their
            // enforced detail-to-ledger and posting-to-detail FKs remain.
            foreach($old as $id=>$detail) {
                $replacement=collect($rows)->first(fn($line)=>
                    (int)$line['detail_id']===(int)$id);
                if($replacement&&$replacement['unchanged'])continue;
                DB::table('ledgers')->where('id',$ledgerRows[$id][0]->id)
                    ->where('commision_voucher_detail_id',$id)
                    ->where('is_active',1)->update(['is_active'=>0,'updated_at'=>now()]);
                DB::table('finance_posting_lines')->where('id',$sourceRows[$id][0]->id)
                    ->where('posting_batch_id',$batchId)->where('is_active',1)
                    ->update(['is_active'=>0,'updated_at'=>now()]);
                DB::table('commision_voucher_details')->where('id',$id)
                    ->where('commision_voucher_id',$voucherId)->whereNull('deleted_at')
                    ->update(['deleted_at'=>now(),'updated_at'=>now()]);
            }
            $final=[];
            foreach($rows as $line) {
                if ($line['unchanged']) {
                    $detailId=(int)$line['detail_id'];
                }else{
                    $detailId=(int)DB::table('commision_voucher_details')->insertGetId([
                        'commision_voucher_id'=>$voucherId,'account_id'=>$line['account_id'],
                        'debit'=>$line['debit'],'credit'=>$line['credit'],
                        'description'=>$line['description'],
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $ledger=Ledger::create([
                        'type'=>'CV','type_id'=>$voucherId,
                        'commision_voucher_detail_id'=>$detailId,
                        'voucher_number'=>$header->voucher_number,
                        'project_head_subheads_id'=>$line['account_id'],
                        'reference'=>$reference,'date'=>$date,
                        'amount_in'=>$line['debit'],'amount_out'=>$line['credit'],
                        'detail'=>$line['description'],'is_active'=>1,'status'=>'0',
                        'create_by'=>$actorId,
                    ]);
                    $postingId=(int)DB::table('finance_posting_lines')->insertGetId([
                        'posting_batch_id'=>$batchId,
                        'project_head_subhead_id'=>$line['account_id'],
                        'debit'=>$line['debit'],'credit'=>$line['credit'],
                        'description'=>$line['description'],'is_active'=>1,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    DB::table('finance_commission_line_sources')->insert([
                        'posting_line_id'=>$postingId,
                        'commision_voucher_detail_id'=>$detailId,
                    ]);
                }
                $final[]=['detail_id'=>$detailId,'account_id'=>$line['account_id'],
                    'debit'=>$line['debit'],'credit'=>$line['credit'],
                    'description'=>$line['description']];
            }
            // Rebuild fingerprint from persisted active detail rows. This
            // preserves nullable descriptions and the precise PK order used
            // by CommissionPostingWriter, even when UI rows are reordered.
            $final=DB::table('commision_voucher_details')
                ->where('commision_voucher_id',$voucherId)->whereNull('deleted_at')
                ->orderBy('id')->get()->map(fn($detail)=>[
                    'detail_id'=>(int)$detail->id,
                    'account_id'=>(int)$detail->account_id,
                    'debit'=>$this->money($this->cents($detail->debit)),
                    'credit'=>$this->money($this->cents($detail->credit)),
                    'description'=>$detail->description,
                ])->all();
            // CommissionPostingWriter validates this exact fingerprint on
            // idempotent Approve replay. Keep batch identity/key unchanged,
            // but refresh its payload to describe the corrected active legs.
            $fingerprint=hash('sha256',json_encode([
                'source_kind'=>'commission','project_id'=>$projectId,'source_id'=>$voucherId,
                'source_number_text'=>(string)$header->voucher_number,'source_date'=>$date,
                'source_status'=>'approved','legs'=>$final,
            ],JSON_THROW_ON_ERROR));
            DB::table('commision_vouchers')->where('id',$voucherId)
                ->where('project_id',$projectId)->update([
                    'date'=>$date,'reference'=>$reference,'description'=>$description,
                    'total_debit'=>$this->money($debit),'total_credit'=>$this->money($credit),
                    'updated_at'=>now(),
                ]);
            DB::table('finance_posting_batches')->where('id',$batchId)
                ->where('project_id',$projectId)->update([
                    'payload_sha256'=>$fingerprint,'effective_date'=>$date,
                    'updated_at'=>now(),
                ]);
        },3);
    }
}
