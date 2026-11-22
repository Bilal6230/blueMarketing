<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\BankManagementSourceMutation;
use App\Services\Finance\VoucherApproverAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BankManagementMutationController extends Controller
{
    private function project(): int
    {
        $project = (int) getSelectedTown();
        abort_unless($project > 0 && DB::table('projects')->where('id',$project)->exists(),403);
        return $project;
    }

    private function authorizeAction(Request $request,string $method,string $action,bool $linked=false): void
    {
        $user=$request->user();
        abort_unless($user && $user->can('finance '.$method.' '.$action),403);
        if ($linked) {
            // A linked source's withdrawal can void posted accounting entries.
            // Match the existing Super Admin-only allocation release policy.
            abort_unless($user->can('delete voucher')
                && VoucherApproverAccess::isSuperAdmin($user),403);
        }
    }

    public function show(Request $request,string $method,int $id)
    {
        abort_unless(in_array($method,['online','cheque'],true),404);
        $this->authorizeAction($request,$method,'edit');
        $project=$this->project();
        $table=$method==='online'?'finance_online_intakes':'finance_cheque_intakes';
        $record=DB::table($table)->where('project_id',$project)->where('id',$id)->first();
        abort_unless($record,404);
        $impact=app(\App\Services\Finance\BankManagementCascadePlanner::class)
            ->inspect($project,$method,$id);
        return response()->json(['data'=>$record,'cascade_impact'=>$impact])
            ->header('Cache-Control','private, no-store');
    }

    public function update(Request $request,string $method,int $id)
    {
        abort_unless(in_array($method,['online','cheque'],true),404);
        $this->authorizeAction($request,$method,'edit');
        $project=$this->project();
        $input=$method==='online'
            ? $request->validate([
                'reference'=>['required','string','max:120','regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/D'],
                'transaction_date'=>['required','date_format:Y-m-d'],
                'amount'=>['required','numeric','gt:0','max:9999999999999.99','decimal:0,2'],
                'type'=>['required',Rule::in(['receipt','payment'])],
                'sending_bank'=>['required','string','max:150'],
                'sending_account'=>['required','string','max:150'],
                'receiving_bank'=>['required','string','max:150'],
                'receiving_account'=>['required','string','max:150'],
                'sender_name'=>['required','string','max:150'],
                'receiver_name'=>['required','string','max:150'],
                'purpose'=>['required','string','max:500'],
                'account_mapping_id'=>['required','integer','min:1'],
            ])
            : $request->validate([
                'cheque_number'=>['required','string','max:100','regex:/^[A-Za-z0-9\/_-]+$/D'],
                'cheque_title'=>['required','string','max:200'],
                'direction'=>['required',Rule::in(['in','out'])],
                'cheque_date'=>['required','date_format:Y-m-d'],
                'received_date'=>['required','date_format:Y-m-d'],
                'amount'=>['required','numeric','gt:0','max:9999999999999.99','decimal:0,2'],
                'bank_name'=>['required','string','max:150'],
                'branch'=>['nullable','string','max:150'],
                'received_from'=>['required','string','max:150'],
                'purpose'=>['required','string','max:500'],
                'account_mapping_id'=>['required','integer','min:1'],
            ]);
        $linked=\Illuminate\Support\Facades\Schema::hasTable('finance_bank_voucher_allocations')
            && DB::table('finance_bank_voucher_allocations')->where('project_id',$project)
                ->where('source_method',$method)->where('source_id',$id)->exists();
        $table=$method==='online'?'finance_online_intakes':'finance_cheque_intakes';
        $status=DB::table($table)->where('project_id',$project)->where('id',$id)->value('status');
        abort_unless($status!==null,404);
        $verified=($method==='online' && $status==='confirmed')
            || ($method==='cheque' && $status==='cleared');
        $replacement=$linked || $verified;
        if ($replacement) {
            $this->authorizeAction($request,$method,'delete',true);
            $reason=$request->validate(['reason'=>['required','string','min:3','max:500']])['reason'];
        }
        try {
            $updated=$replacement
                ? app(BankManagementSourceMutation::class)->replace(
                    $project,$method,$id,(int)$request->user()->id,$reason,$input)
                : app(BankManagementSourceMutation::class)->update($project,$method,$id,$input);
        } catch (\DomainException $error) {
            throw ValidationException::withMessages(['source'=>$error->getMessage()]);
        }
        return response()->json(['status'=>'success',
            'message'=>$replacement
                ? 'Original verified source archived and linked postings released if present; NEW unconfirmed replacement requires bank verification.'
                : 'Bank management source updated without changing any voucher posting.',
            'data'=>$updated]);
    }

    public function destroy(Request $request,string $method,int $id)
    {
        abort_unless(in_array($method,['online','cheque'],true),404);
        $this->authorizeAction($request,$method,'delete');
        $project=$this->project();
        $input=$request->validate(['reason'=>['required','string','min:3','max:500']]);
        // The authorization decision and source mutation share the project
        // lock. No concurrent voucher writer may insert an allocation between
        // checking privileges and performing a linked cascade.
        try {
            $result=DB::transaction(function()use($request,$method,$id,$project,$input) {
                DB::table('projects')->where('id',$project)->lockForUpdate()->first()
                    ?: abort(403);
                abort_unless(\Illuminate\Support\Facades\Schema::hasTable('finance_bank_voucher_allocations'),409,
                    'Bank allocation evidence is unavailable.');
                $linked=DB::table('finance_bank_voucher_allocations')
                    ->where('project_id',$project)->where('source_method',$method)
                    ->where('source_id',$id)->exists();
                if ($linked) $this->authorizeAction($request,$method,'delete',true);
                return app(BankManagementSourceMutation::class)->withdraw(
                    $project,$method,$id,(int)$request->user()->id,$input['reason']);
            },3);
        } catch (\DomainException $error) {
            return response()->json(['status'=>'error','message'=>$error->getMessage()],409);
        }
        return response()->json([
            'status'=>'success','message'=>'Bank source withdrawn. Linked postings released where verified; originals retained for audit.',
            'data'=>$result,
        ])->header('Cache-Control','private, no-store');
    }
}
