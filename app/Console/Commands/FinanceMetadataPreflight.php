<?php

namespace App\Console\Commands;

use App\Services\Finance\FinanceSchemaPreflight;
use Illuminate\Console\Command;

final class FinanceMetadataPreflight extends Command
{
    protected $signature = 'finance:metadata-preflight';
    protected $description = 'Read-only finance schema column and durable uniqueness preflight';

    public function handle(FinanceSchemaPreflight $inspector): int
    {
        $result = $inspector->inspect();
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        if ($result['status'] !== 'schema_verified') {
            $this->warn('Finance schema preflight blocked; no schema or financial data changed.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
