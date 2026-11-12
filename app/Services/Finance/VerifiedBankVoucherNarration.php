<?php

namespace App\Services\Finance;

/**
 * Ledger narration for vouchers sourced from passed Cheque/Online management.
 * This formats evidence only; it never decides eligibility or posts money.
 */
final class VerifiedBankVoucherNarration
{
    public static function compose(
        string $series,
        string $method,
        int $sourceId,
        string $displayNumber,
        ?string $bankName,
        ?string $instrumentNumber,
        ?string $passingDate,
        ?string $voucherDate,
        ?string $reference,
        ?string $userNarration
    ): string {
        if (!in_array($series,['BOR','BOP','CR','DR'],true)
            || !in_array($method,['online','cheque'],true)
            || $sourceId < 1 || trim($displayNumber)==='') {
            throw new \DomainException('Verified bank narration requires a linked source and voucher identity.');
        }

        $parts = [$displayNumber];
        $fields = [
            'Method' => $method==='online' ? 'Online' : 'Cheque',
            'Source' => ucfirst($method).' #'.$sourceId,
            'Bank' => $bankName,
            $method==='online' ? 'Txn' : 'Chq' => $instrumentNumber,
            'Pass' => $passingDate,
            'Ref' => $reference,
            'Date' => $voucherDate,
            'Note' => $userNarration,
        ];
        foreach ($fields as $key=>$value) {
            $value=trim((string)$value);
            if ($value!=='') {
                $parts[]=$key.': '.$value;
            }
        }
        return implode(' | ',$parts);
    }
}
