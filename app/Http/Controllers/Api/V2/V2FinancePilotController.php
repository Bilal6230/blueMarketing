<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Finance\V2IsolatedConnectionGuard;
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
        Gate::authorize($write ? 'v2-finance-post' : 'v2-finance-read');
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

    public function post(Request $request, int $projectId, V2PostingGateway $gateway): JsonResponse
    {
        $this->project($request, $projectId, true);
        $data = $request->validate([
            'kind' => ['required', Rule::in([
                'journal', 'cash', 'customer', 'commission',
                'booking', 'labour', 'stock_entry', 'stock_bill',
            ])],
            'source_id' => ['required', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'series' => ['required_if:kind,customer', 'nullable', Rule::in(['PPR', 'EC', 'ECP'])],
            'allocation' => ['required_unless:kind,journal,commission', 'array', 'min:2'],
            'allocation.*.account_id' => ['required_with:allocation', 'integer', 'min:1'],
            'allocation.*.debit' => ['required_with:allocation', 'numeric', 'min:0'],
            'allocation.*.credit' => ['required_with:allocation', 'numeric', 'min:0'],
            'allocation.*.description' => ['nullable', 'string', 'max:1000'],
        ]);
        $id = (int) $data['source_id'];
        $key = (string) $data['request_key'];
        $legs = $data['allocation'] ?? [];
        $actor = (int) $request->user()->getAuthIdentifier();
        $batchId = match ($data['kind']) {
            'journal' => $gateway->postJournal($projectId, $id, $key, $actor),
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
        return response()->json([
            'scope' => 'isolated_v2_pilot_only', 'posting_batch_id' => $batchId,
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
        $id = $gateway->reversePostedBatch(
            $projectId, (int) $data['original_batch_id'],
            $data['request_key'], $data['effective_date'],
            $data['reason'], (int) $request->user()->getAuthIdentifier()
        );
        return response()->json([
            'scope' => 'isolated_v2_pilot_only', 'reversal_batch_id' => $id,
        ], 201);
    }
}
