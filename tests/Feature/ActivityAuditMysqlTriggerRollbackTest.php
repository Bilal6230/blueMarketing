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
    private const CONNECTION = 'activity_audit_local_mysql_probe';
    private ?string $token = null;
    private ?int $userId = null;
    private $originalStatus = null;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml force-overrides DB_CONNECTION=sqlite and DB_DATABASE=:memory:.
        // Read the existing local .env WITHOUT modifying global test variables.
        // Reusing config('database.connections.mysql') unmodified would point
        // the MySQL connection at :memory: and can silently skip this gate.
        $envFile = base_path('.env');
        if (!is_file($envFile)) {
            $this->markTestSkipped('Existing local .env is required for the MySQL probe.');
        }
        $values = \Dotenv\Dotenv::parse(file_get_contents($envFile));
        $host = strtolower(trim((string) ($values['DB_HOST'] ?? '127.0.0.1')));
        $database = trim((string) ($values['DB_DATABASE'] ?? ''));
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || $database === '' || $database === ':memory:'
            || !empty($values['DATABASE_URL'])) {
            $this->markTestSkipped('A named loopback MySQL database in local .env is required.');
        }

        $config = config('database.connections.mysql');
        if (!is_array($config)) {
            $this->markTestSkipped('Laravel MySQL driver configuration is missing.');
        }
        config()->set('database.connections.'.self::CONNECTION, array_merge($config, [
            'driver' => 'mysql',
            'url' => null,
            'host' => $host,
            'port' => $values['DB_PORT'] ?? '3306',
            'database' => $database,
            'username' => $values['DB_USERNAME'] ?? 'root',
            'password' => $values['DB_PASSWORD'] ?? '',
            'unix_socket' => $values['DB_SOCKET'] ?? '',
        ]));
        DB::purge(self::CONNECTION);

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
        DB::disconnect(self::CONNECTION);
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
