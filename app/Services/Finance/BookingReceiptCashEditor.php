<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same-ID correction of one already-posted CASH PPR receipt, the associated
 * BookingVoucher and its exact ledger leg. No new voucher/reversal rows.
 * Bank/cheque receipts and typed batches retain their own custody workflow.
 */
final class BookingReceiptCashEditor
{
    private function money(mixed $amount): string
    {
        $amount=str_replace(',','',trim((string)$amount));
        if (!preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D',$amount,$parts)) {
            throw new \DomainException('Receipt amount is invalid.');
        }
        $cents=(int)$parts[1]*100+(int)str_pad($parts[2]??'',2,'0');
        if($cents<1)throw new \DomainException('Receipt amount must be positive.');
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }

    public function edit(int $projectId,int $sourceId,array $changes,int $userId): void
    {
        FinanceConnectionGuard::assertAllowed();
        $date=(string)($changes['date']??'');
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if($projectId<1||$sourceId<1||$userId<1||!$parsed
            ||$parsed->format('Y-m-d')!==$date) {
            throw new \DomainException('Receipt correction project or date is invalid.');
        }
        $amount=$this->money($changes['amount']??'');
        $reference=trim((string)($changes['reference']??''));
        $description=trim((string)($changes['description']??''));
        if($reference===''||strlen($reference)>200||strlen($description)>255) {
            throw new \DomainException('Receipt reference or description is invalid.');
        }
        DB::transaction(function()use($projectId,$sourceId,$userId,$date,$amount,
            $reference,$description):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $source=DB::table('customer_ledger')
                ->where('id',$sourceId)->where('project_id',$projectId)
                ->where('transaction_type','PPR')->where('is_active',1)
                ->lockForUpdate()->first();
            if(!$source||(int)$source->payment_type!==1
                ||(float)$source->amount_in!==0.0
                ||(float)$source->amount_out<=0.0) {
                throw new \DomainException('Only an active cash PPR receipt can be corrected in place.');
            }
            if(Schema::hasTable('finance_posting_cash_sources')
                &&DB::table('finance_posting_cash_sources')
                    ->where('customer_ledger_id',$sourceId)->exists()) {
                throw new \DomainException('Receipt is backed by a typed financial batch.');
            }
            if(Schema::hasTable('finance_bank_voucher_allocations')
                &&DB::table('finance_bank_voucher_allocations')
                    ->where('voucher_id',$sourceId)->where('project_id',$projectId)->exists()) {
                throw new \DomainException('Receipt is linked to bank custody.');
            }
            if(Schema::hasTable('daily_voucher_approvals')
                &&DB::table('daily_voucher_approvals')
                    ->where('project_id',$projectId)->where('source_table','customer_ledger')
                    ->where('source_id',$sourceId)->whereIn('status',['pending','rejected'])->exists()) {
                throw new \DomainException('Receipt has an unresolved approval.');
            }
            $leg=DB::table('ledgers as l')
                ->join('project_head_subheads as account','account.id','=','l.project_head_subheads_id')
                ->where('l.customer_ledger_id',$sourceId)->where('l.type','PPR')
                ->where('l.is_active',1)->where('account.project_id',$projectId)
                ->lockForUpdate()->get(['l.id','l.type_id','l.voucher_number','l.reference',
                    'l.amount_in','l.amount_out','l.project_head_subheads_id','l.is_active']);
            if($leg->count()!==1||DB::table('ledgers')
                ->where('customer_ledger_id',$sourceId)->count()!==1
                ||(float)$leg[0]->amount_in!==(float)$source->amount_out
                ||(float)$leg[0]->amount_out!==0.0) {
                throw new \DomainException('Receipt and its exact ledger leg are inconsistent.');
            }
            $booking=DB::table('booking_vouchers')
                ->where('project_id',$projectId)->where('customer_ledger_id',$sourceId)
                ->where('voucher_series','PPR')->lockForUpdate()->get();
            if($booking->count()!==1|| (int)$booking[0]->ledger_id!==(int)$leg[0]->id
                ||(int)$booking[0]->voucher_number!==(int)$leg[0]->voucher_number
                ||(int)$booking[0]->customer_id!==(int)$source->customer_id
                ||(int)$booking[0]->plot_id!==(int)$source->plot_id
                ||(int)$booking[0]->is_active!==1
                ||(float)$booking[0]->amount!==(float)$source->amount_out) {
                throw new \DomainException('Receipt booking voucher or ledger FK does not match.');
            }
            if(DB::table('customer_ledger')->where('project_id',$projectId)
                ->where('transaction_type','PPR')->where('reference',$reference)
                ->where('customer_id',$source->customer_id)->where('plot_id',$source->plot_id)
                ->where('id','!=',$sourceId)->exists()) {
                throw new \DomainException('Receipt reference already belongs to another payment.');
            }
            if((string)$source->date===$date
                &&(string)$source->reference===$reference
                &&(string)($source->description??'')===$description
                &&number_format((float)$source->amount_out,2,'.','')===$amount) return;
            DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->update([
                    'date'=>$date,'reference'=>$reference,
                    'description'=>$description,'amount_out'=>$amount,'updated_at'=>now(),
                ]);
            DB::table('ledgers')->where('id',$leg[0]->id)
                ->where('customer_ledger_id',$sourceId)->where('is_active',1)
                ->update(['date'=>$date,'reference'=>$reference,
                    'detail'=>$description,'amount_in'=>$amount,'updated_at'=>now(),
                ]);
            DB::table('booking_vouchers')->where('id',$booking[0]->id)
                ->where('project_id',$projectId)->where('customer_ledger_id',$sourceId)
                ->where('ledger_id',$leg[0]->id)->update([
                    'receipt_date'=>$date,'slip_reference'=>$reference,
                    'description'=>$description,'amount'=>$amount,
                    'update_by'=>$userId,'updated_at'=>now(),
                ]);
        },3);
    }
}
