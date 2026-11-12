<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ProjectHeadSubhead;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class OnlineIntakeController extends Controller
{
    private function project(): int
    {
        $project=(int)getSelectedTown();
        abort_unless($project>0 && DB::table('projects')->where('id',$project)->exists(),403);
        return $project;
    }

    private function ready(): void
    {
        abort_unless(Schema::hasTable('finance_online_intakes'),409,
            'Online transaction migration has not been applied on this database.');
    }

    public function index()
    {
        $this->ready();
        $project=$this->project();
        $mappings=ProjectHeadSubhead::availableForPosting()
            ->with(['headAccounting:id,name','subheadAccounting:id,name'])
            ->where('project_id',$project)
            ->orderBy('head_accounting_id')->orderBy('subhead_accounting_id')->get()
            ->map(fn($entry)=>[
                'id'=>(int)$entry->id,
                'head_id'=>(int)$entry->head_accounting_id,
                'head_name'=>(string)($entry->headAccounting?->name ?? ''),
                'child_id'=>(int)$entry->subhead_accounting_id,
                'child_name'=>(string)($entry->subheadAccounting?->name ?? ''),
            ]);
        $heads=$mappings->unique('head_id')->values();
        return view('admin.finance.online.intake',compact('mappings','heads'));
    }

    public function data(Request $request)
    {
        $this->ready();
        $project=$this->project();
        $input=$request->validate([
            'from'=>['nullable','date_format:Y-m-d'],
            'to'=>['nullable','date_format:Y-m-d','after_or_equal:from'],
            'type'=>['nullable',Rule::in(['receipt','payment'])],
            'status'=>['nullable',Rule::in(['pending','confirmed'])],
            'account_mapping_id'=>['nullable','integer',Rule::exists('project_head_subheads','id')->where('project_id',$project)],
            'search'=>['nullable','string','max:150'],
            'column_transaction_date'=>['nullable','date_format:Y-m-d'],
            'column_reference'=>['nullable','string','max:120'],
            'column_type'=>['nullable',Rule::in(['receipt','payment'])],
            'column_sending_bank'=>['nullable','string','max:150'],
            'column_receiving_bank'=>['nullable','string','max:150'],
            'column_party'=>['nullable','string','max:150'],
            'column_purpose'=>['nullable','string','max:150'],
            'column_amount'=>['nullable','numeric','decimal:0,2','min:0'],
            'column_status'=>['nullable',Rule::in(['pending','confirmed'])],
            'per_page'=>['nullable','integer','in:10,25,50,100'],
            'page'=>['nullable','integer','min:1'],
        ]);
        $query=DB::table('finance_online_intakes')->where('project_id',$project);
        if(!empty($input['from']))$query->whereDate('transaction_date','>=',$input['from']);
        if(!empty($input['to']))$query->whereDate('transaction_date','<=',$input['to']);
        if(!empty($input['type']))$query->where('type',$input['type']);
        if(!empty($input['status']))$query->where('status',$input['status']);
        if(!empty($input['account_mapping_id']))$query->where('account_mapping_id',(int)$input['account_mapping_id']);
        if(!empty($input['search'])){
            $search='%'.$input['search'].'%';
            $query->where(function($q)use($search){
                $q->where('reference','like',$search)
                  ->orWhere('sending_bank','like',$search)
                  ->orWhere('receiving_bank','like',$search)
                  ->orWhere('sender_name','like',$search)
                  ->orWhere('receiver_name','like',$search)
                  ->orWhere('purpose','like',$search);
            });
        }
        // Column filters must apply in SQL before pagination (not only to visible rows).
        if(!empty($input['column_transaction_date'])) {
            $query->whereDate('transaction_date',$input['column_transaction_date']);
        }
        foreach(['reference','sending_bank','receiving_bank','purpose'] as $column) {
            $key='column_'.$column;
            if(isset($input[$key]) && $input[$key]!=='') {
                $query->where($column,'like','%'.$input[$key].'%');
            }
        }
        if(!empty($input['column_type'])) $query->where('type',$input['column_type']);
        if(!empty($input['column_status'])) $query->where('status',$input['column_status']);
        if(isset($input['column_amount']) && $input['column_amount']!=='') {
            $query->where('amount',$input['column_amount']);
        }
        if(!empty($input['column_party'])) {
            $party='%'.$input['column_party'].'%';
            $query->where(function($q)use($party){
                $q->where('sender_name','like',$party)
                  ->orWhere('receiver_name','like',$party);
            });
        }
        $page=$query->orderByDesc('id')->paginate((int)($input['per_page']??25),[
            'id','reference','transaction_date','amount','type','sending_bank','receiving_bank',
            'sender_name','receiver_name','purpose','status','account_mapping_id','proof_path'
        ],'page',(int)($input['page']??1));
        $rows=collect($page->items())->map(function($row){
            $row->has_proof=(bool)$row->proof_path;
            $row->proof_url=$row->has_proof
                ? route('finance.online.proof',['id'=>$row->id]) : null;
            unset($row->proof_path);
            return $row;
        })->all();
        return response()->json([
            'data'=>$rows,'total'=>$page->total(),
            'current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),
            'per_page'=>$page->perPage(),'from'=>$page->firstItem(),'to'=>$page->lastItem(),
        ])->header('Cache-Control','private, no-store');
    }

    public function store(Request $request)
    {
        $this->ready();
        $project=$this->project();
        $data=$request->validate([
            'reference'=>['required','string','max:120','regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/D'],
            'transaction_date'=>['required','date_format:Y-m-d'],
            'amount'=>['required','numeric','decimal:0,2','gt:0','max:9999999999999.99'],
            'type'=>['required',Rule::in(['receipt','payment'])],
            'sending_bank'=>['required','string','max:150'],
            'sending_account'=>['required','string','max:150'],
            'receiving_bank'=>['required','string','max:150'],
            'receiving_account'=>['required','string','max:150'],
            'sender_name'=>['required','string','max:150'],
            'receiver_name'=>['required','string','max:150'],
            'head_accounting_id'=>['required','integer'],
            'subhead_accounting_id'=>['required','integer'],
            'purpose'=>['required','string','max:500'],
            'proof'=>['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
        ]);
        $mapping=ProjectHeadSubhead::availableForPosting()
            ->where('project_id',$project)
            ->where('head_accounting_id',(int)$data['head_accounting_id'])
            ->where('subhead_accounting_id',(int)$data['subhead_accounting_id'])->first();
        if(!$mapping){
            throw ValidationException::withMessages([
                'subhead_accounting_id'=>'Select an active Head / Child Account for this project.'
            ]);
        }
        $reference=trim($data['reference']);
        $referenceKey=mb_strtoupper($reference,'UTF-8');
        if(DB::table('finance_online_intakes')->where('project_id',$project)
            ->where('reference_key',$referenceKey)->exists()){
            throw ValidationException::withMessages([
                'reference'=>'Transaction reference already exists for this project.'
            ]);
        }
        $path=null;
        try {
            if($request->hasFile('proof')) {
                $path=$request->file('proof')->store('finance/online-intakes/'.$project.'/proofs','local');
            }
            // Unique project/reference_key DB index is authoritative under concurrent requests.
            $id=DB::table('finance_online_intakes')->insertGetId([
                'project_id'=>$project,'reference'=>$reference,'reference_key'=>$referenceKey,
                'transaction_date'=>$data['transaction_date'],'amount'=>$data['amount'],
                'type'=>$data['type'],'sending_bank'=>trim($data['sending_bank']),
                'sending_account'=>trim($data['sending_account']),
                'receiving_bank'=>trim($data['receiving_bank']),
                'receiving_account'=>trim($data['receiving_account']),
                'sender_name'=>trim($data['sender_name']),'receiver_name'=>trim($data['receiver_name']),
                'account_mapping_id'=>$mapping->id,'purpose'=>trim($data['purpose']),
                'proof_path'=>$path,
                'status'=>'pending','created_by'=>(int)$request->user()->id,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        } catch (QueryException $exception) {
            if($path)Storage::disk('local')->delete($path);
            if(in_array((string)$exception->getCode(),['23000','23505'],true)){
                throw ValidationException::withMessages([
                    'reference'=>'Duplicate transaction reference; please check the register.'
                ]);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if($path)Storage::disk('local')->delete($path);
            throw $exception;
        }
        return response()->json([
            'status'=>'success',
            'message'=>'Online transaction recorded as Pending. No bank voucher or ledger entry was posted.',
            'data'=>['id'=>$id,'reference'=>$reference,'status'=>'pending'],
        ],201);
    }

    public function confirm(Request $request, int $id)
    {
        $this->ready();
        abort_unless($request->user()?->can('create voucher'),403);
        abort_unless(Schema::hasTable('finance_online_confirmation_events'),409,
            'Online confirmation history migration has not been applied.');
        $project=$this->project();
        $data=$request->validate([
            'statement_verified'=>['required','accepted'],
            'statement_reference'=>['required','string','max:150'],
            'verification_date'=>['required','date_format:Y-m-d'],
            'remarks'=>['nullable','string','max:2000'],
        ]);
        $reference=trim($data['statement_reference']);
        if($reference==='') {
            throw ValidationException::withMessages(['statement_reference'=>'Bank statement reference is required.']);
        }

        $result=DB::transaction(function()use($request,$project,$id,$data,$reference){
            $record=DB::table('finance_online_intakes')
                ->where('project_id',$project)->where('id',$id)->lockForUpdate()->first();
            abort_unless($record,404);
            if($record->status!=='pending'){
                throw ValidationException::withMessages(['status'=>'Only Pending online transactions can be confirmed.']);
            }
            if($data['verification_date']<$record->transaction_date){
                throw ValidationException::withMessages([
                    'verification_date'=>'Verification date cannot be before the transaction date.'
                ]);
            }

            // The confirmed amount, bank names, type and account all come from the locked
            // source record, never from submitted form fields.
            DB::table('finance_online_confirmation_events')->insert([
                'project_id'=>$project,'online_intake_id'=>$id,
                'from_status'=>'pending','to_status'=>'confirmed',
                'verification_date'=>$data['verification_date'],
                'statement_reference'=>$reference,
                'remarks'=>$data['remarks']??null,
                'original_amount'=>$record->amount,
                'transaction_type'=>$record->type,
                'actor_id'=>(int)$request->user()->id,
                'confirmed_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            $changed=DB::table('finance_online_intakes')
                ->where('project_id',$project)->where('id',$id)->where('status','pending')
                ->update(['status'=>'confirmed','updated_at'=>now()]);
            if($changed!==1){
                throw ValidationException::withMessages(['status'=>'Transaction has already been processed.']);
            }
            return ['id'=>(int)$record->id,'reference'=>$record->reference,
                'status'=>'confirmed','amount'=>$record->amount,'type'=>$record->type];
        },3);

        return response()->json([
            'status'=>'success',
            'message'=>'Bank statement verification recorded. Online transaction Confirmed; no accounting voucher was posted.',
            'data'=>$result,
        ])->header('Cache-Control','private, no-store');
    }

    public function eligible(Request $request)
    {
        $this->ready();
        abort_unless(Schema::hasTable('finance_online_confirmation_events')
            && Schema::hasTable('finance_online_voucher_claims'),409,
            'Online eligibility migration has not been applied.');
        $project=$this->project();
        $input=$request->validate([
            'type'=>['nullable',Rule::in(['receipt','payment'])],
            'search'=>['nullable','string','max:120'],
            'per_page'=>['nullable','integer','in:10,25,50'],
            'page'=>['nullable','integer','min:1'],
        ]);
        $query=DB::table('finance_online_intakes as i')
            ->where('i.project_id',$project)
            ->where('i.status','confirmed')
            ->whereExists(function($q)use($project){
                $q->selectRaw('1')->from('finance_online_confirmation_events as e')
                    ->whereColumn('e.online_intake_id','i.id')
                    ->where('e.project_id',$project)
                    ->where('e.from_status','pending')
                    ->where('e.to_status','confirmed')
                    ->whereColumn('e.original_amount','i.amount')
                    ->whereColumn('e.transaction_type','i.type');
            })
            ->whereNotExists(function($q)use($project){
                $q->selectRaw('1')->from('finance_online_voucher_claims as l')
                    ->whereColumn('l.online_intake_id','i.id')
                    ->where('l.project_id',$project);
            });
        if(!empty($input['type'])) $query->where('i.type',$input['type']);
        if(!empty($input['search'])) {
            $query->where('i.reference','like','%'.$input['search'].'%');
        }
        $page=$query->orderByDesc('i.id')->paginate((int)($input['per_page']??25),[
            'i.id','i.project_id','i.reference','i.transaction_date','i.type',
            'i.amount','i.sending_bank','i.sending_account','i.receiving_bank',
            'i.receiving_account','i.sender_name','i.receiver_name','i.account_mapping_id',
            'i.purpose','i.status',
        ],'page',(int)($input['page']??1));
        return response()->json([
            'data'=>$page->items(),
            'total'=>$page->total(),
            'current_page'=>$page->currentPage(),
            'last_page'=>$page->lastPage(),
            'per_page'=>$page->perPage(),
            'message'=>'Read-only verified sources. No bank voucher is created from this endpoint.',
        ])->header('Cache-Control','private, no-store');
    }

    public function history(int $id)
    {
        $this->ready();
        $project=$this->project();
        abort_unless(Schema::hasTable('finance_online_confirmation_events'),409,
            'Online confirmation history migration has not been applied.');
        $record=DB::table('finance_online_intakes')
            ->where('project_id',$project)->where('id',$id)
            ->first(['id','reference','amount','type','status','transaction_date','created_by','created_at']);
        abort_unless($record,404);
        $eligibleForFutureVoucher=false;
        $eligibilityReason=$record->status==='pending'
            ? 'Pending bank verification.'
            : 'Confirmation evidence or voucher-claim migration unavailable.';
        if(Schema::hasTable('finance_online_voucher_claims')){
            $linked=DB::table('finance_online_voucher_claims')
                ->where('project_id',$project)->where('online_intake_id',$id)->exists();
            $evidence=DB::table('finance_online_confirmation_events')
                ->where('project_id',$project)->where('online_intake_id',$id)
                ->where('from_status','pending')->where('to_status','confirmed')
                ->where('original_amount',$record->amount)->where('transaction_type',$record->type)->exists();
            $eligibleForFutureVoucher=$record->status==='confirmed'&&$evidence&&!$linked;
            $eligibilityReason=$linked
                ? 'Source already linked to a voucher; it cannot be reused.'
                : (!$evidence?'Confirmed bank-verification evidence is missing.'
                    : ($record->status!=='confirmed'?'Only Confirmed sources are eligible.'
                       :'Verified source is available for a future bank voucher.'));
        }
        $events=DB::table('finance_online_confirmation_events as e')
            ->leftJoin('users as u','u.id','=','e.actor_id')
            ->where('e.project_id',$project)->where('e.online_intake_id',$id)
            ->orderBy('e.id')
            ->get(['e.id','e.from_status','e.to_status','e.verification_date',
                'e.statement_reference','e.remarks','e.original_amount',
                'e.transaction_type','e.actor_id','e.confirmed_at','u.name as actor_name']);
        return response()->json([
            'transaction'=>$record,'events'=>$events,
            'linked_vouchers'=>app(\App\Services\Finance\BankSourceLinkedVoucherHistory::class)
                ->forSource($project,'online',$id),
            'eligibility'=>[
                'eligible'=>$eligibleForFutureVoucher,
                'reason'=>$eligibilityReason,
                'voucher_posted_by_this_module'=>false,
            ]
        ])
            ->header('Cache-Control','private, no-store');
    }

    public function proof(int $id)
    {
        $this->ready();
        $project=$this->project();
        $record=DB::table('finance_online_intakes')->where('project_id',$project)->where('id',$id)
            ->first(['proof_path']);
        abort_unless($record&&$record->proof_path,404);
        abort_unless(Storage::disk('local')->exists($record->proof_path),404);
        return Storage::disk('local')->download($record->proof_path);
    }
}
