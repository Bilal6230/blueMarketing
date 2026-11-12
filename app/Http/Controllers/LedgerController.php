<?php

namespace App\Http\Controllers;

use App\Models\PendingUpdate;
use Exception;
use App\Models\Lead;
use App\Models\Plot;
use App\Models\User;
use App\Models\Ledger;
use App\Models\Project;

use App\Models\DraftLedger;

use Illuminate\Http\Request;
use App\Models\CustomerLedger;
use App\Models\HeadAccounting;
use App\Models\JournalVoucher;
use App\Models\SubheadAccounting;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;


class LedgerController extends Controller
{
    private function typedReport(Request $request, ?string $group = null)
    {
        $project = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorizeRead($project);
        $data = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $report = new \App\Services\Finance\V2OriginalLedgerReport();
        try {
            $rows = $report->rows($project, $data['start_date'] ?? null, $data['end_date'] ?? null);
        } catch (\DomainException $e) { abort(409, 'Ledger report integrity checks failed.'); }
        return DataTables::of(collect($group ? $report->totals($rows, $group) : $rows))
            ->with(['scope' => 'typed_v2_postings_only'])->toJson();
    }

    public function store(Request $request)
    {
        $fixedPaymentType = $request->route('paymentType');
        if ($fixedPaymentType !== null) {
            abort_unless((int) $request->input('payment_type') === (int) $fixedPaymentType, 422,
                'Payment method does not match this voucher screen.');
        }
        $customPaymentMethodId = $request->input('custom_payment_method_id');
        if ($customPaymentMethodId !== null && $customPaymentMethodId !== '') {
            $projectForMethod = (int) getSelectedTown();
            abort_unless(DB::getSchemaBuilder()->hasTable('project_custom_payment_methods')
                && DB::getSchemaBuilder()->hasColumn('customer_ledger', 'custom_payment_method_id'), 409,
                'Custom payment method migration is required.');
            $customMethod = DB::table('project_custom_payment_methods')->where('project_id', $projectForMethod)
                ->where('id', $customPaymentMethodId)->where('is_active', 1)->first();
            abort_unless($customMethod && (int) $customMethod->base_method_code === (int) $request->input('payment_type'), 422,
                'Select a valid payment method for this project.');
            $request->merge(['custom_payment_method_id' => (int) $customMethod->id]);
        } else {
            $request->merge(['custom_payment_method_id' => null]);
        }
        // Isolated V2 posting is a different writer and cannot consume legacy management claims.
        if($request->filled('passed_source_method')||$request->filled('passed_source_id')) {
            abort_if(\App\Services\Finance\V2OriginalWorkflowAccess::selected(),409,
                'Selected management sources require the audited standard voucher writer.');
        }
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            $project = (int) getSelectedTown();
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize($project);
            $data = \App\Services\Finance\V2OriginalVoucherInput::read($request);
            $labels = $request->validate([
                'voucher' => ['required', 'regex:/^(CR|CP)-[1-9][0-9]*$/D'],
                'voucher_number' => ['required', 'same:voucher'],
                'accounts_id' => ['required', 'integer'], 'subaccounts_id' => ['required', 'integer'],
            ]);
            abort_unless(!app(\App\Services\Finance\DailyVoucherApprovalWorkflow::class)->policy($project, substr($labels['voucher'], 0, 2)), 409,
                'This voucher has an approval policy; posting through the isolated pilot is disabled.');
            $account = ProjectHeadSubhead::availableForPosting()->where('project_id', $project)
                ->where('head_accounting_id', $labels['accounts_id'])->where('subhead_accounting_id', $labels['subaccounts_id'])->first();
            abort_unless($account, 422, 'Selected account does not belong to this project.');
            if (str_starts_with($labels['voucher'], 'CP-') && in_array((int) $data['payment_type'], [2, 3], true)) {
                abort_unless(Auth::user()->can('pass cheque'), 403);
                $settlement = $request->validate([
                    'selected_pending_payment_id' => ['required', 'integer', 'min:1'],
                    'pending_status' => ['required', 'integer', 'in:1,2,3'],
                    'bank_account_id' => ['required_if:pending_status,1', 'nullable', 'integer', 'min:1'],
                    'posting_lines' => ['required_if:pending_status,1', 'array', 'min:2', 'max:100'],
                ]);
                $receipt = CustomerLedger::query()->whereKey($settlement['selected_pending_payment_id'])
                    ->where('project_id', $project)->where('payment_type', $data['payment_type'])
                    ->whereIn('transaction_type', ['CR', 'PPR'])->first();
                abort_unless($receipt && \App\Services\Finance\V2OriginalCustomerWorkflow::money($data['amount'])
                    === \App\Services\Finance\V2OriginalCustomerWorkflow::money(max($receipt->amount_in, $receipt->amount_out))
                    && (string) $receipt->t_number === (string) $data['t_number'] && (int) $receipt->bank_id === (int) $data['bank_id'], 409, 'Selected receipt does not match the clearance.');
                try {
                    $result = (new \App\Services\Finance\V2OriginalBankWorkflow())->settle(
                        $project, (int) $receipt->id, (int) $settlement['pending_status'], $data['request_key'],
                        $data['passing_date'], $data['detail'], $data['posting_lines'] ?? [], (int) Auth::id(),
                        isset($settlement['bank_account_id']) ? (int) $settlement['bank_account_id'] : null
                    );
                } catch (\DomainException $e) { abort(409, 'Cash-out bank clearance could not be posted.'); }
                return response()->json(['status' => 'success', 'data' => $result + ['settled_existing_receipt' => true]]);
            }
            if (!in_array((int) $data['payment_type'], [2, 3], true)) {
                abort_unless(collect($data['posting_lines'])->contains(fn ($line) => (int) $line['account_id'] === (int) $account->id), 422, 'Posting allocation must include the selected account.');
            }
            try {
                $result = (new \App\Services\Finance\V2OriginalCustomerWorkflow())->create(
                    $project, substr($labels['voucher'], 0, 2), $data['request_key'],
                    \App\Services\Finance\V2OriginalVoucherInput::source($data), $data['posting_lines'] ?? [], (int) Auth::id()
                );
            } catch (\DomainException $e) {
                abort(409, 'Cash voucher could not be posted.');
            }
            return response()->json(['status' => 'success', 'data' => $result]);
        }
        $validator = Validator::make($request->all(), [
            'amount' => [
                'required',
                function ($attribute, $value, $fail) {
                    $clean = str_replace(',', '', (string) $value);
                    if (!preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/D', $clean) || (float) $clean <= 0) {
                        $fail('Amount must be a valid number greater than zero.');
                    }
                }
            ],
            'detail' => ['required', 'string', 'max:255'],
            'acct_type' => ['required', 'integer', 'min:1'],
            'accounts_id' => ['required', 'integer', 'min:1'],
            'subaccounts_id' => ['required', 'integer', 'min:1'],
            'payment_type' => ['required', 'integer', 'in:1,2,3'],
            'display_series' => ['nullable', 'string', 'in:CR,DR,CP,CCR,CDR,BOR,BOP'],
            'passed_source_method' => ['nullable','in:online,cheque'],
            'passed_source_id' => ['nullable','integer','min:1'],
            'custom_payment_method_id' => ['nullable', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'voucher' => ['required', 'string', 'regex:/^(CR|DR|CP|BOR|BOP)-\d+$/'],
            'voucher_number' => ['required', 'string', 'regex:/^(CR|DR|CP|BOR|BOP)-\d+$/'],
            't_number' => ['required_if:payment_type,2,3', 'nullable', 'string', 'max:255'],
            'bank_id' => ['required_if:payment_type,2,3', 'nullable', 'integer', 'min:1'],
            'passing_date' => ['required_if:payment_type,2,3', 'nullable', 'date_format:Y-m-d'],
            'cheque_purpose' => [
                'nullable', 'string', 'max:500',
                \Illuminate\Validation\Rule::requiredIf(fn () => (int) $request->input('payment_type') === 3
                    && !$request->filled('selected_pending_payment_id')
                    && !$request->filled('passed_source_id')),
            ],
        ]);

        if ($validator->fails()) {
            if ($validator->errors()->has('voucher') || $validator->errors()->has('voucher_number')) {
                return response()->json([
                    'status' => 'error',
                    'error_key' => 'invalid_voucher_number',
                    'message' => 'Invalid voucher number. Please refresh the page and try again.'
                ], 422);
            }

            return response()->json([
                'status' => 'error',
                'error_key' => 'validation_error',
                'message' => $validator->errors()->first() ?: 'Please correct the highlighted fields and try again.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $selectedProjectId = getSelectedTown();
        $voucherValue = (string) $request->input('voucher');
        $voucherNumberValue = (string) $request->input('voucher_number');
        $voucherParts = explode('-', $voucherValue);
        $voucherNumberParts = explode('-', $voucherNumberValue);
        $voucherPrefix = $voucherParts[0] ?? '';
        $voucherNumberPrefix = $voucherNumberParts[0] ?? '';

        if (!in_array($voucherPrefix, ['CR', 'DR', 'CP', 'BOR', 'BOP'], true) || $voucherPrefix !== $voucherNumberPrefix) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'invalid_voucher_number',
                'message' => 'Invalid voucher number. Please refresh the page and try again.'
            ], 422);
        }

        // Client-entered voucher labels cannot be trusted as independent
        // financial identities: both controls must agree and be positive.
        if ($voucherValue !== $voucherNumberValue ||
            !preg_match('/^(CR|DR|CP|BOR|BOP)-[1-9][0-9]*$/D', $voucherValue)) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'invalid_voucher_number',
                'message' => 'Voucher numbers do not match. Please refresh and try again.',
            ], 422);
        }
        // Form identity is authoritative for its own listing/sequence.
        // Legacy CR/CP source and ledger types remain intact until the
        // dedicated bank-ledger migration/approval workflow is accepted.
        $effectiveVoucherType = match ($voucherPrefix) {
            'DR', 'BOP' => 'CP',
            'BOR' => 'CR',
            default => $voucherPrefix,
        };
        $requestedSeries = strtoupper(trim((string)$request->input('display_series','')));
        if (($requestedSeries !== '' && $requestedSeries !== $voucherPrefix)
            || (in_array($voucherPrefix,['BOR','BOP'],true)
                && $requestedSeries !== $voucherPrefix)) {
            return response()->json(['status'=>'error',
                'message'=>'Voucher number series does not match the opened voucher page.'],422);
        }
        $voucherNumber = (int) ($voucherNumberParts[1] ?? 0);
        $cleanAmount = (float) str_replace(',', '', (string) $request->input('amount'));
        $paymentType = (int) $request->input('payment_type');
        $passedKind=(string)$request->input('passed_source_method','');
        $passedSourceId=(int)$request->input('passed_source_id',0);
        if(($passedKind==='')!==($passedSourceId===0) ||
            ($passedKind!=='' && !in_array($paymentType,[2,3],true))) {
            return response()->json(['status'=>'error','message'=>'Select one verified Online/Cheque source.'],422);
        }
        $linkedVerifiedSource=$passedKind!=='' && $passedSourceId>0;
        if($linkedVerifiedSource &&
            (($passedKind==='online' && $paymentType!==2) ||
             ($passedKind==='cheque' && $paymentType!==3))) {
            return response()->json(['status'=>'error',
                'message'=>'Selected passed source does not match payment method.'],422);
        }
        if(in_array((string)$request->input('display_series'),['BOR','BOP'],true)
            && !$linkedVerifiedSource) {
            return response()->json(['status'=>'error',
                'message'=>'Select a passed Online/Cheque Management record before saving this bank voucher.'],422);
        }
        $isCashOutPendingClearance = !$linkedVerifiedSource
            && in_array($paymentType, [2, 3], true) && $effectiveVoucherType === 'CP';
        $selectedPendingPaymentId = (int) $request->input('selected_pending_payment_id');
        $pendingStatus = (int) $request->input('pending_status');
        if ($isCashOutPendingClearance && (!$selectedPendingPaymentId || !in_array($pendingStatus, [1, 2, 3], true))) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'validation_error',
                'message' => 'Please select a pending voucher and valid status before saving.'
            ], 422);
        }
        $amount_in = $effectiveVoucherType === 'CR' ? $cleanAmount : 0;
        $amount_out = $effectiveVoucherType === 'CP' ? $cleanAmount : 0;

        $projectHeadSubhead = ProjectHeadSubhead::availableForPosting()->where('head_accounting_id', $request->accounts_id)
            ->where('subhead_accounting_id', $request->subaccounts_id)
            ->where('project_id', $selectedProjectId)
            ->first();

        if ($projectHeadSubhead && (int) $projectHeadSubhead->headAccounting?->acct_type !== (int) $request->input('acct_type')) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'invalid_account_type',
                'message' => 'Account Type does not match the selected account.',
                'errors' => ['acct_type' => ['Please select the matching Account Type.']],
            ], 422);
        }
        if (!$projectHeadSubhead) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'invalid_account_mapping',
                'message' => 'Selected account and child account are not valid for this project.'
            ], 422);
        }

        try {
            $responsePayload = DB::transaction(function () use (
                $request,
                $paymentType,
                $selectedProjectId,
                $selectedPendingPaymentId,
                $pendingStatus,
                $cleanAmount,
                $projectHeadSubhead,
                $effectiveVoucherType,
                $voucherNumber,
                $amount_in,
                $amount_out,
                $isCashOutPendingClearance,
                $linkedVerifiedSource,$passedKind,$passedSourceId,$voucherPrefix
            ) {
                // Coordinate manual CR/CP posting with other project-scoped
                // financial writers, including first-time voucher creation.
                // Project lock is acquired before any source/ledger write.
                if (!DB::table('projects')
                    ->where('id', $selectedProjectId)
                    ->lockForUpdate()
                    ->exists()) {
                    throw new \DomainException('CASH_VOUCHER_PROJECT_NOT_FOUND');
                }
                if($linkedVerifiedSource) {
                    app(\App\Services\Finance\PassedBankVoucherClaim::class)->verify(
                        (int)$selectedProjectId,$passedKind,$passedSourceId,
                        $effectiveVoucherType==='CR'?'receipt':'payment',
                        $paymentType,$cleanAmount,(string)$request->input('t_number'),
                        (int)$projectHeadSubhead->id,
                        (string)$request->input('passing_date'),
                        (int)$request->input('bank_id')
                    );
                }
                if ($paymentType === 1) {
                    $t_number = $bank_id = null;
                } else {
                    $t_number = $request->input('t_number');
                    $bank_id = $request->input('bank_id');
                }

                if ($isCashOutPendingClearance) {
                    $pendingCustomerLedger = CustomerLedger::where('project_id', $selectedProjectId)
                        ->where('id', $selectedPendingPaymentId)
                        ->where('payment_type', $paymentType)
                        ->where('passing_status', 0)
                        ->where('is_active', 1)
                        ->lockForUpdate()
                        ->first();

                    if (!$pendingCustomerLedger || !in_array((string) $pendingCustomerLedger->transaction_type, ['CR', 'PPR'], true)) {
                        throw new \RuntimeException('Selected pending payment is no longer available. Please refresh and try again.');
                    }

                    $pendingAmount = $this->normalizeVoucherAmount(
                        ($pendingCustomerLedger->amount_out > 0) ? $pendingCustomerLedger->amount_out : $pendingCustomerLedger->amount_in
                    );

                    if (!$this->voucherAmountsMatch($cleanAmount, $pendingAmount)) {
                        throw new \RuntimeException('Amount must match the selected pending voucher.');
                    }

                    $note = $request->input('detail');
                    $pendingBankName = $pendingCustomerLedger->bank_id ? getBankNameById($pendingCustomerLedger->bank_id) : null;
                    $clearanceReference = 'CASH_OUT_CLEARANCE#' . $pendingCustomerLedger->id;

                    $pendingCustomerLedger->update([
                        'passing_status' => $pendingStatus,
                        'note' => $note,
                        'bank_post_at' => $request->input('passing_date'),
                    ]);

                    $pendingCustomerLedger->addCheckHistory([
                        'id' => $pendingCustomerLedger->id,
                        'check_number' => $pendingCustomerLedger->t_number,
                        'passing_date' => $request->input('passing_date'),
                        'passing_status' => $pendingStatus,
                        'description_note' => $note,
                        'bank_name' => $pendingBankName,
                        'credit_account_id' => $projectHeadSubhead->id,
                        'cleared_by_voucher' => null,
                        'updated_by' => Auth::id(),
                    ]);

                    $pendingCustomerLedger->refresh();

                    $cashOutClearanceLedger = null;
                    $clearanceApprovalPending = false;
                    $createdClearanceLedger = false;
                    if ($pendingStatus === 1) {
                        $cashOutClearanceLedger = Ledger::where('customer_ledger_id', $pendingCustomerLedger->id)
                            ->where('type', 'CP')
                            ->where('reference', $clearanceReference)
                            ->lockForUpdate()
                            ->first();

                        if (!$cashOutClearanceLedger) {
                            $resolvedAmount = (float) (($pendingCustomerLedger->amount_out > 0)
                                ? $pendingCustomerLedger->amount_out
                                : $pendingCustomerLedger->amount_in);
                            $sourceReceiptLedger = Ledger::where('customer_ledger_id', $pendingCustomerLedger->id)
                                ->whereIn('type', ['CR', 'PPR'])
                                ->orderBy('id')
                                ->first();

                            $resolvedVoucherNumber = app(\App\Services\Finance\ProjectVoucherAllocator::class)
                                ->next((int) $selectedProjectId, 'CP');
                            if ($resolvedVoucherNumber !== $voucherNumber) {
                                throw new \DomainException('Voucher number changed since this form was opened. Refresh to use the current database number.');
                            }

                            $cashOutClearanceLedger = Ledger::create([
                                'customer_ledger_id' => $pendingCustomerLedger->id,
                                'voucher_number' => $resolvedVoucherNumber,
                                'type' => 'CP',
                                'type_id' => app(\App\Services\Finance\ProjectVoucherAllocator::class)
                                    ->nextLedgerId((int) $selectedProjectId, 'CP'),
                                'project_head_subheads_id' => $projectHeadSubhead->id,
                                'reference' => $clearanceReference,
                                'amount_in' => 0.00,
                                'amount_out' => $resolvedAmount,
                                'is_active' => 1,
                                'date' => $request->input('passing_date') ?: $request->input('date') ?: now()->toDateString(),
                                'detail' => $this->buildCashOutClearanceDetail(
                                    $pendingCustomerLedger,
                                    $sourceReceiptLedger,
                                    $projectHeadSubhead,
                                    $resolvedVoucherNumber,
                                    $request->input('passing_date') ?: $request->input('date') ?: now()->toDateString(),
                                    $note
                                ),
                                'update_by' => Auth::id(),
                                'create_by' => Auth::id(),
                                'status' => 0,
                            ]);
                            $createdClearanceLedger = true;
                        }

                        $approvalWorkflow = app(\App\Services\Finance\DailyVoucherApprovalWorkflow::class);
                        if ($approvalWorkflow->policy((int) $selectedProjectId, 'CP')) {
                            if (!$createdClearanceLedger) {
                                throw new \DomainException('Existing clearance ledger cannot be safely placed into a new approval policy.');
                            }
                            $clearanceApprovalPending = $approvalWorkflow->submitIfRequired(
                                (int) $selectedProjectId, 'CP', 'ledgers', (int) $cashOutClearanceLedger->id, (int) Auth::id()
                            );
                            if ($clearanceApprovalPending) {
                                DB::table('ledgers')->where('id', $cashOutClearanceLedger->id)->update(['is_active' => 0]);
                            }
                        }
                    }

                    session(['last_submit_date' => $request->date]);

                    if ($request->id) {
                        DraftLedger::find($request->input('id'))?->delete();
                    }

                    return [
                        'status' => 'success',
                        'flow_type' => $clearanceApprovalPending ? 'pending_approval' : 'pending_payment_status_updated',
                        'message' => $clearanceApprovalPending
                            ? 'Bank clearance submitted for approval. Its accounting entry remains inactive until approved.'
                            : 'Payment status updated successfully.',
                        'data' => $cashOutClearanceLedger,
                        'customer_ledger_id' => $pendingCustomerLedger->id,
                        'selected_pending_payment_id' => $pendingCustomerLedger->id,
                        'pending_status' => $pendingCustomerLedger->passing_status,
                        'voucher_number' => $cashOutClearanceLedger?->voucher_number,
                        'voucher_display' => $cashOutClearanceLedger ? ('CP-' . $cashOutClearanceLedger->voucher_number) : null,
                    ];
                }

                $customerID = $projectHeadSubhead?->customer_id;
                $plotID = $projectHeadSubhead?->plot_id;
                $bankName = $bank_id ? getBankNameById($bank_id) : null;
                $isPendingCashInBankPayment = !$linkedVerifiedSource
                    && in_array($paymentType, [2, 3], true) && $effectiveVoucherType === 'CR';

                // A cash voucher's project, series, account ownership and slip
                // reference form its submission identity. A retry must never
                // create another source row or ledger leg.
                $sourceReference = trim((string) $request->input('reference'));
                if ($sourceReference !== '' && CustomerLedger::query()
                    ->where('project_id', $selectedProjectId)
                    ->where('transaction_type', $effectiveVoucherType)
                    ->where('reference', $sourceReference)
                    ->where('customer_id', $customerID)
                    ->where('plot_id', $plotID)
                    ->lockForUpdate()
                    ->exists()) {
                    throw new \DomainException('DUPLICATE_CASH_VOUCHER_SOURCE');
                }

                // Reserve once BEFORE inserting the source: allocating after
                // source creation incorrectly skips the just-created number.
                $resolvedVoucherNumber = app(\App\Services\Finance\ProjectVoucherAllocator::class)
                    ->next((int) $selectedProjectId, $effectiveVoucherType);
                // Never save a different number than the one displayed in the
                // active form. Transaction rollback also releases the counter.
                $bankSeriesNumber = null;
                if (in_array($voucherPrefix, ['BOR','BOP'], true)) {
                    // Reserve bank-specific voucher number independently of
                    // the underlying legacy CR/CP source identity.
                    $bankSeriesNumber = app(\App\Services\Finance\ProjectVoucherAllocator::class)
                        ->next((int)$selectedProjectId, $voucherPrefix);
                    if ($bankSeriesNumber !== $voucherNumber) {
                        throw new \DomainException('Bank voucher number changed. Refresh the form before saving.');
                    }
                } elseif ($resolvedVoucherNumber !== $voucherNumber) {
                    throw new \DomainException('Voucher number changed since this form was opened. Refresh and retry to get the current database number.');
                }

                $customerLedger = CustomerLedger::create([
                    'transaction_type' => $effectiveVoucherType,
                    'type_id' => $resolvedVoucherNumber,
                    'reference' => $request->input('reference'),
                    'project_id' => $selectedProjectId ?? null,
                    'customer_id' => $customerID ?? null,
                    'plot_id' => $plotID ?? null,
                    'amount_in' => $amount_in,
                    'amount_out' => $amount_out,
                    'description' => $request->input('detail'),
                    'date' => $request->input('date'),
                    'payment_type' => $paymentType,
                    'custom_payment_method_id' => $request->input('custom_payment_method_id') ?: null,
                    't_number' => $t_number,
                    'bank_id' => $bank_id,
                    'is_active' => 1,
                    'is_approve' => 0,
                    'passing_date' => $request->input('passing_date'),
                    'passing_status' => $isPendingCashInBankPayment ? 0 : null,
                    'cheque_purpose' => $paymentType === 3 ? trim((string) $request->input('cheque_purpose')) : null,
                ]);
                $displaySeries = match ([$effectiveVoucherType, $paymentType]) {
                    ['CR', 1] => 'CCR', ['CP', 1] => 'CDR',
                    ['CR', 2] => 'BOR', ['CP', 2] => 'BOP',
                    ['CR', 3] => 'BOR', ['CP', 3] => 'BOP',
                    default => $effectiveVoucherType,
                };
                $requestedDisplaySeries = strtoupper(trim((string) $request->input('display_series')));
                if ($requestedDisplaySeries !== '') {
                    $seriesMapping = [
                        'CCR' => ['CR', 1], 'CDR' => ['CP', 1],
                        'CR' => ['CR', 1], 'DR' => ['CP', 1],
                        'BOR' => ['CR', 2], 'BOP' => ['CP', 2],
                        'CP' => ['CP', 1],
                    ];
                    $seriesAllowed = match ($requestedDisplaySeries) {
                        'CR' => $effectiveVoucherType === 'CR' && in_array($paymentType, [1, 2, 3], true),
                        'DR' => $effectiveVoucherType === 'CP' && in_array($paymentType, [1, 2, 3], true),
                        'BOR' => $effectiveVoucherType === 'CR' && in_array($paymentType, [2, 3], true),
                        'BOP' => $effectiveVoucherType === 'CP' && in_array($paymentType, [2, 3], true),
                        default => isset($seriesMapping[$requestedDisplaySeries])
                            && $seriesMapping[$requestedDisplaySeries] === [$effectiveVoucherType, $paymentType],
                    };
                    abort_unless($seriesAllowed,
                        422, 'Voucher series does not match its direction and payment method.');
                    $displaySeries = $requestedDisplaySeries;
                }
                $displayNumber = app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)->assign(
                    (int) $selectedProjectId, 'customer_ledger', (int) $customerLedger->id,
                    $displaySeries, \Carbon\Carbon::parse($request->input('date'))->toDateString(),
                    $bankSeriesNumber
                );

                if ($t_number != null) {
                    $check_slip = $customerLedger->transaction_type . '-' . $customerLedger->reference;

                    if ($isPendingCashInBankPayment) {
                        $customerLedger->update([
                            'passing_status' => 0,
                            'note' => null,
                            'bank_post_at' => null,
                        ]);
                    } else {
                        $passing_status = ($effectiveVoucherType === 'CP' || $linkedVerifiedSource) ? 1 : 0;
                        $customerLedger->update([
                            'passing_status' => $passing_status,
                            'note' => '(' . $check_slip . ') ' . $request->detail,
                            'bank_post_at' => $request->passing_date,
                        ]);

                        $customerLedger->addCheckHistory([
                            'id' => $customerLedger->id,
                            'check_number' => $customerLedger->t_number,
                            'passing_date' => $request->passing_date,
                            'passing_status' => '1',
                            'description_note' => '(' . $check_slip . ') ' . $request->detail,
                            'bank_name' => $bankName,
                            'credit_account_id' => $projectHeadSubhead->id,
                        ]);
                    }
                }


                $detail = $this->buildCashVoucherDetail(
                    $effectiveVoucherType,
                    $paymentType,
                    $request->input('reference'),
                    $request->input('date'),
                    $request->input('passing_date'),
                    $request->input('detail'),
                    $bankName,
                    $customerLedger->t_number
                );

                $data = Ledger::create([
                    'customer_ledger_id' => $customerLedger->id,
                    'voucher_number' => $resolvedVoucherNumber,
                    'type' => $effectiveVoucherType,
                    'type_id' => $resolvedVoucherNumber,
                    'project_head_subheads_id' => $projectHeadSubhead->id,
                    'reference' => $request->reference,
                    'amount_in' => $amount_in,
                    'amount_out' => $amount_out,
                    'is_active' => 1,
                    'date' => $request->date,
                    'detail' => $detail,
                    'update_by' => Auth::user()->id,
                    'create_by' => Auth::user()->id,
                    'status' => 0,
                ]);

                if($linkedVerifiedSource) {
                    app(\App\Services\Finance\PassedBankVoucherClaim::class)->claim(
                        (int)$selectedProjectId,$passedKind,$passedSourceId,
                        $displaySeries,(int)$customerLedger->id,(int)Auth::id(),$cleanAmount);
                }

                $approvalPending = app(\App\Services\Finance\DailyVoucherApprovalWorkflow::class)
                    ->submitIfRequired((int) $selectedProjectId, $effectiveVoucherType, 'customer_ledger', (int) $customerLedger->id, (int) Auth::id());
                if ($approvalPending) {
                    // The source and ledger leg are retained for review, but excluded
                    // from posting reports until the assigned approver accepts them.
                    DB::table('customer_ledger')->where('id', $customerLedger->id)
                        ->where('project_id', $selectedProjectId)->update(['is_active' => 0, 'is_approve' => 0]);
                    DB::table('ledgers')->where('id', $data->id)
                        ->whereIn('project_head_subheads_id', DB::table('project_head_subheads')->select('id')->where('project_id', $selectedProjectId))
                        ->update(['is_active' => 0]);
                }

                session(['last_submit_date' => $request->date]);

                if ($request->id) {
                    DraftLedger::find($request->input('id'))?->delete();
                }

                $flowType = $approvalPending ? 'pending_approval' : ($isPendingCashInBankPayment ? 'posted_pending_clearance' : 'posted');
                $successMessage = $approvalPending
                    ? 'Voucher submitted for approval. It is not posted until the assigned approver accepts it.'
                    : ($isPendingCashInBankPayment
                    ? 'Voucher saved successfully. Payment status is pending clearance.'
                    : 'Data saved successfully.');

                return [
                    'status' => 'success',
                    'flow_type' => $flowType,
                    'message' => $successMessage,
                    'data' => $data,
                    'customer_ledger_id' => $customerLedger->id,
                    'voucher_number' => $resolvedVoucherNumber,
                    'voucher_display' => $displayNumber ?: ($effectiveVoucherType . '-' . $resolvedVoucherNumber),
                ];
            });

            return response()->json($responsePayload, 200);
        } catch (\Illuminate\Database\QueryException $e) {
            if($linkedVerifiedSource && in_array((string)$e->getCode(),['23000','23505'],true)){
                return response()->json(['status'=>'error','error_key'=>'bank_source_already_used',
                    'message'=>'This verified source allocation conflicts with an existing voucher. Refresh the remaining balance.'],409);
            }
            Log::error('bank_voucher_database_failure', ['error'=>$e->getMessage(),
                'project_id'=>$selectedProjectId]);
            return response()->json(['status'=>'error','message'=>'Unable to save voucher. Please retry after checking the source.'],500);
        } catch (\DomainException $e) {
            return response()->json(['status'=>'error','error_key'=>'bank_source_conflict',
                'message'=>$e->getMessage()],409);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'pending_payment_invalid_state',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            Log::error('voucher_store_failed', [
                'user_id' => Auth::id(),
                'project_id' => getSelectedTown(),
                'voucher' => $request->input('voucher'),
                'voucher_number' => $request->input('voucher_number'),
                'payment_type' => $request->input('payment_type'),
                'accounts_id' => $request->input('accounts_id'),
                'subaccounts_id' => $request->input('subaccounts_id'),
                'error' => $th->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'error_key' => 'voucher_store_failed',
                'message' => 'Unable to save voucher right now. Please try again.'
            ], 500);
        }
    }

    private function normalizeVoucherAmount($amount): string
    {
        $normalized = str_replace(',', '', trim((string) $amount));

        if ($normalized === '' || $normalized === '.') {
            return '0.00';
        }

        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$integerPart, $fractionPart] = array_pad(explode('.', $normalized, 2), 2, '');
        $integerPart = ltrim(preg_replace('/\D/', '', $integerPart), '0');
        $fractionPart = preg_replace('/\D/', '', $fractionPart);

        if ($integerPart === '') {
            $integerPart = '0';
        }

        $fractionPart = substr(str_pad($fractionPart, 2, '0'), 0, 2);
        $result = $integerPart . '.' . $fractionPart;

        return $negative && $result !== '0.00' ? '-' . $result : $result;
    }

    private function voucherAmountsMatch($submittedAmount, $pendingAmount): bool
    {
        return $this->normalizeVoucherAmount($submittedAmount) === $this->normalizeVoucherAmount($pendingAmount);
    }

    private function formatPlotLabel(?Plot $plot): string
    {
        if (!$plot) {
            return 'Unknown Plot';
        }

        $prefix = match ((int) ($plot->type ?? 0)) {
            1 => 'R-',
            2 => 'C-',
            default => '',
        };

        return 'Plot ' . trim($prefix . trim((string) ($plot->name ?? '')));
    }

    private function formatPartyName(?Lead $customer): string
    {
        $name = trim((string) (($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? '')));

        return $name !== '' ? $name : 'Unknown Customer';
    }

    private function paymentMethodLabel(int $paymentType): string
    {
        return match ($paymentType) {
            2 => 'online transfer',
            3 => 'cheque',
            default => 'cash',
        };
    }

    private function paymentInstrumentLabel(int $paymentType): ?string
    {
        return match ($paymentType) {
            2 => 'Txn',
            3 => 'Chq',
            default => null,
        };
    }

    private function paymentMethodCode(int $paymentType): string
    {
        return match ($paymentType) {
            2 => 'Online',
            3 => 'Cheque',
            default => 'Cash',
        };
    }

    private function appendDetailPart(array &$parts, string $label, $value): void
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return;
        }

        $parts[] = $label . ': ' . $normalized;
    }

    private function buildCashVoucherDetail(
        string $voucherType,
        int $paymentType,
        ?string $reference,
        ?string $receiptDate,
        ?string $passingDate,
        ?string $narration,
        ?string $bankName = null,
        ?string $instrumentNumber = null
    ): string {
        if ($voucherType !== 'CR') {
            return trim((string) $narration);
        }

        $parts = ['CR'];
        $this->appendDetailPart($parts, 'Method', $this->paymentMethodCode($paymentType));

        if (in_array($paymentType, [2, 3], true)) {
            $this->appendDetailPart($parts, 'Bank', $bankName);
            $this->appendDetailPart($parts, $paymentType === 2 ? 'Txn' : 'Chq', $instrumentNumber);
            $this->appendDetailPart($parts, 'Pass', $passingDate);
        }

        $this->appendDetailPart($parts, 'Ref', $reference);
        $this->appendDetailPart($parts, 'Date', $receiptDate);
        $this->appendDetailPart($parts, 'Note', $narration);

        return implode(' | ', $parts);
    }

    private function buildCashOutClearanceDetail(
        CustomerLedger $pendingCustomerLedger,
        ?Ledger $sourceReceiptLedger,
        ProjectHeadSubhead $destinationAccount,
        int $cashOutVoucherNumber,
        ?string $clearanceDate,
        ?string $narration
    ): string {
        $pendingCustomerLedger->loadMissing(['customer_list', 'plot_list', 'bookingVoucher']);
        $destinationAccount->loadMissing(['headAccounting', 'subheadAccounting']);

        $sourceType = (string) ($pendingCustomerLedger->transaction_type ?? '');
        $sourceVoucherNumber = $sourceReceiptLedger?->voucher_number
            ?? ($sourceType === 'PPR' ? $pendingCustomerLedger->bookingVoucher?->voucher_number : null)
            ?? $pendingCustomerLedger->type_id;
        $sourceVoucherDisplay = trim($sourceType . '-' . $sourceVoucherNumber, '-');
        $parts = ['CP Clear'];
        $this->appendDetailPart($parts, 'Src', $sourceVoucherDisplay);
        if ($sourceType === 'PPR') {
            $this->appendDetailPart($parts, 'Plot', $pendingCustomerLedger->plot_list ? $this->formatPlotLabel($pendingCustomerLedger->plot_list) : null);
            $this->appendDetailPart($parts, 'Cust', $pendingCustomerLedger->customer_list ? $this->formatPartyName($pendingCustomerLedger->customer_list) : null);
        } elseif ($pendingCustomerLedger->customer_list) {
            $this->appendDetailPart($parts, 'Cust', $this->formatPartyName($pendingCustomerLedger->customer_list));
        }

        $bankName = $pendingCustomerLedger->bank_id ? trim((string) getBankNameById($pendingCustomerLedger->bank_id)) : '';
        $origParts = [$this->paymentMethodCode((int) $pendingCustomerLedger->payment_type)];
        if ($bankName !== '') {
            $origParts[] = $bankName;
        }
        $instrumentLabel = $this->paymentInstrumentLabel((int) $pendingCustomerLedger->payment_type);
        $instrumentNumber = trim((string) ($pendingCustomerLedger->t_number ?? ''));
        if ($instrumentLabel && $instrumentNumber !== '') {
            $origParts[] = $instrumentLabel . ' ' . $instrumentNumber;
        }
        $this->appendDetailPart($parts, 'Orig', implode('/', $origParts));

        $destinationHead = trim((string) ($destinationAccount->headAccounting->name ?? ''));
        $destinationSubhead = trim((string) ($destinationAccount->subheadAccounting->name ?? ''));
        $destinationLabel = trim($destinationHead . ($destinationSubhead !== '' ? '/' . $destinationSubhead : ''));
        $this->appendDetailPart($parts, 'To', $destinationLabel);
        $this->appendDetailPart($parts, 'Date', $clearanceDate);
        $this->appendDetailPart($parts, 'Note', $narration);

        return implode(' | ', $parts);
    }

    public function saveAsDraft(Request $request)
    {
        $fixedPaymentType = $request->route('paymentType');
        if ($fixedPaymentType !== null) {
            abort_unless((int) $request->input('payment_type') === (int) $fixedPaymentType, 422,
                'Payment method does not match this voucher screen.');
        }
        $selectedProjectId = getSelectedTown();
        $action = $request->input('action');
        $customer_id = $request->input('customer_id');
        $x['today'] = date("d-m-Y");
        $cleanAmount = $request->amount ? str_replace(',', '', $request->amount) : 0;
        $voucherValue = $request->input('voucher');
        $firstTwoDigits = substr($voucherValue, 0, 2);
        $amount_in = $amount_out = 0;
        if ($firstTwoDigits === 'CR') {
            $amount_in = $cleanAmount;
        } elseif ($firstTwoDigits === 'CP') {
            $amount_out = $cleanAmount;
        }
        DB::beginTransaction();
        try {
            $payment_type = $request->input('payment_type');
            if ($payment_type == 1) {
                $t_number = $bank_id = null;
            } else {
                $t_number = $request->input('t_number');
                $bank_id = $request->input('bank_id');
            }
            $projectHeadSubhead = ProjectHeadSubhead::availableForPosting()->where('head_accounting_id', $request->accounts_id)
                ->where('subhead_accounting_id', $request->subaccounts_id)
                ->where('project_id', $selectedProjectId)
                ->first();

            // if (!$projectHeadSubhead) {
            //     throw new \Exception('Credit account ID not found.');
            // }
            $draftLedger = $request->filled('id')
                ? DraftLedger::query()->whereKey((int) $request->input('id'))
                    ->where('project_id', $selectedProjectId)->firstOrFail()
                : null;
            if ($fixedPaymentType !== null && $draftLedger) {
                abort_unless((int) $draftLedger->payment_type === (int) $fixedPaymentType, 422,
                    'This draft belongs to a different payment method.');
            }

            $newValues = [
                // Customer Ledger Fields
                'customer_id' => $customer_id,
                'transaction_type' => $firstTwoDigits,
                'type_id' => get_new_typeID($firstTwoDigits),
                'reference' => $request->input('reference'),
                'project_id' => $selectedProjectId,
                'plot_id' => $request->input('plot_id'),
                'amount_in' => $amount_in ?? 0,
                'amount_out' => $amount_out ?? 0,
                'description' => $request->input('detail'),
                'date' => $request->input('date'),
                'payment_type' => $request->input('payment_type'),
                't_number' => $t_number,
                'bank_id' => $bank_id,
                'is_active' => 1, // As Draft
                'is_approve' => 0,
                'passing_date' => $request->input('passing_date'),
                'check_history' => $request->input('check_history'),
                'note' => $request->input('note'),
                'bank_post_at' => $request->input('bank_post_at'),

                // Ledger Fields
                'type' => $firstTwoDigits,
                'project_head_subheads_id' => $projectHeadSubhead?->id,
                'detail' => $request->input('detail'),
                'update_by' => Auth::user()->id,
                'status' => 'draft', // you can set string status
            ];

            if ($draftLedger) {
                $oldValues = $draftLedger->only(array_keys($newValues));
                if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update')) {
                    $draftLedger->update($newValues);
                    DB::commit();
                    return back()->with('success', 'Record updated successfully (direct update).');
                }
                $pendingUpdate = PendingUpdate::where('table_name', 'draft_ledgers')
                    ->where('record_id', $draftLedger->id)
                    ->where('status', 'pending')
                    ->first();

                if ($pendingUpdate) {
                    $pendingUpdate->update([
                        'old_values' => json_encode($oldValues),
                        'new_values' => json_encode($newValues),
                        'submitted_by' => Auth::id(),
                    ]);
                } else {
                    PendingUpdate::create([
                        'table_name' => 'draft_ledgers',
                        'record_id' => $draftLedger->id,
                        'old_values' => json_encode($oldValues),
                        'new_values' => json_encode($newValues),
                        'status' => 'pending',
                        'submitted_by' => Auth::id(),
                    ]);
                }
            } else {
                $data['create_by'] = Auth::user()->id;
                DraftLedger::create($newValues);
            }
            DB::commit();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $th) {
            DB::rollBack();
            throw $th;
        } catch (\Throwable $th) {
            DB::rollBack();
            Alert::error('Notification', 'Unable to save draft right now.')->toToast()->toHtml();
            return back();
        }
        return back();
        return response()->json(['message' => 'Draft saved successfully.']);
    }

    public function getSubaccountDetails(Request $request)
    {
        $subaccountId = $request->input('subaccounts_id');
        $selectedProjectId = getSelectedTown();
        $subaccount = ProjectHeadSubhead::with(['subheadAccounting', 'headAccounting'])
            ->withSum('ledgers as total_in', 'amount_in')
            ->withSum('ledgers as total_out', 'amount_out')
            ->where(['subhead_accounting_id' => $subaccountId])
            ->where(['project_id' => $selectedProjectId])
            ->get()
            ->map(function ($item) {
                $item->balance = ($item->total_in ?? 0) - ($item->total_out ?? 0);
                return $item;
            });
        // dd($subaccount);
        return response()->json([
            'headId' => $subaccount[0]->headAccounting->id,
            'headName' => $subaccount[0]->headAccounting->name,
            'acct_type' => $subaccount[0]->headAccounting->acct_type,
            'balance' => $subaccount[0]->balance,
            'cnic' => $subaccount[0]->subheadAccounting->cnic,
            'phone' => $subaccount[0]->subheadAccounting->phone,
        ]);
    }

    public function show(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'voucher_type' => ['nullable', 'in:CR,CP'],
            'voucher_number' => ['nullable', 'integer', 'min:1'],
        ]);
        $base = Ledger::with([
            'customerLedger',
            'projectHeadSubhead.headAccounting',
            'projectHeadSubhead.subheadAccounting',
            'projectHeadSubhead.project',
        ])
            ->whereIn('type', ['CR', 'CP'])
            ->whereNotNull('customer_ledger_id')
            ->whereHas('projectHeadSubhead', function ($q) {
                $q->where('project_id', getSelectedTown());
            });
        $ledger = (clone $base)->whereKey((int) $data['id'])->first();
        if (!$ledger && isset($data['voucher_type'], $data['voucher_number'])) {
            $matches = (clone $base)->where('type', $data['voucher_type'])
                ->where('voucher_number', (int) $data['voucher_number'])->limit(2)->get();
            $ledger = $matches->count() === 1 ? $matches->first() : null;
        }
        if (!$ledger) {
            return response()->json([
                'status' => 'error',
                'message' => 'The voucher was not found in the selected project. Please refresh the page and try again.',
            ], 404);
        }

        $customerLedger = $ledger->customerLedger;
        $accountTypeId = (int) ($ledger->projectHeadSubhead->headAccounting->acct_type ?? 0);
        $headAccountingId = $ledger->projectHeadSubhead->head_accounting_id ?? null;
        $subheadAccountingId = $ledger->projectHeadSubhead->subhead_accounting_id ?? null;
        $amountDisplay = $ledger->type === 'CP' ? (float) ($ledger->amount_out ?? 0) : (float) ($ledger->amount_in ?? 0);
        $displayNumber = app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)
            ->lookup((int) getSelectedTown(), 'customer_ledger', (int) $customerLedger?->id);

        return response()->json([
            'status' => Response::HTTP_OK,
            'data' => [
                'id' => $ledger->id,
                'type' => $ledger->type,
                'voucher_number' => $ledger->voucher_number,
                'voucher_number_display' => $displayNumber ?: (($ledger->type === 'CP' ? 'DR' : $ledger->type) . '-' . ($customerLedger?->type_id ?: $ledger->voucher_number)),
                'date' => $ledger->date,
                'detail' => $ledger->detail,
                'amount_in' => (float) ($ledger->amount_in ?? 0),
                'amount_out' => (float) ($ledger->amount_out ?? 0),
                'amount_display' => $amountDisplay,
                'account_type_id' => $accountTypeId,
                'account_type_name' => getAccountTypeName($accountTypeId),
                'head_accounting_id' => $headAccountingId,
                'head_accounting_name' => $ledger->projectHeadSubhead->headAccounting->name ?? null,
                'subhead_accounting_id' => $subheadAccountingId,
                'subhead_accounting_name' => $ledger->projectHeadSubhead->subheadAccounting->name ?? null,
                'customer_ledger' => $customerLedger ? [
                    'id' => $customerLedger->id,
                    'payment_type' => $customerLedger->payment_type !== null ? (int) $customerLedger->payment_type : null,
                    't_number' => $customerLedger->t_number,
                    'bank_id' => $customerLedger->bank_id !== null ? (int) $customerLedger->bank_id : null,
                    'bank_name' => $customerLedger->bank_id ? getBankNameById($customerLedger->bank_id) : null,
                    'passing_date' => $customerLedger->passing_date,
                    'passing_status' => $customerLedger->passing_status !== null ? (int) $customerLedger->passing_status : null,
                    'cheque_purpose' => $customerLedger->cheque_purpose,
                    'note' => $customerLedger->note,
                    'bank_post_at' => $customerLedger->bank_post_at,
                ] : null,
            ]
        ], Response::HTTP_OK);
    }
    public function customerLedgerShow(Request $request)
    {
        $data_list = CustomerLedger::with([
            'ledger' => function ($q) {
                $q->with([
                    'projectHeadSubhead.headAccounting',
                    'projectHeadSubhead.subheadAccounting',
                    'projectHeadSubhead.project'
                ]);
            }
        ])->where('id', $request->id)->first();
        // $data_list = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project', 'customerLedger')->where(['id' => $request->id])->first();
        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }
    public function draftShow(Request $request)
    {
        $data_list = DraftLedger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')->where(['id' => $request->id])->first();
        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }

    public function show_ledger()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Cash Book';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CR';
        $x['class'] = 'cash-in';

        $selectedProjectId = getSelectedTown();

        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('is_active', 1) // Add this condition to filter by is_active
            ->where('type', 'CR')
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            })
            ->get();
        // dd($data->where('type', 'JV')->toArray());
        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
            return $item;
        });
        $x['data'] = $data;


        $projects = Project::where('id', $selectedProjectId)->get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.show_ledger', $x);
    }

    public function destroy(Request $request)
    {
        // Administrative soft-deactivation: preserve the posted voucher and
        // all of its accounting rows. A linked source must be inactive too,
        // otherwise customer-ledger reports would retain an active posting.
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'delete_reason' => ['required', 'string', 'max:500'],
        ]);
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project first.');
        $expectsJson = $request->expectsJson() || $request->ajax();

        try {
            $changed = DB::transaction(function () use ($validated, $projectId): bool {
                $ledger = Ledger::query()->whereKey((int) $validated['id'])
                    ->whereIn('type', ['CR', 'CP','CCR','CDR'])
                    ->whereHas('projectHeadSubhead', fn ($query) => $query->where('project_id', $projectId))
                    ->lockForUpdate()->firstOrFail();

                if ((int) $ledger->is_active === 0) return false;

                $reason = trim((string) $validated['delete_reason']);
                if ($ledger->customer_ledger_id !== null) {
                    $source = \App\Models\CustomerLedger::query()
                        ->whereKey((int) $ledger->customer_ledger_id)
                        ->where('project_id', $projectId)
                        ->where('transaction_type', $ledger->type)
                        ->lockForUpdate()->firstOrFail();
                    $trackedApproval = \App\Models\DailyVoucherApproval::query()
                        ->where('project_id', $projectId)
                        ->where('source_table', 'customer_ledger')
                        ->where('source_id', $source->id)->lockForUpdate()->first();
                    $approvedCashVoid = $trackedApproval && $trackedApproval->status === 'approved'
                        && (int) $source->payment_type === 1
                        && (int) $source->is_active === 1;
                    // Pending edits/corrections must finish before the original
                    // can be soft-deactivated, even for approved cash sources.
                    app(\App\Services\Finance\VoucherApprovalMutationGuard::class)
                        ->assertNoPendingCorrection($projectId, (int) $source->id);
                    if (!$approvedCashVoid) {
                        app(\App\Services\Finance\VoucherApprovalMutationGuard::class)
                            ->assertLegacyMutationAllowed($projectId, 'customer_ledger', (int) $source->id);
                    }
                    // Approved CASH vouchers may be administratively voided,
                    // never hard-deleted. Settled bank/cheque sources require an
                    // independent clearing reversal and remain blocked.
                    if ($approvedCashVoid) {
                        abort_unless(auth()->user()?->can('delete voucher'), 403);
                        DB::table('daily_voucher_approval_events')->insert([
                            'approval_id' => $trackedApproval->id,
                            'event' => 'approved_cash_void',
                            'actor_id' => (int) auth()->id(),
                            'remarks' => $reason,
                            'created_at' => now(),
                        ]);
                    }

                    // A voucher's linked accounting legs must be deactivated
                    // together, never just one side of a multi-leg posting.
                    $linked = Ledger::query()->where('customer_ledger_id', $source->id)
                        ->lockForUpdate()->get();
                    if ($linked->isEmpty()) {
                        throw new \DomainException('Voucher accounting legs were not found.');
                    }
                    foreach ($linked as $leg) {
                        if (!$leg->projectHeadSubhead || (int) $leg->projectHeadSubhead->project_id !== $projectId) {
                            throw new \DomainException('Voucher contains a ledger entry outside this project.');
                        }
                        if ((int) $leg->is_active === 1) {
                            $leg->is_active = 0;
                            $leg->delete_reason = $reason;
                            $leg->update_by = auth()->id();
                            $leg->save();
                        }
                    }
                    if ((int) $source->is_active === 1) {
                        $source->is_active = 0;
                        $source->delete_reason = $reason;
                        $source->save();
                    }
                } else {
                    $ledger->is_active = 0;
                    $ledger->delete_reason = $reason;
                    $ledger->update_by = auth()->id();
                    $ledger->save();
                }
                return true;
            });

            $message = $changed ? 'Voucher deactivated successfully. No ledger rows were deleted.' : 'Voucher is already inactive.';
            if ($expectsJson) return response()->json(['status' => 'success', 'message' => $message]);
            Alert::success('Voucher Updated', $message)->toToast()->toHtml();
            return back();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            if ($expectsJson) return response()->json(['status' => 'error', 'message' => 'Voucher not found in this project.'], 404);
            Alert::error('Voucher not found', 'Voucher not found in this project.')->toToast()->toHtml();
            return back();
        } catch (\DomainException $exception) {
            if ($expectsJson) return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
            Alert::error('Cannot deactivate', $exception->getMessage())->toToast()->toHtml();
            return back();
        }
    }

    public function draftDestroy(Request $request)
    {
        $data = [
            'is_active' => "0",
        ];
        DB::beginTransaction();
        try {
            $result = DraftLedger::find($request->id);
            if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update')) {
                $result->update(['is_active' => 0]);
                DB::commit();

                Alert::success('Notification', 'Voucher <b>' . $result->detail . '</b> deleted successfully.')
                    ->toToast()->toHtml();
                return back();
            }

            PendingUpdate::create([
                'table_name' => 'draft_ledgers',
                'record_id' => $result->id,
                'old_values' => json_encode($result->toArray()),
                'new_values' => json_encode($data),
                'status' => 'pending',
                'submitted_by' => Auth::id(),
            ]);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> Deleted')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> failed to delete: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function update(Request $request, ?int $paymentType = null)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'], 'amount' => ['required', 'regex:/^\d[\d,]*(?:\.\d{1,2})?$/D'],
            'detail' => ['required', 'string', 'max:255'],
            'edit_reason' => ['required', 'string', 'min:3', 'max:220'],
            'accounts_id' => ['required', 'integer'],
            'subaccounts_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'],
            'payment_type' => ['required', 'integer', 'in:1,2,3'],
            't_number' => ['required_if:payment_type,2,3', 'nullable', 'string', 'max:255'],
            'bank_id' => ['required_if:payment_type,2,3', 'nullable', 'integer'],
            'passing_date' => ['required_if:payment_type,2,3', 'nullable', 'date_format:Y-m-d'],
            'cheque_purpose' => ['required_if:payment_type,3', 'nullable', 'string', 'max:500'],
            'passing_status' => ['nullable', 'integer', 'in:0,1,2,3'],
        ]);
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project.');
        $ledger = Ledger::query()->whereKey((int) $data['id'])
            ->whereIn('type', ['CR', 'CP'])->whereNotNull('customer_ledger_id')
            ->whereHas('projectHeadSubhead', fn ($q) => $q->where('project_id', $projectId))->firstOrFail();
        if ($paymentType !== null) {
            abort_unless((int) $data['payment_type'] === $paymentType
                && (int) optional($ledger->customerLedger)->payment_type === $paymentType,
                422, 'Payment method does not match this voucher screen.');
        }
        $account = ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
            ->where('head_accounting_id', (int) $data['accounts_id'])
            ->where('subhead_accounting_id', (int) $data['subaccounts_id'])->firstOrFail();
        abort_unless(auth()->user()?->can('update voucher'),403);
        $source=$ledger->customerLedger;
        abort_unless($source && (int)$source->project_id===$projectId,403);
        abort_unless((int)$data['payment_type']===(int)$source->payment_type,409,
            'Changing the original payment method needs a separate verified financial workflow.');
        try {
            $result=app(\App\Services\Finance\SameSourceCashVoucherEdit::class)->submit(
                $projectId,(int)$source->id,(int)Auth::id(),[
                    'date'=>$data['date'],
                    'amount'=>str_replace(',','',(string)$data['amount']),
                    'account_id'=>(int)$account->id,
                    'description'=>$data['detail'],
                    'revision_reason'=>$data['edit_reason'],
                ]);
        } catch (\DomainException $e) {
            return response()->json(['status'=>'error','message'=>$e->getMessage()],409);
        }
        $message=$result['pending']
            ? 'Same voucher sent for approval; current posting preserved until decision.'
            : 'Same voucher updated directly with a retained audit snapshot.';
        $response=['status'=>'success','flow_type'=>$result['pending']?'pending_approval':'direct_update',
            'message'=>$message,'voucher_number'=>$result['voucher_number'],
            'customer_ledger_id'=>$source->id];
        return ($request->expectsJson() || $request->ajax())
            ? response()->json($response) : back()->with('success',$message);

        /* Legacy mutation retained temporarily below for forensic reference; it
         * is unreachable and should not be restored as an edit workflow.
         */
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'amount' => [
                'required',
                function ($attribute, $value, $fail) {
                    $clean = str_replace(',', '', (string) $value);
                    if (!is_numeric($clean) || (float) $clean <= 0) {
                        $fail('Amount must be a valid number greater than zero.');
                    }
                }
            ],
            'detail' => ['required', 'string', 'max:255'],
            'accounts_id' => ['required', 'integer'],
            'subaccounts_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'payment_type' => ['required', 'integer', 'in:1,2,3'],
            't_number' => ['required_if:payment_type,2,3', 'nullable', 'string', 'max:255'],
            'bank_id' => ['required_if:payment_type,2,3', 'nullable', 'integer'],
            'passing_date' => ['required_if:payment_type,2,3', 'nullable', 'date'],
            'cheque_purpose' => ['required_if:payment_type,3', 'nullable', 'string', 'max:500'],
            'passing_status' => ['nullable', 'integer', 'in:0,1,2,3'],
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first() ?: 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $selectedProjectId = getSelectedTown();
        $cleanAmount = (float) str_replace(',', '', (string) $request->input('amount'));
        $paymentType = (int) $request->input('payment_type');
        $providedPassingStatus = $request->filled('passing_status') ? (int) $request->input('passing_status') : null;

        DB::beginTransaction();
        try {
            $ledger = Ledger::where('id', $request->id)
                ->whereHas('projectHeadSubhead', function ($q) use ($selectedProjectId) {
                    $q->where('project_id', $selectedProjectId);
                })
                ->lockForUpdate()
                ->firstOrFail();

            // This editor is a cash voucher editor, not a generic ledger editor.
            // Never rewrite journal, stock, sale, booking or clearance postings.
            if (!in_array($ledger->type, ['CR', 'CP'], true) || !$ledger->customer_ledger_id) {
                throw new \DomainException('CASH_VOUCHER_SOURCE_REQUIRED');
            }

            $source = CustomerLedger::query()
                ->whereKey($ledger->customer_ledger_id)
                ->lockForUpdate()
                ->first();

            if (!$source || (int) $source->project_id !== (int) $selectedProjectId ||
                (string) $source->transaction_type !== (string) $ledger->type ||
                (int) $source->is_active !== 1 ||
                (int) $ledger->is_active !== 1 ||
                (string) $source->reference !== (string) $ledger->reference ||
                substr((string) $source->date, 0, 10) !== substr((string) $ledger->date, 0, 10) ||
                round((float) $source->amount_in, 2) !== round((float) $ledger->amount_in, 2) ||
                round((float) $source->amount_out, 2) !== round((float) $ledger->amount_out, 2)) {
                throw new \DomainException('CASH_VOUCHER_SOURCE_CONFLICT');
            }

            $projectHeadSubhead = ProjectHeadSubhead::availableForPosting()
                ->where('head_accounting_id', $request->accounts_id)
                ->where('subhead_accounting_id', $request->subaccounts_id)
                ->where('project_id', $selectedProjectId)
                ->first();

            if (!$projectHeadSubhead) {
                DB::rollBack();
                $errorMessage = 'Selected account and child account are not valid for this project.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $errorMessage,
                    ], 422);
                }
                return back()->with('error', $errorMessage)->withInput();
            }

            $amountIn = $ledger->type === 'CR' ? $cleanAmount : 0;
            $amountOut = $ledger->type === 'CP' ? $cleanAmount : 0;
            $tNumber = in_array($paymentType, [2, 3], true) ? $request->input('t_number') : null;
            $bankId = in_array($paymentType, [2, 3], true) ? $request->input('bank_id') : null;
            $passingDate = in_array($paymentType, [2, 3], true) ? $request->input('passing_date') : null;

            $ledger->update([
                'project_head_subheads_id' => $projectHeadSubhead->id,
                'amount_in' => $amountIn,
                'amount_out' => $amountOut,
                'date' => $request->input('date'),
                'detail' => $request->input('detail'),
                'update_by' => Auth::id(),
            ]);

            $customerLedger = $source;
            $customerLedgerPayload = [
                        'project_id' => $selectedProjectId,
                        'customer_id' => $projectHeadSubhead->customer_id,
                        'plot_id' => $projectHeadSubhead->plot_id,
                        'amount_in' => $amountIn,
                        'amount_out' => $amountOut,
                        'description' => $request->input('detail'),
                        'date' => $request->input('date'),
                        'payment_type' => $paymentType,
                        't_number' => $tNumber,
                        'bank_id' => $bankId,
                        'passing_date' => $passingDate,
                    ];

                    if ($paymentType === 1) {
                        // Keep history intact; only clear active bank/check fields.
                        $customerLedgerPayload['note'] = null;
                        $customerLedgerPayload['bank_post_at'] = null;
                    } else {
                        $customerLedgerPayload['bank_post_at'] = $passingDate;
                    }

                    if ($ledger->type === 'CR' && in_array($paymentType, [2, 3], true)) {
                        if (in_array((int) $customerLedger->passing_status, [1, 2, 3], true)) {
                            if ($providedPassingStatus !== null) {
                                $customerLedgerPayload['passing_status'] = $providedPassingStatus;
                            }
                        } else {
                            $customerLedgerPayload['passing_status'] = $providedPassingStatus ?? 0;
                        }
                    } elseif ($providedPassingStatus !== null) {
                        $customerLedgerPayload['passing_status'] = $providedPassingStatus;
                    }

                    $customerLedger->update($customerLedgerPayload);

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Voucher updated successfully.',
                ], 200);
            }

            return back()->with('success', 'Voucher updated successfully.');
        } catch (\Throwable $th) {
            DB::rollBack();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update voucher. Please try again.',
                ], 500);
            }
            return back()->with('error', 'Failed to update voucher. Please try again.');
        }
    }




    public function fetch_data_url(Request $request)
    {
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            return $this->typedReport($request);
        }
        $selectedProjectId = getSelectedTown();

        $query = Ledger::with(
            'projectHeadSubhead.project',
            'projectHeadSubhead.headAccounting',
            'projectHeadSubhead.subheadAccounting',
            'createdBy:id,name'
        )
            ->where('is_active', 1)
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            });

        if (!empty($request->start_date) && empty($request->end_date)) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if (empty($request->start_date) && !empty($request->end_date)) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        return DataTables::eloquent($query)
            ->addColumn('display_voucher_number', function ($ledger) use ($selectedProjectId) {
                // Untyped legacy JV/type_id can refer to either JV or CV.
                return app(\App\Services\Finance\LedgerVoucherDisplayService::class)
                    ->display($ledger, (int) $selectedProjectId);
            })
            ->addColumn('project_name', function ($ledger) {
                return $ledger->projectHeadSubhead->project->project ?? '';
            })
            ->addColumn('accountent_name', function ($ledger) {
                return $ledger->createdBy->name ?? '';
            })
            ->addColumn('head_account_name', function ($ledger) {
                return $ledger->projectHeadSubhead->headAccounting->name ?? '';
            })
            ->addColumn('subhead_account_name', function ($ledger) {
                return $ledger->projectHeadSubhead->subheadAccounting->name ?? '';
            })
            ->addColumn('action', function ($ledger) {
                return '<button class="btn btn-info">Edit</button>';
            })
            ->toJson();
    }

    public function show_ledger_party()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Party wise ledger';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CR';
        $x['class'] = 'cash-in';


        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('is_active', 1) // Add this condition to filter by is_active
            ->where('type', 'CR')
            ->get();
        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
            return $item;
        });
        $x['data'] = $data;


        $projects = Project::get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.show_ledger_party', $x);
    }

    public function show_ledger_head()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = request('name') ?? 'Head wise ledger';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CR';
        $x['account_type'] = request('type') ?? 'head';
        $x['class'] = 'cash-in';


        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('is_active', 1) // Add this condition to filter by is_active
            ->where('type', 'CR')
            ->get();
        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
            return $item;
        });
        $x['data'] = $data;


        $projects = Project::get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.head_ledger', $x);
    }

    public function trialBalance(Request $request)
    {
        return view('admin.finance.reports.trial_balance', [
            'title' => 'Trial Balance',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function trialBalanceShow(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Please select a project.');
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $service = new \App\Services\Finance\TrialBalanceReport();
        try { $report = $service->build($projectId, $data['from'], $data['to']); }
        catch (\DomainException $e) { abort(409, 'Trial balance integrity check failed.'); }
        $allAccounts = $service->flatten($report['tree']);
        $page = max((int) $request->input('page', 1), 1); $perPage = 50;
        $slice = array_slice($allAccounts, ($page - 1) * $perPage, $perPage);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($slice, count($allAccounts), $perPage, $page, [
            'path' => route('finance.reports.trial_balance.show'), 'query' => $data,
        ]);
        return view('admin.finance.reports.trial_balance_report', [
            'title' => 'Trial Balance',
            'from' => $data['from'], 'to' => $data['to'],
            'hierarchy' => $service->hierarchy($slice), 'paginator' => $paginator,
            'rowNumberStart' => ($page - 1) * $perPage,
            'projectName' => Project::whereKey($projectId)->value('project') ?? 'Project',
        ]);
    }

    public function trialBalancePrint(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Please select a project.');
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $service = new \App\Services\Finance\TrialBalanceReport();
        try { $report = $service->build($projectId, $data['from'], $data['to']); }
        catch (\DomainException $e) { abort(409, 'Trial balance integrity check failed.'); }
        return view('admin.finance.reports.trial_balance_print', [
            'from' => $data['from'], 'to' => $data['to'],
            'hierarchy' => $service->hierarchy($service->flatten($report['tree'])),
            'rowNumberStart' => 0,
            'projectName' => Project::whereKey($projectId)->value('project') ?? 'Project',
        ]);
    }

    public function fetch_data_by_party(Request $request)
    {
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            return $this->typedReport($request, 'project_head_subheads_id');
        }
        $selectedProjectId = getSelectedTown();
        $data = Ledger::with('projectHeadSubhead.project', 'projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')
            ->where('is_active', 1)
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            })
            ->selectRaw('project_head_subheads_id, sum(amount_in) as total_amount_in, sum(amount_out) as total_amount_out')
            ->groupBy('project_head_subheads_id');

        return DataTables::eloquent($data)
            ->addColumn('project_name', function ($ledger) {
                // Access the project name from the relationship
                return $ledger->projectHeadSubhead->project->project ?? '';
            })
            ->addColumn('head_account_name', function ($ledger) {
                // Access the head account name from the relationship
                return $ledger->projectHeadSubhead->headAccounting->name ?? '';
            })
            ->addColumn('subhead_account_name', function ($ledger) {
                // Access the sub account name from the relationship
                return $ledger->projectHeadSubhead->subheadAccounting->name ?? '';
            })
            ->addColumn('total_amount_in', function ($ledger) {
                // Access the total amount in for the project_head_subheads_id
                return $ledger->total_amount_in ?? '';
            })
            ->addColumn('total_amount_out', function ($ledger) {
                // Access the total amount out for the project_head_subheads_id
                return $ledger->total_amount_out ?? '';
            })
            ->addColumn('balance_amount', function ($ledger) {
                // Calculate the balance by subtracting total_amount_out from total_amount_in
                return ($ledger->total_amount_in ?? 0) - ($ledger->total_amount_out ?? 0);
            })
            ->toJson();
    }

    public function fetch_data_by_head(Request $request)
    {
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            return $this->typedReport($request, $request->input('account_type') === 'account' ? 'acct_type' : 'head_accounting_id');
        }
        $selectedProjectId = getSelectedTown();
        if ($request->account_type == 'account') {
            $data = Ledger::query()
                ->join('project_head_subheads', 'ledgers.project_head_subheads_id', '=', 'project_head_subheads.id')
                ->join('head_accountings', 'project_head_subheads.head_accounting_id', '=', 'head_accountings.id')
                ->where('ledgers.is_active', 1)
                ->where('project_head_subheads.project_id', $selectedProjectId)
                ->selectRaw('
                head_accountings.acct_type as acct_type,
                SUM(ledgers.amount_in) as total_amount_in,
                SUM(ledgers.amount_out) as total_amount_out
            ')
                ->groupBy('head_accountings.acct_type'); // ✅ only group by acct_type

            return DataTables::of($data)
                ->addColumn('head_account_name', function ($ledger) {
                    return $this->getAccountName($ledger->acct_type); // 👈 map acct_type → readable name
                })
                ->addColumn('total_amount_in', fn($ledger) => $ledger->total_amount_in ?? 0)
                ->addColumn('total_amount_out', fn($ledger) => $ledger->total_amount_out ?? 0)
                ->addColumn('balance_amount', fn($ledger) => ($ledger->total_amount_in ?? 0) - ($ledger->total_amount_out ?? 0))
                ->toJson();
        }
        $data = Ledger::query()
            ->join('project_head_subheads', 'ledgers.project_head_subheads_id', '=', 'project_head_subheads.id')
            ->join('head_accountings', 'project_head_subheads.head_accounting_id', '=', 'head_accountings.id')
            ->where('ledgers.is_active', 1)
            ->where('project_head_subheads.project_id', $selectedProjectId)
            ->selectRaw('
            project_head_subheads.head_accounting_id,
            head_accountings.name as head_account_name,
            SUM(ledgers.amount_in) as total_amount_in,
            SUM(ledgers.amount_out) as total_amount_out
        ')
            ->groupBy('project_head_subheads.head_accounting_id', 'head_accountings.name'); // Ensure name is grouped too

        return DataTables::of($data)
            ->addColumn('total_amount_in', function ($ledger) {
                return $ledger->total_amount_in ?? '';
            })
            ->addColumn('total_amount_out', function ($ledger) {
                return $ledger->total_amount_out ?? '';
            })
            ->addColumn('balance_amount', function ($ledger) {
                return ($ledger->total_amount_in ?? 0) - ($ledger->total_amount_out ?? 0);
            })
            ->toJson();
    }
    private function getAccountName($typeId)
    {
        return getAccountTypeName($typeId);
    }
}
