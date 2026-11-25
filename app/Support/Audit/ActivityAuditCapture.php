<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Request-scoped capture. SQL statements indicate only possible table impact:
 * queries cannot prove which rows changed, especially after transaction rollback.
 * Eloquent events supply model identities and allowlisted field-level values,
 * but unlike DB-trigger snapshots they do not independently prove commit.
 */
final class ActivityAuditCapture
{
    private bool $active = true;
    private array $rows = [];
    private array $statements = [];

    /** Only known non-secret values can leave the originating business record. */
    private const VISIBLE = [
        'id', 'project_id', 'booking_id', 'plot_id', 'customer_id',
        'journal_voucher_id', 'journal_voucher_detail_id', 'customer_ledger_id',
        'account_id', 'project_head_subheads_id', 'type_id', 'role_id',
        'voucher_number', 'type', 'status', 'is_active', 'is_approve',
        'date', 'booking_date', 'due_date', 'passing_date',
        'total_debit', 'total_credit', 'debit', 'credit', 'amount_in',
        'amount_out', 'total_price', 'plot_rate', 'dicount_value',
        'is_corner', 'is_park', 'park_facing', 'carner_price',
        'assigned_to', 'priority', 'start_date', 'completed_at',
        'name', 'title',
    ];

    private const MAX_ROWS = 150;
    private const MAX_TABLES = 75;

    public function start(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (!$this->active) {
                return;
            }
            $sql = ltrim($query->sql);
            if (!preg_match('/^(?:INSERT(?:\\s+IGNORE)?\\s+INTO|REPLACE\\s+INTO|UPDATE|DELETE\\s+FROM)\\s+[`"\\[]?([a-zA-Z_][a-zA-Z_0-9]*)/i', $sql, $match)) {
                return;
            }
            $verb = strtoupper(strtok($sql, ' '));
            $operation = in_array($verb, ['INSERT', 'REPLACE'], true) ? 'INSERT' : $verb;
            $table = strtolower($match[1]);
            if (str_starts_with($table, 'activity_audit') || in_array($table, ['sessions', 'cache', 'cache_locks'], true)) {
                return;
            }
            $key = $table.':'.$operation;
            if (!isset($this->statements[$key]) && count($this->statements) >= self::MAX_TABLES) {
                return;
            }
            $this->statements[$key] = [
                'table_name' => $table,
                'operation' => $operation,
                'statement_count' => ($this->statements[$key]['statement_count'] ?? 0) + 1,
            ];
        });

        foreach (['created' => 'INSERT', 'updated' => 'UPDATE', 'deleted' => 'DELETE'] as $event => $operation) {
            Event::listen('eloquent.'.$event.': *', function ($name, array $data) use ($operation): void {
                $model = $data[0] ?? null;
                if (!$this->active || !$model instanceof Model || count($this->rows) >= self::MAX_ROWS) {
                    return;
                }
                $table = $model->getTable();
                if (str_starts_with($table, 'activity_audit') || $table === 'sessions') {
                    return;
                }
                // Never record password, API tokens, free-text narration,
                // phone, email, ID documents, bank instruments or attachments.
                $before = $operation === 'INSERT' ? [] : $model->getRawOriginal();
                $after = $operation === 'DELETE' ? [] : $model->getAttributes();
                $fields = $operation === 'UPDATE' ? array_keys($model->getChanges()) :
                    array_unique(array_merge(array_keys($before), array_keys($after)));
                $fields = array_values(array_intersect($fields, self::VISIBLE));
                $left = [];
                $right = [];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $before)) $left[$field] = $before[$field];
                    if (array_key_exists($field, $after)) $right[$field] = $after[$field];
                }
                $this->rows[] = [
                    'table_name' => substr($table, 0, 100),
                    'operation' => $operation,
                    'record_id' => substr((string) $model->getKey(), 0, 100),
                    'capture_type' => 'eloquent_row',
                    'before_values' => $left === [] ? null : json_encode($left),
                    'after_values' => $right === [] ? null : json_encode($right),
                ];
            });
        }
    }

    public function stop(): void
    {
        $this->active = false;
    }

    /**
     * A write-style HTTP method is not proof that a business mutation happened.
     * Many legacy account lookup endpoints are POST requests with no writes.
     */
    public function hasObservedWrites(): bool
    {
        return $this->rows !== [] || $this->statements !== [];
    }

    public function impacts(): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            $out[] = $row + ['statement_count' => 1];
        }
        foreach ($this->statements as $row) {
            $out[] = $row + [
                'record_id' => null,
                'capture_type' => 'sql_statement',
                'before_values' => null,
                'after_values' => null,
            ];
        }
        return $out;
    }
}
