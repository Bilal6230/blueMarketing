<?php

namespace App\Services\Finance;

/**
 * Bank voucher postings represent ONE directional accounting movement.
 * This guard is shared by approval and audited allocation release/void.
 */
final class BankVoucherLegIntegrity
{
    public static function assert(
        string $series,
        object $source,
        object $leg,
        float $allocationAmount
    ): void {
        if (!in_array($series, ['CR','DR','BOR','BOP'], true)) {
            throw new \DomainException('Unsupported bank voucher direction.');
        }
        $incoming=in_array($series,['CR','BOR'],true);
        $sourceIn=round((float)($source->amount_in ?? 0),2);
        $sourceOut=round((float)($source->amount_out ?? 0),2);
        $legIn=round((float)($leg->amount_in ?? 0),2);
        $legOut=round((float)($leg->amount_out ?? 0),2);
        $amount=round($allocationAmount,2);
        if ($amount<=0
            || ($incoming && ($sourceIn!==$amount || $sourceOut!==0.0
                || $legIn!==$amount || $legOut!==0.0))
            || (!$incoming && ($sourceOut!==$amount || $sourceIn!==0.0
                || $legOut!==$amount || $legIn!==0.0))) {
            throw new \DomainException('Bank voucher posting direction or allocated amount mismatch.');
        }
    }
}
