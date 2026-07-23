<?php

namespace Tests\Feature;

use Tests\TestCase;

class LabourViewTest extends TestCase
{
    public function test_labour_view_uses_adminlte_content_wrapper_layout(): void
    {
        $html = file_get_contents(resource_path('views/admin/labours/index.blade.php'));

        $this->assertStringContainsString('content-wrapper labour-content-wrapper', $html);
        $this->assertStringContainsString('class="wrap labour-wrap"', $html);
        $this->assertStringNotContainsString('<div class="wrap">', $html);

        $wrapperPosition = strpos($html, 'content-wrapper labour-content-wrapper');
        $labourWrapPosition = strpos($html, 'class="wrap labour-wrap"');

        $this->assertNotFalse($wrapperPosition);
        $this->assertNotFalse($labourWrapPosition);
        $this->assertLessThan($labourWrapPosition, $wrapperPosition);
    }

    public function test_labour_history_view_uses_adminlte_content_wrapper_layout(): void
    {
        $html = file_get_contents(resource_path('views/admin/labours/history.blade.php'));

        $this->assertStringContainsString('labour-history-content-wrapper', $html);
        $this->assertStringContainsString('class="labour-history-wrap"', $html);
        $this->assertStringNotContainsString('<div class="wrap">', $html);

        $wrapperPosition = strpos($html, 'labour-history-content-wrapper');
        $historyWrapPosition = strpos($html, 'class="labour-history-wrap"');

        $this->assertNotFalse($wrapperPosition);
        $this->assertNotFalse($historyWrapPosition);
        $this->assertLessThan($historyWrapPosition, $wrapperPosition);
    }

    public function test_person_report_buttons_and_handlers_keep_print_separate_from_voucher_validation(): void
    {
        $personReportHtml = file_get_contents(resource_path('views/admin/labours/person-wise-report.blade.php'));
        $indexHtml = file_get_contents(resource_path('views/admin/labours/index.blade.php'));

        $this->assertStringContainsString('type="button" class="btn ghost" id="createLabourVoucher"', $personReportHtml);
        $this->assertStringContainsString('type="button" class="btn btn-sm btn-outline-primary" id="btnPersonExport"', $indexHtml);

        $createStart = strpos($indexHtml, "$(document).on('click', '#createLabourVoucher'");
        $exportStart = strpos($indexHtml, "$(document).on('click', '#btnPersonExport'");
        $loadWeekStart = strpos($indexHtml, 'function loadWeekData()');

        $this->assertNotFalse($createStart);
        $this->assertNotFalse($exportStart);
        $this->assertNotFalse($loadWeekStart);

        $createHandler = substr($indexHtml, $createStart, $exportStart - $createStart);
        $exportHandler = substr($indexHtml, $exportStart, $loadWeekStart - $exportStart);

        $this->assertStringContainsString('No Amount Entered', $createHandler);
        $this->assertStringContainsString('e.preventDefault();', $createHandler);
        $this->assertStringContainsString('e.stopPropagation();', $createHandler);

        $this->assertStringContainsString("$('#personWiseForm').submit();", $exportHandler);
        $this->assertStringContainsString('e.preventDefault();', $exportHandler);
        $this->assertStringContainsString('e.stopPropagation();', $exportHandler);
        $this->assertStringNotContainsString('No Amount Entered', $exportHandler);
    }
}
