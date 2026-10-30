<?php

namespace App\Services\Finance;

/**
 * Single V2 posting application boundary for isolated finance experiments.
 *
 * No public route or V1 controller calls this gateway. Callers must provide
 * independently authorized project access and approved cash allocations.
 * Writers themselves enforce isolated connection, FK, locks, balance,
 * exact source identity and idempotency; this service cannot bypass them.
 */
final class V2PostingGateway
{
    public function postJournal(
        int $projectId,
        int $journalVoucherId,
        string $idempotencyKey,
        ?int $actorId = null
    ): int {
        return (new V2JournalPostingWriter())->post(
            $projectId, $journalVoucherId, $idempotencyKey, $actorId
        );
    }

    public function postCash(
        int $projectId,
        int $customerLedgerId,
        string $idempotencyKey,
        array $approvedAccountLegs,
        ?int $actorId = null
    ): int {
        return (new V2CashPostingWriter())->post(
            $projectId, $customerLedgerId, $idempotencyKey,
            $approvedAccountLegs, $actorId
        );
    }

    public function reversePostedBatch(
        int $projectId,
        int $originalBatchId,
        string $idempotencyKey,
        string $effectiveDate,
        string $approvedReason,
        ?int $actorId = null
    ): int {
        return (new V2PostingReversalWriter())->reverse(
            $projectId, $originalBatchId, $idempotencyKey,
            $effectiveDate, $approvedReason, $actorId
        );
    }

    public function postingLines(
        int $projectId,
        ?string $from = null,
        ?string $through = null
    ): array {
        return (new V2PostingReportReader())->lines($projectId, $from, $through);
    }

    public function trialBalance(
        int $projectId,
        ?string $from = null,
        ?string $through = null
    ): array {
        return (new V2ProjectTrialBalanceReader())->summarize(
            $projectId, $from, $through
        );
    }
}
