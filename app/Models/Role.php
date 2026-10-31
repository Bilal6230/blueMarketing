<?php

namespace App\Models;

class Role extends \Spatie\Permission\Models\Role
{
    use \App\Models\Concerns\PreservesDeletedRecords;
    use \App\Models\Concerns\ArchivesAssignments;

    public function syncPermissions(...$permissions): static
    {
        if (!$this->exists) {
            return parent::syncPermissions(...$permissions);
        }
        return $this->getConnection()->transaction(function () use ($permissions) {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            return parent::syncPermissions(...$permissions);
        });
    }
}
