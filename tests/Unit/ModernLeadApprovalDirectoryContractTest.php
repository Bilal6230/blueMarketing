<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModernLeadApprovalDirectoryContractTest extends TestCase
{
    private function source(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($contents, $path);
        return $contents;
    }

    public function test_lead_approvals_use_modern_card_fixed_controls_and_cell_only_skeleton(): void
    {
        $view = $this->source('resources/views/admin/pending-approve/lead_approvals_index.blade.php');
        $css = $this->source('public/css/modern-lead-approvals.css');
        $shared = $this->source('public/js/modern-account-directory.js');

        foreach ([
            'bm-modern-accounts bm-lead-approvals',
            'data-account-kind="lead-approvals"',
            'bm-account-card',
            'bm-modern-export-toolbar bm-export-placeholder',
            'bm-account-table-wrap',
            'id="bmAccountDirectory"',
            'bm-cell-skeleton-row',
            'bm-account-footer-placeholder',
            'modern-account-directory.js',
            'modern-lead-approvals.css',
        ] as $part) {
            self::assertStringContainsString($part, $view);
        }
        self::assertStringContainsString('thead th{position:sticky', $css);
        self::assertStringContainsString("kind==='lead-approvals'?'Lead Assignment Requests'", $shared);
        self::assertStringNotContainsString('window.location.reload()', $view);
        self::assertStringNotContainsString("$('#leadApprovalTable').DataTable(", $view);
    }

    public function test_approval_routes_and_details_payload_survive_ajax_refresh(): void
    {
        $view = $this->source('resources/views/admin/pending-approve/lead_approvals_index.blade.php');
        $shared = $this->source('public/js/modern-account-directory.js');
        $controller = $this->source('app/Http/Controllers/LeadController.php');

        foreach ([
            "route('approvals.leads.approve', \$update->id)",
            "route('approvals.leads.reject', \$update->id)",
            'btn-view-details',
            'lead-approval-payload-',
            'JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT',
            'showActionPrompt(',
            "dataType: 'json'",
            'bm:account-refresh',
            'leadApprovalModalBody',
        ] as $part) {
            self::assertStringContainsString($part, $view);
        }
        self::assertStringContainsString("kind==='lead-approvals'", $shared);
        self::assertStringContainsString("'Lead assignment request approved successfully.'", $controller);
        self::assertStringContainsString("'Lead assignment request rejected.'", $controller);
        self::assertStringContainsString('return $request->expectsJson()', $controller);
    }
}
