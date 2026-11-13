<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\BankSourceLinkedVoucherHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only, project-scoped printable audit trail for Online/Cheque-linked
 * bank vouchers. Never posts, claims, releases or rewrites an allocation.
 */
final class BankSourceLinkedVoucherPrintController extends Controller
{
    public function show(string $method, int $source, BankSourceLinkedVoucherHistory $history)
    {
        abort_unless(in_array($method, ['online', 'cheque'], true) && $source > 0, 404);
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 404);

        $table = $method === 'online' ? 'finance_online_intakes' : 'finance_cheque_intakes';
        abort_unless(Schema::hasTable($table), 404);

        $record = DB::table($table)
            ->where('project_id', $projectId)->where('id', $source)
            ->first();

        abort_unless($record, 404);

        $rows = $history->forSource($projectId, $method, $source);
        $sourceLabel = $method === 'online'
            ? (string) ($record->reference ?: ('Online #'.$source))
            : (string) ($record->cheque_number ?: ('Cheque #'.$source));

        return response()->view('admin.finance.voucher.bank_source_linked_history_print', [
            'method' => $method,
            'projectId' => $projectId,
            'sourceId' => $source,
            'sourceLabel' => $sourceLabel,
            'sourceStatus' => (string) ($record->status ?? ''),
            'sourceAmount' => (string) ($record->amount ?? '0'),
            'rows' => $rows,
        ])->header('Cache-Control', 'private, no-store, max-age=0')
          ->header('X-Content-Type-Options', 'nosniff');
    }
}
