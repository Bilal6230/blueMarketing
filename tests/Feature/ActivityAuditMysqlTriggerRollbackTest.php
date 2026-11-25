<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Opt-in MySQL trigger commit/rollback tests on a SEPARATE EMPTY test schema.
 *
 * Set AUDIT_MYSQL_TEST_DATABASE to an existing, empty MySQL test database.
 * This test NEVER creates or alters tables in the application's configured
 * database and refuses a database with existing tables. All fixture objects
 * are removed in tearDown; there is no permanent new table or migration.
 */
final class ActivityAuditMysqlTriggerRollbackTest extends TestCase
{
    private const CONNECTION = 'activity_audit_mysql_probe';
    private const RECORDS = 'audit_mysql_probe_records';
    private const IMPACTS = 'activity_audit_impacts';
    private const TRIGGER = 'bm_audit_probe_ai';

    private bool $createdFixture = false;

    protected function setUp(): void
    {
        parent::setUp();

        $database = trim((string) getenv('AUDIT_MYSQL_TEST_DATABASE'));
        if ($database === '') {
            $this->markTestSkipped('Set AUDIT_MYSQL_TEST_DATABASE to a separate EMPTY MySQL test database.');
        }

        $source = config('database.connections.mysql');
        if (!is_array($source) || ($source['driver'] ?? null) !== 'mysql') {
            $this->markTestSkipped('This integration test requires the configured mysql connection.');
        }

        if (strcasecmp($database, (string) ($source['database'] ?? '')) === 0) {
            $this->fail('Refusing to run MySQL trigger test against the application database.');
        }

        config()->set('database.connections.'.self::CONNECTION, array_merge($source, [
            'database' => $database,
        ]));
        DB::purge(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        if ($connection->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Test connection must be MySQL.');
        }

        $existingTables = (int) $connection->table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $database)->count();

        if ($existingTables !== 0) {
            $this->fail('Refusing to create probe fixtures: separate test database must be EMPTY.');
        }

        Schema::connection(self::CONNECTION)->create(self::IMPACTS, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('request_token', 40)->nullable();
            $table->string('table_name');
            $table->string('operation');
            $table->string('record_id');
            $table->string('capture_type');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
        });
        $this->createdFixture = true;
        Schema::connection(self::CONNECTION)->create(self::RECORDS, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('status');
        });

        $connection->unprepared("CREATE TRIGGER `".self::TRIGGER."`
            AFTER INSERT ON `".self::RECORDS."`
            FOR EACH ROW BEGIN
                IF @activity_audit_token IS NOT NULL THEN
                    INSERT INTO `".self::IMPACTS."`
                        (request_token, table_name, operation, record_id,
                         capture_type, before_values, after_values)
                    VALUES (@activity_audit_token, '".self::RECORDS."', 'INSERT',
                        CAST(NEW.id AS CHAR), 'db_trigger_row', NULL,
                        JSON_OBJECT('id', NEW.id, 'status', NEW.status));
                END IF;
            END");
    }

    protected function tearDown(): void
    {
        if ($this->createdFixture) {
            try {
                $connection = DB::connection(self::CONNECTION);
                // Always clear session scope, even if an assertion failed.
                if ($connection->transactionLevel() > 0) {
                    while ($connection->transactionLevel() > 0) {
                        $connection->rollBack();
                    }
                }
                $connection->statement('SET @activity_audit_token = NULL');
                $connection->unprepared('DROP TRIGGER IF EXISTS `'.self::TRIGGER.'`');
                Schema::connection(self::CONNECTION)->dropIfExists(self::RECORDS);
                Schema::connection(self::CONNECTION)->dropIfExists(self::IMPACTS);
                DB::disconnect(self::CONNECTION);
            } finally {
                $this->createdFixture = false;
            }
        }
        parent::tearDown();
    }

    public function test_mysql_trigger_rollback_removes_both_business_row_and_audit_snapshot(): void
    {
        $connection = DB::connection(self::CONNECTION);
        $connection->statement('SET @activity_audit_token = ?', ['mysql-audit-rollback-probe']);
        $connection->beginTransaction();
        try {
            $connection->table(self::RECORDS)->insert(['status' => 'rolled-back']);
            $this->assertSame(1, $connection->table(self::RECORDS)->count());
            $this->assertSame(1, $connection->table(self::IMPACTS)->count());
        } finally {
            $connection->rollBack();
        }

        $this->assertSame(0, $connection->table(self::RECORDS)->count());
        $this->assertSame(0, $connection->table(self::IMPACTS)->count());
    }

    public function test_mysql_trigger_commit_persists_matching_audit_row_and_allowlisted_snapshot(): void
    {
        $connection = DB::connection(self::CONNECTION);
        $connection->statement('SET @activity_audit_token = ?', ['mysql-audit-commit-probe']);
        $connection->transaction(function () use ($connection): void {
            $connection->table(self::RECORDS)->insert(['status' => 'committed']);
        });

        $row = $connection->table(self::IMPACTS)->first();
        $this->assertSame(1, $connection->table(self::RECORDS)->count());
        $this->assertNotNull($row);
        $this->assertSame('mysql-audit-commit-probe', $row->request_token);
        $this->assertSame('db_trigger_row', $row->capture_type);
        $after = json_decode((string) $row->after_values, true);
        $this->assertSame('committed', $after['status'] ?? null);
    }
}
