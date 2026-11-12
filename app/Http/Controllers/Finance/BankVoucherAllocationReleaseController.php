<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\BankVoucherAllocationRelease;
use App\Services\Finance\VoucherApproverAccess;
use Illuminate\Http\Request;

final class BankVoucherAllocationReleaseController extends Controller
{
    public function store(Request $request,int $customerLedger)
    {
        $actor=$request->user();
        abort_unless($actor && $actor->can('delete voucher')
            && VoucherApproverAccess::isSuperAdmin($actor),403);
        $project=(int)getSelectedTown();
        abort_unless($project>0,403);
        $data=$request->validate([
            'reason'=>['required','string','min:3','max:500'],
        ]);
        try {
            $result=app(BankVoucherAllocationRelease::class)
                ->release($project,$customerLedger,(int)$actor->id,$data['reason']);
        }catch(\DomainException $e){
            return response()->json(['status'=>'error','message'=>$e->getMessage()],409);
        }
        return response()->json([
            'status'=>'success',
            'message'=>$result['action']==='rejected_release'
                ? 'Rejected bank voucher allocation released with an immutable audit record.'
                : 'Posted bank voucher administratively voided and its allocation released with an immutable audit record.',
            'data'=>$result,
        ])->header('Cache-Control','private, no-store');
    }
}
