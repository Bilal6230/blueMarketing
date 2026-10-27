<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\FinanceSchemaPreflight;
use PHPUnit\Framework\TestCase;

final class MysqlUniqueIndexPreflightTest extends TestCase
{
    private function key(string $name, int $position, string $column, ?int $prefix = null, string $nullable = ''): object
    {
        return (object) [
            'Non_unique'=>0, 'Key_name'=>$name, 'Seq_in_index'=>$position,
            'Column_name'=>$column, 'Sub_part'=>$prefix, 'Null'=>$nullable,
        ];
    }

    public function test_exact_full_length_nonnullable_unique_scope_passes(): void
    {
        $rows=[
            $this->key('request_unique', 1, 'project_id'),
            $this->key('request_unique', 2, 'source_kind'),
            $this->key('request_unique', 3, 'request_key'),
        ];
        $this->assertTrue(FinanceSchemaPreflight::mysqlHasCompleteUniqueIndex($rows,
            ['project_id','source_kind','request_key']));
    }

    public function test_prefix_unique_index_is_not_accepted_as_full_uniqueness(): void
    {
        $rows=[
            $this->key('request_unique', 1, 'project_id'),
            $this->key('request_unique', 2, 'source_kind'),
            $this->key('request_unique', 3, 'request_key', 40),
        ];
        $this->assertFalse(FinanceSchemaPreflight::mysqlHasCompleteUniqueIndex($rows,
            ['project_id','source_kind','request_key']));
    }

    public function test_nullable_unique_index_does_not_certify_durable_identity(): void
    {
        $rows=[$this->key('ledger_unique',1,'ledger_id',null,'YES')];
        $this->assertFalse(FinanceSchemaPreflight::mysqlHasCompleteUniqueIndex($rows,['ledger_id']));
    }

    public function test_unordered_or_partial_column_scope_is_rejected(): void
    {
        $rows=[
            $this->key('request_unique', 1, 'source_kind'),
            $this->key('request_unique', 2, 'project_id'),
            $this->key('request_unique', 3, 'request_key'),
        ];
        $this->assertFalse(FinanceSchemaPreflight::mysqlHasCompleteUniqueIndex($rows,
            ['project_id','source_kind','request_key']));
    }
}
