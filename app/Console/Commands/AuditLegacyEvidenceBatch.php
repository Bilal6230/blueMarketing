<?php
namespace App\Console\Commands;

use App\Services\Finance\LegacyVoucherEvidencePacket;
use Illuminate\Console\Command;

final class AuditLegacyEvidenceBatch extends Command
{
    protected $signature = 'finance:audit-legacy-evidence-batch {project : Numeric project ID} {ledgers : Comma-separated ledger IDs (1-100)}';
    protected $description = 'Read-only consolidated legacy ledger evidence packets for accounting review';

    public function handle(LegacyVoucherEvidencePacket $service): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1]]);
        $raw = explode(',', (string)$this->argument('ledgers'));
        if ($project === false || count($raw) > 100 || !$raw) {
            $this->error('Provide an existing project and 1-100 ledger IDs.');
            return self::INVALID;
        }
        $ids = [];
        foreach ($raw as $id) {
            $value = filter_var(trim($id), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($value === false) {
                $this->error('Every ledger ID must be positive numeric.');
                return self::INVALID;
            }
            $ids[] = (int)$value;
        }
        try {
            $result = $service->inspectBatch((int)$project, $ids);
        } catch (\DomainException | \InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
