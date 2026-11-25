<?php

namespace Tests\Unit;

use App\Support\Audit\TriggerDefinitionValidator;
use PHPUnit\Framework\TestCase;

final class ActivityAuditTriggerDefinitionValidatorTest extends TestCase
{
    private function metadata(): object
    {
        return (object) [
            'EVENT_OBJECT_TABLE' => 'ledgers',
            'EVENT_MANIPULATION' => 'UPDATE',
            'ACTION_TIMING' => 'AFTER',
            'ACTION_STATEMENT' => "BEGIN IF @activity_audit_token IS NOT NULL THEN
                INSERT INTO activity_audit_impacts (table_name, capture_type)
                VALUES ('ledgers', 'db_trigger_row'); END IF; END",
        ];
    }

    public function test_exact_expected_trigger_metadata_passes(): void
    {
        $this->assertTrue(TriggerDefinitionValidator::valid($this->metadata(), 'ledgers', 'UPDATE'));
    }

    public function test_a_trigger_with_wrong_table_or_event_is_rejected(): void
    {
        $this->assertFalse(TriggerDefinitionValidator::valid($this->metadata(), 'users', 'UPDATE'));
        $this->assertFalse(TriggerDefinitionValidator::valid($this->metadata(), 'ledgers', 'DELETE'));
    }

    public function test_before_trigger_does_not_pass_atomic_after_write_requirement(): void
    {
        $meta = $this->metadata();
        $meta->ACTION_TIMING = 'BEFORE';
        $this->assertFalse(TriggerDefinitionValidator::valid($meta, 'ledgers', 'UPDATE'));
    }

    public function test_missing_request_token_or_audit_insert_fails_closed(): void
    {
        $meta = $this->metadata();
        $meta->ACTION_STATEMENT = "INSERT INTO activity_audit_impacts VALUES ('db_trigger_row')";
        $this->assertFalse(TriggerDefinitionValidator::valid($meta, 'ledgers', 'UPDATE'));

        $meta = $this->metadata();
        $meta->ACTION_STATEMENT = 'SELECT @activity_audit_token';
        $this->assertFalse(TriggerDefinitionValidator::valid($meta, 'ledgers', 'UPDATE'));
    }

    public function test_a_trigger_not_writing_row_snapshots_is_rejected(): void
    {
        $meta = $this->metadata();
        $meta->ACTION_STATEMENT = str_replace('db_trigger_row', 'sql_statement', $meta->ACTION_STATEMENT);
        $this->assertFalse(TriggerDefinitionValidator::valid($meta, 'ledgers', 'UPDATE'));
    }
}
