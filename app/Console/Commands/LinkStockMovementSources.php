<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit and explicitly backfill EXACT stock line FKs on legacy movements.
 * No amount/date/party matching guesses; never changes inventory balances.
 */
final class LinkStockMovementSources extends Command
{
    protected $signature='finance:link-stock-movement-sources
        {--project= : Only the named project ID}
        {--apply : Update verified link columns (default read-only)}';

    protected $description='Verify and link physical entry/bill source-line FKs on existing stock movements';

    public function handle(): int
    {
        foreach(['stock_entry_line_id','stock_bill_line_id'] as $column){
            if(!Schema::hasColumn('stock_movements',$column)){
                $this->error('Stock movement FK migration is required: '.$column);
                return self::FAILURE;
            }
        }
        $projectOption=$this->option('project');
        $projectIds=$projectOption!==null
            ? [(int)$projectOption] : DB::table('projects')->orderBy('id')->pluck('id')->all();
        if($projectIds===[]||in_array(0,$projectIds,true)){
            $this->error('Select a valid project ID.');
            return self::FAILURE;
        }
        $apply=(bool)$this->option('apply');
        $counts=['eligible'=>0,'linked'=>0,'already_linked'=>0,'invalid'=>0];
        $examples=[];
        foreach($projectIds as $projectId){
            if(!DB::table('projects')->where('id',$projectId)->exists()){
                $this->error('Unknown project ID: '.$projectId);
                return self::FAILURE;
            }
            DB::table('stock_movements')->where('project_id',$projectId)
                ->orderBy('id')->chunkById(200,function($rows)use($projectId,$apply,&$counts,&$examples){
                    foreach($rows as $movement){
                        $type=(string)$movement->source_type;
                        if(!in_array($type,['entry','bill'],true)){
                            $counts['invalid']++;
                            if(count($examples)<10)$examples[]='Movement '.$movement->id.': source type invalid';
                            continue;
                        }
                        $entry=$type==='entry';
                        $sourceLineColumn=$entry?'stock_entry_line_id':'stock_bill_line_id';
                        $otherColumn=$entry?'stock_bill_line_id':'stock_entry_line_id';
                        $rowId=(int)$movement->source_line_id;
                        $lineTable=$entry?'stock_entry_lines':'stock_bill_lines';
                        $headerTable=$entry?'stock_entries':'stock_bills';
                        $parentFK=$entry?'entry_id':'bill_id';
                        // Enforce every available physical relationship rather
                        // than trusting the polymorphic source_line_id alone.
                        $target=DB::table($lineTable.' as line')
                            ->join($headerTable.' as header','header.id','=','line.'.$parentFK)
                            ->where('line.id',$rowId)
                            ->where('line.'.$parentFK,(int)$movement->source_id)
                            ->where('line.project_id',$projectId)
                            ->where('header.project_id',$projectId)
                            ->where('line.item_id',(int)$movement->item_id)
                            ->first(['line.id','line.qty','line.rate_minor','line.amount_minor']);
                        $matches=$target!==null
                            &&(int)$movement->{$otherColumn}===0
                            &&(string)$target->rate_minor===(string)$movement->rate_minor
                            &&(string)$target->amount_minor===(string)$movement->amount_minor
                            &&round((float)$target->qty,3)===round((float)$movement->qty,3)
                            &&((int)($movement->{$sourceLineColumn}??0)===0
                                ||(int)$movement->{$sourceLineColumn}===$rowId);
                        if(!$matches){
                            $counts['invalid']++;
                            if(count($examples)<10)$examples[]='Movement '.$movement->id.
                                ': line/source/project/item/qty/rate/amount mismatch';
                            continue;
                        }
                        if((int)($movement->{$sourceLineColumn}??0)===$rowId){
                            $counts['already_linked']++;
                            continue;
                        }
                        $counts['eligible']++;
                        if(!$apply)continue;
                        $updated=DB::transaction(function()use($projectId,$movement,$sourceLineColumn,$otherColumn,$rowId):int{
                            return DB::table('stock_movements')
                                ->where('id',$movement->id)->where('project_id',$projectId)
                                ->whereNull($sourceLineColumn)->whereNull($otherColumn)
                                ->where('source_id',$movement->source_id)
                                ->where('source_line_id',$rowId)
                                ->update([$sourceLineColumn=>$rowId,'updated_at'=>now()]);
                        },3);
                        if($updated!==1){
                            $counts['invalid']++;
                            if(count($examples)<10)$examples[]='Movement '.$movement->id.
                                ': row changed while applying source link';
                        }else $counts['linked']++;
                    }
                });
        }
        $this->line(($apply?'APPLY':'READ-ONLY').' stock source relationship audit: '
            .json_encode($counts,JSON_THROW_ON_ERROR));
        foreach($examples as $example)$this->warn($example);
        if($counts['invalid']>0){
            $this->error('Unverified stock movements require manual source reconciliation.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
