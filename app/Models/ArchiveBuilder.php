<?php

namespace App\Models;

class ArchiveBuilder extends \Illuminate\Database\Eloquent\Builder
{
    public function forceDelete()
    {
        throw new \DomainException('HARD_DELETE_FORBIDDEN');
    }
}
