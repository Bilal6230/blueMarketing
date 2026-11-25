<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fail-closed deletion of master records. A configured test database may have
 * both FK-enforced and historical non-FK references to its master records.
 * Never cascade-delete those business relationships.
 */
final class SafeMasterRecordDeletion
{
    public static function assertUnreferenced(string $masterTable, int $id, string $referenceColumn): void
    {
        if (!in_array([$masterTable, $referenceColumn], [
            ['projects', 'project_id'],
            ['zones', 'zone_id'],
            ['areas', 'area_id'],
        ], true)) {
            throw new RuntimeException('Unsupported master deletion target.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Safe master deletion requires MySQL reference verification.');
        }

        $database = $connection->getDatabaseName();
        // Include historical tables that never declared a foreign key.
        // Names come exclusively from INFORMATION_SCHEMA (not user input).
        $columns = match ($masterTable) {
            'projects' => [$referenceColumn, 'town_id'],
            'zones' => [$referenceColumn, 'sector_id'],
            'areas' => [$referenceColumn],
        };
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $references = $connection->select(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND COLUMN_NAME IN ('.$placeholders.')
             AND TABLE_NAME <> ?',
            array_merge([$database], $columns, [$masterTable])
        );

        if (count($references) === 0) {
            throw new RuntimeException('Reference safety could not be established for this record.');
        }

        foreach ($references as $reference) {
            $table = (string) $reference->table_name;
            $column = (string) $reference->column_name;
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $table) ||
                !preg_match('/^[A-Za-z0-9_]+$/D', $column)) {
                throw new RuntimeException('Invalid dependency table metadata.');
            }
            if ($connection->table($table)->where($column, $id)->exists()) {
                throw new RuntimeException('This record is linked to '.$table.'. Remove or reassign its linked data before deleting.');
            }
        }

        // Also check explicit FK metadata where a historical table may use
        // a non-standard column name instead of project_id / zone_id.
        $foreignKeys = $connection->select(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_SCHEMA = ?
             AND REFERENCED_TABLE_NAME = ?',
            [$database, $database, $masterTable]
        );

        foreach ($foreignKeys as $key) {
            $table = (string) $key->table_name;
            $column = (string) $key->column_name;
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $table) ||
                !preg_match('/^[A-Za-z0-9_]+$/D', $column)) {
                throw new RuntimeException('Invalid foreign key metadata.');
            }
            if ($connection->table($table)->where($column, $id)->exists()) {
                throw new RuntimeException('This record has related data in '.$table.'.');
            }
        }

        // Relationships carried by audit evidence or textual aliases are
        // intentionally not removed; only unreferenced master rows qualify.
    }
}
