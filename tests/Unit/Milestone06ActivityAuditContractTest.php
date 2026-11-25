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
        $this->assertStringContainsString("Route::post('postings', [\u0024controller, 'post'])", $routes);
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
