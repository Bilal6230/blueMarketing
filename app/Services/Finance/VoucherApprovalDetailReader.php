<?php

namespace App\Services\Finance;

use App\Models\DailyVoucherApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Project-scoped, read-only details for the existing approval modal. */
final class VoucherApprovalDetailReader
{
    public function read(DailyVoucherApproval $approval): array
    {
        $project=(int)$approval->project_id;
        $base=['number'=>'—','date'=>null,'reference'=>null,'description'=>null,
            'amount'=>null,'status'=>(string)$approval->status,'lines'=>[],
            'created_at'=>null,'updated_at'=>null,'payment_type'=>null,
            'transaction_reference'=>null,'bank'=>null,'passing_date'=>null,
            'pending_edit'=>false,'revision_reason'=>null,'rejection_reason'=>$approval->rejection_reason,
            'edit_history'=>[]];
        $identity=app(ProjectVoucherDisplayIdentity::class);
        if ($approval->source_table==='customer_ledger' && Schema::hasTable('customer_ledger')) {
            $s=DB::table('customer_ledger')->where('id',$approval->source_id)
                ->where('project_id',$project)->first();
            if (!$s) return $base;
            $type=(string)$s->transaction_type;
            $number=$identity->lookup($project,'customer_ledger',(int)$s->id)
                ?: (($type==='CP'?'DR':$type).'-'.($s->type_id ?: $s->id));
            $base=array_merge($base,[
                'number'=>$number,'date'=>$s->date,'reference'=>$s->reference,
                'description'=>$s->description,
                'amount'=>max((float)$s->amount_in,(float)$s->amount_out),
                'created_at'=>$s->created_at??null,'updated_at'=>$s->updated_at??null,
                'payment_type'=>[1=>'Cash',2=>'Online',3=>'Cheque'][(int)$s->payment_type]??'Other',
                'transaction_reference'=>$s->t_number??null,'passing_date'=>$s->passing_date??null,
            ]);
            if (in_array($type,['CCR','CDR'],true) && Schema::hasTable('cash_voucher_attachments')) {
                $file=DB::table('cash_voucher_attachments')
                    ->where('project_id',$project)->where('customer_ledger_id',$s->id)
                    ->orderByDesc('id')->first(['id','original_name']);
                if ($file) {
                    $base['attachment_url']=route('finance.voucher.cash-only.attachment',['attachment'=>$file->id]);
                    $base['attachment_name']=$file->original_name;
                }
            }
            if ($s->bank_id && Schema::hasTable('banks')) {
                $base['bank']=DB::table('banks')->where('id',$s->bank_id)->value('name');
            }
            if (Schema::hasTable('ledgers')) {
                $base['lines']=DB::table('ledgers as l')
                    ->join('project_head_subheads as a','a.id','=','l.project_head_subheads_id')
                    ->leftJoin('head_accountings as h','h.id','=','a.head_accounting_id')
                    ->leftJoin('subhead_accountings as sub','sub.id','=','a.subhead_accounting_id')
                    ->where('a.project_id',$project)->where('l.customer_ledger_id',$s->id)
                    ->where('l.type',$type)->orderBy('l.id')
                    ->get(['h.name as head','sub.name as child','l.detail as description',
                        'l.amount_in','l.amount_out','l.is_active'])
                    ->map(fn($l)=>['account'=>trim(($l->head??'').' / '.($l->child??''),' /'),
                        'description'=>$l->description,'debit'=>(float)$l->amount_out,
                        'credit'=>(float)$l->amount_in,'active'=>(int)$l->is_active===1])
                    ->all();
            }
        } elseif (in_array($approval->source_table,['journal_vouchers','journal_voucher'],true) &&
            Schema::hasTable('journal_vouchers')) {
            $j=DB::table('journal_vouchers')->where('id',$approval->source_id)
                ->where('project_id',$project)->first();
            if (!$j) return $base;
            $stored=trim((string)($j->voucher_number??''));
            $base=array_merge($base,[
                'number'=>$identity->lookup($project,'journal_voucher',(int)$j->id)
                    ?: ($stored===''?'JV-'.$j->id:(preg_match('/^JV-/i',$stored)?$stored:'JV-'.$stored)),
                'date'=>$j->date,'reference'=>$j->reference??null,'description'=>$j->description,
                'amount'=>max((float)($j->total_debit??0),(float)($j->total_credit??0)),
                'created_at'=>$j->created_at??null,'updated_at'=>$j->updated_at??null,
            ]);
            if (Schema::hasTable('journal_voucher_details')) {
                $base['lines']=DB::table('journal_voucher_details as d')
                    ->join('project_head_subheads as a','a.id','=','d.account_id')
                    ->leftJoin('head_accountings as h','h.id','=','a.head_accounting_id')
                    ->leftJoin('subhead_accountings as sub','sub.id','=','a.subhead_accounting_id')
                    ->where('a.project_id',$project)->where('d.journal_voucher_id',$j->id)
                    ->whereNull('d.deleted_at')->orderBy('d.id')
                    ->get(['h.name as head','sub.name as child','d.description',
                        'd.debit','d.credit'])
                    ->map(fn($d)=>['account'=>trim(($d->head??'').' / '.($d->child??''),' /'),
                        'description'=>$d->description,'debit'=>(float)$d->debit,
                        'credit'=>(float)$d->credit,'active'=>true])->all();
            }
        } else {
            return $base;
        }
        if ($approval->source_table==='customer_ledger' &&
            Schema::hasTable('daily_voucher_edit_snapshots')) {
            $snapshots=DB::table('daily_voucher_edit_snapshots')
                ->where('project_id',$project)->where('source_id',$approval->source_id)
                ->orderByDesc('id')->limit(25)->get();
            $base['edit_history']=$snapshots->map(function($snapshot) {
                $previous=json_decode((string)$snapshot->before_snapshot,true);
                $proposed=json_decode((string)$snapshot->proposed_snapshot,true);
                return [
                    'status'=>$snapshot->status,'reason'=>$snapshot->reason,
                    'created_at'=>$snapshot->created_at,
                    'before'=>$previous,'after'=>$proposed,
                ];
            })->all();
            $snapshot=DB::table('daily_voucher_edit_snapshots')
                ->where('project_id',$project)->where('source_id',$approval->source_id)
                ->where('approval_id',$approval->id)->where('status','pending')
                ->latest('id')->first();
            if ($snapshot) {
                $p=json_decode((string)$snapshot->proposed_snapshot,true);
                if (is_array($p)) {
                    $base['pending_edit']=true;
                    $base['revision_reason']=$snapshot->reason;
                    $base['date']=$p['date']??$base['date'];
                    $base['description']=$p['description']??$base['description'];
                    $base['amount']=max((float)($p['amount_in']??0),(float)($p['amount_out']??0));
                    // These are PROPOSED amounts. Original posted ledger lines
                    // remain in the line table and are explicitly labeled.
                    $base['proposed_amount']=$base['amount'];
                    $base['proposed_account_id']=$p['account_id']??null;
                }
            }
        }
        return $base;
    }
}
