<?php

namespace App\Services\Finance;

/** Shared upload contract for CR, DR, CCR, CDR, BOR and BOP.
 * No public paths, arbitrary file types or required uploads.
 */
final class VoucherAttachmentRules
{
    /** BOR/BOP use the existing CR/CP source storage types. */
    public static function supportsSourceType(string $type): bool
    {
        return in_array($type, ['CR', 'CP', 'CCR', 'CDR'], true);
    }

    public static function optional(): array
    {
        return ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:5120'];
    }
}
