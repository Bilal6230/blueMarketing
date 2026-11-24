<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same-source correction/ordinary archive of a legacy posted stock document.
 *
 * Exact movement.source_line_id and customer_ledger -> ledgers FK ownership
 * are mandatory. Typed approved stock batches have a different accounting
 * owner and are never silently altered through this legacy path.
 */
final class StockDocumentEditor
{
    private function qty(mixed $value): int
    {
        $s=trim((string)$value);
        if(!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,3}))?$/D',$s,$m)) {
            throw new \DomainException('Stock quantity is invalid.');
        }
        return (int)$m[1]*1000+(int)str_pad($m[2]??'',3,'0');
    }

    private function qtyText(int $qty): string
    {
        return intdiv($qty,1000).'.'.str_pad((string)($qty%1000),3,'0',STR_PAD_LEFT);
    }

    private function money(int $minor): string
    {
        return intdiv($minor,100).'.'.str_pad((string)($minor%100),2,'0',STR_PAD_LEFT);
    }

    private function parseMoney(mixed $amount): int
    {
        $text=(string)$amount;
        if(!preg_match('/^([0-9]{1,13})(?:\.([0-9]{1,2}))?$/D',$text,$m))
            throw new \DomainException('Stock posting amount is invalid.');
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    private function schema(): void
    {
        foreach([
            ['stock_entry_lines','deleted_at'],
            ['stock_bill_lines','deleted_at'],
            ['stock_movements','is_active'],
            ['stock_movements','deleted_at'],
            ['stock_movements','source_line_id'],
            ['stock_bills','deleted_at'],
            ['stock_entries','deleted_at'],
        ] as [$table,$field]){
            if(!Schema::hasTable($table)||!Schema::hasColumn($table,$field)) {
                throw new \DomainException('Stock document lifecycle migration or source-line FK is missing.');
            }
        }
    }

    /** @return array{0:object,1:\Illuminate\Support\Collection,2:\Illuminate\Support\Collection,3:object,4:\Illuminate\Support\Collection,5:string,6:string,7:string} */
    private function source(int $projectId,string $kind,int $sourceId): array
    {
        $entry=$kind==='entry';
        $headerTable=$entry?'stock_entries':'stock_bills';
        $lineTable=$entry?'stock_entry_lines':'stock_bill_lines';
        $sourceColumn=$entry?'entry_id':'bill_id';
        $header=DB::table($headerTable)->where('id',$sourceId)
            ->where('project_id',$projectId)->whereNull('deleted_at')
            ->lockForUpdate()->first();
        if(!$header)throw new \DomainException('Stock voucher is not active in this project.');
        if($entry&&($header->billed_at!==null
            ||DB::table('stock_bills')->where('entry_id',$sourceId)
                ->where('project_id',$projectId)->whereNull('deleted_at')->exists())) {
            throw new \DomainException('Stock entry is already linked to a bill; edit the linked document first.');
        }
        $direction=$entry?(string)$header->flow:
            ((string)$header->type==='purchase'?'IN':'OUT');
        if(!in_array($direction,['IN','OUT'],true)) {
            throw new \DomainException('Stock source direction is invalid.');
        }
        if(!$entry&&!in_array((string)$header->type,['purchase','sale'],true))
            throw new \DomainException('Stock bill source type is invalid.');
        $date=(string)($entry?$header->entry_date:$header->bill_date);
        $reference=($entry?'STOCK-ENTRY:':'STOCK-BILL:').$sourceId;
        $typedTable=$entry?'finance_posting_stock_entry_sources':'finance_posting_stock_bill_sources';
        $typedFk=$entry?'stock_entry_id':'stock_bill_id';
        if(Schema::hasTable($typedTable)
            &&DB::table($typedTable)->where($typedFk,$sourceId)->exists()) {
            throw new \DomainException('This stock document has a typed financial batch owner.');
        }
        $lines=DB::table($lineTable)->where($sourceColumn,$sourceId)
            ->where('project_id',$projectId)->whereNull('deleted_at')
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if($lines->isEmpty())throw new \DomainException('Stock document has no current source details.');
        $moves=DB::table('stock_movements')->where('source_type',$kind)
            ->where('source_id',$sourceId)->where('project_id',$projectId)
            ->where('is_active',1)->orderBy('id')->lockForUpdate()
            ->get()->groupBy('source_line_id');
        if($moves->flatten(1)->count()!==$lines->count()) {
            throw new \DomainException('Stock detail-to-movement FK counts are inconsistent.');
        }
        $oldTotal=0;
        foreach($lines as $id=>$line) {
            $move=$moves->get($id,collect());
            if($move->count()!==1
                ||(int)$move[0]->item_id!==(int)$line->item_id
                ||(string)$move[0]->direction!==$direction
                ||$this->qty($move[0]->qty)!==$this->qty($line->qty)
                ||(int)$move[0]->rate_minor!==(int)$line->rate_minor
                ||(int)$move[0]->amount_minor!==(int)$line->amount_minor
                ||(int)$move[0]->party_id!==(int)$header->party_id
                ||$this->qty($line->qty)<1) {
                throw new \DomainException('Stock source line or original movement has been altered.');
            }
            $oldTotal+=(int)$line->amount_minor;
        }
        if($oldTotal<1||(!$entry&&(int)$header->total_minor!==$oldTotal)) {
            throw new \DomainException('Stock source financial total does not match details.');
        }
        $customers=DB::table('customer_ledger')
            ->where('project_id',$projectId)->where('transaction_type','JV')
            ->where('reference',$reference)->lockForUpdate()->get();
        if($customers->count()!==1||(int)$customers[0]->is_active!==1
            ||(string)$customers[0]->date!==$date) {
            throw new \DomainException('Stock source has no unique active financial voucher.');
        }
        $customer=$customers[0];
        // Old stock writers formatted the minor-unit total as whole rupees,
        // causing a 100x financial amount. Accept that exact legacy signature
        // only when the original source and both GL legs agree; an Edit then
        // normalizes the historical posting to the corrected amount.
        $postedTotal=$direction==='OUT'
            ?$this->parseMoney($customer->amount_in)
            :$this->parseMoney($customer->amount_out);
        $legacyHundredfold=$oldTotal<=intdiv(PHP_INT_MAX,100)
            &&$postedTotal===$oldTotal*100;
        if(($postedTotal!==$oldTotal&&!$legacyHundredfold)
            ||($direction==='OUT'?$this->parseMoney($customer->amount_out)
                :$this->parseMoney($customer->amount_in))!==0) {
            throw new \DomainException('Stock source customer-ledger amount is inconsistent.');
        }
        $legs=DB::table('ledgers as ledger')
            ->join('project_head_subheads as account','account.id','=','ledger.project_head_subheads_id')
            ->where('ledger.customer_ledger_id',$customer->id)
            ->where('account.project_id',$projectId)->lockForUpdate()
            ->get(['ledger.id','ledger.type','ledger.reference','ledger.type_id',
                'ledger.voucher_number','ledger.is_active','ledger.date',
                'ledger.amount_in','ledger.amount_out']);
        if($legs->count()!==2 ||DB::table('ledgers')
            ->where('customer_ledger_id',$customer->id)->count()!==2
            ||$legs->contains(fn($leg)=>(string)$leg->type!=='JV'
                ||(string)$leg->reference!==$reference
                ||(string)$leg->date!==$date||(int)$leg->is_active!==1)
            ||$legs->pluck('type_id')->unique()->count()!==1
            ||$legs->pluck('voucher_number')->unique()->count()!==1
            ||$this->parseMoney($legs->sum('amount_in'))!==$postedTotal
            ||$this->parseMoney($legs->sum('amount_out'))!==$postedTotal
            ||$legs->filter(fn($leg)=>$this->parseMoney($leg->amount_in)>0
                &&$this->parseMoney($leg->amount_out)===0)->count()!==1
            ||$legs->filter(fn($leg)=>$this->parseMoney($leg->amount_out)>0
                &&$this->parseMoney($leg->amount_in)===0)->count()!==1) {
            throw new \DomainException('Stock source ledger FK or balanced debit/credit legs differ.');
        }
        return [$header,$lines,$moves,$customer,$legs,$direction,$date,$reference];
    }

    private function balanceChanges(int $projectId,string $direction,
        array $oldQty,array $newQty): array
    {
        $ids=array_values(array_unique(array_merge(array_keys($oldQty),array_keys($newQty))));
        sort($ids);
        $balances=DB::table('stock_project_item_balances')
            ->where('project_id',$projectId)->whereIn('item_id',$ids)
            ->orderBy('item_id')->lockForUpdate()->get()->keyBy('item_id');
        $result=[];
        foreach($ids as $id){
            $current=$balances->get($id);
            $was=$current?$this->qty($current->on_hand_qty):0;
            // Before applying a delta, verify the materialized project stock
            // balance still agrees with *all* active movements for this item.
            // Never compound a pre-existing inconsistency by editing a voucher.
            $sum=DB::table('stock_movements')
                ->where('project_id',$projectId)->where('item_id',$id)
                ->where('is_active',1)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction='IN' THEN qty ELSE -qty END),0) AS net_qty")
                ->value('net_qty');
            $expected=(int)round((float)$sum*1000);
            if($expected!==$was) {
                throw new \DomainException('Stock balance and active movement totals disagree for item '.$id.'.');
            }
            $shift=($direction==='IN'?1:-1)
                *(($newQty[$id]??0)-($oldQty[$id]??0));
            if($was+$shift<0) {
                throw new \DomainException('Stock correction would cause negative inventory for item '.$id.'.');
            }
            $result[$id]=[$this->qtyText($was+$shift),(bool)$current];
        }
        return $result;
    }

    public function update(int $projectId,string $kind,int $sourceId,string $date,
        array $submitted,?string $narration=null): void
    {
        FinanceConnectionGuard::assertAllowed();
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if($projectId<1||$sourceId<1||!in_array($kind,['entry','bill'],true)
            ||!$day||$day->format('Y-m-d')!==$date
            ||count($submitted)<1||count($submitted)>100) {
            throw new \DomainException('Stock correction source, date or row count is invalid.');
        }
        $this->schema();
        DB::transaction(function()use($projectId,$kind,$sourceId,$date,$submitted,$narration):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            [$header,$old,$moves,$customer,$legs,$direction,$oldDate,$reference]
                =$this->source($projectId,$kind,$sourceId);
            $entry=$kind==='entry';$lineTable=$entry?'stock_entry_lines':'stock_bill_lines';
            $headerTable=$entry?'stock_entries':'stock_bills';
            $lineFk=$entry?'entry_id':'bill_id';
            $all=[];$used=[];$newQty=[];$oldQty=[];$total=0;
            foreach($old as $line){
                $oldQty[(int)$line->item_id]=($oldQty[(int)$line->item_id]??0)+$this->qty($line->qty);
            }
            foreach($submitted as $row){
                $id=(int)($row['detail_id']??0);
                if($id&&(!$old->has($id)||isset($used[$id]))) {
                    throw new \DomainException('Stock detail ID belongs to another voucher or is duplicated.');
                }
                if($id)$used[$id]=true;
                $item=(int)($row['item_id']??0);
                $qty=$this->qty($row['qty']??'');
                $rate=$row['rate']??null;
                if($item<1||$qty<1||!is_numeric($rate)||!preg_match('/^[0-9]{1,10}$/D',(string)$rate)) {
                    throw new \DomainException('Stock item, quantity or rate is invalid.');
                }
                if(!DB::table('stock_project_item_definitions')
                    ->where('project_id',$projectId)->where('item_id',$item)
                    ->where('is_active',1)->whereNull('deleted_at')->exists()) {
                    throw new \DomainException('Stock item is missing or inactive in this project.');
                }
                if((int)$rate>0 && $qty>intdiv(PHP_INT_MAX,(int)$rate)) {
                    throw new \DomainException('Stock quantity and rate multiplication would overflow.');
                }
                $amount=intdiv($qty*(int)$rate,1000);
                if($total>PHP_INT_MAX-$amount) {
                    throw new \DomainException('Stock voucher total would overflow.');
                }
                $total+=$amount;
                $newQty[$item]=($newQty[$item]??0)+$qty;
                $all[]=['id'=>$id,'item_id'=>$item,'qty'=>$qty,
                    'rate_minor'=>(int)$rate,'amount_minor'=>$amount];
            }
            if($total<1)throw new \DomainException('Stock corrected financial total must be positive.');
            $same=count($all)===$old->count()&&$oldDate===$date
                &&($narration===null||trim($narration)===(string)($entry?$header->reason:''));
            if($same){
                foreach($all as $row){
                    $past=$old->get($row['id']);
                    if(!$past||(int)$past->item_id!==$row['item_id']
                        ||$this->qty($past->qty)!==$row['qty']
                        ||(int)$past->rate_minor!==$row['rate_minor']
                        ||(int)$past->amount_minor!==$row['amount_minor']){
                        $same=false;break;
                    }
                }
            }
            if($same&&!$legacyHundredfold)return;
            $newBalance=$this->balanceChanges($projectId,$direction,$oldQty,$newQty);
            $now=now();
            foreach($old as $id=>$previous){
                if(isset($used[$id]))continue;
                DB::table($lineTable)->where('id',$id)->where($lineFk,$sourceId)
                    ->where('project_id',$projectId)->whereNull('deleted_at')
                    ->update(['deleted_at'=>$now,'updated_at'=>$now]);
                DB::table('stock_movements')->where('id',$moves[$id][0]->id)
                    ->where('project_id',$projectId)->where('source_line_id',$id)
                    ->where('is_active',1)
                    ->update(['is_active'=>0,'deleted_at'=>$now,'updated_at'=>$now]);
            }
            foreach($all as $row){
                $payload=['item_id'=>$row['item_id'],
                    'qty'=>$this->qtyText($row['qty']),
                    'rate_minor'=>$row['rate_minor'],
                    'amount_minor'=>$row['amount_minor'],'updated_at'=>$now];
                if($row['id']){
                    DB::table($lineTable)->where('id',$row['id'])
                        ->where($lineFk,$sourceId)->where('project_id',$projectId)
                        ->whereNull('deleted_at')->update($payload);
                    DB::table('stock_movements')->where('id',$moves[$row['id']][0]->id)
                        ->where('project_id',$projectId)->where('source_line_id',$row['id'])
                        ->where('is_active',1)
                        ->update($payload+['movement_date'=>$date]);
                }else{
                    $lineId=DB::table($lineTable)->insertGetId($payload+[
                        $lineFk=>$sourceId,'project_id'=>$projectId,'created_at'=>$now,
                    ]);
                    $move=['project_id'=>$projectId,'movement_date'=>$date,
                        'direction'=>$direction,'item_id'=>$row['item_id'],
                        'qty'=>$this->qtyText($row['qty']),
                        'rate_minor'=>$row['rate_minor'],'amount_minor'=>$row['amount_minor'],
                        'source_type'=>$kind,'source_id'=>$sourceId,'source_line_id'=>$lineId,
                        'party_id'=>$header->party_id,'created_at'=>$now,'updated_at'=>$now,
                        'is_active'=>1];
                    $extra=$entry?'stock_entry_line_id':'stock_bill_line_id';
                    if(Schema::hasColumn('stock_movements',$extra))$move[$extra]=$lineId;
                    DB::table('stock_movements')->insert($move);
                }
            }
            foreach($newBalance as $itemId=>[$qty,$exists]){
                if($exists){
                    DB::table('stock_project_item_balances')
                        ->where('project_id',$projectId)->where('item_id',$itemId)
                        ->update(['on_hand_qty'=>$qty,'updated_at'=>$now]);
                }else{
                    DB::table('stock_project_item_balances')->insert([
                        'project_id'=>$projectId,'item_id'=>$itemId,
                        'on_hand_qty'=>$qty,'created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }
            $narration=$narration===null?(string)($customer->description??'')
                : trim($narration);
            $money=$this->money($total);
            $dateField=$entry?'entry_date':'bill_date';
            $headerUpdate=[$dateField=>$date,'updated_at'=>$now];
            if($entry){
                if($narration!==null)$headerUpdate['reason']=$narration;
            }else $headerUpdate['total_minor']=$total;
            DB::table($headerTable)->where('id',$sourceId)
                ->where('project_id',$projectId)->whereNull('deleted_at')
                ->update($headerUpdate);
            DB::table('customer_ledger')->where('id',$customer->id)
                ->where('project_id',$projectId)->where('is_active',1)
                ->update(['date'=>$date,'description'=>$narration,
                    'amount_in'=>$direction==='OUT'?$money:'0.00',
                    'amount_out'=>$direction==='IN'?$money:'0.00',
                    'updated_at'=>$now]);
            foreach($legs as $leg){
                $debit=$this->parseMoney($leg->amount_in)>0;
                DB::table('ledgers')->where('id',$leg->id)
                    ->where('customer_ledger_id',$customer->id)->where('is_active',1)
                    ->update(['date'=>$date,'detail'=>$narration,
                        'amount_in'=>$debit?$money:'0.00',
                        'amount_out'=>$debit?'0.00':$money,
                        'updated_at'=>$now]);
            }
        },3);
    }

    public function delete(int $projectId,string $kind,int $sourceId): void
    {
        FinanceConnectionGuard::assertAllowed();
        if($projectId<1||$sourceId<1||!in_array($kind,['entry','bill'],true))
            throw new \DomainException('Stock deletion source is invalid.');
        $this->schema();
        DB::transaction(function()use($projectId,$kind,$sourceId):void {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            [$header,$lines,$moves,$customer,$legs,$direction]
                =$this->source($projectId,$kind,$sourceId);
            $entry=$kind==='entry';$lineTable=$entry?'stock_entry_lines':'stock_bill_lines';
            $lineFk=$entry?'entry_id':'bill_id';$headerTable=$entry?'stock_entries':'stock_bills';
            $oldQty=[];foreach($lines as $line){
                $oldQty[(int)$line->item_id]=($oldQty[(int)$line->item_id]??0)+$this->qty($line->qty);
            }
            $newBalance=$this->balanceChanges($projectId,$direction,$oldQty,[]);
            $now=now();
            foreach($newBalance as $itemId=>[$qty,$exists]){
                if($exists)DB::table('stock_project_item_balances')
                    ->where('project_id',$projectId)->where('item_id',$itemId)
                    ->update(['on_hand_qty'=>$qty,'updated_at'=>$now]);
            }
            DB::table('stock_movements')->where('source_type',$kind)
                ->where('source_id',$sourceId)->where('project_id',$projectId)
                ->whereIn('source_line_id',$lines->keys()->all())->where('is_active',1)
                ->update(['is_active'=>0,'deleted_at'=>$now,'updated_at'=>$now]);
            DB::table($lineTable)->where($lineFk,$sourceId)
                ->whereIn('id',$lines->keys()->all())->where('project_id',$projectId)
                ->whereNull('deleted_at')->update(['deleted_at'=>$now,'updated_at'=>$now]);
            DB::table('ledgers')->where('customer_ledger_id',$customer->id)
                ->whereIn('id',$legs->pluck('id')->all())->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>$now]);
            DB::table('customer_ledger')->where('id',$customer->id)
                ->where('project_id',$projectId)->where('is_active',1)
                ->update(['is_active'=>0,'updated_at'=>$now]);
            DB::table($headerTable)->where('id',$sourceId)
                ->where('project_id',$projectId)->whereNull('deleted_at')
                ->update(['deleted_at'=>$now,'updated_at'=>$now]);
            if(!$entry&&$header->entry_id!==null){
                $other=DB::table('stock_bills')->where('project_id',$projectId)
                    ->where('entry_id',$header->entry_id)->whereNull('deleted_at')->exists();
                if(!$other){
                    DB::table('stock_entries')->where('id',$header->entry_id)
                        ->where('project_id',$projectId)
                        ->update(['billed_at'=>null,'updated_at'=>$now]);
                }
            }
        },3);
    }
}
