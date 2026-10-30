<?php

namespace App\Services\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * V2 only: verify model ↔ physical schema contracts without reading or
 * changing business rows. Never invoked by existing V1 posting paths.
 */
final class V2ModelSchemaContract
{
    public const MODEL_CLASSES = [
        \App\Models\JournalVoucher::class,
        \App\Models\JournalVoucherDetail::class,
        \App\Models\Ledger::class,
        \App\Models\ProjectHeadSubhead::class,
    ];

    public function inspect(): array
    {
        $db = \Illuminate\Support\Facades\DB::connection();
        $name = (string) $db->getDatabaseName();
        if (!($db->getDriverName() === 'sqlite' && $name === ':memory:')
            && !($db->getDriverName() === 'mysql'
                && str_starts_with($name, 'bluemarketing_v2_')
                && !preg_match('/(?:stag|live|prod|client|dev)/i', $name))) {
            throw new RuntimeException('V2 schema model inspection requires isolated V2 or SQLite memory');
        }
        $findings = [];
        foreach (self::MODEL_CLASSES as $class) {
            /** @var Model $model */
            $model = new $class();
            $table = $model->getTable();
            if (!Schema::hasTable($table)) {
                $findings[$table] = ['table_missing'];
                continue;
            }
            $columns = Schema::getColumnListing($table);
            $missingFillable = array_values(array_diff($model->getFillable(), $columns));
            sort($missingFillable);
            if ($missingFillable !== []) {
                $findings[$table] = $missingFillable;
            }
        }
        return $findings;
    }
}
