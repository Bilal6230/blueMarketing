<?php

namespace App\Relations;

class ArchivedMorphToMany extends \Illuminate\Database\Eloquent\Relations\MorphToMany
{
    use ArchivesPivotRows;
}
