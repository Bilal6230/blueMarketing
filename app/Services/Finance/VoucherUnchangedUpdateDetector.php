<?php

namespace App\Services\Finance;

use Illuminate\Support\Collection;

/**
 * Detect exact no-change legacy JV/CV edits before retiring any historical
 * posting rows. A material change still uses the existing audited transaction.
 */
final class VoucherUnchangedUpdateDetector
{
    private function amount(mixed $value): string
    {
        return number_format((float)$value,2,'.','');
    }

    public function same(object $voucher, Collection $oldDetails, array $header, array $lines): bool
    {
        if ((string)$voucher->date !== (string)$header['date']
            || (string)($voucher->reference??'') !== (string)($header['reference']??'')
            || (string)($voucher->description??'') !== (string)($header['description']??'')
            || $oldDetails->count() !== count($lines)) {
            return false;
        }
        $old=$oldDetails->sortBy('id')->values();
        foreach (array_values($lines) as $index=>$line) {
            $detail=$old[$index];
            if ((int)$detail->account_id !== (int)$line['account_id']
                || $this->amount($detail->debit) !== $this->amount($line['debit'])
                || $this->amount($detail->credit) !== $this->amount($line['credit'])
                || (string)($detail->description??'') !== (string)($line['description']??'')) {
                return false;
            }
        }
        return true;
    }
}
