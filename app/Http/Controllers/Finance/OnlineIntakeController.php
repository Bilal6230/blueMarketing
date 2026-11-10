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
            'remarks'=>['nullable','string','max:2000'],
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
                'remarks'=>$data['remarks']??null,'proof_path'=>$path,
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
