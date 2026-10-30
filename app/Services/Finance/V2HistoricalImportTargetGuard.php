<?php

namespace App\Services\Finance;

/**
 * Pure pre-connection protection for one-off historical V2 imports.
 * A CLI import must never trust its DB_DATABASE prefix alone: remote endpoints,
 * replicas, URLs or a test/production environment mismatch fail closed.
 */
final class V2HistoricalImportTargetGuard
{
    public static function assertAllowed(array $config, string $environment, bool $synthetic): void
    {
        $driver = $config['driver'] ?? null;
        $database = $config['database'] ?? null;
        $host = $config['host'] ?? null;
        $url = $config['url'] ?? null;
        $read = $config['read'] ?? null;
        $write = $config['write'] ?? null;

        if ($driver !== 'mysql'
            || !is_string($database) || !is_string($host)
            || !in_array($host, ['127.0.0.1', 'localhost'], true)
            || ($url !== null && $url !== '')
            || !empty($read) || !empty($write)
            || !empty($config['unix_socket'] ?? null)
            || !in_array($environment, ['local', 'testing'], true)
            || ($synthetic
                ? ($environment !== 'testing' || $database !== 'bluemarketing_v2_ci')
                : ($environment !== 'local'
                    || !preg_match('/^bluemarketing_v2_local_[a-z0-9_]+$/D', $database)
                    || $database === 'bluemarketing_v2_local_rehearsal'
                    || preg_match('/(?:live|prod|stag|dev|client)/i', $database)))
        ) {
            throw new \DomainException('V2_IMPORT_REQUIRES_EXACT_ISOLATED_LOCAL_TARGET');
        }
    }
}
