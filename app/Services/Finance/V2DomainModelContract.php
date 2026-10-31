<?php

namespace App\Services\Finance;

use App\Models\V2\ImportedHistoricalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;

/**
 * Full physically grounded V2 read-model contract for finance, booking,
 * stock and labour source evidence. Does not certify V1 CRUD compatibility.
 * No original business row is altered or exposed.
 */
final class V2DomainModelContract
{
    public const MODELS = [
        \App\Models\V2\ImportedJournalVoucher::class,
        \App\Models\V2\ImportedJournalVoucherDetail::class,
        \App\Models\V2\ImportedLedger::class,
        \App\Models\V2\ImportedProjectAccount::class,
        \App\Models\V2\ImportedCustomerLedger::class,
        \App\Models\V2\ImportedBookingVoucher::class,
        \App\Models\V2\ImportedBooking::class,
        \App\Models\V2\ImportedCommissionVoucher::class,
        \App\Models\V2\ImportedCommissionVoucherDetail::class,
        \App\Models\V2\ImportedStockEntry::class,
        \App\Models\V2\ImportedStockEntryLine::class,
        \App\Models\V2\ImportedStockBill::class,
        \App\Models\V2\ImportedStockBillLine::class,
        \App\Models\V2\ImportedStockParty::class,
        \App\Models\V2\ImportedStockItem::class,
        \App\Models\V2\ImportedLabourLedger::class,
        \App\Models\V2\ImportedLabour::class,
        \App\Models\V2\ImportedLabourAttendance::class,
        \App\Models\V2\ImportedLead::class,
        \App\Models\V2\ImportedPlot::class,
        \App\Models\V2\ImportedStockCounter::class,
        \App\Models\V2\ImportedStockItemBalance::class,
    ];

    public function inspect(): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        $results = [];
        $totalRelations = 0;
        $totalCasts = 0;

        foreach (self::MODELS as $class) {
            /** @var ImportedHistoricalModel $model */
            $model = new $class();
            $table = $model->getTable();
            // Source stock counters and quantity balances do not have id
            // keys. Explicitly retain their original natural primary keys.
            if (($table === 'stock_counters' && $model->getKeyName() !== 'key')
                || ($table === 'stock_item_balances'
                    && $model->getKeyName() !== 'item_id')) {
                throw new RuntimeException('V2_DOMAIN_NATURAL_KEY_DRIFT');
            }
            if (!Schema::hasTable($table) || $model->getGuarded() !== ['*']) {
                throw new RuntimeException("V2_DOMAIN_MODEL_UNSAFE_OR_MISSING: {$table}");
            }
            $columns = Schema::getColumnListing($table);
            $casts = $model->getCasts();
            $verified = 0;
            foreach ($casts as $column => $cast) {
                if (!in_array($column, $columns, true)) {
                    throw new RuntimeException("V2_DOMAIN_CAST_COLUMN_MISSING: {$table}.{$column}");
                }
                $matches = [];
                if (preg_match('/^decimal:([0-9]+)$/D', (string) $cast, $matches)
                    && DB::connection()->getDriverName() === 'mysql') {
                    $physical = DB::connection()->selectOne(
                        'SELECT DATA_TYPE AS type, NUMERIC_SCALE AS scale'
                        . ' FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()'
                        . ' AND TABLE_NAME=? AND COLUMN_NAME=?',
                        [$table, $column]
                    );
                    if (!$physical || $physical->type !== 'decimal'
                        || (int) $physical->scale !== (int) $matches[1]) {
                        throw new RuntimeException("V2_DOMAIN_DECIMAL_DRIFT: {$table}.{$column}");
                    }
                }
                $verified++;
            }

            $related = 0;
            $reflection = new \ReflectionClass($model);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class
                    || $method->getNumberOfRequiredParameters() > 0) {
                    continue;
                }
                $declared = $method->getReturnType();
                if (!$declared || !is_a((string) $declared, Relation::class, true)) {
                    continue;
                }
                /** @var Relation $relation */
                $relation = $model->{$method->getName()}();
                $owner = $relation instanceof BelongsTo
                    ? $model : $relation->getRelated();
                $key = $relation->getForeignKeyName();
                if (!Schema::hasColumn($owner->getTable(), $key)
                    || !Schema::hasColumn(
                        $relation->getRelated()->getTable(),
                        $relation->getRelated()->getKeyName()
                    )) {
                    throw new RuntimeException(
                        "V2_DOMAIN_RELATION_DRIFT: {$class}::{$method->getName()}"
                    );
                }
                $related++;
            }
            $results[$table] = [
                'model' => $class, 'casts' => $verified, 'relations' => $related,
            ];
            $totalCasts += $verified;
            $totalRelations += $related;
        }

        if (count($results) !== count(self::MODELS)) {
            throw new RuntimeException('V2_DOMAIN_DUPLICATE_SOURCE_TABLE');
        }
        return [
            'model_count' => count($results),
            'cast_count' => $totalCasts,
            'relation_count' => $totalRelations,
            'tables' => $results,
            'scope' => 'isolated_read_only_v2_domain_models',
        ];
    }
}
