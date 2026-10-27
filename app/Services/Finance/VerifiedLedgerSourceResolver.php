<?php

namespace App\Services\Finance;

/**
 * Fail-closed typed ledger display resolver.
 *
 * Source evidence is supplied by an independently verified attribution.
 * Never infer attribution from legacy ledgers.type / type_id.
 */
final class VerifiedLedgerSourceResolver
{
    /**
     * @param array{id:int|string,type:string,type_id?:int|string|null,voucher_number:string|int|null} $ledger
     * @param array{ledger_id:int|string,project_id:int|string,source_kind:string,source_id:int|string,evidence_kind:string} | null $attribution
     * @param array{id:int|string,project_id:int|string,voucher_number:string|int|null} | null $source
     * @return array{display_voucher_number:string,source_verified:bool,source_kind:string,issues:array}
     */
    public function resolve(array $ledger, int $projectId, ?array $attribution = null, ?array $source = null): array
    {
        if ($projectId <= 0 || !isset($ledger['id'], $ledger['type'])) {
            throw new \InvalidArgumentException('A ledger ID, ledger type and positive project are required.');
        }

        $original = (string) ($ledger['type'] ?? '') . '-' . (string) ($ledger['voucher_number'] ?? '');
        $unverified = static fn (string $issue): array => [
            'display_voucher_number' => $original,
            'source_verified' => false,
            'source_kind' => 'unknown_legacy',
            'issues' => [$issue],
        ];

        if ($attribution === null || $source === null) {
            return $unverified('missing_verified_source_attribution');
        }

        foreach (['ledger_id', 'project_id', 'source_kind', 'source_id', 'evidence_kind'] as $field) {
            if (!array_key_exists($field, $attribution)) {
                return $unverified('incomplete_source_attribution');
            }
        }

        $kind = $attribution['source_kind'];
        $prefix = match ($kind) {
            'journal_voucher' => 'JV',
            'commission_voucher' => 'CV',
            default => null,
        };
        if ($prefix === null || !in_array($attribution['evidence_kind'], [
            'new_write_transaction', 'verified_external_reference', 'manual_review',
        ], true)) {
            return $unverified('untrusted_source_attribution');
        }

        if ((string) $attribution['ledger_id'] !== (string) $ledger['id'] ||
            (string) $attribution['project_id'] !== (string) $projectId ||
            !isset($source['id'], $source['project_id'], $source['voucher_number']) ||
            (string) $source['id'] !== (string) $attribution['source_id'] ||
            (string) $source['project_id'] !== (string) $projectId ||
            (string) $source['voucher_number'] === '') {
            return $unverified('source_attribution_mismatch');
        }

        if (($ledger['type'] ?? null) !== 'JV') {
            return $unverified('unsupported_legacy_ledger_type');
        }

        return [
            'display_voucher_number' => $prefix . '-' . $source['voucher_number'],
            'source_verified' => true,
            'source_kind' => $kind,
            'issues' => [],
        ];
    }
}
