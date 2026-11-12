<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

final class PassedBankSourcePickerController extends Controller
{
    /**
     * Read-only source picker. A verified management record is NOT permission
     * to create an accounting posting: posting needs an atomic single-use claim.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('read voucher'), 403);
        $project=(int)getSelectedTown();
        abort_unless($project>0 && DB::table('projects')->where('id',$project)->exists(),403);
        $input=$request->validate([
            'method'=>['required',Rule::in(['online','cheque'])],
            'direction'=>['required',Rule::in(['receipt','payment'])],
            'search'=>['nullable','string','max:120'],
        ]);
        $search=trim((string)($input['search']??''));
        $direction=$input['direction']==='receipt'?'receipt':'payment';
        if($input['method']==='online'){
            abort_unless(Schema::hasTable('finance_online_intakes') &&
                Schema::hasTable('finance_online_confirmation_events') &&
                Schema::hasTable('finance_online_voucher_claims'),409,
                'Verified online intake and claim migrations are required.');
            $rows=DB::table('finance_online_intakes as i')
                ->where('i.project_id',$project)->where('i.status','confirmed')
                ->where('i.type',$direction)
                ->whereExists(function($q)use($project){
                    $q->selectRaw('1')->from('finance_online_confirmation_events as e')
                        ->whereColumn('e.online_intake_id','i.id')
                        ->where('e.project_id',$project)
                        ->where('e.from_status','pending')->where('e.to_status','confirmed')
                        ->whereColumn('e.original_amount','i.amount')
                        ->whereColumn('e.transaction_type','i.type');
                })->whereNotExists(function($q)use($project){
                    $q->selectRaw('1')->from('finance_online_voucher_claims as c')
                        ->whereColumn('c.online_intake_id','i.id')->where('c.project_id',$project);
                });
            if($search!=='')$rows->where('i.reference','like','%'.$search.'%');
            $items=$rows->orderByDesc('i.id')->limit(100)->get([
                'i.id','i.reference','i.transaction_date','i.amount','i.sending_bank',
                'i.receiving_bank','i.purpose','i.account_mapping_id',
            ])->map(fn($i)=>[
                'id'=>(int)$i->id,'method'=>'online','reference'=>(string)$i->reference,
                'date'=>(string)$i->transaction_date,'passing_date'=>(string)$i->transaction_date,
                'amount'=>(string)$i->amount,'bank'=>(string)($direction==='receipt'?$i->receiving_bank:$i->sending_bank),
                'purpose'=>(string)($i->purpose??''),'account_mapping_id'=>(int)$i->account_mapping_id,
                'status'=>'Confirmed',
            ]);
        } else {
            abort_unless(Schema::hasTable('finance_cheque_intakes') &&
                Schema::hasTable('finance_cheque_intake_actions') &&
                Schema::hasColumn('finance_cheque_intake_actions','verification_confirmed_at'),
                409,'Verified cheque intake migration is required.');
            abort_unless(Schema::hasTable('finance_cheque_voucher_claims'),409,
                'Cheque voucher claim migration is required.');
            $rows=DB::table('finance_cheque_intakes as c')
                ->where('c.project_id',$project)->where('c.status','cleared')
                ->where('c.direction',$direction==='receipt'?'in':'out')
                ->whereNotExists(function($q)use($project){
                    $q->selectRaw('1')->from('finance_cheque_voucher_claims as claim')
                        ->whereColumn('claim.cheque_intake_id','c.id')
                        ->where('claim.project_id',$project);
                })
                ->whereExists(function($q)use($project){
                    $q->selectRaw('1')->from('finance_cheque_intake_actions as a')
                        ->whereColumn('a.cheque_intake_id','c.id')->where('a.project_id',$project)
                        ->where('a.action','cleared')
                        ->whereNotNull('a.verification_confirmed_at');
                });
            if($search!=='')$rows->where('c.cheque_number','like','%'.$search.'%');
            $items=$rows->orderByDesc('c.id')->limit(100)->get([
                'c.id','c.cheque_number','c.cheque_date','c.amount','c.bank_name',
                'c.purpose','c.account_mapping_id',
            ])->map(function($c)use($project){
                $verified=DB::table('finance_cheque_intake_actions')->where('project_id',$project)
                    ->where('cheque_intake_id',$c->id)->where('action','cleared')
                    ->orderByDesc('id')->first(['action_date','bank_name']);
                return [
                    'id'=>(int)$c->id,'method'=>'cheque','reference'=>(string)$c->cheque_number,
                    'date'=>(string)$c->cheque_date,'passing_date'=>(string)($verified->action_date??''),
                    'amount'=>(string)$c->amount,'bank'=>(string)($verified->bank_name?:$c->bank_name),
                    'purpose'=>(string)($c->purpose??''),'account_mapping_id'=>(int)$c->account_mapping_id,
                    'status'=>'Cleared',
                ];
            });
        }
        $mappingIds=$items->pluck('account_mapping_id')->filter()->unique()->all();
        $mappings=$mappingIds
            ? DB::table('project_head_subheads as p')
                ->join('head_accountings as h','h.id','=','p.head_accounting_id')
                ->where('p.project_id',$project)->whereIn('p.id',$mappingIds)
                ->get(['p.id','p.head_accounting_id','p.subhead_accounting_id','h.acct_type'])
                ->keyBy('id') : collect();
        $items=$items->map(function(array $row)use($mappings){
            $mapping=$mappings->get($row['account_mapping_id']);
            $row['head_id']=$mapping?(int)$mapping->head_accounting_id:null;
            $row['subhead_id']=$mapping?(int)$mapping->subhead_accounting_id:null;
            $row['account_type']=$mapping?(int)$mapping->acct_type:null;
            return $row;
        });
        return response()->json(['data'=>$items,'count'=>$items->count(),'source_only'=>true,
            'message'=>'Verified management records. Source selection alone does not post or claim a voucher.'])
            ->header('Cache-Control','private, no-store');
    }
}
