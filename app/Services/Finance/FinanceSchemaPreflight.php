<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Purely read-only finance metadata rollout preflight; never creates or alters schema. */
final class FinanceSchemaPreflight
{
    public function inspect(): array
    {
        $requirements = [
            'ledger_source_attributions' => [
                'id', 'ledger_id', 'project_id', 'source_kind', 'source_id',
                'evidence_kind', 'created_at', 'updated_at',
            ],
            'financial_posting_requests' => [
                'id', 'project_id', 'source_kind', 'request_key',
                'payload_hash', 'status', 'voucher_id', 'created_at', 'updated_at',
            ],
        ];
        $problems = [];
        $tables = [];
        foreach ($requirements as $table => $columns) {
            $present = Schema::hasTable($table);
            $missing = $present ? array_values(array_filter($columns,
                fn ($column) => !Schema::hasColumn($table, $column))) : $columns;
            $tables[$table] = ['exists' => $present, 'missing_columns' => $missing];
            if (!$present) {
                $problems[] = $table . '_missing';
            } elseif ($missing !== []) {
                $problems[] = $table . '_incomplete_columns';
            }
        }

        // In addition to column presence, inspect the *actual* database's
        // indexes: duplicate-post protection is not safe without uniqueness.
        $indexes = [];
        if ($tables['ledger_source_attributions']['exists']) {
            $indexes['ledger_source_attributions'] = $this->hasUniqueColumns(
                'ledger_source_attributions', ['ledger_id']);
            if (!$indexes['ledger_source_attributions']) {
                $problems[] = 'ledger_attribution_unique_index_missing';
            }
        }
        if ($tables['financial_posting_requests']['exists']) {
            $indexes['financial_posting_requests'] = $this->hasUniqueColumns(
                'financial_posting_requests', ['project_id', 'source_kind', 'request_key']);
            if (!$indexes['financial_posting_requests']) {
                $problems[] = 'financial_request_unique_index_missing';
            }
        }

        return ['status' => $problems === [] ? 'schema_verified' : 'blocked',
            'driver' => DB::connection()->getDriverName(),
            'tables' => $tables, 'unique_indexes' => $indexes,
            'blockers' => $problems,
            'limitations' => 'Schema structure only; does not prove MySQL concurrency or historical balances.'];
    }

    private function hasUniqueColumns(string $table, array $columns): bool
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if (!(int) $index->unique) {
                    continue;
                }
                $name = str_replace("'", "''", $index->name);
                $fields = DB::select("PRAGMA index_info('{$name}')");
                usort($fields, fn ($a, $b) => $a->seqno <=> $b->seqno);
                if (array_map(fn ($field) => $field->name, $fields) === $columns) {
                    return true;
                }
            }
            return false;
        }
        if ($driver === 'mysql') {
            $rows = DB::select('SHOW INDEX FROM ' . $table);
            $groups = [];
            foreach ($rows as $row) {
                if ((int) $row->Non_unique === 0) {
                    $groups[$row->Key_name][(int) $row->Seq_in_index] = $row->Column_name;
                }
            }
            foreach ($groups as $group) {
                ksort($group);
                if (array_values($group) === $columns) {
                    return true;
                }
            }
            return false;
        }
        return false; // Unknown DB driver never gives false confidence.
    }
}
