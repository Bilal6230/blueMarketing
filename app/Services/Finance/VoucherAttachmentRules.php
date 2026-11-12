<?php

namespace App\Services\Finance;

/** Shared upload contract for CR, DR, CCR, CDR, BOR and BOP.
 * No public paths, arbitrary file types or required uploads.
 */
final class VoucherAttachmentRules
{
    public static function optional(): array
    {
        return ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'];
    }
}
