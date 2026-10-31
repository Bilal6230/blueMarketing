<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\ArchiveScope;

/** Reads remain compatible before rollout; deletes require the archive column. */
trait PreservesDeletedRecords
{
    use SoftDeletes;

    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new ArchiveScope());
    }

    public function forceDelete()
    {
        throw new \DomainException('HARD_DELETE_FORBIDDEN');
    }

    public function newEloquentBuilder($query)
    {
        return new \App\Models\ArchiveBuilder($query);
    }
}
