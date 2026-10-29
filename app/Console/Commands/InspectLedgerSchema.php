<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only compatibility inspection; never guesses historical source ownership.
 */
final class InspectLedgerSchema extends Command
{
    protected $signature = 'finance:inspect-ledger-schema';
    protected $description = 'Read-only finance table/column/index and FK compatibility assessment';

    public function handle(): int
    {
        $tables = [
            'ledgers' => ['id', 'type', 'type_id', 'voucher_number', 'reference', 'customer_ledger_id', 'project_head_subheads_id'],
            'customer_ledger' => ['id', 'project_id', 'transaction_type', 'type_id', 'reference'],
            'project_head_subheads' => ['id', 'project_id'],
            'projects' => ['id'],
            'journal_vouchers' => ['id', 'project_id', 'voucher_number'],
        ];
        $results = [];
        $missing = false;
        foreach ($tables as $name => $required) {
            $exists = Schema::hasTable($name);
            $columns = $exists ? Schema::getColumnListing($name) : [];
            $absent = array_values(array_diff($required, $columns));
            $results[$name] = [
                'exists' => $exists,
                'missing_required_columns' => $absent,
            ];
            if (!$exists || $absent !== []) {
                $missing = true;
            }
        }
        $driver = DB::connection()->getDriverName();
        $this->line(json_encode([
            'database_driver' => $driver,
            'schema' => $results,
            'minimum_shape_compatible' => !$missing,
            'source_type_id_is_polymorphic' => true,
            'cp_clearance_can_reference_cr_or_ppr' => true,
            'safe_to_automatically_add_strict_source_fk' => false,
            'indexes_and_historical_collisions_verified' => false,
            'read_only' => true,
        ], JSON_THROW_ON_ERROR));
        return $missing ? self::FAILURE : self::SUCCESS;
    }
}
