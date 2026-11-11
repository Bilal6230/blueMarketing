<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CustomerLedger;
use App\Models\DailyVoucherApproval;
use App\Models\Ledger;
use App\Services\Finance\RejectedCashVoucherResubmission;
use App\Services\Finance\VoucherApproverAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RejectedCashVoucherController extends Controller
{
    public function edit(int $customerLedger)
    {
        $project=(int)getSelectedTown();
        abort_unless($project>0,403);
        $source=CustomerLedger::query()->where('project_id',$project)
            ->whereKey($customerLedger)->whereIn('transaction_type',['CR','CP'])->firstOrFail();
        abort_unless(in_array((int)$source->payment_type,[1,2,3],true),409,'Unsupported payment method.');
        $approval=DailyVoucherApproval::query()->where('project_id',$project)
            ->where('source_table','customer_ledger')->where('source_id',$source->id)
            ->where('status','rejected')->firstOrFail();
        $actor=auth()->user();
        abort_unless(VoucherApproverAccess::isSuperAdmin($actor)
            || ((int)$approval->submitted_by===(int)$actor->id
                && (int)$actor->project_id===$project),403);
        $leg=Ledger::query()->withoutGlobalScopes()->where('customer_ledger_id',$source->id)->first();
        $accounts=DB::table('project_head_subheads as p')
            ->leftJoin('head_accountings as h','h.id','=','p.head_accounting_id')
            ->leftJoin('subhead_accountings as s','s.id','=','p.subhead_accounting_id')
            ->where('p.project_id',$project)->orderBy('h.name')->orderBy('s.name')
            ->get(['p.id','h.name as head_name','s.name as sub_name']);
        return view('admin.finance.voucher.rejected_cash_revision',
            compact('source','approval','leg','accounts'));
    }
    public function store(int $customerLedger, Request $request)
    {
        $data=$request->validate([
            'date'=>['required','date_format:Y-m-d'],
            'amount'=>['required','numeric','gt:0','max:999999999999'],
            'account_id'=>['required','integer'],
            'description'=>['required','string','max:255'],
            'revision_reason'=>['required','string','min:3','max:500'],
            't_number'=>['required_if:payment_type,2,3','nullable','string','max:255'],
            'bank_id'=>['nullable','integer','min:1'],
            'passing_date'=>['nullable','date_format:Y-m-d'],
            'cheque_purpose'=>['nullable','string','max:500'],
        ]);
        $source=CustomerLedger::query()->where('project_id',(int)getSelectedTown())
            ->whereKey($customerLedger)->firstOrFail();
        if (in_array((int)$source->payment_type,[2,3],true)) {
            $request->validate([
                't_number'=>['required','string','max:255'],
                'bank_id'=>['required','integer','min:1'],
                'passing_date'=>['required','date_format:Y-m-d'],
                'cheque_purpose'=>[(int)$source->payment_type===3?'required':'nullable','string','max:500'],
            ]);
        }
        $data=array_merge($data,$request->only(['t_number','bank_id','passing_date','cheque_purpose']));
        try {
            $saved=app(RejectedCashVoucherResubmission::class)->resubmit(
                (int)getSelectedTown(),$customerLedger,(int)auth()->id(),$data);
        } catch (\DomainException $e) {
            return response()->json(['message'=>$e->getMessage()],409);
        }
        return response()->json([
            'message'=>'New voucher '.$saved['display'].' saved as Pending Approval. Original remains Rejected.'
        ]+$saved);
    }
}
