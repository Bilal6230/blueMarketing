<?php

namespace App\Console\Commands;

use App\Models\JournalVoucher;
use App\Services\Finance\JournalVoucherIntegrityInspector;
use Illuminate\Console\Command;

/**
 * Read-only integrity report. Never modifies or reclassifies ledger history.
 */
class AuditJournalVouchers extends Command
{
    protected $signature = 'finance:audit-jv {project : Numeric project ID} {--limit=100 : Maximum headers to inspect (1-1000)}';
    protected $description = 'Read-only JV header/detail audit; ledger ownership remains unverified';

    public function handle(JournalVoucherIntegrityInspector $inspector): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($project === false || $limit === false) {
            $this->error('Provide a positive numeric project ID and --limit between 1 and 1000.');
            return self::INVALID;
        }

        $total = 0;
        $flagged = 0;
        $query = JournalVoucher::withTrashed()->where('project_id', $project)->where('type', 'JV')
            ->orderBy('id')->limit($limit)->pluck('id');
        foreach ($query as $id) {
            $result = $inspector->inspect($project, (int) $id);
            $total++;
            if (!$result['header_details_consistent']) {
                $flagged++;
            }
            $this->line(json_encode([
                'journal_voucher_id' => $id,
                'issues' => $result['issues'],
                'detail_debit_cents' => $result['detail_debit_cents'],
                'detail_credit_cents' => $result['detail_credit_cents'],
                'candidate_jv_ledger_rows' => $result['candidate_jv_ledger_rows'],
                'ledger_provenance' => $result['ledger_provenance'],
            ], JSON_THROW_ON_ERROR));
        }
        $this->info("Read-only audit: {$total} vouchers, {$flagged} flagged. Candidate ledger rows are NOT verified JV provenance.");
        return $flagged === 0 ? self::SUCCESS : self::FAILURE;
    }
}
