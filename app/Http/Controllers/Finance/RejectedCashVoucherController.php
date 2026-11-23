<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CustomerLedger;
use App\Models\DailyVoucherApproval;
use App\Models\Ledger;
use App\Services\Finance\RejectedCashVoucherResubmission;
use App\Services\Finance\LegacyCashVoucherCorrection;
use App\Services\Finance\DailyVoucherApprovalWorkflow;
use App\Models\ProjectHeadSubhead;
use App\Services\Finance\VoucherApproverAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class RejectedCashVoucherController extends Controller
{
    public function edit(int $customerLedger)
    {
        $project=(int)getSelectedTown();
        abort_unless($project>0,403);
        $source=CustomerLedger::query()->where('project_id',$project)
            ->whereKey($customerLedger)->whereIn('transaction_type',['CR','CP','CCR','CDR'])->firstOrFail();
        abort_unless(in_array((int)$source->payment_type,[1,2,3],true),409,'Unsupported payment method.');
        $approval=DailyVoucherApproval::query()->where('project_id',$project)
            ->where('source_table','customer_ledger')->where('source_id',$source->id)
            ->whereIn('status',['rejected','approved'])->first();
        if (!$approval) abort_unless((int)$source->is_active===1,409,'Inactive voucher is not editable.');
        if ($approval && $approval->status === 'approved') {
            abort_unless((int)$source->payment_type === 1 && (int)$source->is_active === 1,409,
                'Approved bank/cheque voucher correction requires a separately audited bank reversal.');
        }
        $actor=auth()->user();
        $module = \App\Support\Finance\FinanceActionPermission::sourceModule($customerLedger);
        abort_unless($actor?->can('finance '.$module.' edit'),403);
        // The Edit grant is independent of voucher creator; project scope is enforced below.
        abort_unless(VoucherApproverAccess::isSuperAdmin($actor)
            || (int)$actor->project_id===$project,403);
        $voucherDisplay=app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)
            ->lookup($project,'customer_ledger',(int)$source->id)
            ?: (($source->transaction_type==='CP'?'DR':$source->transaction_type).'-'.$source->type_id);
        $isBankRevision=in_array((string)($approval?->voucher_type??''),['BOR','BOP'],true);
        $revisionPolicyType=$isBankRevision ? (string)$approval->voucher_type
            : (string)$source->transaction_type;
        // Use the same two-leg identity as the posted ledger writer: the
        // earliest ledger is the party leg, the second is the counter-leg.
        // Historical single-leg vouchers remain unchanged.
        $legs=Ledger::query()->withoutGlobalScopes()
            ->where('customer_ledger_id',$source->id)
            ->where('type',$source->transaction_type)
            ->whereHas('projectHeadSubhead',fn($q)=>$q->where('project_id',$project))
            ->orderBy('id')->get();
        $leg=$legs->first();
        $counterLeg=$legs->count()===2 ? $legs->last() : null;
        $counterAccountId=(!$isBankRevision && $counterLeg)
            ? (int)$counterLeg->project_head_subheads_id : null;
        $bankCounterLabel=null;
        if($isBankRevision && $counterLeg
            && Schema::hasTable('finance_bank_voucher_allocations')
            && Schema::hasTable('project_bank_accounts')){
            $allocation=DB::table('finance_bank_voucher_allocations')
                ->where('project_id',$project)
                ->where('voucher_type',$revisionPolicyType)
                ->where('voucher_id',$source->id)->first(['bank_account_id']);
            if($allocation && (int)$allocation->bank_account_id>0){
                $bank=DB::table('project_bank_accounts')
                    ->where('project_id',$project)
                    ->where('id',$allocation->bank_account_id)
                    ->where('account_mapping_id',$counterLeg->project_head_subheads_id)
                    ->first(['bank_name','account_title']);
                if($bank){
                    $bankCounterLabel=trim((string)$bank->bank_name.' / '.(string)$bank->account_title,' /');
                }
            }
        }
        $accounts=DB::table('project_head_subheads as p')
            ->leftJoin('head_accountings as h','h.id','=','p.head_accounting_id')
            ->leftJoin('subhead_accountings as s','s.id','=','p.subhead_accounting_id')
            ->where('p.project_id',$project)->orderBy('h.name')->orderBy('s.name')
            ->get(['p.id','p.head_accounting_id','p.subhead_accounting_id','h.acct_type','h.name as head_name','s.name as sub_name']);
        $accountTypes=\App\Models\AccountType::query()->where('status',1)->orderBy('id')->get(['id','name']);
        $currentAttachment=null;
        if (Schema::hasTable('cash_voucher_attachments')
            && Schema::hasColumn('cash_voucher_attachments','status')) {
            $currentAttachment=DB::table('cash_voucher_attachments')
                ->where('project_id',$project)->where('customer_ledger_id',$source->id)
                ->where('status','applied')->orderByDesc('id')
                ->first(['id','original_name']);
        }
        return view('admin.finance.voucher.rejected_cash_revision',
            compact('source','approval','leg','accounts','accountTypes',
                'currentAttachment','voucherDisplay','isBankRevision','revisionPolicyType',
                'counterLeg','counterAccountId','bankCounterLabel'));
    }
    public function store(int $customerLedger, Request $request)
    {
        $data=$request->validate([
            'date'=>['required','date_format:Y-m-d'],
            'amount'=>['required','numeric','gt:0','max:999999999999'],
            'account_id'=>['required','integer'],
            'counter_account_id'=>['nullable','integer','min:1'],
            'description'=>['required','string','max:255'],
            'revision_reason'=>['required','string','min:3','max:500'],
            'attachment'=>\App\Services\Finance\VoucherAttachmentRules::optional(),
            't_number'=>['required_if:payment_type,2,3','nullable','string','max:255'],
            'bank_id'=>['nullable','integer','min:1'],
            'passing_date'=>['nullable','date_format:Y-m-d'],
            'cheque_purpose'=>['nullable','string','max:500'],
        ]);
        $source=CustomerLedger::query()->where('project_id',(int)getSelectedTown())
            ->whereKey($customerLedger)->firstOrFail();
        abort_unless(in_array((string)$source->transaction_type,['CR','CP','CCR','CDR'],true),404);
        if (in_array((int)$source->payment_type,[2,3],true)) {
            $request->validate([
                't_number'=>['required','string','max:255'],
                'bank_id'=>['required','integer','min:1'],
                'passing_date'=>['required','date_format:Y-m-d'],
                'cheque_purpose'=>[(int)$source->payment_type===3?'required':'nullable','string','max:500'],
            ]);
        }
        $data=array_merge($data,$request->only(['t_number','bank_id','passing_date','cheque_purpose']));
        $project=(int)getSelectedTown();
        $actor=auth()->user();
        $module = \App\Support\Finance\FinanceActionPermission::sourceModule($customerLedger);
        abort_unless($actor?->can('finance '.$module.' edit') &&
            (VoucherApproverAccess::isSuperAdmin($actor) || (int)$actor->project_id===$project),403);
        $tracked=DailyVoucherApproval::query()->where('project_id',$project)
            ->where('source_table','customer_ledger')->where('source_id',$source->id)->first();
        // Finance Edit permission is role-based, not limited to original submitter.
        $path=null;
        if ($request->hasFile('attachment')) {
            // Shared private attachment lifecycle for all six voucher series.
            abort_unless(Schema::hasTable('cash_voucher_attachments')
                && Schema::hasColumn('cash_voucher_attachments','status')
                && Schema::hasColumn('cash_voucher_attachments','edit_snapshot_id'),409,
                'Apply the cash voucher edit-attachment migration.');
            $path=$request->file('attachment')->store('private/voucher-attachments/'.$project,'local');
            abort_unless($path,422,'Attachment could not be saved.');
            $data['attachment_metadata']=[
                'path'=>$path,'disk'=>'local',
                'original_name'=>mb_substr($request->file('attachment')->getClientOriginalName(),0,255),
                'mime_type'=>$request->file('attachment')->getMimeType(),
                'size'=>$request->file('attachment')->getSize(),
            ];
        }
        try {
            $saved=app(\App\Services\Finance\SameSourceCashVoucherEdit::class)
                ->submit($project,$customerLedger,(int)$actor->id,$data);
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            if ($e instanceof \DomainException)
                return response()->json(['message'=>$e->getMessage()],409);
            throw $e;
        }
        return response()->json([
            'message'=>$saved['pending']
                ? 'Same voucher submitted as Pending. Number and source ID unchanged; original posting protected until approval.'
                : 'Same voucher updated directly. Number and source ID unchanged; audit snapshot saved.',
            'approval_pending'=>$saved['pending'],
            'source_id'=>$saved['source_id'],
            'voucher_number'=>$saved['voucher_number'],
        ]);
    }
}
