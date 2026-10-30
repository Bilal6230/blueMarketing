<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression for V2 baseline SQL type parsing. Uses only migration metadata:
 * no database bootstrap operations, no source data, no production writes.
 */
class DatabaseV2SourceEnumFidelityTest extends TestCase
{
    public static function originalEnumCases(): array
    {
        return [
            'stock entry flow uppercase' => ["enum('IN','OUT')", ['IN', 'OUT']],
            'stock movement direction uppercase' => ["enum('IN','OUT')", ['IN', 'OUT']],
            'stock party original mixed case' => [
                "enum('Supplier','Consumer','Site')", ['Supplier', 'Consumer', 'Site'],
            ],
            'escaped quote in enum member' => ["enum('O''Brien','Site')", ["O'Brien", 'Site']],
        ];
    }

    #[DataProvider('originalEnumCases')]
    public function test_enum_members_preserve_source_case_and_order(string $sqlType, array $expected): void
    {
        $migration = require base_path('database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php');

        // Exercise the exact parser the baseline migration uses for enum values.
        $method = new \ReflectionMethod($migration, 'enumValues');
        preg_match('/^([a-z]+)\\((.*)\\)$/i', $sqlType, $parts);
        $this->assertSame('enum', strtolower($parts[1]));
        $this->assertSame($expected, $method->invoke($migration, $parts[2]));
    }

    public function test_source_schema_metadata_contains_exact_stock_enum_labels(): void
    {
        $expected = [
            'stock_entries' => ['flow' => "enum('IN','OUT')"],
            'stock_movements' => ['direction' => "enum('IN','OUT')"],
            'stock_parties' => ['type' => "enum('Supplier','Consumer','Site')"],
        ];
        $found = [];
        for ($i = 1; $i <= 4; $i++) {
            $path = base_path(sprintf('database/v2_schema/legacy_columns_%02d.json', $i));
            $part = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(
                'bbc45fe7f0b6cdab1a188d2437e973e2bb56d442a29585ab328bd062e89335ed',
                $part['source_sha256']
            );
            foreach ($part['tables'] as $table) {
                if (!isset($expected[$table['table']])) {
                    continue;
                }
                foreach ($table['columns'] as [$name, $definition]) {
                    if (isset($expected[$table['table']][$name])) {
                        $this->assertStringStartsWith($expected[$table['table']][$name], $definition);
                        $found[$table['table']][$name] = true;
                    }
                }
            }
        }
        foreach ($expected as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $this->assertTrue($found[$table][$column] ?? false, "$table.$column missing");
            }
        }
    }
}
