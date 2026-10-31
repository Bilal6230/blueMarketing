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

    /**
     * Booking sales vouchers are journal_vouchers.type = SV, linked by
     * their real journal_vouchers.id and exact journal detail source rows.
     * They are NOT booking_vouchers IDs or ledger.type_id guesses.
     */
    public function postSalesVoucher(
        int $projectId, int $salesJournalVoucherId,
        string $idempotencyKey, ?int $actorId = null
    ): int {
        return (new V2JournalPostingWriter())->post(
            $projectId, $salesJournalVoucherId, $idempotencyKey,
            $actorId, 'SV'
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

    public function postCommission(
        int $projectId,
        int $commissionVoucherId,
        string $idempotencyKey,
        ?int $actorId = null
    ): int {
        return (new V2CommissionPostingWriter())->post(
            $projectId, $commissionVoucherId, $idempotencyKey, $actorId
        );
    }

    /**
     * PPR / EC / ECP are sourced ONLY from their actual customer_ledger PK.
     * An explicit expected series is mandatory; never infer type_id identity.
     */
    public function postCustomerVoucher(
        int $projectId,
        int $customerLedgerId,
        string $series,
        string $idempotencyKey,
        array $approvedAccountLegs,
        ?int $actorId = null
    ): int {
        if (!in_array($series, ['PPR', 'EC', 'ECP'], true)) {
            throw new \DomainException('V2_CUSTOMER_VOUCHER_SERIES_NOT_SUPPORTED');
        }
        return (new V2CashPostingWriter())->post(
            $projectId, $customerLedgerId, $idempotencyKey,
            $approvedAccountLegs, $actorId, $series
        );
    }

    public function postBooking(
        int $projectId,
        int $bookingVoucherId,
        string $idempotencyKey,
        array $approvedAccountLegs,
        ?int $actorId = null
    ): int {
        return (new V2ApprovedSourcePostingWriter())->post(
            $projectId, 'booking', $bookingVoucherId,
            $idempotencyKey, $approvedAccountLegs, $actorId
        );
    }

    public function postLabour(
        int $projectId,
        int $labourLedgerId,
        string $idempotencyKey,
        array $approvedAccountLegs,
        ?int $actorId = null
    ): int {
        return (new V2ApprovedSourcePostingWriter())->post(
            $projectId, 'labour', $labourLedgerId,
            $idempotencyKey, $approvedAccountLegs, $actorId
        );
    }

    public function postStockEntry(
        int $projectId,
        int $stockEntryId,
        string $idempotencyKey,
        array $approvedAccountLegs,
        ?int $actorId = null
    ): int {
        return (new V2ApprovedSourcePostingWriter())->post(
            $projectId, 'stock_entry', $stockEntryId,
            $idempotencyKey, $approvedAccountLegs, $actorId
        );
    }

    public function postStockBill(
        int $projectId,
        int $stockBillId,
        string $idempotencyKey,
        array $approvedAccountLegs,
        ?int $actorId = null
    ): int {
        return (new V2ApprovedSourcePostingWriter())->post(
            $projectId, 'stock_bill', $stockBillId,
            $idempotencyKey, $approvedAccountLegs, $actorId
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

    public function voucherRegister(
        int $projectId,
        ?string $from = null,
        ?string $through = null
    ): array {
        return (new V2VoucherRegisterReader())->documents(
            $projectId, $from, $through
        );
    }

    public function postingSummary(
        int $projectId,
        ?string $from = null,
        ?string $through = null
    ): array {
        return (new V2PostingSummaryReader())->summarize(
            $projectId, $from, $through
        );
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

    public function accountStatement(
        int $projectId,
        int $accountId,
        ?string $from = null,
        ?string $through = null
    ): array {
        return (new V2AccountStatementReader())->lines(
            $projectId, $accountId, $from, $through
        );
    }
}
