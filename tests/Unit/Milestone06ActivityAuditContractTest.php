<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class Milestone06ActivityAuditContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_admin_routes_require_read_audit_permission_for_both_listing_and_details(): void
    {
        $routes = $this->source('routes/web.php');
        $this->assertStringContainsString('RecordActivityAudit::class', $routes);
        $this->assertStringContainsString("Route::get('activity-audit'", $routes);
        $this->assertStringContainsString("Route::get('activity-audit/{audit}'", $routes);
        $this->assertSame(2, substr_count($routes, "permission:read activity audit"));
    }

    public function test_recorder_never_captures_request_bodies_or_query_strings(): void
    {
        $middleware = $this->source('app/Http/Middleware/RecordActivityAudit.php');
        $capture = $this->source('app/Support/Audit/ActivityAuditCapture.php');
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            $this->assertStringContainsString("'".$verb."'", $middleware);
        }
        $this->assertStringContainsString("DB::table('activity_audits')->insertGetId", $middleware);
        $this->assertStringContainsString("DB::table('activity_audit_impacts')->insert", $middleware);
        $this->assertStringContainsString('$request->path()', $middleware);
        $this->assertStringNotContainsString('$request->all()', $middleware);
        $this->assertStringNotContainsString('$request->input()', $middleware);
        $this->assertStringNotContainsString('$request->fullUrl()', $middleware);
        $this->assertStringNotContainsString('$query->bindings', $capture);
        $this->assertStringNotContainsString('$query->sql,', $capture);
        $this->assertStringContainsString('Log::error', $middleware);
    }

    public function test_migrations_keep_audit_evidence_and_index_lookup_paths(): void
    {
        $migration = $this->source('database/migrations/2026_11_25_130000_create_activity_audits.php');
        $detail = $this->source('database/migrations/2026_11_25_131000_add_activity_audit_impacts.php');
        $this->assertStringContainsString("Schema::create('activity_audits'", $migration);
        $this->assertStringContainsString("Permission::findOrCreate('read activity audit', 'web')", $migration);
        $this->assertStringContainsString("Schema::create('activity_audit_impacts'", $detail);
        $this->assertStringContainsString("['activity_audit_id', 'table_name']", $detail);
        $this->assertStringContainsString("['table_name', 'record_id']", $detail);
        $this->assertStringNotContainsString('Schema::dropIfExists', $migration.$detail);
    }

    public function test_model_row_differences_are_separate_from_sql_table_observations(): void
    {
        $capture = $this->source('app/Support/Audit/ActivityAuditCapture.php');
        $this->assertStringContainsString("'eloquent_row'", $capture);
        $this->assertStringContainsString("'sql_statement'", $capture);
        $this->assertStringContainsString('getRawOriginal()', $capture);
        $this->assertStringContainsString('getChanges()', $capture);
        $this->assertStringContainsString('self::VISIBLE', $capture);
        $this->assertStringNotContainsString('getBindings()', $capture);
    }

    public function test_mysql_jv_trigger_records_before_and_after_values_with_request_token(): void
    {
        $source = $this->source('database/migrations/2026_11_25_132000_install_activity_audit_jv_triggers.php');
        $middleware = $this->source('app/Http/Middleware/RecordActivityAudit.php');
        $this->assertStringContainsString("'journal_vouchers'", $source);
        $this->assertStringContainsString("'journal_voucher_details'", $source);
        $this->assertStringContainsString("'ledgers'", $source);
        $this->assertStringContainsString("'customer_ledger'", $source);
        $this->assertStringContainsString("'finance_posting_batches'", $source);
        $this->assertStringContainsString("'finance_posting_lines'", $source);
        $this->assertStringContainsString("'finance_posting_journal_sources'", $source);
        $this->assertStringContainsString("['INSERT' => 'ai', 'UPDATE' => 'au', 'DELETE' => 'ad']", $source);
        $this->assertStringContainsString('JSON_OBJECT(', $source);
        $this->assertStringContainsString('@activity_audit_token', $source);
        $this->assertStringContainsString("'db_trigger_row'", $source);
        $this->assertStringContainsString("SET @activity_audit_token = ?", $middleware);
        $this->assertStringContainsString("SET @activity_audit_token = NULL", $middleware);
        $this->assertStringContainsString("where('request_token', ", $middleware);
        $this->assertStringContainsString("->whereNull('activity_audit_id')", $middleware);
    }

    public function test_read_only_ajax_post_is_not_logged_as_mutation_without_write_evidence(): void
    {
        $source = $this->source('app/Http/Middleware/RecordActivityAudit.php');
        $capture = $this->source('app/Support/Audit/ActivityAuditCapture.php');
        $this->assertStringContainsString('hasObservedWrites()', $source);
        $this->assertStringContainsString('if (!$hasCapturedRows && (!$capture || !$capture->hasObservedWrites()))', $source);
        $this->assertStringContainsString("where('request_token',", $source);
        $this->assertStringContainsString('public function hasObservedWrites(): bool', $capture);
        $this->assertStringContainsString("'cache_locks'", $capture);
    }

    public function test_module_row_triggers_cover_high_value_modules_without_sensitive_fields(): void
    {
        $migration = $this->source('database/migrations/2026_11_25_133000_expand_activity_audit_row_triggers.php');
        foreach (['users', 'roles', 'leads', 'bookings', 'booking_details',
                  'employee_tasks', 'daily_voucher_approvals'] as $table) {
            $this->assertStringContainsString("'".$table."'", $migration);
        }
        $this->assertStringContainsString("['INSERT'=>'ai', 'UPDATE'=>'au', 'DELETE'=>'ad']", $migration);
        $this->assertStringContainsString('@activity_audit_token', $migration);
        $this->assertStringContainsString("'db_trigger_row'", $migration);
        foreach (["'password'", "'remember_token'", "'email'", "'phone_number'",
                  "'t_number'", "'description'", "'check_history'"] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $migration);
        }
    }

    public function test_mobile_and_finance_api_mutations_are_audited_after_sanctum_authentication(): void
    {
        $routes = $this->source('routes/api.php');
        $this->assertStringContainsString(
            "Route::middleware(['auth:sanctum', \\App\\Http\\Middleware\\RecordActivityAudit::class])->group",
            $routes
        );
        $this->assertStringContainsString(
            "Route::middleware(['auth:sanctum', 'throttle:30,1', \\App\\Http\\Middleware\\RecordActivityAudit::class])",
            $routes
        );
        $this->assertStringContainsString("Route::post('leads', [MobileCrmController::class, 'store'])", $routes);
        $this->assertStringContainsString("Route::post('entries/in', [MobileStockController::class, 'storeIn'])", $routes);
        $this->assertStringContainsString('Route::post(\'postings\', [$controller, \'post\'])', $routes);
        $this->assertStringNotContainsString(
            "Route::middleware([\\App\\Http\\Middleware\\RecordActivityAudit::class, 'auth:sanctum'])",
            $routes
        );
    }

    public function test_stock_plot_bank_and_labour_row_audit_uses_atomic_allowlisted_triggers(): void
    {
        $source = $this->source('database/migrations/2026_11_25_134000_expand_activity_audit_stock_plot_bank_sources.php');
        foreach ([
            'stock_entries', 'stock_entry_lines', 'stock_bills', 'stock_bill_lines',
            'stock_movements', 'finance_plot_receipts', 'finance_plot_purchases',
            'finance_bank_transfer_sources', 'finance_bank_voucher_allocations',
            'labour_ledgers',
        ] as $table) {
            $this->assertStringContainsString("'".$table."'", $source);
        }
        $this->assertStringContainsString("'db_trigger_row'", $source);
        $this->assertStringContainsString('@activity_audit_token', $source);
        $this->assertStringContainsString('JSON_OBJECT(', $source);
        $this->assertStringContainsString("!in_array('id',", $source);
        foreach (["'password'", "'email'", "'phone_number'", "'t_number'",
            "'description'", "'check_history'", "'reference'"] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $source);
        }
        $this->assertStringNotContainsString('Schema::dropIfExists', $source);
    }

    public function test_activity_health_command_is_read_only_and_checks_db_trigger_integrity(): void
    {
        $source = $this->source('app/Console/Commands/ActivityAuditHealth.php');
        $this->assertStringContainsString('activity-audit:health', $source);
        $this->assertStringContainsString('activity_audit_impacts', $source);
        $this->assertStringContainsString('activity_audits', $source);
        $this->assertStringContainsString('information_schema.TRIGGERS', $source);
        $this->assertStringContainsString('whereNull(\'activity_audit_id\')', $source);
        $this->assertStringContainsString('whereNotExists', $source);
        $this->assertStringContainsString('bm_audit_', $source);
        $this->assertStringContainsString('--strict', $source);
        $this->assertStringNotContainsString('->delete(', $source);
        $this->assertStringNotContainsString('->truncate(', $source);
        $this->assertStringNotContainsString('DB::statement(', $source);
        $this->assertStringNotContainsString('DB::unprepared(', $source);
    }

    public function test_composite_primary_key_auditing_never_invents_surrogate_ids(): void
    {
        $source = $this->source('database/migrations/2026_11_25_135000_audit_composite_primary_key_tables.php');
        $health = $this->source('app/Console/Commands/ActivityAuditHealth.php');

        foreach ([
            'finance_posting_source_claims',
            'finance_posting_journal_sources',
            'finance_journal_line_sources',
            'stock_item_balances',
            'stock_project_item_balances',
            'stock_project_item_definitions',
            'finance_plot_receipt_postings',
        ] as $table) {
            $this->assertStringContainsString("'".$table."'", $source);
            $this->assertStringContainsString("'".$table."'", $health);
        }

        $this->assertStringContainsString("where('CONSTRAINT_NAME', 'PRIMARY')", $source);
        $this->assertStringContainsString('array_diff($primary, self::SAFE_FIELDS)', $source);
        $this->assertStringContainsString("CONCAT('pk:', SHA2(", $source);
        $this->assertStringContainsString("'db_trigger_row'", $source);
        $this->assertStringContainsString('@activity_audit_token', $source);
        $this->assertStringContainsString('COMPOSITE PRIMARY KEY', $health);
        $this->assertStringContainsString('no safe declared primary key', $health);
        $this->assertStringNotContainsString('Schema::table(', $source);
        $this->assertStringNotContainsString('Schema::drop', $source);
    }

    public function test_transaction_boundary_capture_keeps_only_committed_request_observations(): void
    {
        $capture = $this->source('app/Support/Audit/ActivityAuditCapture.php');
        $integration = $this->source('tests/Feature/ActivityAuditTransactionBoundaryTest.php');
        $this->assertStringContainsString('TransactionBeginning::class', $capture);
        $this->assertStringContainsString('TransactionCommitted::class', $capture);
        $this->assertStringContainsString('TransactionRolledBack::class', $capture);
        $this->assertStringContainsString('array_pop($this->transactionSnapshots)', $capture);
        $this->assertStringContainsString("test_committed_query_builder_write_is_recorded", $integration);
        $this->assertStringContainsString("test_caught_transaction_rollback_does_not_create_false_audit", $integration);
        $this->assertStringContainsString("test_committed_write_before_a_later_rollback_is_preserved", $integration);
    }

    public function test_error_response_does_not_discard_actual_committed_impact(): void
    {
        $source = $this->source('app/Http/Middleware/RecordActivityAudit.php');
        $integration = $this->source('tests/Feature/ActivityAuditTransactionBoundaryTest.php');
        $this->assertStringNotContainsString('$response->getStatusCode() >= 400', $source);
        $this->assertStringContainsString('\'response_status\' => $response->getStatusCode()', $source);
        $this->assertStringContainsString('test_error_response_after_committed_write_retains_actual_status_and_impact', $integration);
        $this->assertStringContainsString('test_error_response_without_business_write_is_not_logged', $integration);
    }

    public function test_strict_health_validates_trigger_metadata_and_transactional_engines(): void
    {
        $health = $this->source('app/Console/Commands/ActivityAuditHealth.php');
        $validator = $this->source('app/Support/Audit/TriggerDefinitionValidator.php');
        $this->assertStringContainsString('information_schema.TABLES', $health);
        $this->assertStringContainsString('information_schema.TRIGGERS', $health);
        $this->assertStringContainsString('TriggerDefinitionValidator::valid(', $health);
        $this->assertStringContainsString('Invalid trigger definitions:', $health);
        $this->assertStringContainsString('Nontransactional/unknown engines:', $health);
        $this->assertStringContainsString("'InnoDB'", $health);
        $this->assertStringContainsString('EVENT_OBJECT_TABLE', $validator);
        $this->assertStringContainsString('EVENT_MANIPULATION', $validator);
        $this->assertStringContainsString('ACTION_TIMING', $validator);
        $this->assertStringContainsString('@activity_audit_token', $validator);
        $this->assertStringContainsString('activity_audit_impacts', $validator);
        $this->assertStringNotContainsString('DB::statement(', $health);
        $this->assertStringNotContainsString('DB::unprepared(', $health);
    }

    public function test_real_mysql_rollback_probe_uses_existing_local_tables_without_schema_changes(): void
    {
        $probe = $this->source('tests/Feature/ActivityAuditMysqlTriggerRollbackTest.php');
        $this->assertStringContainsString("'127.0.0.1', 'localhost', '::1'", $probe);
        $this->assertStringContainsString("base_path('.env')", $probe);
        $this->assertStringContainsString('Dotenv::parse(', $probe);
        $this->assertStringContainsString("config()->set('database.connections.'.self::CONNECTION", $probe);
        $this->assertStringContainsString("'url' => null", $probe);
        $this->assertStringContainsString("DB::purge(self::CONNECTION)", $probe);
        $this->assertStringContainsString("'bm_audit_users_au'", $probe);
        $this->assertStringContainsString('->update([\'status_id\' => $this->originalStatus])', $probe);
        $this->assertStringContainsString('test_mysql_existing_trigger_discards_audit_snapshot_after_rollback', $probe);
        $this->assertStringContainsString('test_mysql_existing_trigger_commits_audit_snapshot_then_cleans_probe', $probe);
        $this->assertStringContainsString('where(\'request_token\', $this->token)', $probe);
        $this->assertStringContainsString("SET @activity_audit_token = NULL", $probe);
        $this->assertStringNotContainsString('Schema::create(', $probe);
        $this->assertStringNotContainsString('dropIfExists(', $probe);
        $this->assertStringNotContainsString('CREATE TRIGGER', $probe);
        $this->assertStringNotContainsString('DROP TRIGGER', $probe);
    }

    public function test_modern_activity_history_has_column_filters_ajax_skeleton_and_view_modal(): void
    {
        $blade = $this->source('resources/views/admin/activity-audit/index.blade.php');
        $controller = $this->source('app/Http/Controllers/ActivityAuditController.php');
        $this->assertStringContainsString('<x-finance.voucher-column-filters />', $blade);
        $this->assertStringContainsString('$.blueVoucherColumnFilters.init', $blade);
        $this->assertStringContainsString('$.blueVoucherPagination.render', $blade);
        $this->assertStringContainsString('dv-excel-trigger', $blade);
        $this->assertStringContainsString('dv-page-length', $blade);
        $this->assertStringContainsString('dv-table-search', $blade);
        $this->assertStringContainsString('$.blueTableSkeleton.show', $blade);
        $this->assertStringContainsString('audit-detail-modal', $blade);
        $this->assertStringContainsString('audit-view', $blade);
        $this->assertStringContainsString('db_trigger_row', $blade);
        $this->assertStringContainsString('affected_tables', $blade);
        $this->assertStringContainsString('sql_statement', $blade);
        $this->assertStringContainsString('->offset($start)->limit($length)->get()', $controller);
        $this->assertStringContainsString("activity_audit_impacts", $controller);
        $this->assertStringContainsString("'recordsFiltered'", $controller);
        $this->assertStringNotContainsString('method="POST"', $blade);
        $this->assertStringNotContainsString('method="DELETE"', $blade);
    }
}
