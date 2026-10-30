<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit project attribution for stock documents with NO project_id.
 * Opt-in future V2-only administrative operation: ability is intentionally
 * NOT granted by default; must be defined by authorized policy before use.
 */
final class V2StockProjectApprovalWriter
{
    public function approve(
        string $kind,
        int $sourceId,
        int $projectId,
        string $evidenceReference
    ): void {
        V2IsolatedConnectionGuard::assertAllowed();
        // The global legacy superadmin Gate::before must NOT implicitly
        // activate an as-yet-undefined V2 finance authorization ability.
        if (!Gate::has('approve-v2-stock-finance')) {
            throw new \DomainException('V2_STOCK_APPROVAL_POLICY_NOT_REGISTERED');
        }
        Gate::authorize('approve-v2-stock-finance');
        $actor = Auth::id();
        if (!is_int($actor) && !(is_string($actor) && ctype_digit($actor))) {
            throw new \DomainException('V2_STOCK_APPROVAL_ACTOR_REQUIRED');
        }
        if ($projectId < 1
            || !preg_match('/^[A-Za-z0-9_.:\/-]{12,128}$/D', $evidenceReference)) {
            throw new \DomainException('V2_STOCK_APPROVAL_EVIDENCE_REQUIRED');
        }
        $table = match ($kind) {
            'stock_entry' => 'finance_stock_entry_project_approvals',
            'stock_bill' => 'finance_stock_bill_project_approvals',
            default => throw new \DomainException('V2_STOCK_APPROVAL_KIND_INVALID'),
        };
        $fk = $kind === 'stock_entry' ? 'stock_entry_id' : 'stock_bill_id';
        if (!Schema::hasTable($table)) {
            throw new \RuntimeException('V2_STOCK_APPROVAL_SCHEMA_MISSING');
        }

        DB::transaction(function () use ($kind, $sourceId, $projectId,
            $evidenceReference, $actor, $table, $fk): void {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()
                || !DB::table('users')->where('id', $actor)->exists()) {
                throw new \DomainException('V2_STOCK_APPROVAL_PROJECT_OR_USER_INVALID');
            }
            $snapshot = (new V2StockSourceSnapshot())->read($kind, $sourceId);
            $existing = DB::table($table)->where($fk, $sourceId)
                ->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->project_id !== $projectId
                    || !hash_equals((string) $existing->source_sha256, $snapshot['fingerprint'])
                    || (string) $existing->evidence_reference !== $evidenceReference) {
                    throw new \DomainException('V2_STOCK_APPROVAL_CONFLICT');
                }
                return;
            }
            DB::table($table)->insert([
                $fk => $sourceId, 'project_id' => $projectId,
                'approved_by' => (int) $actor,
                'evidence_reference' => $evidenceReference,
                'source_sha256' => $snapshot['fingerprint'],
                'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }
}
