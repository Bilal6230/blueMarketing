<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ArchivesAssignments
{
    protected function archiveAssignmentRelation($relation)
    {
        if ($this->getConnection()->getSchemaBuilder()->hasColumn($relation->getTable(), 'deleted_at')) {
            $relation->wherePivotNull('deleted_at');
        }
        return $relation;
    }

    protected function newBelongsToMany(Builder $query, Model $parent, $table, $foreignPivotKey,
        $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        return $this->archiveAssignmentRelation(new \App\Relations\ArchivedBelongsToMany(
            $query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName
        ));
    }

    protected function newMorphToMany(Builder $query, Model $parent, $name, $table, $foreignPivotKey,
        $relatedPivotKey, $parentKey, $relatedKey, $relationName = null, $inverse = false)
    {
        return $this->archiveAssignmentRelation(new \App\Relations\ArchivedMorphToMany(
            $query, $parent, $name, $table, $foreignPivotKey, $relatedPivotKey,
            $parentKey, $relatedKey, $relationName, $inverse
        ));
    }
}
