<?php

namespace App\Models;

class Permission extends \Spatie\Permission\Models\Permission
{
    use \App\Models\Concerns\PreservesDeletedRecords;
    use \App\Models\Concerns\ArchivesAssignments;
}
