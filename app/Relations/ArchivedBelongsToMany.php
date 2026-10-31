<?php

namespace App\Relations;

class ArchivedBelongsToMany extends \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    use ArchivesPivotRows;
}
