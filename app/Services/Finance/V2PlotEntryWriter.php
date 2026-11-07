<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Isolated, append-only plot receipts and plot purchase posting. */
final class V2PlotEntryWriter
{
    private function cents(mixed $value, int $scale = 2): int
    {
        $s = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,'. $scale .'}))?$/D', $s, $m)) {
            throw new \DomainException('V2_PLOT_AMOUNT_INVALID');
        }
        return (int) $m[1] * (10 ** $scale) + (int) str_pad($m[2] ?? '', $scale, '0');
    }
    private function money(int $cents): string
    { return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT); }
    private function date(string $value): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new \DomainException('V2_PLOT_DATE_INVALID');
    }
    private function projectAccount(int $project, int $account): bool
    { return \App\Models\ProjectHeadSubhead::availableForPosting()->where('project_id',$project)->where('id',$account)->exists(); }

    private function createAvailablePlot(int $project, array $item, ?int $actor): int
    {
        $type = (int) $item['type'];
        $road = DB::table('roads')->where('id', (int) $item['road_id'])->where('is_active', 1)->exists();
        $facing = DB::table('facings')->where('id', (int) $item['facing_id'])->where('is_active', 1)->exists();
        if (!$road || !$facing || !in_array($type, [1, 2], true)) {
            throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
        }
        $name = trim((string) $item['plot_code']);
        if (DB::table('plots')->where('project_id', $project)->where('name', $name)->lockForUpdate()->exists()) {
            throw new \DomainException('V2_PLOT_CODE_ALREADY_EXISTS');
        }

        $definitionIds = [];
        foreach (['category', 'type', 'size', 'unit'] as $kind) {
            $column = 'plot_'.$kind.'_definition_id';
            if (!Schema::hasColumn('plots', $column)) continue;
            $definitionId = (int) ($item[$column] ?? 0);
            if (!Schema::hasTable('plot_definitions')) {
                if ($definitionId > 0) throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
                $definitionIds[$column] = null;
                continue;
            }
            $hasDefinitions = DB::table('plot_definitions')
                ->where('project_id', $project)->where('kind', $kind)->where('is_active', 1)->exists();
            if ($definitionId < 1) {
                if ($hasDefinitions) throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_REQUIRED');
                $definitionIds[$column] = null;
                continue;
            }
            $definition = DB::table('plot_definitions')->where('id', $definitionId)->where('project_id', $project)
                ->where('kind', $kind)->where('is_active', 1)->first();
            if (!$definition) throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
            $definitionIds[$column] = $definitionId;
            if ($kind === 'type' && (int) $definition->legacy_value !== $type) {
                throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
            }
            if ($kind === 'size' && abs((float) $definition->size_value - (float) $item['area']) > 0.0001) {
                throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
            }
            if ($kind === 'unit' && !empty($item['plot_size_definition_id'])) {
                $size = DB::table('plot_definitions')->where('id', (int) $item['plot_size_definition_id'])
                    ->where('project_id', $project)->where('kind', 'size')->where('is_active', 1)->first();
                if (!$size || (int) $size->unit_definition_id !== $definitionId) {
                    throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
                }
            }
            if ($kind === 'unit') {
                $expectedUnit = (string) ($definition->legacy_value ?: $definition->unit_code);
                if ($expectedUnit !== '' && strcasecmp(trim((string) $item['unit']), $expectedUnit) !== 0) {
                    throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
                }
            }
        }
        if (!empty($item['plot_size_definition_id']) && !empty($item['plot_unit_definition_id'])
            && (int) DB::table('plot_definitions')->where('id', $item['plot_size_definition_id'])->value('unit_definition_id') !== (int) $item['plot_unit_definition_id']) {
            throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
        }

        $plot = [
            'name' => $name, 'type' => (string) $type, 'size' => (string) $item['area'], 'unit' => (string) $item['unit'],
            'description' => trim((string) $item['description']), 'is_corner' => (int) ($item['is_corner'] ?? 0),
            'project_id' => $project, 'road_id' => (int) $item['road_id'], 'facing_id' => (int) $item['facing_id'],
            'is_active' => 1, 'create_by' => $actor,
        ];
        if (Schema::hasColumn('plots', 'sold')) $plot['sold'] = 0;
        // Purchase cost belongs to the purchase source and inventory posting;
        // never copy it into the legacy sale-price field.
        if (Schema::hasColumn('plots', 'amount')) $plot['amount'] = '0.00';
        if (Schema::hasColumn('plots', 'plot_history')) $plot['plot_history'] = null;
        foreach ($definitionIds as $column => $id) $plot[$column] = $id;
        return (int) DB::table('plots')->insertGetId($plot);
    }
    private function existingReplay(int $project, string $key, string $kind, string $hash): ?array
    {
        $batch = DB::table('finance_posting_batches')->where('project_id',$project)->where('request_key',$key)->lockForUpdate()->first();
        if (!$batch) return null;
        $sourceTable = $kind === 'plot_receipt' ? 'finance_plot_receipt_postings' : 'finance_plot_purchase_postings';
        $link = DB::table($sourceTable)->where('posting_batch_id',$batch->id)->first();
        if (!$link || $batch->voucher_series !== ($kind === 'plot_receipt' ? 'PR' : 'PP')
            || !hash_equals((string)$batch->payload_sha256,$hash)) throw new \DomainException('V2_PLOT_REQUEST_CONFLICT');
        return ['source_id'=>(int)$link->source_id,'posting_batch_id'=>(int)$batch->id,'replayed'=>true];
    }
    private function post(int $project, string $key, string $series, string $kind, string $date, array $lines, string $payloadHash, ?int $actor): int
    {
        $debit=0; $credit=0;
        foreach ($lines as $line) { $debit += $this->cents($line['debit']); $credit += $this->cents($line['credit']); }
        if ($debit < 1 || $debit !== $credit) throw new \DomainException('V2_PLOT_POSTING_UNBALANCED');
        $batch=DB::table('finance_posting_batches')->insertGetId([
            'project_id'=>$project,'voucher_series'=>$series,'voucher_number'=>null,'request_key'=>$key,
            'payload_sha256'=>$payloadHash,'effective_date'=>$date,'posted_by'=>$actor,'posted_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('finance_posting_source_claims')->insert(['posting_batch_id'=>$batch,'source_kind'=>$kind]);
        foreach ($lines as $line) DB::table('finance_posting_lines')->insert([
            'posting_batch_id'=>$batch,'project_head_subhead_id'=>$line['account_id'],'debit'=>$line['debit'],
            'credit'=>$line['credit'],'description'=>$line['description'] ?? null,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $batch;
    }

    public function receive(int $project, int $customer, string $key, array $data, ?int $actor): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        $this->date($data['date']); $total=$this->cents($data['amount']);
        if ($project<1 || $customer<1 || $total<1 || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key)) throw new \DomainException('V2_PLOT_RECEIPT_INPUT_INVALID');
        return DB::transaction(function() use($project,$customer,$key,$data,$actor,$total):array {
            DB::table('projects')->where('id',$project)->lockForUpdate()->firstOrFail();
            if (!DB::table('leads')->where('id',$customer)->where('project_id',$project)->exists()) throw new \DomainException('V2_PLOT_CUSTOMER_PROJECT_MISMATCH');
            $bank=(int)$data['cash_account_id'];
            if (!$this->projectAccount($project,$bank)) throw new \DomainException('V2_PLOT_ACCOUNT_PROJECT_MISMATCH');
            if (count($data['allocations'])<2) throw new \DomainException('V2_PLOT_RECEIPT_REQUIRES_MULTIPLE_PLOTS');
            $allocations=[]; $sum=0; $seen=[]; $seenPlots=[]; $lines=[['account_id'=>$bank,'debit'=>$this->money($total),'credit'=>'0.00','description'=>$data['narration']]];
            foreach ($data['allocations'] as $allocation) {
                $bookingId=(int)$allocation['booking_id']; $plotId=(int)$allocation['plot_id']; $amount=$this->cents($allocation['amount']);
                $keyPair=$bookingId.':'.$plotId;
                if ($amount<1 || isset($seen[$keyPair]) || isset($seenPlots[$plotId])) throw new \DomainException('V2_PLOT_ALLOCATION_INVALID');
                $booking=DB::table('bookings')->where('id',$bookingId)->where('project_id',$project)->where('customer_id',$customer)
                    ->where('plot_id',$plotId)->where('status','active')->where('cancel_status','0')->lockForUpdate()->first();
                if (!$booking || !DB::table('plots')->where('id',$plotId)->where('project_id',$project)->exists()) throw new \DomainException('V2_PLOT_BOOKING_PROJECT_MISMATCH');
                $account=(int)($allocation['receivable_account_id']??0);
                if (!$this->projectAccount($project,$account) || !DB::table('project_head_subheads')->where('id',$account)->where('project_id',$project)->where('customer_id',$customer)->where('plot_id',$plotId)->exists()) throw new \DomainException('V2_PLOT_RECEIVABLE_ACCOUNT_MISMATCH');
                if ($account === $bank) throw new \DomainException('V2_PLOT_RECEIPT_ACCOUNT_SIDES_INVALID');
                $sum+=$amount; $seen[$keyPair]=true; $seenPlots[$plotId]=true;
                $allocations[]=['booking_id'=>$bookingId,'plot_id'=>$plotId,'amount'=>$this->money($amount)];
                $lines[]=['account_id'=>$account,'debit'=>'0.00','credit'=>$this->money($amount),'description'=>$data['narration']];
            }
            if ($sum!==$total) throw new \DomainException('V2_PLOT_ALLOCATION_TOTAL_MISMATCH');
            $hash=hash('sha256',json_encode([$project,$customer,$data['date'],$this->money($total),$data['narration'],$bank,$allocations,$lines],JSON_THROW_ON_ERROR));
            $replay=$this->existingReplay($project,$key,'plot_receipt',$hash); if($replay) return $replay;
            $number=trim($data['receipt_number']);
            if(DB::table('finance_plot_receipts')->where('project_id',$project)->where('receipt_number',$number)->exists()) throw new \DomainException('V2_PLOT_RECEIPT_NUMBER_EXISTS');
            $source=DB::table('finance_plot_receipts')->insertGetId(['project_id'=>$project,'customer_id'=>$customer,'receipt_number'=>$number,
                'receipt_date'=>$data['date'],'amount'=>$this->money($total),'narration'=>$data['narration'],'created_by'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            foreach($allocations as $allocation) DB::table('finance_plot_receipt_allocations')->insert($allocation+['receipt_id'=>$source,'created_at'=>now(),'updated_at'=>now()]);
            $batch=$this->post($project,$key,'PR','plot_receipt',$data['date'],$lines,$hash,$actor);
            DB::table('finance_plot_receipt_postings')->insert(['posting_batch_id'=>$batch,'source_id'=>$source,'source_kind'=>'plot_receipt']);
            return ['source_id'=>$source,'posting_batch_id'=>$batch,'replayed'=>false];
        },3);
    }

    public function purchase(int $project, string $key, array $data, ?int $actor): array
    {
        V2IsolatedConnectionGuard::assertAllowed(); $this->date($data['date']);
        if($project<1 || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key) || count($data['items'])<1) throw new \DomainException('V2_PLOT_PURCHASE_INPUT_INVALID');
        return DB::transaction(function()use($project,$key,$data,$actor):array {
            DB::table('projects')->where('id',$project)->lockForUpdate()->firstOrFail();
            $seller=(int)$data['seller_account_id'];
            if(!$this->projectAccount($project,$seller)) throw new \DomainException('V2_PLOT_ACCOUNT_PROJECT_MISMATCH');
            $items=[];$lines=[];$total=0;$codes=[];
            foreach($data['items'] as $item){
                $code=trim($item['plot_code']);$area4=$this->cents($item['area'],4);$rate=$this->cents($item['unit_rate']);$account=(int)$item['inventory_account_id'];
                if($code===''||$area4<1||$rate<1||isset($codes[strtolower($code)])||!$this->projectAccount($project,$account)) throw new \DomainException('V2_PLOT_PURCHASE_ITEM_INVALID');
                if ($area4 > intdiv(PHP_INT_MAX, $rate)) throw new \DomainException('V2_PLOT_PURCHASE_AMOUNT_OVERFLOW');
                $cents=(int)round(($area4*$rate)/10000,0,PHP_ROUND_HALF_UP); if($cents<1) throw new \DomainException('V2_PLOT_PURCHASE_ITEM_INVALID');
                if ($total > PHP_INT_MAX - $cents) throw new \DomainException('V2_PLOT_PURCHASE_AMOUNT_OVERFLOW');
                $codes[strtolower($code)]=true;$total+=$cents;$amount=$this->money($cents);
                $items[]=['plot_code'=>$code,'description'=>trim($item['description']),'area'=>number_format($area4/10000,4,'.',''),'unit'=>$item['unit'],'unit_rate'=>$this->money($rate),'amount'=>$amount,'inventory_account_id'=>$account,
                    'type'=>(int)$item['type'],'road_id'=>(int)$item['road_id'],'facing_id'=>(int)$item['facing_id'],'is_corner'=>(int)($item['is_corner']??0),
                    'plot_category_definition_id'=>isset($item['plot_category_definition_id'])?(int)$item['plot_category_definition_id']:null,
                    'plot_type_definition_id'=>isset($item['plot_type_definition_id'])?(int)$item['plot_type_definition_id']:null,
                    'plot_size_definition_id'=>isset($item['plot_size_definition_id'])?(int)$item['plot_size_definition_id']:null,
                    'plot_unit_definition_id'=>isset($item['plot_unit_definition_id'])?(int)$item['plot_unit_definition_id']:null];
                $lines[]=['account_id'=>$account,'debit'=>$amount,'credit'=>'0.00','description'=>'Plot purchase '.$code.' · '.$item['description']];
            }
            $lines[]=['account_id'=>$seller,'debit'=>'0.00','credit'=>$this->money($total),'description'=>$data['narration']];
            if($total<1) throw new \DomainException('V2_PLOT_PURCHASE_TOTAL_INVALID');
            $hash=hash('sha256',json_encode([$project,$data['date'],$data['purchase_number'],$this->money($total),$seller,$data['narration'],$items,$lines],JSON_THROW_ON_ERROR));
            $replay=$this->existingReplay($project,$key,'plot_purchase',$hash);if($replay)return $replay;
            $number=trim($data['purchase_number']);if(DB::table('finance_plot_purchases')->where('project_id',$project)->where('purchase_number',$number)->exists())throw new \DomainException('V2_PLOT_PURCHASE_NUMBER_EXISTS');
            $source=DB::table('finance_plot_purchases')->insertGetId(['project_id'=>$project,'seller_account_id'=>$seller,'purchase_number'=>$number,'purchase_date'=>$data['date'],
                'amount'=>$this->money($total),'narration'=>$data['narration'],'created_by'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            foreach($items as $item) {
                $plotId = $this->createAvailablePlot($project, $item, $actor);
                $stored = collect($item)->only(['plot_code','description','area','unit','unit_rate','amount','inventory_account_id'])->all();
                DB::table('finance_plot_purchase_items')->insert($stored+['purchase_id'=>$source,'legacy_plot_id'=>$plotId,'created_at'=>now(),'updated_at'=>now()]);
            }
            $batch=$this->post($project,$key,'PP','plot_purchase',$data['date'],$lines,$hash,$actor);
            DB::table('finance_plot_purchase_postings')->insert(['posting_batch_id'=>$batch,'source_id'=>$source,'source_kind'=>'plot_purchase']);
            return ['source_id'=>$source,'posting_batch_id'=>$batch,'replayed'=>false];
        },3);
    }

    public function receipt(int $project,int $id): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        $source=DB::table('finance_plot_receipts')->where('project_id',$project)->where('id',$id)->first();
        if(!$source) throw new \DomainException('V2_PLOT_RECEIPT_NOT_FOUND');
        $allocations=DB::table('finance_plot_receipt_allocations as a')->join('plots as p','p.id','=','a.plot_id')
            ->join('bookings as b','b.id','=','a.booking_id')->join('leads as customer','customer.id','=',$source->customer_id)
            ->where('a.receipt_id',$id)->where('p.project_id',$project)->where('b.project_id',$project)
            ->get(['a.plot_id','p.name as plot_name','a.booking_id','a.amount','customer.first_name','customer.last_name','customer.business']);
        return ['receipt'=>$source,'allocations'=>$allocations];
    }

    public function purchaseDetails(int $project,int $id): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        $source=DB::table('finance_plot_purchases')->where('project_id',$project)->where('id',$id)->first();
        if(!$source) throw new \DomainException('V2_PLOT_PURCHASE_NOT_FOUND');
        return ['purchase'=>$source,'items'=>DB::table('finance_plot_purchase_items')->where('purchase_id',$id)->orderBy('id')->get()];
    }
}
