<?php

namespace App\Services\Finance;

/**
 * Preflight typed finance source connections WITHOUT creating a DB connection or doing I/O.
 * An incomplete test config must fail closed with a controlled exception.
 */
final class V2IsolatedConnectionGuard
{
    public static function isAllowed(): bool
    {
        $name = (string) config('database.default', '');
        $settings = config('database.connections.' . $name);
        if (!is_array($settings)) {
            return false;
        }

        $driver = (string) ($settings['driver'] ?? '');
        $database = (string) ($settings['database'] ?? '');
        $host = (string) ($settings['host'] ?? '');
        $url = (string) ($settings['url'] ?? '');
        $environment = (string) config('app.env');

        if ($driver === 'sqlite' && $database === ':memory:'
            && $environment === 'testing' && $url === '') {
            return true;
        }

        // Approved local ERP databases share one unified finance workflow.
        // Keep the allowlist explicit: never enable finance writes on a remote
        // or unreviewed database just because its name looks similar.
        $localTestDatabase = $environment === 'local'
            && in_array($database, ['bluenew', 'bluenew_clean_final'], true);

        // Read/write replicas can override the top-level host: reject them.
        return !($driver !== 'mysql'
            || !in_array($environment, ['local', 'testing'], true)
            || !(str_starts_with($database, 'bluemarketing_v2_') || $localTestDatabase)
            || preg_match('/(?:stag|prod|live|client|dev)/i', $database)
            || !in_array($host, ['localhost', '127.0.0.1'], true)
            || $url !== ''
            || !empty($settings['read'])
            || !empty($settings['write']));
    }

    public static function assertAllowed(): void
    {
        if (!self::isAllowed()) {
            throw new \RuntimeException('V2_ISOLATED_CONNECTION_REQUIRED');
        }
    }
}
