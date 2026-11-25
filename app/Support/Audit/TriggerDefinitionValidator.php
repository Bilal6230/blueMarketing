<?php

namespace App\Support\Audit;

/**
 * Read-only validation of the MySQL information_schema.TRIGGERS metadata.
 * Matching a trigger's name is insufficient to prove auditing is enabled.
 */
final class TriggerDefinitionValidator
{
    public static function valid(object $definition, string $table, string $event): bool
    {
        $body = (string) ($definition->ACTION_STATEMENT ?? '');

        return (string) ($definition->EVENT_OBJECT_TABLE ?? '') === $table
            && strtoupper((string) ($definition->EVENT_MANIPULATION ?? '')) === $event
            && strtoupper((string) ($definition->ACTION_TIMING ?? '')) === 'AFTER'
            && str_contains($body, '@activity_audit_token')
            && (bool) preg_match('/INSERT\\s+INTO\\s+`?activity_audit_impacts`?/i', $body)
            && str_contains($body, 'db_trigger_row');
    }
}
