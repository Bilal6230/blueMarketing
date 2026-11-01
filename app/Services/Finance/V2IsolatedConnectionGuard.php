<?php

namespace App\Services\Finance;

/**
 * V2 pilot only. Preflight config WITHOUT creating a DB connection or doing I/O.
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

        // Read/write replicas can override the top-level host: reject them.
        return !($driver !== 'mysql'
            || !in_array($environment, ['local', 'testing'], true)
            || !str_starts_with($database, 'bluemarketing_v2_')
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
