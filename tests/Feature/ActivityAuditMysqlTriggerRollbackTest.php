<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verify the EXISTING users UPDATE trigger in the EXISTING local MySQL schema.
 * No database/table/trigger is created. Business row values remain unchanged.
 * All committed probe impacts are deleted by their unique request token.
 */
final class ActivityAuditMysqlTriggerRollbackTest extends TestCase
{
    private const CONNECTION = 'mysql';
    private ?string $token = null;
    private ?int $userId = null;
    private $originalStatus = null;

    protected function setUp(): void
    {
        parent::setUp();

        $config = config('database.connections.'.self::CONNECTION);
        if (!is_array($config) || ($config['driver'] ?? null) !== 'mysql') {
            $this->markTestSkipped('Requires a configured local MySQL connection.');
        }

        // This probe must never run over a remote/live database connection.
        $host = strtolower(trim((string) ($config['host'] ?? '')));
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->markTestSkipped('MySQL trigger probe is restricted to a local database host.');
        }

        $db = DB::connection(self::CONNECTION);
        if ($db->getDriverName() !== 'mysql'
            || !Schema::connection(self::CONNECTION)->hasTable('users')
            || !Schema::connection(self::CONNECTION)->hasTable('activity_audit_impacts')
            || !Schema::connection(self::CONNECTION)->hasColumn('users', 'status_id')) {
            $this->markTestSkipped('Existing users/status_id and Activity Audit tables are required.');
        }

        $trigger = $db->table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', $db->getDatabaseName())
            ->where('TRIGGER_NAME', 'bm_audit_users_au')
            ->where('EVENT_OBJECT_TABLE', 'users')
            ->where('EVENT_MANIPULATION', 'UPDATE')
            ->where('ACTION_TIMING', 'AFTER')
            ->first();
        if (!$trigger) {
            $this->markTestSkipped('Existing users AFTER UPDATE audit trigger is required.');
        }

        $user = $db->table('users')->orderBy('id')->first(['id', 'status_id']);
        if (!$user) {
            $this->markTestSkipped('At least one existing local test user is required.');
        }

        $this->userId = (int) $user->id;
        $this->originalStatus = $user->status_id;
        $this->token = (string) Str::uuid();
    }

    protected function tearDown(): void
    {
        if ($this->token !== null) {
            $db = DB::connection(self::CONNECTION);
            try {
                while ($db->transactionLevel() > 0) {
                    $db->rollBack();
                }
                // Remove ONLY committed, unattached probe evidence.
                $db->table('activity_audit_impacts')
                    ->where('request_token', $this->token)
                    ->whereNull('activity_audit_id')
                    ->where('table_name', 'users')
                    ->where('operation', 'UPDATE')
                    ->where('capture_type', 'db_trigger_row')
                    ->delete();
            } finally {
                // A pooled MySQL session must not leak its audit token.
                $db->statement('SET @activity_audit_token = NULL');
            }
        }
        parent::tearDown();
    }

    private function noOpUpdate(): void
    {
        DB::connection(self::CONNECTION)->table('users')
            ->where('id', $this->userId)
            ->update(['status_id' => $this->originalStatus]);
    }

    private function probeCount(): int
    {
        return DB::connection(self::CONNECTION)->table('activity_audit_impacts')
            ->where('request_token', $this->token)
            ->where('table_name', 'users')
            ->where('capture_type', 'db_trigger_row')
            ->count();
    }

    public function test_mysql_existing_trigger_discards_audit_snapshot_after_rollback(): void
    {
        $db = DB::connection(self::CONNECTION);
        $db->statement('SET @activity_audit_token = ?', [$this->token]);

        $db->beginTransaction();
        try {
            $this->noOpUpdate();
            $this->assertSame(1, $this->probeCount());
        } finally {
            $db->rollBack();
        }

        $this->assertSame(0, $this->probeCount());
        $this->assertEquals($this->originalStatus,
            $db->table('users')->where('id', $this->userId)->value('status_id'));
    }

    public function test_mysql_existing_trigger_commits_audit_snapshot_then_cleans_probe(): void
    {
        $db = DB::connection(self::CONNECTION);
        $db->statement('SET @activity_audit_token = ?', [$this->token]);

        $db->transaction(function (): void {
            $this->noOpUpdate();
        });

        $impact = $db->table('activity_audit_impacts')
            ->where('request_token', $this->token)->first();

        $this->assertNotNull($impact);
        $this->assertSame('users', $impact->table_name);
        $this->assertSame('UPDATE', $impact->operation);
        $this->assertSame('db_trigger_row', $impact->capture_type);
        $this->assertSame((string) $this->userId, $impact->record_id);
        $this->assertEquals($this->originalStatus,
            $db->table('users')->where('id', $this->userId)->value('status_id'));
        $this->assertSame(1, $this->probeCount());
    }
}
