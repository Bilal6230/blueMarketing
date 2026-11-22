<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\BankLinkedCorrection;
use App\Services\Finance\VoucherApproverAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deliberately separate from verified Replace/Withdraw.
 * Only a privileged financial corrector can review and atomically modify
 * already-posted same-ID bank-linked allocations.
 */
final class BankLinkedCorrectionController extends Controller
{
    private function authorizeCorrection(Request $request, string $method): int
    {
        abort_unless(in_array($method,['online','cheque'],true),404);
        $user=$request->user();
        abort_unless($user && $user->can('finance '.$method.' edit')
            && $user->can('delete voucher')
            && VoucherApproverAccess::isSuperAdmin($user),403);
        $project=(int)getSelectedTown();
        abort_unless($project>0 && DB::table('projects')->where('id',$project)->exists(),403);
        return $project;
    }

    private function validated(Request $request, bool $confirm): array
    {
        $rules=[
            'amount'=>['required','numeric','gt:0','max:9999999999999.99','decimal:0,2'],
            'vouchers'=>['required','array','min:1','max:500'],
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
            $plan=$correction->preview($project,$method,$id,$data['amount'],$data['vouchers']);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['source'=>$e->getMessage()]);
        }
        return response()->json(['status'=>'success','data'=>$plan])
            ->header('Cache-Control','private, no-store');
    }

    public function confirm(Request $request,string $method,int $id,BankLinkedCorrection $correction)
    {
        $project=$this->authorizeCorrection($request,$method);
        $data=$this->validated($request,true);
        try {
            $result=$correction->confirm($project,$method,$id,(int)$request->user()->id,
                $data['reason'],$data['amount'],$data['vouchers'],$data['fingerprint']);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['source'=>$e->getMessage()]);
        }
        return response()->json([
            'status'=>'success','message'=>'Original bank source and linked voucher amounts corrected atomically; all original IDs preserved.',
            'data'=>$result,
        ])->header('Cache-Control','private, no-store');
    }
}
