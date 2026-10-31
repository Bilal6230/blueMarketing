<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ArchiveScope extends SoftDeletingScope
{
    public function apply(Builder $builder, Model $model)
    {
        // Existing databases are never silently migrated during a request.
        if ($model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $model->getDeletedAtColumn())) {
            parent::apply($builder, $model);
        }
    }
}
