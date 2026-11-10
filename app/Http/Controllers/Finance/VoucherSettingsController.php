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
        'CCR' => ['label'=>'Cash Credit Voucher', 'supported'=>false],
        'CDR' => ['label'=>'Cash Debit Voucher', 'supported'=>false],
        'BOR' => ['label'=>'Bank Receipt Voucher', 'supported'=>false],
        'BOP' => ['label'=>'Bank Payment Voucher', 'supported'=>false],
        'BD' => ['label'=>'Bank Deposit Voucher', 'supported'=>false],
        'BW' => ['label'=>'Bank Withdrawal Voucher', 'supported'=>false],
        'DR' => ['label'=>'Debit Voucher', 'supported'=>false],
        'CV' => ['label'=>'Credit / Commission Voucher', 'supported'=>false],
        'PPR' => ['label'=>'Plot Receive Payment Voucher', 'supported'=>false],
        'PP' => ['label'=>'Plot Purchase Voucher', 'supported'=>false],
        'SV' => ['label'=>'Plot Sale Voucher', 'supported'=>false],
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
            ->orderBy('name')->get(['id','name'])->filter(
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
            'message'=>$type.' approval policy saved for the selected project. Changes apply to new vouchers only.',
            'data'=>['voucher_type'=>$type,'approval_required'=>$required,'approver_user_id'=>$approverId],
        ]);
    }
}
