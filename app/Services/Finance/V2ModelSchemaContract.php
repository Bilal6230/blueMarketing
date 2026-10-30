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

    /**
     * Detect relationship foreign-key drift as well as fillable drift.
     * The relationship is inspected without executing business queries.
     */
    public function inspectRelationships(): array
    {
        $this->assertIsolatedDatabase();
        $relationships = [
            [\App\Models\JournalVoucher::class, 'details'],
            [\App\Models\JournalVoucherDetail::class, 'journalVoucher'],
            [\App\Models\JournalVoucherDetail::class, 'account'],
            [\App\Models\Ledger::class, 'projectHeadSubhead'],
            [\App\Models\Ledger::class, 'customerLedger'],
            [\App\Models\ProjectHeadSubhead::class, 'ledgers'],
        ];
        $findings = [];
        foreach ($relationships as [$class, $method]) {
            $model = new $class();
            $relation = $model->$method();
            $foreignKey = $relation->getForeignKeyName();
            $related = $relation->getRelated();
            $owner = $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo
                ? $model : $related;
            if (!Schema::hasColumn($owner->getTable(), $foreignKey)) {
                $findings[] = $class . '::' . $method . ':' . $owner->getTable() . '.' . $foreignKey;
            }
            if (!Schema::hasColumn($related->getTable(), $related->getKeyName())) {
                $findings[] = $class . '::' . $method . ':related_pk_missing';
            }
        }
        return $findings;
    }

    private function assertIsolatedDatabase(): void
    {
        $db = \Illuminate\Support\Facades\DB::connection();
        $name = (string) $db->getDatabaseName();
        if (!($db->getDriverName() === 'sqlite' && $name === ':memory:')
            && !($db->getDriverName() === 'mysql'
                && str_starts_with($name, 'bluemarketing_v2_')
                && !preg_match('/(?:stag|live|prod|client|dev)/i', $name))) {
            throw new RuntimeException('V2 schema model inspection requires isolated V2 or SQLite memory');
        }
    }

    public function inspect(): array
    {
        $this->assertIsolatedDatabase();
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
