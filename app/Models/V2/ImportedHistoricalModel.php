<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

/**
 * The imported legacy tables are immutable V2 source evidence.
 * This guard protects Eloquent model instance mutations; query-builder and
 * raw-SQL writes require a separate connection/authorization policy.
 */
abstract class ImportedHistoricalModel extends Model
{
    protected $guarded = ['*'];

    public function save(array $options = [])
    {
        throw new \DomainException('V2_IMPORTED_SOURCE_MODEL_READ_ONLY');
    }

    public function delete()
    {
        throw new \DomainException('V2_IMPORTED_SOURCE_MODEL_READ_ONLY');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new \DomainException('V2_IMPORTED_SOURCE_MODEL_READ_ONLY');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new \DomainException('V2_IMPORTED_SOURCE_MODEL_READ_ONLY');
    }
}
