<?php

namespace App\Models\Concerns;

/** Posted balances must be corrected through reversal, never hidden or removed. */
trait ProtectsFinancialHistory
{
    public function delete()
    {
        throw new \DomainException('FINANCIAL_HISTORY_DELETE_FORBIDDEN');
    }

    public function forceDelete()
    {
        throw new \DomainException('FINANCIAL_HISTORY_DELETE_FORBIDDEN');
    }

    public function newEloquentBuilder($query)
    {
        return new \App\Models\ProtectedFinancialBuilder($query);
    }
}
