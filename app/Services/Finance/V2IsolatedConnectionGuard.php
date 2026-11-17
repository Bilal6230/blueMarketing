<?php

namespace App\Services\Finance;

/**
 * Compatibility entry point for finance connection readiness.
 *
 * Finance uses Laravel's configured default connection in every environment.
 * Database names, hosts and deployments must not select a separate finance
 * implementation. Authentication, permissions and project authorization are
 * enforced by the workflow layer, not by a database-name allowlist.
 */
final class V2IsolatedConnectionGuard
{
    public static function isAllowed(): bool
    {
        $name = (string) config('database.default', '');
        $settings = config('database.connections.'.$name);

        // Configuration sanity check only; never establish or mutate a connection.
        // Laravel will report actual connectivity and missing schema errors.
        return $name !== '' && is_array($settings)
            && (string) ($settings['driver'] ?? '') !== '';
    }

    public static function assertAllowed(): void
    {
        if (!self::isAllowed()) {
            throw new \RuntimeException('FINANCE_CONNECTION_NOT_CONFIGURED');
        }
    }
}
