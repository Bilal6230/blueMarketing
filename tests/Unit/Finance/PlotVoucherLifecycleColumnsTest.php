<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class PlotVoucherLifecycleColumnsTest extends TestCase
{
    public function test_lifecycle_migration_is_safe_and_retains_records(): void
    {
        $root=dirname(__DIR__,3);
        $migration=file_get_contents($root.'/database/migrations/2026_11_18_160000_add_plot_voucher_lifecycle_columns.php');
        self::assertIsString($migration);
        self::assertStringContainsString("'finance_plot_receipts','finance_plot_purchases'",$migration);
        self::assertStringContainsString("hasColumn(\$table,'is_active')",$migration);
        self::assertStringContainsString("hasColumn(\$table,'deleted_at')",$migration);
        self::assertStringContainsString("->boolean('is_active')->default(true)",$migration);
        self::assertStringContainsString('->softDeletes()',$migration);
        self::assertStringNotContainsString('dropColumn',$migration);
        self::assertStringNotContainsString('truncate(', $migration);
        self::assertStringNotContainsString('->delete(', $migration);
    }

    public function test_histories_and_details_do_not_read_archived_plot_roots(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherController.php');
        $writer=file_get_contents($root.'/app/Services/Finance/PlotEntryWriter.php');
        foreach (['finance_plot_receipts','finance_plot_purchases'] as $table) {
            self::assertStringContainsString("Schema::hasColumn('{$table}','is_active')",$controller);
            self::assertStringContainsString("Schema::hasColumn('{$table}','deleted_at')",$controller);
            self::assertStringContainsString("Schema::hasColumn('{$table}','is_active')",$writer);
            self::assertStringContainsString("Schema::hasColumn('{$table}','deleted_at')",$writer);
        }
        self::assertStringContainsString("->whereNull('receipt.deleted_at')",$controller);
        self::assertStringContainsString("->whereNull('purchase.deleted_at')",$controller);
    }
}
