<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\V2IsolatedConnectionGuard;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2IsolatedConnectionGuardTest extends TestCase
{
    /** A complete configuration; constructing a real connection is unnecessary. */
    private function fakeMysql(string $host, string $database): void
    {
        config()->set('app.env', 'testing');
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => $host,
            'port' => 3306,
            'database' => $database,
            'username' => 'unused_test_user',
            'password' => 'unused_test_password',
            'url' => null,
        ]);
        DB::purge('mysql');
    }

    public function test_memory_sqlite_is_allowed_only_in_testing(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        config()->set('app.env', 'testing');
        V2IsolatedConnectionGuard::assertAllowed();
        $this->assertTrue(true);
        config()->set('app.env', 'production');
        $this->expectException(\RuntimeException::class);
        V2IsolatedConnectionGuard::assertAllowed();
    }

    public function test_v2_named_remote_mysql_connection_is_refused_before_network_access(): void
    {
        $this->fakeMysql('192.0.2.30', 'bluemarketing_v2_local_rehearsal');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('V2_ISOLATED_CONNECTION_REQUIRED');
        V2IsolatedConnectionGuard::assertAllowed();
    }

    public function test_v2_named_staging_database_is_refused_before_network_access(): void
    {
        $this->fakeMysql('127.0.0.1', 'bluemarketing_v2_staging');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('V2_ISOLATED_CONNECTION_REQUIRED');
        V2IsolatedConnectionGuard::assertAllowed();
    }

    public function test_incomplete_mysql_config_fails_with_controlled_exception(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'host' => '127.0.0.1',
            'database' => 'bluemarketing_v2_local_rehearsal',
        ]);
        DB::purge('mysql');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('V2_ISOLATED_CONNECTION_REQUIRED');
        V2IsolatedConnectionGuard::assertAllowed();
    }

    public function test_local_mysql_configuration_passes_without_connecting(): void
    {
        $this->fakeMysql('127.0.0.1', 'bluemarketing_v2_local_rehearsal');
        V2IsolatedConnectionGuard::assertAllowed();
        $this->assertTrue(true);
    }

    public function test_remote_read_replica_is_refused_even_with_local_primary(): void
    {
        $this->fakeMysql('127.0.0.1', 'bluemarketing_v2_local_rehearsal');
        config()->set('database.connections.mysql.read', ['host' => '192.0.2.30']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('V2_ISOLATED_CONNECTION_REQUIRED');
        V2IsolatedConnectionGuard::assertAllowed();
    }
}
