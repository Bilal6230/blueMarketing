<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Finance\V2IsolatedConnectionGuard;
use App\Services\Finance\V2SourceApprovalReadiness;
use App\Services\Finance\V2FinanceCsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\Finance\V2PostingGateway;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Strictly local, default-OFF V2 trial integration. This cannot replace
 * existing /api/v1/mobile or legacy production voucher endpoints.
 * Explicit project membership and Gate authorization apply per request.
 */
final class V2FinancePilotController extends Controller
{
    private function project(Request $request, int $projectId, bool $write): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        if (!in_array(config('app.env'), ['local', 'testing'], true)
            || !config('finance.v2_pilot_api_enabled')
            || ($write && !config('finance.v2_pilot_write_enabled'))) {
            abort(404);
        }
        if (!$request->user() || $projectId < 1) {
            throw new AuthorizationException('V2 project finance access denied.');
        }
        $ability = $write ? 'v2-finance-post' : 'v2-finance-read';
        if (!Gate::has($ability)) {
            throw new AuthorizationException('V2 finance ability is not configured.');
        }
        Gate::authorize($ability);
        $user = $request->user();
        if (!$user->hasRole('superadmin')
            && (int) $user->project_id !== $projectId) {
            throw new AuthorizationException('V2 project finance access denied.');
        }
        if (!Project::query()->where('id', $projectId)->where('is_active', 1)->exists()) {
            throw new AuthorizationException('V2 project is not available.');
        }
    }

    public function lines(Request $request, int $projectId, V2PostingGateway $gateway): JsonResponse
    {
        $this->project($request, $projectId, false);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        return response()->json([
            'scope' => 'typed_v2_postings_only',
            'lines' => $gateway->postingLines($projectId, $data['from'] ?? null, $data['through'] ?? null),
        ]);
    }

    public function sourceReadiness(
        Request $request, int $projectId, string $kind, int $sourceId,
        V2SourceApprovalReadiness $readiness
    ): JsonResponse {
        $this->project($request, $projectId, false);
        $input = $request->validate([
            'series' => ['nullable', Rule::in(['PPR', 'EC', 'ECP'])],
        ]);
        return response()->json($readiness->inspect(
            $projectId, $kind, $sourceId, $input['series'] ?? null
        ));
    }

    public function voucherRegister(
        Request $request, int $projectId, V2PostingGateway $gateway
    ): JsonResponse {
        $this->project($request, $projectId, false);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        return response()->json([
            'scope' => 'typed_v2_postings_only',
            'documents' => $gateway->voucherRegister(
                $projectId, $data['from'] ?? null, $data['through'] ?? null
            ),
        ]);
    }

    public function exportVoucherRegister(
        Request $request, int $projectId,
        V2PostingGateway $gateway, V2FinanceCsvExporter $exporter
    ): StreamedResponse {
        $this->project($request, $projectId, false);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $documents = $gateway->voucherRegister(
            $projectId, $data['from'] ?? null, $data['through'] ?? null
        );
        $csv = $exporter->voucherRegister($documents);
        return response()->streamDownload(
            static function () use ($csv): void { echo $csv; },
            'v2-voucher-register-project-' . $projectId . '.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function summary(Request $request, int $projectId, V2PostingGateway $gateway): JsonResponse
    {
        $this->project($request, $projectId, false);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        return response()->json($gateway->postingSummary(
            $projectId, $data['from'] ?? null, $data['through'] ?? null
        ));
    }

    public function trialBalance(Request $request, int $projectId, V2PostingGateway $gateway): JsonResponse
    {
        $this->project($request, $projectId, false);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        return response()->json($gateway->trialBalance(
            $projectId, $data['from'] ?? null, $data['through'] ?? null
        ));
    }

    public function accountStatement(
        Request $request, int $projectId, int $accountId, V2PostingGateway $gateway
    ): JsonResponse {
        $this->project($request, $projectId, false);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        return response()->json($gateway->accountStatement(
            $projectId, $accountId, $data['from'] ?? null, $data['through'] ?? null
        ));
    }

    public function exportTrialBalance(
        Request $request, int $projectId,
        V2PostingGateway $gateway, V2FinanceCsvExporter $exporter
    ): StreamedResponse {
        $this->project($request, $projectId, false);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $report = $gateway->trialBalance(
            $projectId, $data['from'] ?? null, $data['through'] ?? null
        );
        $csv = $exporter->trialBalance($report);
        return response()->streamDownload(
            static function () use ($csv): void { echo $csv; },
            'v2-trial-balance-project-' . $projectId . '.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function exportAccountStatement(
        Request $request, int $projectId, int $accountId,
        V2PostingGateway $gateway, V2FinanceCsvExporter $exporter
    ): StreamedResponse {
        $this->project($request, $projectId, false);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $report = $gateway->accountStatement(
            $projectId, $accountId, $data['from'] ?? null, $data['through'] ?? null
        );
        $csv = $exporter->accountStatement($report);
        return response()->streamDownload(
            static function () use ($csv): void { echo $csv; },
            'v2-account-statement-project-' . $projectId . '-account-' . $accountId . '.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function post(Request $request, int $projectId, V2PostingGateway $gateway): JsonResponse
    {
        $this->project($request, $projectId, true);
        $data = $request->validate([
            'kind' => ['required', Rule::in([
                'journal', 'sales', 'cash', 'customer', 'commission',
                'booking', 'labour', 'stock_entry', 'stock_bill',
            ])],
            'source_id' => ['required', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'series' => ['required_if:kind,customer', 'nullable', Rule::in(['PPR', 'EC', 'ECP'])],
            'allocation' => ['required_unless:kind,journal,commission,sales', 'array', 'min:2'],
            'allocation.*.account_id' => ['required_with:allocation', 'integer', 'min:1'],
            'allocation.*.debit' => ['required_with:allocation', 'numeric', 'min:0'],
            'allocation.*.credit' => ['required_with:allocation', 'numeric', 'min:0'],
            'allocation.*.description' => ['nullable', 'string', 'max:1000'],
        ]);
        $id = (int) $data['source_id'];
        $key = (string) $data['request_key'];
        $legs = $data['allocation'] ?? [];
        $actor = (int) $request->user()->getAuthIdentifier();
        try {
            // Mirror legacy approval/project state before dispatching; the
            // transactional writer rechecks under row locks to prevent races.
            $ready = (new V2SourceApprovalReadiness())->inspect(
                $projectId, $data['kind'], $id, $data['series'] ?? null
            );
            if (!$ready['eligible']) {
                throw new \DomainException('V2_SOURCE_APPROVAL_REQUIRED');
            }
            $batchId = match ($data['kind']) {
            'journal' => $gateway->postJournal($projectId, $id, $key, $actor),
            'sales' => $gateway->postSalesVoucher($projectId, $id, $key, $actor),
            'commission' => $gateway->postCommission($projectId, $id, $key, $actor),
            'cash' => $gateway->postCash($projectId, $id, $key, $legs, $actor),
            'customer' => $gateway->postCustomerVoucher(
                $projectId, $id, $data['series'], $key, $legs, $actor
            ),
            'booking' => $gateway->postBooking($projectId, $id, $key, $legs, $actor),
            'labour' => $gateway->postLabour($projectId, $id, $key, $legs, $actor),
            'stock_entry' => $gateway->postStockEntry($projectId, $id, $key, $legs, $actor),
            'stock_bill' => $gateway->postStockBill($projectId, $id, $key, $legs, $actor),
        };
        } catch (\DomainException $error) {
            // Business/source conflicts are intentional denials, not 500s.
            // Do not leak source details or SQL into a pilot HTTP response.
            return response()->json(['error' => 'V2_POSTING_CONFLICT'], 409);
        }
        return response()->json([
            'scope' => 'isolated_v2_pilot_only', 'posting_batch_id' => $batchId,
        ], 201);
    }

    public function bankTransfer(Request $request, int $projectId, V2PostingGateway $gateway): JsonResponse
    {
        $this->project($request, $projectId, true);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['deposit', 'withdrawal'])],
            'bank_account_id' => ['required', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,2})?$/D'],
            'narration' => ['required', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.account_id' => ['required', 'integer', 'min:1'],
            'allocations.*.amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,2})?$/D'],
        ]);
        try {
            $result = $gateway->postBankTransfer(
                $projectId, (int) $data['bank_account_id'], $data['kind'],
                $data['request_key'], $data['date'], $data['amount'],
                $data['narration'], $data['allocations'],
                (int) $request->user()->getAuthIdentifier()
            );
        } catch (\DomainException $error) {
            return response()->json(['error' => 'V2_BANK_TRANSFER_CONFLICT'], 409);
        }
        return response()->json([
            'scope' => 'isolated_v2_pilot_only', ...$result,
        ], $result['replayed'] ? 200 : 201);
    }

    public function plotReceipt(Request $request, int $projectId, ?int $receiptId = null): JsonResponse
    {
        $this->project($request, $projectId, $receiptId === null);
        $writer = new \App\Services\Finance\V2PlotEntryWriter();
        if ($receiptId !== null) {
            try { return response()->json($writer->receipt($projectId, $receiptId)); }
            catch (\DomainException $error) { return response()->json(['error' => 'V2_PLOT_RECEIPT_NOT_FOUND'], 404); }
        }
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'receipt_number' => ['required', 'string', 'max:64'],
            'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,2})?$/D'],
            'cash_account_id' => ['required', 'integer', 'min:1'],
            'narration' => ['required', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:2', 'max:100'],
            'allocations.*.booking_id' => ['required', 'integer', 'min:1'],
            'allocations.*.plot_id' => ['required', 'integer', 'min:1'],
            'allocations.*.receivable_account_id' => ['required', 'integer', 'min:1'],
            'allocations.*.amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,2})?$/D'],
        ]);
        try {
            $result = $writer->receive($projectId, (int) $data['customer_id'], $data['request_key'], $data,
                (int) $request->user()->getAuthIdentifier());
        } catch (\DomainException $error) {
            return response()->json(['error' => 'V2_PLOT_RECEIPT_CONFLICT'], 409);
        }
        return response()->json(['scope' => 'isolated_v2_pilot_only', ...$result], $result['replayed'] ? 200 : 201);
    }

    public function plotReceiptPrint(Request $request, int $projectId, int $receiptId, int $plotId)
    {
        $this->project($request, $projectId, false);
        try {
            $details = (new \App\Services\Finance\V2PlotEntryWriter())->receipt($projectId, $receiptId);
        } catch (\DomainException $error) {
            abort(404);
        }
        $allocation = $details['allocations']->firstWhere('plot_id', $plotId);
        abort_unless($allocation, 404);

        return response()->view('admin.finance.voucher.plot_receipt_print', [
            'receipt' => $details['receipt'], 'allocation' => $allocation,
        ]);
    }

    public function plotPurchase(Request $request, int $projectId, ?int $purchaseId = null): JsonResponse
    {
        $this->project($request, $projectId, $purchaseId === null);
        $writer = new \App\Services\Finance\V2PlotEntryWriter();
        if ($purchaseId !== null) {
            try { return response()->json($writer->purchaseDetails($projectId, $purchaseId)); }
            catch (\DomainException $error) { return response()->json(['error' => 'V2_PLOT_PURCHASE_NOT_FOUND'], 404); }
        }
        $data = $request->validate([
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'purchase_number' => ['required', 'string', 'max:64'],
            'date' => ['required', 'date_format:Y-m-d'],
            'seller_account_id' => ['required', 'integer', 'min:1'],
            'narration' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.plot_code' => ['required', 'string', 'max:64'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.area' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,4})?$/D'],
            'items.*.unit' => ['required', 'string', 'max:24'],
            'items.*.type' => ['required', 'integer', Rule::in([1, 2])],
            'items.*.road_id' => ['required', 'integer', 'min:1'],
            'items.*.facing_id' => ['required', 'integer', 'min:1'],
            'items.*.is_corner' => ['sometimes', 'boolean'],
            'items.*.plot_category_definition_id' => ['nullable', 'integer', 'min:1'],
            'items.*.plot_type_definition_id' => ['nullable', 'integer', 'min:1'],
            'items.*.plot_size_definition_id' => ['nullable', 'integer', 'min:1'],
            'items.*.plot_unit_definition_id' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_rate' => ['required', 'regex:/^(0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,2})?$/D'],
            'items.*.inventory_account_id' => ['required', 'integer', 'min:1'],
        ]);
        try {
            $result = $writer->purchase($projectId, $data['request_key'], $data,
                (int) $request->user()->getAuthIdentifier());
        } catch (\DomainException $error) {
            return response()->json(['error' => 'V2_PLOT_PURCHASE_CONFLICT'], 409);
        }
        return response()->json(['scope' => 'isolated_v2_pilot_only', ...$result], $result['replayed'] ? 200 : 201);
    }

    public function voidPlotVoucher(Request $request, int $projectId, string $kind, int $sourceId): JsonResponse
    {
        $this->project($request, $projectId, true);
        $data = $request->validate([
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:1', 'max:200'],
        ]);
        try {
            $batchId = (new \App\Services\Finance\V2PlotEntryLifecycleService())->reverse(
                $projectId, $kind, $sourceId, $data['request_key'], $data['effective_date'],
                $data['reason'], (int) $request->user()->getAuthIdentifier()
            );
        } catch (\DomainException $error) {
            return response()->json(['error' => 'V2_PLOT_VOUCHER_VOID_CONFLICT'], 409);
        }
        return response()->json(['scope' => 'isolated_v2_pilot_only', 'reversal_batch_id' => $batchId], 201);
    }

    /**
     * Isolated CV draft endpoints: never operate on V1's default database.
     */
    public function commissionDraft(
        Request $request, int $projectId, string $action,
        ?int $sourceId = null
    ): JsonResponse {
        $this->project($request, $projectId, true);
        $service = new \App\Services\Finance\V2CommissionDraftWorkflow();
        try {
            if ($action === 'create' && $sourceId === null) {
                $data = $request->validate([
                    'voucher_number' => ['required', 'string', 'max:64'],
                    'date' => ['required', 'date_format:Y-m-d'],
                    'lines' => ['required', 'array', 'min:2', 'max:100'],
                    'lines.*.account_id' => ['required', 'integer', 'min:1'],
                    'lines.*.debit' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/D'],
                    'lines.*.credit' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/D'],
                    'lines.*.description' => ['nullable', 'string', 'max:1000'],
                ]);
                $id = $service->createDraft(
                    $projectId, $data['voucher_number'], $data['date'],
                    $data['lines'], (int) $request->user()->getAuthIdentifier()
                );
                return response()->json(['source_id' => $id, 'status' => 'draft'], 201);
            }
            if ($sourceId === null || $sourceId < 1) {
                abort(404);
            }
            if ($action === 'update') {
                $data = $request->validate([
                    'date' => ['required', 'date_format:Y-m-d'],
                    'lines' => ['required', 'array', 'min:2', 'max:100'],
                    'lines.*.account_id' => ['required', 'integer', 'min:1'],
                    'lines.*.debit' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/D'],
                    'lines.*.credit' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/D'],
                ]);
                $service->updateDraft($projectId, $sourceId, $data['date'], $data['lines']);
                return response()->json(['source_id' => $sourceId, 'status' => 'draft']);
            }
            if ($action === 'cancel') {
                $service->cancelDraft($projectId, $sourceId);
                return response()->json(['source_id' => $sourceId, 'status' => 'cancelled']);
            }
            if ($action === 'approve') {
                $data = $request->validate([
                    'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
                ]);
                $batch = $service->approveAndPost(
                    $projectId, $sourceId, $data['request_key'],
                    (int) $request->user()->getAuthIdentifier()
                );
                return response()->json(['source_id' => $sourceId, 'posting_batch_id' => $batch], 201);
            }
            abort(404);
        } catch (\DomainException $exception) {
            return response()->json(['error' => 'V2_CV_DRAFT_CONFLICT'], 409);
        }
    }

    public function voidVoucher(
        Request $request, int $projectId, string $kind, int $sourceId
    ): JsonResponse {
        $this->project($request, $projectId, true);
        $data = $request->validate([
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:1', 'max:200'],
        ]);
        try {
            $batchId = (new \App\Services\Finance\V2VoucherLifecycleService())->reverse(
                $projectId, $kind, $sourceId,
                $data['request_key'], $data['effective_date'],
                $data['reason'], (int) $request->user()->getAuthIdentifier()
            );
        } catch (\DomainException $error) {
            return response()->json(['error' => 'V2_VOUCHER_VOID_CONFLICT'], 409);
        }
        return response()->json([
            'scope' => 'isolated_v2_pilot_only',
            'reversal_batch_id' => $batchId,
        ], 201);
    }

    public function reverse(Request $request, int $projectId, V2PostingGateway $gateway): JsonResponse
    {
        $this->project($request, $projectId, true);
        $data = $request->validate([
            'original_batch_id' => ['required', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:1', 'max:200'],
        ]);
        try {
        $id = $gateway->reversePostedBatch(
            $projectId, (int) $data['original_batch_id'],
            $data['request_key'], $data['effective_date'],
            $data['reason'], (int) $request->user()->getAuthIdentifier()
        );
        } catch (\DomainException $error) {
            return response()->json(['error' => 'V2_REVERSAL_CONFLICT'], 409);
        }
        return response()->json([
            'scope' => 'isolated_v2_pilot_only', 'reversal_batch_id' => $id,
        ], 201);
    }
}
