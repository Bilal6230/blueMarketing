<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountType;
use App\Models\ProjectHeadSubhead;
use Illuminate\Http\Request;

class SixVoucherFormController extends Controller
{
    /**
     * Presentation-only preparation. Do not create financial entries here:
     * DR and the four new voucher identities require coordinated ledger,
     * approval, history and number-allocation changes first.
     */
    public function create(Request $request, string $type)
    {
        $type = strtoupper($type);
        abort_unless(in_array($type, ['CR', 'DR', 'CCR', 'CDR', 'BOR', 'BOP'], true), 404);
        abort_unless(auth()->user()?->can('create voucher'), 403);

        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 403);

        $accountTypes = AccountType::query()->where('status', 1)->orderBy('id')->get(['id', 'name']);
        $accounts = ProjectHeadSubhead::availableForPosting()
            ->where('project_id', $projectId)
            ->with(['headAccounting', 'subheadAccounting'])
            ->get()
            ->map(fn ($item) => [
                'head_id' => (int) $item->head_accounting_id,
                'head_name' => (string) ($item->headAccounting?->name ?? ''),
                'child_id' => (int) $item->subhead_accounting_id,
                'child_name' => (string) ($item->subheadAccounting?->name ?? ''),
                'account_type' => (int) ($item->headAccounting?->acct_type ?? 0),
            ])
            ->filter(fn ($item) => $item['head_id'] > 0 && $item['child_id'] > 0)
            ->values();

        $titles = [
            'CR' => 'Credit Voucher',
            'DR' => 'Debit Voucher',
            'CCR' => 'Cash Credit Voucher',
            'CDR' => 'Cash Debit Voucher',
            'BOR' => 'Bank Online Receipt Voucher',
            'BOP' => 'Bank Online Payment Voucher',
        ];

        return view('admin.finance.voucher.six_voucher_form', [
            'type' => $type,
            'title' => $titles[$type],
            'accountTypes' => $accountTypes,
            'accounts' => $accounts,
            'projectId' => $projectId,
            'legacyRoute' => $type === 'CR' ? route('finance.voucher.in')
                : ($type === 'DR' ? route('finance.voucher.out') : null),
        ]);
    }
}
