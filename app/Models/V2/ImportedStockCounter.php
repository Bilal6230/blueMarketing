<?php

namespace App\Models\V2;



/** Read-only exact physical source 'stock_counters' in isolated V2. */
final class ImportedStockCounter extends ImportedHistoricalModel
{
    protected $table = 'stock_counters';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';


    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }

}
