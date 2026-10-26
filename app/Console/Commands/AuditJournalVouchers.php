<?php

namespace App\Console\Commands;

use App\Models\JournalVoucher;
use App\Models\CommisionVoucher;
use App\Services\Finance\CommissionVoucherIntegrityInspector;
use App\Services\Finance\JournalVoucherIntegrityInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only integrity report. Never modifies or reclassifies ledger history.
 */
class AuditJournalVouchers extends Command
{
    protected $signature = 'finance:audit-jv {project : Numeric project ID} {--limit=100 : Maximum headers to inspect (1-1000)} {--type=JV : JV, CV or ALL voucher types} {--only-issues : Print only vouchers with detected inconsistencies}';
    protected $description = 'Read-only JV/CV header-detail audit with incomplete-scan safeguards';

    public function handle(JournalVoucherIntegrityInspector $inspector, CommissionVoucherIntegrityInspector $cvInspector): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $type = strtoupper((string) $this->option('type'));
        if ($project === false || $limit === false || !in_array($type, ['JV', 'CV', 'ALL'], true)) {
            $this->error('Provide positive project ID, --limit 1-1000 and --type JV, CV or ALL.');
            return self::INVALID;
        }

        $types = $type === 'ALL' ? ['JV', 'CV'] : [$type];
        $total = 0;
        $flagged = 0;
        $available = 0;
        $onlyIssues = (bool) $this->option('only-issues');

        foreach ($types as $voucherType) {
            $headers = $voucherType === 'JV'
                ? JournalVoucher::withTrashed()->where('project_id', $project)->where('type', 'JV')
                : CommisionVoucher::withTrashed()->where('project_id', $project);
            $available += (clone $headers)->count();

            // The limit applies per voucher type. Never claim a truncated scan is clean.
            foreach ((clone $headers)->orderBy('id')->limit($limit)->pluck('id') as $id) {
                $result = $voucherType === 'JV'
                    ? $inspector->inspect($project, (int) $id)
                    : $cvInspector->inspect($project, (int) $id);
                // A shared numeric ID across the two header tables is an
                // unresolved source-identity risk even if both vouchers balance.
                $otherTable = $voucherType === 'JV' ? 'commision_vouchers' : 'journal_vouchers';
                $overlappingHeader = DB::table($otherTable)
                    ->where('project_id', $project)->where('id', $id)->exists();
                $issues = $result['issues'];
                if ($overlappingHeader) {
                    $issues[] = 'numeric_jv_cv_header_id_overlap';
                }
                $total++;
                if ($issues !== []) {
                    $flagged++;
                }
                if ($onlyIssues && $issues === []) {
                    continue;
                }
                $this->line(json_encode([
                    'voucher_type' => $voucherType,
                    'voucher_id' => (int) $id,
                    'issues' => $issues,
                    'detail_debit_cents' => $result['detail_debit_cents'],
                    'detail_credit_cents' => $result['detail_credit_cents'],
                    'candidate_jv_ledger_rows' => $result['candidate_jv_ledger_rows'] ?? null,
                    'ledger_provenance' => $result['ledger_provenance'],
                ], JSON_THROW_ON_ERROR));
            }
        }

        $remaining = $available - $total;
        $this->info("Read-only audit: {$total} vouchers, {$flagged} flagged. Candidate ledger rows are NOT verified JV provenance.");
        if ($remaining > 0) {
            $this->warn("INCOMPLETE SCAN: {$remaining} additional vouchers not inspected; raise --limit for a larger bounded scan.");
            return self::FAILURE;
        }
        return $flagged === 0 ? self::SUCCESS : self::FAILURE;
    }
}
