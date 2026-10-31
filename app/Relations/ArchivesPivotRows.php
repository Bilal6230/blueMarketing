<?php

namespace App\Relations;

/** Preserve assignment rows, including composite-key and polymorphic identity. */
trait ArchivesPivotRows
{
    protected function hasArchiveColumn(): bool
    {
        return $this->parent->getConnection()->getSchemaBuilder()->hasColumn($this->table, 'deleted_at');
    }

    protected function serialized(callable $operation)
    {
        return $this->parent->getConnection()->transaction(function () use ($operation) {
            $this->parent->newQuery()->whereKey($this->parent->getKey())->lockForUpdate()->firstOrFail();
            return $operation();
        });
    }

    public function sync($ids, $detaching = true)
    {
        return $this->serialized(fn () => parent::sync($ids, $detaching));
    }

    public function attach($ids, array $attributes = [], $touch = true)
    {
        if (!$this->hasArchiveColumn()) {
            // Insertion alone cannot remove historical data before migration.
            return parent::attach($ids, $attributes, $touch);
        }
        return $this->serialized(function () use ($ids, $attributes, $touch) {
            foreach ($this->formatAttachRecords($this->parseIds($ids), $attributes) as $record) {
                unset($record['deleted_at']);
                $identity = [
                    $this->foreignPivotKey => $record[$this->foreignPivotKey],
                    $this->relatedPivotKey => $record[$this->relatedPivotKey],
                ];
                if (isset($this->morphType)) {
                    $identity[$this->morphType] = $record[$this->morphType];
                }
                $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                if (array_key_exists($teamKey, $record)) {
                    $identity[$teamKey] = $record[$teamKey];
                }
                $existing = $this->newPivotStatement()->where($identity)->lockForUpdate()->first();
                if ($existing) {
                    // Preserve original attachment time and row ID on reactivation.
                    unset($record['created_at']);
                    $this->newPivotStatement()->where($identity)->update($record + ['deleted_at' => null]);
                } else {
                    $this->newPivotStatement()->insert($record + ['deleted_at' => null]);
                }
            }
            if ($touch) {
                $this->touchIfTouching();
            }
        });
    }

    public function detach($ids = null, $touch = true)
    {
        return $this->serialized(function () use ($ids, $touch) {
            $query = $this->newPivotQuery();
            if ($ids !== null) {
                $ids = $this->parseIds($ids);
                if (empty($ids)) {
                    return 0;
                }
                $query->whereIn($this->getQualifiedRelatedPivotKeyName(), (array) $ids);
            }
            if (!$this->hasArchiveColumn()) {
                if (!$query->exists()) {
                    return 0;
                }
                throw new \DomainException('ASSIGNMENT_ARCHIVE_MIGRATION_REQUIRED');
            }
            $count = $query->whereNull($this->table.'.deleted_at')->update(['deleted_at' => now()]);
            if ($touch) {
                $this->touchIfTouching();
            }
            return $count;
        });
    }
}
