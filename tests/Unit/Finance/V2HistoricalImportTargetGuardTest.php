<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\V2HistoricalImportTargetGuard;
use DomainException;
use PHPUnit\Framework\TestCase;

final class V2HistoricalImportTargetGuardTest extends TestCase
{
    private function localConfig(): array
    {
        return [
            'driver' => 'mysql',
            'database' => 'bluemarketing_v2_local_utc_rehearsal',
            'host' => '127.0.0.1',
            'port' => 3306,
            'url' => '',
            'read' => null,
            'write' => null,
            'unix_socket' => '',
        ];
    }

    public function test_local_v2_target_is_accepted_only_as_a_non_synthetic_import(): void
    {
        V2HistoricalImportTargetGuard::assertAllowed($this->localConfig(), 'local', false);
        $this->addToAssertionCount(1);
    }

    public function test_disposable_ci_target_is_accepted_only_with_synthetic_data(): void
    {
        $config = $this->localConfig();
        $config['database'] = 'bluemarketing_v2_ci';
        V2HistoricalImportTargetGuard::assertAllowed($config, 'testing', true);
        $this->addToAssertionCount(1);
    }

    public function test_unsafe_import_configs_fail_before_connecting(): void
    {
        $base = $this->localConfig();
        $cases = [
            'remote host' => [array_replace($base, ['host' => 'db.internal.example']), 'local', false],
            'host array' => [array_replace($base, ['host' => ['127.0.0.1', 'db.internal']]), 'local', false],
            'replica read' => [array_replace($base, ['read' => ['host' => 'db.remote']]), 'local', false],
            'replica write' => [array_replace($base, ['write' => ['host' => 'db.remote']]), 'local', false],
            'database URL' => [array_replace($base, ['url' => 'mysql://somewhere/db']), 'local', false],
            'unix socket' => [array_replace($base, ['unix_socket' => '/tmp/mysql.sock']), 'local', false],
            'production environment' => [$base, 'production', false],
            'staging environment' => [$base, 'staging', false],
            'testing real import' => [$base, 'testing', false],
            'production named database' => [array_replace($base, ['database' => 'bluemarketing_v2_local_production']), 'local', false],
            'staging named database' => [array_replace($base, ['database' => 'bluemarketing_v2_local_staging']), 'local', false],
            'client named database' => [array_replace($base, ['database' => 'bluemarketing_v2_local_client']), 'local', false],
            'dev named database' => [array_replace($base, ['database' => 'bluemarketing_v2_local_dev']), 'local', false],
            'v1 database' => [array_replace($base, ['database' => 'bluemarketing']), 'local', false],
            'ci synthetic against local rehearsal' => [$base, 'local', true],
            'ci non-synthetic' => [array_replace($base, ['database' => 'bluemarketing_v2_ci']), 'testing', false],
            'ci synthetic on production' => [array_replace($base, ['database' => 'bluemarketing_v2_ci']), 'production', true],
            'sqlite driver' => [array_replace($base, ['driver' => 'sqlite']), 'local', false],
        ];
        foreach ($cases as $label => [$config, $environment, $synthetic]) {
            try {
                V2HistoricalImportTargetGuard::assertAllowed(
                    $config, $environment, $synthetic
                );
                $this->fail('Unsafe source import config was allowed: ' . $label);
            } catch (DomainException $exception) {
                $this->assertSame(
                    'V2_IMPORT_REQUIRES_EXACT_ISOLATED_LOCAL_TARGET',
                    $exception->getMessage(), $label
                );
            }
        }
    }
}
