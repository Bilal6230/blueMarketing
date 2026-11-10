<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only, project-scoped directory over the existing voucher sources. */
final class DailyVoucherDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $projectId = (int) getSelectedTown();
        $presetType = strtoupper((string) $request->query('type', ''));
        $allowedTypes = ['CR', 'DR', 'CCR', 'CDR', 'BOR', 'BOP', 'CP', 'PPR', 'JV', 'CV', 'BR', 'BO', 'BW', 'BD', 'PR', 'PP'];
        abort_unless($presetType === '' || in_array($presetType, $allowedTypes, true), 404);
        $accounts = DB::table('project_head_subheads as account')
            ->leftJoin('head_accountings as head', 'head.id', '=', 'account.head_accounting_id')
            ->leftJoin('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')
            ->where('account.project_id', $projectId)->orderBy('head.name')->orderBy('sub.name')
            ->get(['account.id', 'head.name as head_name', 'sub.name as sub_name']);
        return view('admin.finance.voucher.daily_directory', compact('accounts', 'presetType'));
    }

    public function data(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 404);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', 'string', 'max:12'],
            'search' => ['nullable', 'string', 'max:100'],
            'account' => ['nullable', 'integer', 'min:1'],
            'column_date' => ['nullable', 'string', 'max:100'],
            'column_type' => ['nullable', 'string', 'max:100'],
            'column_number' => ['nullable', 'string', 'max:100'],
            'column_reference' => ['nullable', 'string', 'max:100'],
            'column_head' => ['nullable', 'string', 'max:100'],
            'column_subhead' => ['nullable', 'string', 'max:100'],
            'column_account' => ['nullable', 'string', 'max:100'],
            'column_amount' => ['nullable', 'string', 'max:100'],
            'column_status' => ['nullable', 'string', 'max:100'],
            'column_detail' => ['nullable', 'string', 'max:100'],
            'start' => ['sometimes', 'integer', 'min:0', 'max:50000'],
            'length' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_column' => ['sometimes','string','in:date,number,head,subhead,amount,detail'],
            'sort_direction' => ['sometimes','string','in:asc,desc'],
        ]);
        $rows = collect();
        $total = 0;
        $start = (int) ($filters['start'] ?? 0);
        $length = (int) ($filters['length'] ?? 25);
        $columnFilters = array_filter(array_intersect_key($filters, array_flip([
            'column_date', 'column_type', 'column_number', 'column_reference',
            'column_account', 'column_head', 'column_subhead', 'column_amount', 'column_status', 'column_detail',
        ])), fn ($value) => trim((string) $value) !== '');
        // Load a bounded recent window before applying cross-source column filters.
        // A merged page needs the first (offset + size) rows from every source.
        // Cross-source column filters require a bounded complete candidate set.
        $filterCandidateLimit = 5000;
        $sortColumn = (string) ($filters['sort_column'] ?? 'date');
        $sortDirection = (string) ($filters['sort_direction'] ?? 'desc');
        $requiresFullCandidateSet = (bool) $columnFilters || $sortColumn !== 'date' || $sortDirection !== 'desc';
        $window = $requiresFullCandidateSet ? $filterCandidateLimit : $start + $length;
        $identities = app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class);

        if (Schema::hasTable('customer_ledger')) {
            $query = DB::table('customer_ledger as source')->where('source.project_id', $projectId);
            if(Schema::hasColumn('customer_ledger','deleted_at')) $query->whereNull('source.deleted_at');
            if(Schema::hasTable('daily_voucher_approvals')){
                $query->where(function($visible)use($projectId){
                    $visible->where('source.is_active',1)
                        ->orWhereExists(function($a)use($projectId){
                            $a->selectRaw('1')->from('daily_voucher_approvals as approval')
                              ->where('approval.project_id',$projectId)
                              ->where('approval.source_table','customer_ledger')
                              ->whereColumn('approval.source_id','source.id')
                              ->whereIn('approval.status',['pending','rejected']);
                        });
                });
            }else $query->where('source.is_active',1);
            if (!empty($filters['from'])) $query->whereDate('source.date', '>=', $filters['from']);
            if (!empty($filters['to'])) $query->whereDate('source.date', '<=', $filters['to']);
            if (!empty($filters['type'])) {
                $methodSeries = [
                    'CR' => ['CR', 1], 'CP' => ['CP', 1], 'DR' => ['CP', 1],
                    'CCR' => ['CR', 1], 'CDR' => ['CP', 1],
                    'BOR' => ['CR', [2, 3]], 'BOP' => ['CP', [2, 3]],
                ];
                if (isset($methodSeries[$filters['type']])) {
                    [$sourceType, $paymentType] = $methodSeries[$filters['type']];
                    $query->where('source.transaction_type', $sourceType);
                    if (in_array($filters['type'], ['CR', 'CP', 'DR'], true)) {
                        // Keep historical CR/CP source rows visible even before
                        // display identities are installed. With identities,
                        // exclude rows assigned to one of the new dedicated series.
                        if (Schema::hasTable('project_voucher_display_numbers')) {
                            $legacySeries = $filters['type'] === 'CR' ? 'CR' : 'CP';
                            $query->where(function ($seriesQuery) use ($projectId, $legacySeries) {
                                $seriesQuery->whereNotExists(function ($identity) use ($projectId) {
                                    $identity->selectRaw('1')->from('project_voucher_display_numbers as display_number')
                                        ->whereColumn('display_number.source_id', 'source.id')
                                        ->where('display_number.source_kind', 'customer_ledger')
                                        ->where('display_number.project_id', $projectId);
                                })->orWhereExists(function ($identity) use ($projectId, $legacySeries) {
                                    $identity->selectRaw('1')->from('project_voucher_display_numbers as display_number')
                                        ->whereColumn('display_number.source_id', 'source.id')
                                        ->where('display_number.source_kind', 'customer_ledger')
                                        ->where('display_number.project_id', $projectId)
                                        ->where('display_number.series', $legacySeries);
                                });
                            });
                        }
                    } else {
                    is_array($paymentType)
                        ? $query->whereIn('source.payment_type', $paymentType)
                        : $query->where('source.payment_type', $paymentType);
                        if (Schema::hasTable('project_voucher_display_numbers')) {
                            $seriesTypes = in_array($filters['type'], ['BOR', 'BOP'], true)
                                ? [$filters['type'], $filters['type'] === 'BOR' ? 'CHR' : 'CHP']
                                : [$filters['type']];
                            $query->whereExists(function ($identity) use ($projectId, $seriesTypes) {
                                $identity->selectRaw('1')->from('project_voucher_display_numbers as display_number')
                                    ->whereColumn('display_number.source_id', 'source.id')
                                    ->where('display_number.source_kind', 'customer_ledger')
                                    ->where('display_number.project_id', $projectId)
                                    ->whereIn('display_number.series', $seriesTypes);
                            });
                        }
                    }
                } else {
                    $query->where('source.transaction_type', $filters['type']);
                }
            }
            if (!empty($filters['account'])) {
                $query->whereExists(function ($sub) use ($filters, $projectId) {
                    $sub->selectRaw('1')->from('ledgers as linked')->join('project_head_subheads as a', 'a.id', '=', 'linked.project_head_subheads_id')
                        ->whereColumn('linked.customer_ledger_id', 'source.id')->where('a.project_id', $projectId)->where('a.id', (int) $filters['account']);
                });
            }
            if (!empty($filters['search'])) {
                $term = '%'.addcslashes($filters['search'], '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('source.reference', 'like', $term)->orWhere('source.description', 'like', $term)
                        ->orWhere('source.transaction_type', 'like', $term)->orWhere('source.type_id', 'like', $term);
                });
            }
            $total += (clone $query)->count();
            $sources = $query->orderByDesc('source.date')->orderByDesc('source.id')->limit($window)->get();
            $leadIds = $sources->pluck('customer_id')->filter()->unique();
            $leadNames = $leadIds->isNotEmpty() && Schema::hasTable('leads')
                ? DB::table('leads')->whereIn('id', $leadIds)->get()->keyBy('id')->map(function ($lead) {
                    return trim(implode(' ', array_filter([$lead->first_name ?? null, $lead->last_name ?? null]))) ?: (string) ($lead->business ?? '—');
                }) : collect();
            $sourceApprovals=app(\App\Services\Finance\VoucherApprovalStatusReader::class)->forSources($projectId,'customer_ledger',$sources->pluck('id'));
            foreach ($sources as $source) {
                $series = $identities->series($projectId, 'customer_ledger', (int) $source->id);
                $display = $identities->lookup($projectId, 'customer_ledger', (int) $source->id)
                    ?: trim((string) $source->transaction_type.'-'.(string) ($source->type_id ?? $source->id));
                $displayType = match ($series) {
                    'CP' => 'DR', 'CHR' => 'BOR', 'CHP' => 'BOP',
                    default => $series ?: (string) $source->transaction_type,
                };
                if ($series === 'CHR') $display = preg_replace('/^CHR(?=-)/', 'BOR', $display);
                if ($series === 'CHP') $display = preg_replace('/^CHP(?=-)/', 'BOP', $display);
                $linkedLines = DB::table('ledgers as linked')
                    ->join('project_head_subheads as a', 'a.id', '=', 'linked.project_head_subheads_id')
                    ->leftJoin('head_accountings as h', 'h.id', '=', 'a.head_accounting_id')
                    ->leftJoin('subhead_accountings as s', 's.id', '=', 'a.subhead_accounting_id')
                    ->where('linked.customer_ledger_id', $source->id)->where('a.project_id', $projectId)
                    ->orderBy('linked.id')->get(['linked.id', 'a.id as account_id', 'h.name as head_name', 's.name as subhead_name', 'linked.amount_in', 'linked.amount_out', 'linked.detail']);
                $ledgerId = $linkedLines->first()->id ?? null;
                $rows->push([
                    'date' => (string) $source->date, 'type' => $displayType,
                    'number' => $display, 'reference' => (string) ($source->reference ?? ''),
                    'head' => (string) ($linkedLines->first()->head_name ?? '—'),
                    'subhead' => (string) ($linkedLines->first()->subhead_name ?? '—'),
                    'account' => $leadNames->get($source->customer_id)
                        ?: ($linkedLines->first()
                            ? trim(($linkedLines->first()->head_name ? $linkedLines->first()->head_name.' / ' : '').($linkedLines->first()->subhead_name ?: 'Account #'.$linkedLines->first()->account_id))
                            : '—'),
                    'amount' => (float) max((float) ($source->amount_in ?? 0), (float) ($source->amount_out ?? 0)),
                    'status' => ucfirst((string)($sourceApprovals[$source->id]['status']??(!empty($source->is_active)?'posted':'inactive'))),
                    'approval_approver' => $sourceApprovals[$source->id]['approver']??null,
                    'detail' => (string) ($source->description ?? ''),
                    'audit' => array_filter([
                        'Prepared by' => $source->created_by ?? $source->create_by ?? null,
                        'Updated by' => $source->updated_by ?? $source->update_by ?? null,
                        'Created at' => $source->created_at ?? null,
                        'Updated at' => $source->updated_at ?? null,
                        'Cheque history' => $source->check_history ?? null,
                        'Cheque purpose' => $source->cheque_purpose ?? null,
                        'Online transaction' => (int) ($source->payment_type ?? 0) === 2 ? $source->t_number : null,
                        'Bank account ID' => (int) ($source->payment_type ?? 0) === 2 ? $source->bank_id : null,
                        'Bank passing date' => (int) ($source->payment_type ?? 0) === 2 ? $source->passing_date : null,
                        'Bank status' => (int) ($source->payment_type ?? 0) === 2 ? $source->passing_status : null,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'delete_url' => $ledgerId && auth()->user()?->can('delete voucher') && in_array((string) $source->transaction_type, ['CR', 'CP'], true)
                        ? route('ledger.destroy') : null,
                    'delete_id' => $ledgerId,
                    'edit_url' => $ledgerId && auth()->user()?->can('update voucher')
                        ? route(in_array($displayType, ['CR', 'CCR', 'BOR'], true) ? 'finance.voucher.in.method' : 'finance.voucher.out.method', [
                            'method' => in_array((int) ($source->payment_type ?? 1), [2, 3], true)
                                ? ((int) $source->payment_type === 3 ? 'cheque' : 'online') : 'cash',
                            'series' => $series ?: $displayType,
                            'edit_ledger_id' => $ledgerId,
                            'edit_voucher_type' => (string) $source->transaction_type,
                        ])
                        : null,
                    'url' => $ledgerId
                        ? route('finance.voucher.print', ['id' => $ledgerId])
                        : route('finance.daily-vouchers.source.print', ['customerLedger' => $source->id]),
                    'lines' => $linkedLines->map(fn ($line) => [
                        'account' => trim(($line->head_name ? $line->head_name.' / ' : '').($line->subhead_name ?: 'Account #'.$line->account_id)),
                        'description' => (string) ($line->detail ?? ''),
                        'debit' => (float) ($line->amount_out ?? 0), 'credit' => (float) ($line->amount_in ?? 0),
                    ])->all(),
                ]);
            }
        }

        foreach ([['journal_vouchers', 'JV', 'journal_voucher', 'journal.voucher.print'], ['commision_vouchers', 'CV', 'commission_voucher', 'commision.voucher.print']] as [$table, $type, $kind, $printRoute]) {
            if (!empty($filters['type']) && $filters['type'] !== $type) continue;
            if (!auth()->user()?->can('read jv')) continue;
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'project_id')) continue;
            $query = DB::table($table)->where('project_id', $projectId);
            if (Schema::hasColumn($table, 'deleted_at')) $query->whereNull('deleted_at');
            if (!empty($filters['from'])) $query->whereDate('date', '>=', $filters['from']);
            if (!empty($filters['to'])) $query->whereDate('date', '<=', $filters['to']);
            if (!empty($filters['type']) && Schema::hasColumn($table, 'type')) $query->where('type', $filters['type']);
            if (!empty($filters['search'])) {
                $term = '%'.addcslashes($filters['search'], '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('voucher_number', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('description', 'like', $term);
                });
            }
            if (!empty($filters['account'])) {
                $detailsTable = $type === 'JV' ? 'journal_voucher_details' : 'commision_voucher_details';
                if (!Schema::hasTable($detailsTable)) continue;
                $foreignKey = $type === 'JV' ? 'journal_voucher_id' : 'commision_voucher_id';
                $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from($detailsTable.' as vd')
                    ->join('project_head_subheads as detail_account', 'detail_account.id', '=', 'vd.account_id')
                    ->whereColumn('vd.'.$foreignKey, $table.'.id')->where('detail_account.project_id', $projectId)
                    ->where('vd.account_id', (int) $filters['account']));
            }
            $total += (clone $query)->count();
            $voucherResults=$query->orderByDesc('date')->orderByDesc('id')->limit($window)->get();
            $voucherApprovals=$type==='JV'
                ? app(\App\Services\Finance\VoucherApprovalStatusReader::class)->forSources($projectId,'journal_vouchers',$voucherResults->pluck('id'))
                : [];
            foreach ($voucherResults as $voucher) {
                $actualType = (string) ($voucher->type ?? $type);
                $detailsTable = $type === 'JV' ? 'journal_voucher_details' : 'commision_voucher_details';
                $foreignKey = $type === 'JV' ? 'journal_voucher_id' : 'commision_voucher_id';
                $lines = Schema::hasTable($detailsTable)
                    ? DB::table($detailsTable.' as vd')
                        ->join('project_head_subheads as a', 'a.id', '=', 'vd.account_id')
                        ->leftJoin('head_accountings as h', 'h.id', '=', 'a.head_accounting_id')
                        ->leftJoin('subhead_accountings as s', 's.id', '=', 'a.subhead_accounting_id')
                        ->where('a.project_id', $projectId)
                        ->where("vd.$foreignKey", $voucher->id)
                        ->when(Schema::hasColumn($detailsTable, 'deleted_at'), fn ($q) => $q->whereNull('vd.deleted_at'))
                        ->orderBy('vd.id')
                        ->get(['a.id as account_id', 'h.name as head_name', 's.name as subhead_name', 'vd.debit', 'vd.credit', 'vd.description'])
                        ->map(fn ($line) => [
                            'account_id' => $line->account_id,
                            'head' => (string) ($line->head_name ?? '—'),
                            'subhead' => (string) ($line->subhead_name ?? '—'),
                            'account' => $line->account_id
                                ? trim(($line->head_name ? $line->head_name.' / ' : '').($line->subhead_name ?: 'Account #'.$line->account_id))
                                : 'Unavailable account',
                            'debit' => (float) ($line->debit ?? 0), 'credit' => (float) ($line->credit ?? 0),
                            'description' => (string) ($line->description ?? ''),
                        ])->all()
                    : [];
                $rows->push([
                    'date' => (string) $voucher->date, 'type' => $actualType,
                    'number' => $identities->lookup($projectId, $kind, (int) $voucher->id) ?: (string) $voucher->voucher_number,
                    'reference' => (string) ($voucher->reference ?? ''), 'account' => $lines[0]['account'] ?? '—',
                    'head' => $lines[0]['head'] ?? '—', 'subhead' => $lines[0]['subhead'] ?? '—',
                    'amount' => (float) ($voucher->total_debit ?? 0),
                    'status' => ucfirst((string)($voucherApprovals[$voucher->id]['status']??'posted')),
                    'approval_approver' => $voucherApprovals[$voucher->id]['approver']??null,
                    'detail' => (string) ($voucher->description ?? ''),
                    'lines' => $lines,
                    'audit' => array_filter([
                        'Prepared by user ID' => $voucher->created_by ?? null,
                        'Updated by user ID' => $voucher->updated_by ?? null,
                        'Created at' => $voucher->created_at ?? null,
                        'Updated at' => $voucher->updated_at ?? null,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'url' => auth()->user()?->can('print jv') ? route($printRoute, ['id' => $voucher->id]) : null,
                    'edit_url' => auth()->user()?->can('edit jv') ? route($type === 'JV' ? 'journal.voucher.edit' : 'commision.voucher.edit', ['id' => $voucher->id]) : null,
                    'delete_url' => auth()->user()?->can('delete jv') ? route($type === 'JV' ? 'journal.voucher.delete' : 'commision.voucher.delete', ['id' => $voucher->id]) : null,
                ]);
            }
        }

        // Unlinked legacy ledger vouchers (D/C/BR/BO/BW etc.). Linked CR/CP/PPR
        // legs are represented by their customer_ledger source above; JV/CV
        // parent vouchers are represented by their own records.
        if (Schema::hasTable('ledgers') && Schema::hasTable('project_head_subheads')) {
            $query = DB::table('ledgers as l')->join('project_head_subheads as account', 'account.id', '=', 'l.project_head_subheads_id')
                ->leftJoin('head_accountings as head', 'head.id', '=', 'account.head_accounting_id')
                ->leftJoin('subhead_accountings as subhead', 'subhead.id', '=', 'account.subhead_accounting_id')
                ->where('account.project_id', $projectId)->whereNull('l.customer_ledger_id');
            $query->whereNotIn(DB::raw('UPPER(l.type)'), ['JV', 'CV']);
            if (Schema::hasColumn('ledgers', 'is_active')) $query->where('l.is_active', 1);
            if (!empty($filters['from'])) $query->whereDate('l.date', '>=', $filters['from']);
            if (!empty($filters['to'])) $query->whereDate('l.date', '<=', $filters['to']);
            if (!empty($filters['type'])) $query->where('l.type', $filters['type']);
            if (!empty($filters['account'])) $query->where('account.id', (int) $filters['account']);
            if (!empty($filters['search'])) {
                $term = '%'.addcslashes($filters['search'], '%_\\').'%';
                $query->where(function ($q) use ($term) { $q->where('l.voucher_number', 'like', $term)->orWhere('l.reference', 'like', $term)->orWhere('l.detail', 'like', $term); });
            }
            $ledgerColumns = ['l.id','l.date','l.type','l.voucher_number','l.reference','l.detail','l.amount_in','l.amount_out','account.subhead_accounting_id', 'head.name as head_name', 'subhead.name as subhead_name'];
            foreach (['create_by', 'update_by'] as $auditColumn) {
                if (Schema::hasColumn('ledgers', $auditColumn)) $ledgerColumns[] = 'l.'.$auditColumn;
            }
            $total += (clone $query)->count();
            $ledgers = $query->orderByDesc('l.date')->orderByDesc('l.id')->limit($window)->get($ledgerColumns);
            $subheadIds = $ledgers->pluck('subhead_accounting_id')->filter()->unique();
            $subheadNames = $subheadIds->isNotEmpty() && Schema::hasTable('subhead_accountings')
                ? DB::table('subhead_accountings')->whereIn('id', $subheadIds)->pluck('name', 'id') : collect();
            foreach ($ledgers as $ledger) {
                if (in_array(strtoupper((string) $ledger->type), ['JV', 'CV'], true)) continue;
                $rows->push([
                    'date' => (string) $ledger->date, 'type' => (string) $ledger->type,
                    'number' => $identities->lookup($projectId, 'ledger', (int) $ledger->id) ?: trim((string) $ledger->type.'-'.(string) $ledger->voucher_number, '-'),
                    'reference' => (string) ($ledger->reference ?? ''), 'account' => $subheadNames->get($ledger->subhead_accounting_id) ?: '—',
                    'head' => (string) ($ledger->head_name ?? '—'), 'subhead' => (string) ($ledger->subhead_name ?? '—'),
                    'amount' => (float) max((float) $ledger->amount_in, (float) $ledger->amount_out), 'status' => 'Posted',
                    'detail' => (string) ($ledger->detail ?? ''),
                    'lines' => [],
                    'audit' => array_filter([
                        'Prepared by user ID' => $ledger->create_by ?? null,
                        'Updated by user ID' => $ledger->update_by ?? null,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'delete_url' => auth()->user()?->can('delete voucher') && in_array((string) $ledger->type, ['CR', 'CP'], true)
                        ? route('ledger.destroy') : null,
                    'delete_id' => (int) $ledger->id,
                    'url' => route('finance.voucher.print', ['id' => $ledger->id]),
                ]);
            }
        }

        // Typed isolated-pilot vouchers are separate immutable sources. Include
        // them in the same project-scoped register without duplicating their
        // legacy/customer-ledger counterparts.
        if (Schema::hasTable('finance_bank_transfer_sources') && Schema::hasTable('finance_posting_batches')) {
            $query = DB::table('finance_bank_transfer_sources as source')
                ->join('finance_posting_batches as batch', 'batch.id', '=', 'source.posting_batch_id')
                ->where('source.project_id', $projectId);
            if (!empty($filters['from'])) $query->whereDate('batch.effective_date', '>=', $filters['from']);
            if (!empty($filters['to'])) $query->whereDate('batch.effective_date', '<=', $filters['to']);
            if (!empty($filters['type'])) $query->where('batch.voucher_series', $filters['type']);
            if (!empty($filters['account']) && !Schema::hasTable('finance_posting_lines')) {
                $query->whereRaw('1 = 0');
            } elseif (!empty($filters['account'])) {
                $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('finance_posting_lines as filtered_line')
                    ->whereColumn('filtered_line.posting_batch_id', 'batch.id')
                    ->where('filtered_line.project_head_subhead_id', (int) $filters['account']));
            }
            if (!empty($filters['search'])) {
                $term = '%'.addcslashes($filters['search'], '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('batch.voucher_number', 'like', $term)->orWhere('source.narration', 'like', $term)
                        ->orWhere('source.amount', 'like', $term);
                });
            }
            $total += (clone $query)->count();
            foreach ($query->orderByDesc('batch.effective_date')->orderByDesc('batch.id')->limit($window)
                ->get(['source.posting_batch_id', 'source.transfer_kind', 'source.amount', 'source.narration', 'batch.voucher_series', 'batch.voucher_number', 'batch.effective_date']) as $source) {
                $batchId = (int) $source->posting_batch_id;
                $lines = $this->postingLines($batchId, $projectId);
                $rows->push([
                    'date' => (string) $source->effective_date, 'type' => (string) $source->voucher_series,
                    'number' => trim((string) $source->voucher_series.'-'.(string) $source->voucher_number, '-'),
                    'reference' => '', 'account' => ucfirst((string) $source->transfer_kind), 'amount' => (float) $source->amount,
                    'status' => 'Posted', 'detail' => (string) $source->narration, 'lines' => $lines,
                    'head' => $lines[0]['head'] ?? '—', 'subhead' => $lines[0]['subhead'] ?? '—',
                    'audit' => [], 'url' => route('finance.voucher.transfer.print', ['kind' => $source->transfer_kind, 'postingBatch' => $batchId]),
                ]);
            }
        }

        foreach ([
            ['table' => 'finance_plot_receipts', 'posting' => 'finance_plot_receipt_postings', 'kind' => 'receipt', 'type' => 'PR', 'number' => 'receipt_number', 'date' => 'receipt_date', 'print' => 'receipt'],
            ['table' => 'finance_plot_purchases', 'posting' => 'finance_plot_purchase_postings', 'kind' => 'purchase', 'type' => 'PP', 'number' => 'purchase_number', 'date' => 'purchase_date', 'print' => 'purchase'],
        ] as $typed) {
            if (!Schema::hasTable($typed['table']) || !Schema::hasTable($typed['posting'])) continue;
            $query = DB::table($typed['table'].' as source')->where('source.project_id', $projectId);
            if (!empty($filters['from'])) $query->whereDate('source.'.$typed['date'], '>=', $filters['from']);
            if (!empty($filters['to'])) $query->whereDate('source.'.$typed['date'], '<=', $filters['to']);
            if (!empty($filters['type']) && $filters['type'] !== $typed['type']) continue;
            if (!empty($filters['account']) && !Schema::hasTable('finance_posting_lines')) continue;
            if (!empty($filters['account'])) {
                $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from($typed['posting'].' as source_posting')
                    ->join('finance_posting_lines as filtered_line', 'filtered_line.posting_batch_id', '=', 'source_posting.posting_batch_id')
                    ->whereColumn('source_posting.source_id', 'source.id')
                    ->where('filtered_line.project_head_subhead_id', (int) $filters['account']));
            }
            if (!empty($filters['search'])) {
                $term = '%'.addcslashes($filters['search'], '%_\\').'%';
                $query->where(function ($q) use ($term, $typed) {
                    $q->where('source.'.$typed['number'], 'like', $term)->orWhere('source.narration', 'like', $term)
                        ->orWhere('source.amount', 'like', $term);
                });
            }
            $total += (clone $query)->count();
            foreach ($query->orderByDesc('source.'.$typed['date'])->orderByDesc('source.id')->limit($window)->get() as $source) {
                $link = DB::table($typed['posting'])->where('source_id', $source->id)->first(['posting_batch_id']);
                $lines = $link ? $this->postingLines((int) $link->posting_batch_id, $projectId) : [];
                $row = [
                    'date' => (string) $source->{$typed['date']}, 'type' => $typed['type'], 'number' => (string) $source->{$typed['number']},
                    'reference' => '', 'account' => $typed['kind'] === 'receipt' ? 'Plot receipt' : 'Plot purchase',
                    'amount' => (float) $source->amount, 'status' => 'Posted', 'detail' => (string) $source->narration,
                    'lines' => $lines, 'audit' => ['Prepared by user ID' => $source->created_by ?? null],
                    'head' => $lines[0]['head'] ?? '—', 'subhead' => $lines[0]['subhead'] ?? '—',
                    'url' => $typed['kind'] === 'purchase'
                        ? route('finance.voucher.plot-purchase.print', ['purchaseId' => $source->id]) : null,
                ];
                if ($typed['kind'] === 'receipt') {
                    $allocations = DB::table('finance_plot_receipt_allocations as allocation')
                        ->join('plots as plot', 'plot.id', '=', 'allocation.plot_id')
                        ->where('allocation.receipt_id', $source->id)
                        ->orderBy('allocation.id')->get(['allocation.plot_id', 'allocation.amount', 'plot.name as plot_name']);
                    foreach ($allocations as $allocation) {
                        $row['prints'][] = [
                            'label' => (string) $allocation->plot_name.' · '.number_format((float) $allocation->amount, 2),
                            'url' => route('finance.voucher.plot-receipt.print', ['receiptId' => $source->id, 'plotId' => $allocation->plot_id]),
                        ];
                    }
                }
                $rows->push($row);
            }
        }

        if ($requiresFullCandidateSet && $total > $filterCandidateLimit) {
            return response()->json([
                'message' => 'Too many vouchers to sort or filter completely. Select a narrower date range first.',
            ], 422);
        }
        // Sort AFTER combining the voucher sources, so ASC/DESC acts on the
        // actual displayed column instead of on one source table alone.
        $rows = $rows->sort(function (array $a, array $b) use ($sortColumn, $sortDirection) {
            $aValue = $a[$sortColumn] ?? '';
            $bValue = $b[$sortColumn] ?? '';
            $comparison = $sortColumn === 'amount'
                ? ((float) $aValue <=> (float) $bValue)
                : strnatcasecmp((string) $aValue, (string) $bValue);
            if ($comparison === 0) {
                $comparison = strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''))
                    ?: strnatcasecmp((string) ($a['number'] ?? ''), (string) ($b['number'] ?? ''));
            }
            return $sortDirection === 'asc' ? $comparison : -$comparison;
        })->values();
        if ($columnFilters) {
            $keys = [
                'column_date' => 'date', 'column_type' => 'type', 'column_number' => 'number',
                'column_reference' => 'reference', 'column_account' => 'account',
                'column_head' => 'head', 'column_subhead' => 'subhead',
                'column_amount' => 'amount', 'column_status' => 'status', 'column_detail' => 'detail',
            ];
            $rows = $rows->filter(function (array $row) use ($columnFilters, $keys) {
                foreach ($columnFilters as $filter => $needle) {
                    $value = $row[$keys[$filter]] ?? '';
                    $searchValue = trim((string) $needle);
                    if ($filter === 'column_amount') {
                        $value = number_format((float) $value, 2, '.', '');
                        $searchValue = str_replace([',', ' '], '', $searchValue);
                    }
                    if (stripos((string) $value, $searchValue) === false) return false;
                }
                return true;
            })->values();
        }
        $filteredTotal = $columnFilters ? $rows->count() : $total;
        return response()->json([
            'draw' => (int) $request->input('draw', 1), 'recordsTotal' => $total,
            'recordsFiltered' => $filteredTotal, 'data' => $rows->slice($start, $length)->values(),
        ]);
    }

    private function postingLines(int $batchId, int $projectId): array
    {
        if (!Schema::hasTable('finance_posting_lines')) return [];
        return DB::table('finance_posting_lines as line')
            ->join('project_head_subheads as account', function ($join) use ($projectId) {
                $join->on('account.id', '=', 'line.project_head_subhead_id')->where('account.project_id', '=', $projectId);
            })->leftJoin('head_accountings as head', 'head.id', '=', 'account.head_accounting_id')
            ->leftJoin('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')
            ->where('line.posting_batch_id', $batchId)->orderBy('line.id')
            ->get(['account.id as account_id', 'head.name as head_name', 'sub.name as subhead_name', 'line.debit', 'line.credit', 'line.description'])
            ->map(fn ($line) => [
                'account' => trim(($line->head_name ? $line->head_name.' / ' : '').($line->subhead_name ?: 'Account #'.$line->account_id)),
                'head' => (string) ($line->head_name ?? '—'),
                'subhead' => (string) ($line->subhead_name ?? '—'),
                'description' => (string) ($line->description ?? ''), 'debit' => (float) $line->debit, 'credit' => (float) $line->credit,
            ])->all();
    }

    public function printSource(int $customerLedger)
    {
        $projectId = (int) getSelectedTown();
        $source = DB::table('customer_ledger as source')
            ->leftJoin('leads as customer', 'customer.id', '=', 'source.customer_id')
            ->where('source.id', $customerLedger)->where('source.project_id', $projectId)
            ->first(['source.*', 'customer.first_name', 'customer.last_name', 'customer.business']);
        abort_if(!$source || $projectId < 1, 404);

        $lines = DB::table('ledgers as linked')
            ->join('project_head_subheads as a', 'a.id', '=', 'linked.project_head_subheads_id')
            ->leftJoin('head_accountings as h', 'h.id', '=', 'a.head_accounting_id')
            ->leftJoin('subhead_accountings as s', 's.id', '=', 'a.subhead_accounting_id')
            ->where('linked.customer_ledger_id', $customerLedger)->where('a.project_id', $projectId)
            ->orderBy('linked.id')->get(['h.name as head_name', 's.name as subhead_name', 'linked.amount_in', 'linked.amount_out', 'linked.detail']);

        $displayNumber = app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)
            ->lookup($projectId, 'customer_ledger', $customerLedger)
            ?: trim((string) $source->transaction_type.'-'.(string) ($source->type_id ?? $source->id), '-');
        $projectName = DB::table('projects')->where('id', $projectId)->value('name') ?: 'Project '.$projectId;
        $customerName = trim(implode(' ', array_filter([$source->first_name ?? null, $source->last_name ?? null])))
            ?: ((string) ($source->business ?? ''));
        $lifecycleEvents = app(\App\Services\Finance\ChequeLifecycleHistory::class)
            ->events($projectId, $customerLedger);
        $onlineLifecycleEvents = app(\App\Services\Finance\OnlineLifecycleHistory::class)
            ->events($projectId, $customerLedger);

        return view('admin.finance.voucher.daily_source_print', compact('source', 'lines', 'displayNumber', 'projectName', 'customerName', 'lifecycleEvents', 'onlineLifecycleEvents'));
    }

}
