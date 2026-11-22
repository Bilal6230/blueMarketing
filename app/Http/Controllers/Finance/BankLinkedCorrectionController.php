<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\BankLinkedCorrection;
use App\Services\Finance\VoucherApproverAccess;
use App\Services\Finance\BankLinkedCorrectionApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deliberately separate from verified Replace/Withdraw.
 * Independent permissions govern correction submission and approval decisions.
 * Financial changes are applied only via the guarded same-ID writer.
 */
final class BankLinkedCorrectionController extends Controller
{
    private function authorizeCorrection(Request $request, string $method): int
    {
        abort_unless(in_array($method,['online','cheque'],true),404);
        $user=$request->user();
        abort_unless($user && $user->can('finance '.$method.' correct'),403);
        $project=(int)getSelectedTown();
        abort_unless($project>0 && DB::table('projects')->where('id',$project)->exists(),403);
        return $project;
    }

    private function validated(Request $request, bool $confirm): array
    {
        $rules=[
            'amount'=>['required','numeric','gt:0','max:9999999999999.99','decimal:0,2'],
            'vouchers'=>['sometimes','array','max:500'],
            'vouchers.*'=>['required','numeric','gt:0','max:9999999999999.99','decimal:0,2'],
        ];
        if ($confirm) {
            $rules['reason']=['required','string','min:3','max:500'];
            $rules['fingerprint']=['required','string','regex:/^[a-f0-9]{64}$/D'];
        }
        return $request->validate($rules);
    }

    public function preview(Request $request,string $method,int $id,BankLinkedCorrection $correction)
    {
        $project=$this->authorizeCorrection($request,$method);
        $data=$this->validated($request,false);
        try {
            $plan=$correction->preview($project,$method,$id,$data['amount'],$data['vouchers']??[]);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['source'=>$e->getMessage()]);
        }
        return response()->json(['status'=>'success','data'=>$plan])
            ->header('Cache-Control','private, no-store');
    }

    public function confirm(Request $request,string $method,int $id,BankLinkedCorrectionApproval $approval)
    {
        $project=$this->authorizeCorrection($request,$method);
        $data=$this->validated($request,true);
        try {
            $result=$approval->submit($project,$method,$id,(int)$request->user()->id,
                $data['reason'],$data['amount'],$data['vouchers']??[],$data['fingerprint']);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['source'=>$e->getMessage()]);
        }
        return response()->json([
            'status'=>'success','message'=>$result['pending']
                ? 'Correction submitted for approval. Original source and linked vouchers remain unchanged.'
                : 'Source and linked voucher amounts corrected atomically; original IDs preserved.',
            'data'=>$result,
        ])->header('Cache-Control','private, no-store');
    }

    public function pending(Request $request)
    {
        abort_unless($request->user()?->can('finance online correction approve')
            || $request->user()?->can('finance cheque correction approve'),403);
        $project=(int)getSelectedTown();
        abort_unless($project>0,403);
        $rows=DB::table('finance_bank_correction_requests')
            ->where('project_id',$project)->where('status','pending')
            ->orderBy('created_at')->limit(100)->get()
            ->filter(function($row) use($request) {
                return $request->user()->can('finance '.$row->source_method.' correction approve')
                    && (VoucherApproverAccess::isSuperAdmin($request->user())
                        || (int)$row->assigned_approver_id===(int)$request->user()->id);
            })->values();
        return response()->json(['data'=>$rows])->header('Cache-Control','private, no-store');
    }

    public function decide(Request $request,int $requestId,BankLinkedCorrectionApproval $approval)
    {
        $data=$request->validate([
            'decision'=>['required','in:approved,rejected'],
            'reason'=>['nullable','string','min:3','max:500']
        ]);
        $project=(int)getSelectedTown();
        abort_unless($project>0,403);
        $item=DB::table('finance_bank_correction_requests')
            ->where('project_id',$project)->where('id',$requestId)->first();
        abort_unless($item,404);
        abort_unless($request->user()?->can('finance '.$item->source_method.' correction approve'),403);
        try {
            $result=$approval->decide($project,$requestId,(int)$request->user()->id,
                $data['decision'],$data['reason']??null);
        } catch (\\DomainException $e) {
            throw ValidationException::withMessages(['source'=>$e->getMessage()]);
        }
        return response()->json(['status'=>'success','data'=>$result])
            ->header('Cache-Control','private, no-store');
    }
}
