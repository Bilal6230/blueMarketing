<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\DailyVoucherApprovalPolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Project-scoped policy configuration. Intentionally enables only the posting
 * engines that already honour DailyVoucherApprovalWorkflow::submitIfRequired.
 * Other voucher types are visible but locked until a dedicated guarded writer
 * is delivered and browser-tested in its own later milestone.
 */
final class VoucherSettingsController extends Controller
{
    private const TYPES = [
        'CR' => ['label'=>'Cash Receipt Voucher', 'supported'=>true],
        'CP' => ['label'=>'Cash Payment Voucher', 'supported'=>true],
        'JV' => ['label'=>'Journal Voucher', 'supported'=>true],
        'CCR' => ['label'=>'Cash Credit Voucher', 'supported'=>true],
        'CDR' => ['label'=>'Cash Debit Voucher', 'supported'=>true],
        'BOR' => ['label'=>'Bank Receipt Voucher', 'supported'=>true],
        'BOP' => ['label'=>'Bank Payment Voucher', 'supported'=>true],
        'BD' => ['label'=>'Bank Deposit Voucher', 'supported'=>true],
        'BW' => ['label'=>'Bank Withdrawal Voucher', 'supported'=>true],
        'DR' => ['label'=>'Debit Voucher', 'supported'=>true],
        'CV' => ['label'=>'Credit / Commission Voucher', 'supported'=>true],
        'PPR' => ['label'=>'Plot Receive Payment Voucher', 'supported'=>true],
        'PP' => ['label'=>'Plot Purchase Voucher', 'supported'=>true],
        'SV' => ['label'=>'Sales Voucher', 'supported'=>true],
        // Cash EC/ECP can have typed reversals, but their approval lifecycle
        // is not managed by the generic voucher approval form.
        'EC' => ['label'=>'Extra Charge', 'supported'=>false],
        'ECP' => ['label'=>'Extra Charge Payment', 'supported'=>false],
        // These posted source series are reversible via the unified finance
        // API, without generic Daily Voucher approval controls.
        'LAB' => ['label'=>'Labour Posting', 'supported'=>false],
        'ST-IN' => ['label'=>'Stock Entry In', 'supported'=>false],
        'ST-OUT' => ['label'=>'Stock Entry Out', 'supported'=>false],
        'SB-PUR' => ['label'=>'Stock Purchase Bill', 'supported'=>false],
        'SB-SALE' => ['label'=>'Stock Sale Bill', 'supported'=>false],
        'ONL_CORR' => ['label'=>'Online Transaction Correction', 'supported'=>true],
        'CHQ_CORR' => ['label'=>'Cheque Correction', 'supported'=>true],
    ];

    private function authorizeSettings(): int
    {
        $user=auth()->user();
        abort_unless($user && $user->can('read setting') &&
            \App\Services\Finance\VoucherApproverAccess::isSuperAdmin($user),403);
        $project=(int)getSelectedTown();
        abort_unless($project>0 && DB::table('projects')->where('id',$project)->exists(),403);
        abort_unless(Schema::hasTable('daily_voucher_approval_policies'),409,
            'Run the existing Daily Voucher Approval migration first.');
        return $project;
    }

    public function index()
    {
        $project=$this->authorizeSettings();
        $policies=DailyVoucherApprovalPolicy::query()->where('project_id',$project)->get()->keyBy('voucher_type');
        $approvers=User::query()->where('project_id',$project)->where('status_id',1)
            ->orderBy('name')->get(['id','name','project_id','status_id'])->filter(
                fn(User $user)=>\App\Services\Finance\VoucherApproverAccess::eligibleForAssignment($user,$project)
            )->values();
        $types=self::TYPES;
        return view('admin.finance.voucher.settings',compact('policies','approvers','types'));
    }

    public function save(Request $request)
    {
        $project=$this->authorizeSettings();
        abort_unless($request->user()->can('update setting'),403);
        $data=$request->validate([
            'voucher_type'=>['required',Rule::in(array_keys(self::TYPES))],
            'approval_required'=>['nullable','boolean'],
            'approver_user_id'=>['nullable','integer','min:1'],
        ]);
        $type=$data['voucher_type'];
        if(!self::TYPES[$type]['supported']){
            throw ValidationException::withMessages([
                'voucher_type'=>'This voucher type is not yet connected to the guarded approval posting engine. Its policy cannot be enabled.'
            ]);
        }
        $required=$request->boolean('approval_required');
        $approverId=$required?($data['approver_user_id']??null):null;
        if($required){
            $approver=$approverId?User::query()->where('project_id',$project)
                ->where('status_id',1)->whereKey($approverId)->first():null;
            if(!$approver||!\App\Services\Finance\VoucherApproverAccess::eligibleForAssignment($approver,$project)){
                throw ValidationException::withMessages([
                    'approver_user_id'=>'Select an active authorized finance approver from the current project.'
                ]);
            }
            // Assignment belongs to voucher type, not to a particular submitter.
            // Self-approval is restricted for non-superadmin actors at decision time.
        }
        DailyVoucherApprovalPolicy::query()->updateOrCreate(
            ['project_id'=>$project,'voucher_type'=>$type],
            ['approval_required'=>$required,'approver_user_id'=>$approverId,
                'updated_by'=>(int)$request->user()->id]
        );
        return response()->json([
            'success'=>true,
            'message'=>$type.' approval policy saved for the selected project. Changes apply to new correction requests or vouchers only.',
            'data'=>['voucher_type'=>$type,'approval_required'=>$required,'approver_user_id'=>$approverId],
        ]);
    }
    /**
     * Reversal is an independent switch, never an alias for approval or Delete.
     * The posting writer enforces OFF for verified typed postings.
     */
    public function saveReversal(Request $request)
    {
        $project=$this->authorizeSettings();
        abort_unless($request->user()->can('update setting'),403);
        abort_unless(Schema::hasColumn('daily_voucher_approval_policies','reversal_enabled'),409,
            'Run the Milestone 05 reversal policy migration first.');
        $data=$request->validate([
            'voucher_type'=>['required',Rule::in(array_keys(self::TYPES))],
            'reversal_enabled'=>['required','boolean'],
        ]);
        $type=$data['voucher_type'];
        if (in_array($type,['CHQ_CORR','ONL_CORR'],true)) {
            throw ValidationException::withMessages([
                'voucher_type'=>'Management source corrections use the linked custody workflow, not voucher reversal settings.',
            ]);
        }
        // Avoid resetting an existing approver/approval configuration.
        $policy=DailyVoucherApprovalPolicy::query()->firstOrCreate(
            ['project_id'=>$project,'voucher_type'=>$type],
            ['approval_required'=>false,'approver_user_id'=>null,
                'updated_by'=>(int)$request->user()->id]
        );
        $policy->reversal_enabled=(bool)$data['reversal_enabled'];
        $policy->updated_by=(int)$request->user()->id;
        $policy->save();

        return response()->json([
            'success'=>true,
            'message'=>$type.' reversal '.($policy->reversal_enabled?'enabled':'disabled').' for the selected project. Existing posted records are unchanged.',
            'data'=>['voucher_type'=>$type,'reversal_enabled'=>(bool)$policy->reversal_enabled],
        ]);
    }

}
