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

    public function delete(int $projectId,int $sourceId): void
    {
        FinanceConnectionGuard::assertAllowed();
        if($projectId<1||$sourceId<1)
            throw new \DomainException('Receipt deletion identity is invalid.');
        DB::transaction(function()use($projectId,$sourceId):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $source=DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->where('transaction_type','PPR')
                ->where('is_active',1)->lockForUpdate()->first();
            if(!$source||(int)$source->payment_type!==1
                ||(float)$source->amount_in!==0.0
                ||(float)$source->amount_out<=0.0) {
                throw new \DomainException('Only an active cash PPR can be deleted.');
            }
            if(Schema::hasTable('finance_posting_cash_sources')
                &&DB::table('finance_posting_cash_sources')
                    ->where('customer_ledger_id',$sourceId)->exists()) {
                throw new \DomainException('Typed PPR posting requires its own source lifecycle.');
            }
            if(Schema::hasTable('finance_bank_voucher_allocations')
                &&DB::table('finance_bank_voucher_allocations')
                    ->where('voucher_id',$sourceId)->where('project_id',$projectId)->exists()) {
                throw new \DomainException('PPR is linked to bank custody.');
            }
            if(Schema::hasTable('daily_voucher_approvals')
                &&DB::table('daily_voucher_approvals')
                    ->where('project_id',$projectId)->where('source_table','customer_ledger')
                    ->where('source_id',$sourceId)->whereIn('status',['pending','rejected'])->exists()) {
                throw new \DomainException('PPR has an unresolved approval.');
            }
            $ledgers=DB::table('ledgers as leg')
                ->join('project_head_subheads as account','account.id','=','leg.project_head_subheads_id')
                ->where('leg.customer_ledger_id',$sourceId)->where('leg.type','PPR')
                ->where('leg.is_active',1)->where('account.project_id',$projectId)
                ->orderBy('leg.id')->lockForUpdate()->get(['leg.id','leg.type',
                    'leg.project_head_subheads_id','leg.voucher_number','leg.amount_in',
                    'leg.amount_out','leg.is_active']);
            if(!in_array($ledgers->count(),[1,2],true)||DB::table('ledgers')
                ->where('customer_ledger_id',$sourceId)->count()!==$ledgers->count()
                ||(float)$ledgers[0]->amount_in!==(float)$source->amount_out
                ||(float)$ledgers[0]->amount_out!==0.0) {
                throw new \DomainException('PPR ledger link or amount does not match.');
            }
            $bookings=DB::table('booking_vouchers')
                ->where('project_id',$projectId)->where('customer_ledger_id',$sourceId)
                ->where('voucher_series','PPR')->lockForUpdate()->get();
            // A BookingVoucher may have its own typed posting batch even
            // when the customer_ledger row has no cash-source claim. Never
            // mutate either source through the legacy PPR correction path.
            if($bookings->count()===1
                &&Schema::hasTable('finance_posting_booking_sources')
                &&DB::table('finance_posting_booking_sources')
                    ->where('booking_voucher_id',$bookings[0]->id)->exists()) {
                throw new \DomainException('Receipt is owned by a typed booking financial batch.');
            }
            if($bookings->count()!==1
                ||(int)$bookings[0]->ledger_id!==(int)$ledgers[0]->id
                ||(int)$bookings[0]->voucher_number!==(int)$ledgers[0]->voucher_number
                ||(int)$bookings[0]->is_active!==1
                ||(float)$bookings[0]->amount!==(float)$source->amount_out) {
                throw new \DomainException('PPR BookingVoucher FK or amount does not match.');
            }
            if($ledgers->count()===2){
                BalancedOriginalPprIntegrity::assert($source,$ledgers,(int)$bookings[0]->ledger_id);
            }
            DB::table('ledgers')->where('customer_ledger_id',$sourceId)
                ->whereIn('id',$ledgers->pluck('id')->all())->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>now()]);
            DB::table('booking_vouchers')->where('id',$bookings[0]->id)
                ->where('project_id',$projectId)->where('customer_ledger_id',$sourceId)
                ->where('ledger_id',$ledgers[0]->id)->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>now()]);
            DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>now()]);
        },3);
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
                ->orderBy('l.id')->lockForUpdate()->get(['l.id','l.type','l.type_id',
                    'l.voucher_number','l.reference','l.amount_in','l.amount_out',
                    'l.project_head_subheads_id','l.is_active']);
            if(!in_array($leg->count(),[1,2],true)||DB::table('ledgers')
                ->where('customer_ledger_id',$sourceId)->count()!==$leg->count()
                ||(float)$leg[0]->amount_in!==(float)$source->amount_out
                ||(float)$leg[0]->amount_out!==0.0) {
                throw new \DomainException('Receipt and its exact ledger leg are inconsistent.');
            }
            $booking=DB::table('booking_vouchers')
                ->where('project_id',$projectId)->where('customer_ledger_id',$sourceId)
                ->where('voucher_series','PPR')->lockForUpdate()->get();
            if($booking->count()===1
                &&Schema::hasTable('finance_posting_booking_sources')
                &&DB::table('finance_posting_booking_sources')
                    ->where('booking_voucher_id',$booking[0]->id)->exists()) {
                throw new \DomainException('Receipt is owned by a typed booking financial batch.');
            }
            if($booking->count()!==1|| (int)$booking[0]->ledger_id!==(int)$leg[0]->id
                ||(int)$booking[0]->voucher_number!==(int)$leg[0]->voucher_number
                ||(int)$booking[0]->customer_id!==(int)$source->customer_id
                ||(int)$booking[0]->plot_id!==(int)$source->plot_id
                ||(int)$booking[0]->is_active!==1
                ||(float)$booking[0]->amount!==(float)$source->amount_out) {
                throw new \DomainException('Receipt booking voucher or ledger FK does not match.');
            }
            if($leg->count()===2){
                BalancedOriginalPprIntegrity::assert($source,$leg,(int)$booking[0]->ledger_id);
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
            if($leg->count()===2){
                DB::table('ledgers')->where('id',$leg[1]->id)
                    ->where('customer_ledger_id',$sourceId)->where('is_active',1)
                    ->update(['date'=>$date,'reference'=>$reference,
                        'detail'=>$description,'amount_in'=>0,'amount_out'=>$amount,
                        'updated_at'=>now()]);
            }
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
