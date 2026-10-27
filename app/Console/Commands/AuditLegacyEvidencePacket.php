<?php

namespace App\Console\Commands;

use App\Services\Finance\LegacyVoucherEvidencePacket;
use Illuminate\Console\Command;

final class AuditLegacyEvidencePacket extends Command
{
    protected $signature = 'finance:audit-legacy-evidence {project : Numeric project ID} {ledger : Numeric ledger ID}';
    protected $description = 'Read-only evidence packet for manually reviewing one historical JV-type ledger';

    public function handle(LegacyVoucherEvidencePacket $inspector): int
    {
        $project = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $ledger = filter_var($this->argument('ledger'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($project === false || $ledger === false) {
            $this->error('Positive project and ledger IDs required.');
            return self::INVALID;
        }
        try {
            $packet = $inspector->inspect((int)$project,(int)$ledger);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->line(json_encode($packet, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
