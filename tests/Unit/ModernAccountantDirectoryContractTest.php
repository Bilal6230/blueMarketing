<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModernAccountantDirectoryContractTest extends TestCase
{
    private function source(string $path): string
    {
        $text = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($text, $path);

        return $text;
    }

    public function test_accountants_use_shared_card_scroll_headers_and_pagination(): void
    {
        $view = $this->source('resources/views/admin/finance/accountant/index.blade.php');
        $css = $this->source('public/css/modern-account-directory.css');
        $js = $this->source('public/js/modern-account-directory.js');

        foreach ([
            'bm-modern-accounts bm-accountants',
            'data-account-kind="accountant"',
            'bm-account-card',
            'bm-account-table-wrap',
            'bm-account-table',
            'id="bmAccountDirectory"',
            'bm-account-loading',
            'modern-account-directory.js',
            'viewLedgersBtn',
        ] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        self::assertStringNotContainsString('$("#accountantTable").DataTable(', $view);
        self::assertStringContainsString('body.bm-account-directory-page', $css);
        self::assertStringContainsString('.bm-modern-accounts .bm-account-table thead th{position:sticky', $css);
        self::assertStringContainsString('$.blueVoucherPagination', $js);
        self::assertStringContainsString('$.financeVoucherComponents.createFilter', $js);
        self::assertStringContainsString("if(kind==='head'||kind==='subhead')", $js);
    }

    public function test_ledger_modal_data_loading_and_financial_totals_are_preserved(): void
    {
        $view = $this->source('resources/views/admin/finance/accountant/index.blade.php');
        foreach ([
            'id="ledgerModal"',
            'id="ledgerSummary"',
            'id="ledgerTable"',
            'id="ledgerUserName"',
            "route('accounting.Accountant.ledgers', ':id')",
            'getSelectedTown()',
            'function ensureLedgerDataTable()',
            'dt.rows.add(dtRows)',
            "dt.page(0).draw(false)",
            "sumIn - sumOut",
            "'#ledgerModal'",
            'viewLedgersBtn',
        ] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
    }
}
