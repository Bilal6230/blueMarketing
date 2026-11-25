<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModernAccountingDirectoryContractTest extends TestCase
{
    private function source(string $path): string
    {
        $result = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($result, $path);
        return $result;
    }

    public function test_three_account_directories_use_one_modern_client_renderer(): void
    {
        foreach ([
            ['head_accounts', 'head', 'Main Account'],
            ['child_accounts', 'subhead', 'Party Account'],
            ['category_index', 'joint', 'Joint Account'],
        ] as [$view, $kind, $name]) {
            $source = $this->source('resources/views/admin/finance/accounting/'.$view.'.blade.php');
            self::assertStringContainsString('bm-modern-accounts', $source);
            self::assertStringContainsString('data-account-kind="'.$kind.'"', $source);
            self::assertStringContainsString('bm-account-card', $source);
            self::assertStringContainsString('bm-cell-skeleton-row', $source);
            self::assertStringContainsString('id="bmAccountDirectory"', $source);
            self::assertStringContainsString('modern-account-directory.css', $source);
            self::assertStringContainsString('modern-account-directory.js', $source);
            self::assertStringNotContainsString('id="example1"', $source);
        }
    }

    public function test_directory_visual_contract_has_dark_thick_borders_stripes_and_full_height(): void
    {
        $css = $this->source('public/css/modern-account-directory.css');
        $js = $this->source('public/js/modern-account-directory.js');

        foreach ([
            'height:var(--bm-account-available-height,calc(100dvh - 225px))',
            'font-size:14px!important',
            'font-size:13px!important',
            'border-right:1.5px solid #aebfd3!important',
            'border-right:1.5px solid #becfe0!important',
            'border-top:1.5px solid #becfe0!important',
            'tbody tr:nth-child(even)',
            'tbody tr:nth-child(odd)',
            'bm-account-footer',
        ] as $needle) {
            self::assertStringContainsString($needle, $css);
        }

        foreach ([
            '$.financeVoucherComponents.createFilter',
            '$.blueVoucherPagination.render',
            '$.blueActionMenu.enhance',
            'bm-account-col',
            'bm-account-create-modal',
            'bm-cell-skeleton-row',
            'Filter status',
            'aria-expanded',
            'widget.dates(',
            'widget.search(',
            'reloadRows()',
            "headers:{Accept:'application/json'",
        ] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        self::assertStringNotContainsString('location.reload()', $js);
    }

    public function test_accounting_permissions_and_existing_account_forms_stay_intact(): void
    {
        foreach (['head_accounts', 'child_accounts'] as $view) {
            $source = $this->source('resources/views/admin/finance/accounting/'.$view.'.blade.php');
            self::assertStringContainsString("@can('create accounting')", $source);
            self::assertStringContainsString("@can('update accounting')", $source);
            self::assertStringContainsString("@can('delete accounting')", $source);
            self::assertStringNotContainsString("@can('create lead')", $source);
            self::assertStringNotContainsString("@can('delete lead')", $source);
            self::assertStringContainsString('status_change_reason', $source);
            self::assertStringContainsString('bm-account-finance-modal', $source);
            self::assertStringContainsString('<th>Status</th>', $source);
            self::assertStringContainsString('<th>Created</th>', $source);
            self::assertStringContainsString('bm-account-status', $source);
        }
        $head = $this->source('resources/views/admin/finance/accounting/head_accounts.blade.php');
        $party = $this->source('resources/views/admin/finance/accounting/child_accounts.blade.php');
        self::assertStringContainsString("route('accounting.head_store')", $head);
        self::assertStringContainsString("route('accounting.head_update')", $head);
        self::assertStringContainsString("route('accounting.subhead_store')", $party);
        self::assertStringContainsString("route('accounting.subhead_update')", $party);
    }

    public function test_joint_account_preserves_mapping_safety_and_permission_scoped_actions(): void
    {
        $joint = $this->source('resources/views/admin/finance/accounting/category_index.blade.php');
        $controller = $this->source('app/Http/Controllers/AccountingController.php');
        self::assertStringContainsString('Joint Accounts', $joint);
        self::assertStringContainsString("@can('create accounting')", $joint);
        self::assertStringContainsString("@can('update accounting')", $joint);
        self::assertStringContainsString("@can('delete accounting')", $joint);
        self::assertStringContainsString("route('accounting.category_store')", $joint);
        self::assertStringContainsString('bm:account-refresh', $joint);
        self::assertStringContainsString('AccountMappingHistoryGuard', $controller);
        self::assertStringContainsString('lockForUpdate()', $controller);
        self::assertStringContainsString('status_change_reason', $controller);
        self::assertStringContainsString('AccountDefinitionStatusAudit', $controller);
    }

    public function test_unimplemented_master_account_delete_fails_closed_and_ajax_writes_are_explicit(): void
    {
        $controller = $this->source('app/Http/Controllers/AccountingController.php');
        self::assertStringContainsString('Main account deletion is unavailable', $controller);
        self::assertStringContainsString('Party account deletion is unavailable', $controller);
        self::assertStringContainsString("abort(409, 'Main account deletion", $controller);
        self::assertStringContainsString("abort(409, 'Party account deletion", $controller);
        self::assertStringContainsString('if ($request->expectsJson())', $controller);
        self::assertStringContainsString('\'errors\' => $validator->errors()', $controller);
        self::assertStringContainsString('Joint account mapping saved successfully.', $controller);
    }

    public function test_three_previous_master_directories_have_darker_thicker_borders(): void
    {
        foreach ([
            'resources/views/admin/projects/index.blade.php',
            'resources/views/admin/zone/index.blade.php',
            'resources/views/admin/area/index.blade.php',
        ] as $path) {
            $view = $this->source($path);
            self::assertStringContainsString('border-right:1.5px solid #b6c7da!important', $view);
            self::assertStringContainsString('border-right:1.5px solid #c5d2e2!important', $view);
            self::assertStringContainsString('border-top:1.5px solid #c5d2e2!important', $view);
        }
    }
}
