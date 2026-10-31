<?php

namespace App\Models;

class ProtectedFinancialBuilder extends \Illuminate\Database\Eloquent\Builder
{
    public function delete()
    {
        throw new \DomainException('FINANCIAL_HISTORY_DELETE_FORBIDDEN');
    }

    public function forceDelete()
    {
        throw new \DomainException('FINANCIAL_HISTORY_DELETE_FORBIDDEN');
    }
}
