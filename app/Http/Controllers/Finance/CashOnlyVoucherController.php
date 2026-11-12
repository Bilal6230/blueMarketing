<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CustomerLedger;
use App\Models\DraftLedger;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;
use App\Services\Finance\DailyVoucherApprovalWorkflow;
use App\Services\Finance\ProjectVoucherAllocator;
use App\Services\Finance\ProjectVoucherDisplayIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

final class CashOnlyVoucherController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(Auth::user()?->can('create voucher'),403);
        $projectId=(int)getSelectedTown();
        abort_unless($projectId>0,403);
        abort_if(\App\Services\Finance\V2OriginalWorkflowAccess::selected(),409,
            'CCR/CDR requires the standard audited voucher posting flow.');
        $data=Validator::make($request->all(),[
            'display_series'=>['required','in:CCR,CDR'],
            'voucher'=>['required','regex:/^(CCR|CDR)-[1-9][0-9]*$/D'],
            'voucher_number'=>['required','same:voucher'],
            'date'=>['required','date'],
            'amount'=>['required',function($key,$value,$fail){
                $money=str_replace(',','',(string)$value);
                if(!preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/D',$money)||(float)$money<=0)
                    $fail('Enter a valid amount greater than zero.');
            }],
            'acct_type'=>['required','integer','min:1'],
            'accounts_id'=>['required','integer','min:1'],
            'subaccounts_id'=>['required','integer','min:1'],
            'detail'=>['required','string','max:255'],
            'payment_type'=>['required','integer','in:1'],
            'attachment'=>['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
        ])->validate();
        $series=$data['display_series'];
        abort_unless(str_starts_with($data['voucher'],$series.'-'),422,'Voucher series mismatch.');
        abort_unless((int)$request->input('payment_type')===1 && !$request->filled('t_number')
            && !$request->filled('bank_id') && !$request->filled('passing_date')
            && !$request->filled('cheque_purpose'),422,'CCR/CDR cannot contain bank or cheque details.');
        $account=ProjectHeadSubhead::availableForPosting()
            ->with('headAccounting')
            ->where('project_id',$projectId)
            ->where('head_accounting_id',$data['accounts_id'])
            ->where('subhead_accounting_id',$data['subaccounts_id'])->first();
        abort_unless($account && (int)$account->headAccounting?->acct_type===(int)$data['acct_type'],
            422,'Select a valid project account and matching account type.');
        $amount=round((float)str_replace(',','',(string)$data['amount']),2);
        $requestedNumber=(int)substr($data['voucher'],strlen($series)+1);
        $uploadedPath=null;
        if($request->hasFile('attachment')) {
            abort_unless(Schema::hasTable('cash_voucher_attachments'),409,
                'Voucher attachment migration is required.');
            $uploadedPath=$request->file('attachment')->store(
                'private/voucher-attachments/'.$projectId,'local');
            if(!$uploadedPath) {
                return response()->json(['status'=>'error','message'=>'Attachment upload failed.'],422);
            }
        }
        try {
            $result=DB::transaction(function()use($request,$projectId,$data,$series,$account,$amount,$requestedNumber,$uploadedPath){
                DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
                    ?:throw new \DomainException('Project not found.');
                $number=app(ProjectVoucherAllocator::class)->next($projectId,$series);
                if($number!==$requestedNumber)
                    throw new \DomainException('Voucher number changed. Refresh the form and try again.');
                $incoming=$series==='CCR';
                $source=CustomerLedger::create([
                    'project_id'=>$projectId,'transaction_type'=>$series,'type_id'=>$number,
                    'reference'=>$request->input('reference'),
                    'customer_id'=>$account->customer_id,'plot_id'=>$account->plot_id,
                    'amount_in'=>$incoming?$amount:0,'amount_out'=>$incoming?0:$amount,
                    'description'=>$data['detail'],'date'=>$data['date'],
                    'payment_type'=>1,'is_active'=>1,'is_approve'=>0,
                ]);
                $ledger=Ledger::create([
                    'customer_ledger_id'=>$source->id,'voucher_number'=>$number,
                    'type'=>$series,'type_id'=>$number,
                    'project_head_subheads_id'=>$account->id,
                    'reference'=>$source->reference,'amount_in'=>$incoming?$amount:0,
                    'amount_out'=>$incoming?0:$amount,'date'=>$data['date'],
                    'detail'=>$data['detail'],'is_active'=>1,'status'=>0,
                    'create_by'=>Auth::id(),'update_by'=>Auth::id(),
                ]);
                $display=app(ProjectVoucherDisplayIdentity::class)
                    ->assign($projectId,'customer_ledger',(int)$source->id,$series,$data['date']);
                $pending=app(DailyVoucherApprovalWorkflow::class)->submitIfRequired(
                    $projectId,$series,'customer_ledger',(int)$source->id,(int)Auth::id());
                if($pending){
                    $source->forceFill(['is_active'=>0,'is_approve'=>0])->save();
                    $ledger->forceFill(['is_active'=>0])->save();
                }
                if($uploadedPath){
                    DB::table('cash_voucher_attachments')->insert([
                        'project_id'=>$projectId,'customer_ledger_id'=>$source->id,
                        'disk'=>'local','path'=>$uploadedPath,
                        'original_name'=>substr($request->file('attachment')->getClientOriginalName(),0,255),
                        'mime_type'=>(string)$request->file('attachment')->getMimeType(),
                        'size'=>$request->file('attachment')->getSize(),'uploaded_by'=>Auth::id(),
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
                return ['source_id'=>$source->id,'number'=>$display ?: $series.'-'.$number,
                    'pending'=>$pending];
            },3);
        } catch(\Throwable $e){
            if($uploadedPath) Storage::disk('local')->delete($uploadedPath);
            if($e instanceof \DomainException) {
                return response()->json(['status'=>'error','message'=>$e->getMessage()],409);
            }
            throw $e;
        }
        return response()->json(['status'=>'success','flow_type'=>$result['pending']?'pending_approval':'posted',
            'message'=>$result['pending']?'Voucher saved Pending Approval.':'Cash voucher saved successfully.',
            'voucher_display'=>$result['number'],'source_id'=>$result['source_id']]);
    }

    /**
     * Drafts never post a source or ledger and never reserve a new sequence.
     * Final Save revalidates the current allocated number.
     */
    public function draft(Request $request)
    {
        abort_unless(Auth::user()?->can('create voucher'),403);
        $projectId=(int)getSelectedTown();
        abort_unless($projectId>0,403);
        $data=$request->validate([
            'display_series'=>['required','in:CCR,CDR'],
            'voucher'=>['required','regex:/^(CCR|CDR)-[1-9][0-9]*$/D'],
            'voucher_number'=>['required','same:voucher'],
            'date'=>['required','date'],
            'amount'=>['required','regex:/^\\d[\\d,]*(?:\\.\\d{1,2})?$/D'],
            'acct_type'=>['required','integer','min:1'],
            'accounts_id'=>['required','integer','min:1'],
            'subaccounts_id'=>['required','integer','min:1'],
            'detail'=>['required','string','max:255'],
            'payment_type'=>['required','in:1'],
            'attachment'=>['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'],
        ]);
        abort_if($request->hasFile('attachment'),422,
            'Please attach the file when saving the final voucher, not the draft.');
        $series=$data['display_series'];
        abort_unless(str_starts_with($data['voucher'],$series.'-'),422);
        abort_unless(!$request->filled('bank_id') && !$request->filled('t_number')
            && !$request->filled('passing_date') && !$request->filled('cheque_purpose'),422);
        $amount=round((float)str_replace(',','',(string)$data['amount']),2);
        abort_unless($amount>0,422,'Amount must be positive.');
        $account=ProjectHeadSubhead::availableForPosting()->with('headAccounting')
            ->where('project_id',$projectId)
            ->where('head_accounting_id',$data['accounts_id'])
            ->where('subhead_accounting_id',$data['subaccounts_id'])->first();
        abort_unless($account && (int)$account->headAccounting?->acct_type===(int)$data['acct_type'],
            422,'Select a matching project account.');
        $draft=DraftLedger::create([
            'project_id'=>$projectId,'transaction_type'=>$series,
            'type'=>$series,'type_id'=>(int)substr($data['voucher'],strlen($series)+1),
            'date'=>$data['date'],'description'=>$data['detail'],'detail'=>$data['detail'],
            'project_head_subheads_id'=>$account->id,
            'customer_id'=>$account->customer_id,'plot_id'=>$account->plot_id,
            'amount_in'=>$series==='CCR'?$amount:0,
            'amount_out'=>$series==='CDR'?$amount:0,
            'payment_type'=>1,'is_active'=>0,'is_approve'=>0,
            'create_by'=>Auth::id(),'update_by'=>Auth::id(),'status'=>'draft',
        ]);
        return response()->json(['status'=>'success','flow_type'=>'draft',
            'message'=>'Cash voucher draft saved. No ledger posting or number reservation occurred.',
            'draft_id'=>$draft->id]);
    }

    public function attachment(int $attachment)
    {
        $actor=Auth::user();
        abort_unless($actor && ($actor->can('read voucher')
            || \App\Services\Finance\VoucherApproverAccess::mayApprove($actor)),403);
        $projectId=(int)getSelectedTown();
        abort_unless($projectId>0 && Schema::hasTable('cash_voucher_attachments')
            && Schema::hasColumn('cash_voucher_attachments','status'),404);
        $row=DB::table('cash_voucher_attachments as file')
            ->join('customer_ledger as source','source.id','=','file.customer_ledger_id')
            ->where('file.id',$attachment)
            ->where('file.project_id',$projectId)
            ->where('source.project_id',$projectId)
            ->whereIn('file.status',['applied','pending'])
            ->whereIn('source.transaction_type',['CCR','CDR'])
            ->first(['file.path','file.disk','file.original_name','file.status',
                'file.customer_ledger_id','file.edit_snapshot_id']);
        abort_unless($row && $row->disk==='local' && Storage::disk('local')->exists($row->path),404);
        if($row->status==='pending') {
            $approval=\App\Models\DailyVoucherApproval::query()
                ->where('project_id',$projectId)
                ->where('source_table','customer_ledger')
                ->where('source_id',$row->customer_ledger_id)
                ->where('status','pending')->first();
            abort_unless($approval && $row->edit_snapshot_id
                && DB::table('daily_voucher_edit_snapshots')
                    ->where('id',$row->edit_snapshot_id)
                    ->where('project_id',$projectId)
                    ->where('approval_id',$approval->id)
                    ->where('status','pending')->exists()
                && \App\Services\Finance\VoucherApproverAccess::mayDecide(
                    $actor,$projectId,(int)$approval->assigned_approver_id,(int)$approval->submitted_by),403);
        } else {
            abort_unless($actor->can('read voucher')
                || \App\Services\Finance\VoucherApproverAccess::isSuperAdmin($actor),403);
        }
        return Storage::disk('local')->download($row->path,$row->original_name);
    }
}
