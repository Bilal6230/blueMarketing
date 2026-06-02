<?php

namespace App\Http\Controllers;

use App\Models\PendingUpdate;
use Exception;
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
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;


class LedgerController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
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
            'payment_type' => ['required', 'integer', 'in:1,2,3'],
            'date' => ['required', 'date'],
            'voucher' => ['required', 'string', 'regex:/^(CR|CP)-\d+$/'],
            'voucher_number' => ['required', 'string', 'regex:/^(CR|CP)-\d+$/'],
            't_number' => ['required_if:payment_type,2,3', 'nullable', 'string', 'max:255'],
            'bank_id' => ['required_if:payment_type,2,3', 'nullable', 'integer'],
            'passing_date' => ['required_if:payment_type,2,3', 'nullable', 'date'],
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

        if (!in_array($voucherPrefix, ['CR', 'CP'], true) || $voucherPrefix !== $voucherNumberPrefix) {
            return response()->json([
                'status' => 'error',
                'error_key' => 'invalid_voucher_number',
                'message' => 'Invalid voucher number. Please refresh the page and try again.'
            ], 422);
        }

        $effectiveVoucherType = $voucherPrefix;
        $voucherNumber = (int) ($voucherNumberParts[1] ?? 0);
        $cleanAmount = (float) str_replace(',', '', (string) $request->input('amount'));
        $paymentType = (int) $request->input('payment_type');
        $isCashOutPendingClearance = in_array($paymentType, [2, 3], true) && $effectiveVoucherType === 'CP';
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

        $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
            ->where('subhead_accounting_id', $request->subaccounts_id)
            ->where('project_id', $selectedProjectId)
            ->first();

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
                $isCashOutPendingClearance
            ) {
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
                    $clearanceReference = 'BANK_CLEARANCE#' . $pendingCustomerLedger->id;

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

                    $bankClearanceLedger = null;
                    if ($pendingStatus === 1) {
                        $bankClearanceLedger = Ledger::where('customer_ledger_id', $pendingCustomerLedger->id)
                            ->where('type', 'BR')
                            ->where('reference', $clearanceReference)
                            ->lockForUpdate()
                            ->first();

                        if (!$bankClearanceLedger) {
                            $resolvedAmount = (float) (($pendingCustomerLedger->amount_out > 0)
                                ? $pendingCustomerLedger->amount_out
                                : $pendingCustomerLedger->amount_in);

                            $bankClearanceLedger = Ledger::create([
                                'customer_ledger_id' => $pendingCustomerLedger->id,
                                'voucher_number' => getVocuherNumber('BR'),
                                'type' => 'BR',
                                'type_id' => ((int) getLastLedgerIdByType('BR')) + 1,
                                'project_head_subheads_id' => $projectHeadSubhead->id,
                                'reference' => $clearanceReference,
                                'amount_in' => 0.00,
                                'amount_out' => $resolvedAmount,
                                'is_active' => 1,
                                'date' => $request->input('passing_date') ?: now()->toDateString(),
                                'detail' => $note,
                                'update_by' => Auth::id(),
                                'create_by' => Auth::id(),
                                'status' => 0,
                            ]);
                        }
                    }

                    session(['last_submit_date' => $request->date]);

                    if ($request->id) {
                        DraftLedger::find($request->input('id'))?->delete();
                    }

                    return [
                        'status' => 'success',
                        'flow_type' => 'pending_payment_status_updated',
                        'message' => 'Payment status updated successfully.',
                        'data' => $bankClearanceLedger,
                        'customer_ledger_id' => $pendingCustomerLedger->id,
                        'voucher_number' => $bankClearanceLedger?->voucher_number,
                        'voucher_display' => $bankClearanceLedger ? ('BR-' . $bankClearanceLedger->voucher_number) : null,
                    ];
                }

                $customerID = $projectHeadSubhead?->customer_id;
                $plotID = $projectHeadSubhead?->plot_id;
                $bankName = $bank_id ? getBankNameById($bank_id) : null;
                $isPendingCashInBankPayment = in_array($paymentType, [2, 3], true) && $effectiveVoucherType === 'CR';

                $customerLedger = CustomerLedger::create([
                    'transaction_type' => $effectiveVoucherType,
                    'type_id' => get_new_typeID($effectiveVoucherType),
                    'reference' => $request->input('reference'),
                    'project_id' => $selectedProjectId ?? null,
                    'customer_id' => $customerID ?? null,
                    'plot_id' => $plotID ?? null,
                    'amount_in' => $amount_in,
                    'amount_out' => $amount_out,
                    'description' => $request->input('detail'),
                    'date' => $request->input('date'),
                    'payment_type' => $paymentType,
                    't_number' => $t_number,
                    'bank_id' => $bank_id,
                    'is_active' => 1,
                    'is_approve' => 0,
                    'passing_date' => $request->input('passing_date'),
                    'passing_status' => $isPendingCashInBankPayment ? 0 : null,
                ]);

                if ($t_number != null) {
                    $check_slip = $customerLedger->transaction_type . '-' . $customerLedger->reference;

                    if ($isPendingCashInBankPayment) {
                        $customerLedger->update([
                            'passing_status' => 0,
                            'note' => null,
                            'bank_post_at' => null,
                        ]);
                    } else {
                        $passing_status = $effectiveVoucherType === 'CP' ? 1 : 0;
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

                $resolvedVoucherNumber = $voucherNumber;
                while (
                    Ledger::where('type', $effectiveVoucherType)
                        ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId))
                        ->where('is_active', 1)
                        ->where('voucher_number', $resolvedVoucherNumber)
                        ->lockForUpdate()
                        ->exists()
                ) {
                    $resolvedVoucherNumber++;
                }

                $lastId = getLastLedgerIdByType($effectiveVoucherType);
                $detail = $request->input('detail');
                if ($paymentType === 1 && $effectiveVoucherType === 'CR') {
                    $detail = $request->input('detail') . '(Cash Payment)';
                } elseif ($paymentType === 2 && $effectiveVoucherType === 'CR') {
                    $detail = $request->input('detail') . ' (Bank: ' . $bankName . ' Account No: ' . $customerLedger->t_number . ' Passing Date: ' . $request->passing_date . ')';
                } elseif ($paymentType === 3 && $effectiveVoucherType === 'CR') {
                    $detail = $request->input('detail') . ' (Bank: ' . $bankName . ' cheque No: ' . $customerLedger->t_number . ' Passing Date: ' . $request->passing_date . ')';
                }

                $data = Ledger::create([
                    'customer_ledger_id' => $customerLedger->id,
                    'voucher_number' => $resolvedVoucherNumber,
                    'type' => $effectiveVoucherType,
                    'type_id' => $lastId + 1,
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

                session(['last_submit_date' => $request->date]);

                if ($request->id) {
                    DraftLedger::find($request->input('id'))?->delete();
                }

                $flowType = $isPendingCashInBankPayment ? 'posted_pending_clearance' : 'posted';
                $successMessage = $isPendingCashInBankPayment
                    ? 'Voucher saved successfully. Payment status is pending clearance.'
                    : 'Data saved successfully.';

                return [
                    'status' => 'success',
                    'flow_type' => $flowType,
                    'message' => $successMessage,
                    'data' => $data,
                    'customer_ledger_id' => $customerLedger->id,
                    'voucher_number' => $resolvedVoucherNumber,
                    'voucher_display' => $effectiveVoucherType . '-' . $resolvedVoucherNumber,
                ];
            });

            return response()->json($responsePayload, 200);
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

    public function saveAsDraft(Request $request)
    {
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
            $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
                ->where('subhead_accounting_id', $request->subaccounts_id)
                ->where('project_id', $selectedProjectId)
                ->first();

            // if (!$projectHeadSubhead) {
            //     throw new \Exception('Credit account ID not found.');
            // }
            $draftLedger = DraftLedger::find($request->input('id'));

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
        $ledger = Ledger::with([
            'customerLedger',
            'projectHeadSubhead.headAccounting',
            'projectHeadSubhead.subheadAccounting',
            'projectHeadSubhead.project',
        ])
            ->where('id', $request->id)
            ->whereHas('projectHeadSubhead', function ($q) {
                $q->where('project_id', getSelectedTown());
            })
            ->firstOrFail();

        $customerLedger = $ledger->customerLedger;
        $accountTypeId = (int) ($ledger->projectHeadSubhead->headAccounting->acct_type ?? 0);
        $headAccountingId = $ledger->projectHeadSubhead->head_accounting_id ?? null;
        $subheadAccountingId = $ledger->projectHeadSubhead->subhead_accounting_id ?? null;
        $amountDisplay = $ledger->type === 'CP' ? (float) ($ledger->amount_out ?? 0) : (float) ($ledger->amount_in ?? 0);

        return response()->json([
            'status' => Response::HTTP_OK,
            'data' => [
                'id' => $ledger->id,
                'type' => $ledger->type,
                'voucher_number' => $ledger->voucher_number,
                'voucher_number_display' => ($ledger->type ?? '') . '-' . ($ledger->voucher_number ?? ''),
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
        $data = [
            'delete_reason' => $request->delete_reason,
            'is_active' => "0",
        ];
        DB::beginTransaction();
        try {
            $ledger = Ledger::findOrFail($request->id);

            if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update')) {
                $customerLedger = $ledger->customerLedger;
                if ($customerLedger) {
                    $customerLedger->update($data);
                }
                $ledger->update($data);
                DB::commit();

                Alert::success('Notification', 'Voucher <b>' . $ledger->detail . '</b> deleted successfully.')
                    ->toToast()->toHtml();
                return back();
            }

            PendingUpdate::create([
                'table_name' => 'ledgers',
                'record_id' => $ledger->id,
                'old_values' => json_encode($ledger->toArray()),
                'new_values' => json_encode($data),
                'status' => 'pending',
                'submitted_by' => Auth::id(),
            ]);
            DB::commit();
            Alert::info('Notification', 'Delete request for <b>' . $ledger->detail . '</b> is pending admin approval.')
                ->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollBack();
            Alert::error('Notification', 'Failed to delete voucher: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
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

    public function update(Request $request)
    {
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

            $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
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

            if ($ledger->customer_ledger_id) {
                $customerLedger = CustomerLedger::where('id', $ledger->customer_ledger_id)
                    ->lockForUpdate()
                    ->first();

                if ($customerLedger) {
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
                }
            }

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
            ->addColumn('display_voucher_number', function ($ledger) {
                $voucherNumber = $ledger->voucher_number;

                if ($ledger->type === 'JV' && !empty($ledger->type_id)) {
                    $journalVoucher = JournalVoucher::where('id', $ledger->type_id)
                        ->select('id', 'voucher_number')
                        ->first();

                    if ($journalVoucher && !empty($journalVoucher->voucher_number)) {
                        $voucherNumber = $journalVoucher->voucher_number;
                    }
                }

                return ($ledger->type ?? '') . '-' . $voucherNumber;
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

    public function fetch_data_by_party(Request $request)
    {
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
