<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class RemainingVoucherApprovalIntegrationsContractTest extends TestCase
{
    private function source(string $path): string
    {
        $content=file_get_contents(base_path($path));
        self::assertIsString($content);
        return $content;
    }

    public function test_all_four_policy_cards_are_enabled_and_supported_by_dashboard(): void
    {
        $settings=$this->source('app/Http/Controllers/Finance/VoucherSettingsController.php');
        foreach(['DR','CV','PPR','PP'] as $type){
            self::assertMatchesRegularExpression(
                "/'".$type."' => \\['label'=>'[^']+', 'supported'=>true\\]/",$settings);
        }
        $controller=$this->source('app/Http/Controllers/Finance/DailyVoucherApprovalController.php');
        self::assertStringContainsString("'DR', 'CV', 'PPR', 'PP'",$controller);
        self::assertStringContainsString('in:CR,CP,CCR,CDR,BOR,BOP,JV,SV,BD,BW,DR,CV,PPR,PP',$controller);
        $workflow=$this->source('app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        self::assertStringContainsString("['SV','BD','BW','DR','CV','PPR','PP']",$workflow);
    }

    public function test_dr_keeps_cp_source_but_uses_dr_approval_policy(): void
    {
        $controller=$this->source('app/Http/Controllers/LedgerController.php');
        self::assertStringContainsString("'DR', 'BOP' => 'CP'",$controller);
        self::assertStringContainsString("in_array(\$displaySeries, ['BOR','BOP','DR'], true)",$controller);
        self::assertStringContainsString('submitIfRequired((int) $selectedProjectId, $approvalPolicyType',$controller);
    }

    public function test_commission_drafts_queue_without_posting_and_manual_approve_cannot_bypass(): void
    {
        $workflow=$this->source('app/Services/Finance/CommissionDraftWorkflow.php');
        $draft=substr($workflow,strpos($workflow,'public function createDraft('),
            strpos($workflow,'public function updateDraft(')-strpos($workflow,'public function createDraft('));
        self::assertStringContainsString("submitIfRequired(\n                    \$projectId,'CV','commision_vouchers'",$draft);
        self::assertStringContainsString('CommissionLedgerSynchronizer::class',$draft);
        self::assertStringNotContainsString('approveAndPost(',$draft);
        $decide=$this->source('app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        self::assertStringContainsString("source_table==='commision_vouchers'",$decide);
        self::assertStringContainsString("app(CommissionDraftWorkflow::class)->approveAndPost(",$decide);
        $controller=$this->source('app/Http/Controllers/CommissionVoucherController.php');
        self::assertStringContainsString('This Commission Voucher belongs to the centralized approval queue.',$controller);
    }

    public function test_plot_drafts_are_deferred_with_one_shared_table_and_approval_custody(): void
    {
        $migration=$this->source('database/migrations/2026_11_21_130000_create_finance_plot_approval_drafts_table.php');
        self::assertStringContainsString("Schema::create('finance_plot_approval_drafts'",$migration);
        self::assertStringContainsString("unique(['project_id','request_key']",$migration);
        $draft=$this->source('app/Services/Finance/PlotVoucherApprovalDraftWriter.php');
        $submit=substr($draft,strpos($draft,'public function submit('),
            strpos($draft,'public function finalizeApproved(')-strpos($draft,'public function submit('));
        self::assertStringContainsString('submitIfRequired(',$submit);
        self::assertStringContainsString('PLOT_APPROVAL_DRAFT_NUMBER_EXISTS',$submit);
        self::assertStringNotContainsString('->receive(',$submit);
        self::assertStringNotContainsString('->purchase(',$submit);
        self::assertStringNotContainsString('finance_posting_lines',$submit);
        self::assertStringContainsString('finalizeApproved(',$draft);
        self::assertStringContainsString('->receive(',$draft);
        self::assertStringContainsString('->purchase(',$draft);
        self::assertStringContainsString("where('status','pending')",$draft);
        $approval=$this->source('app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        self::assertStringContainsString("source_table==='finance_plot_approval_drafts'",$approval);
        self::assertStringContainsString('$writer->finalizeApproved(',$approval);
        self::assertStringContainsString('$writer->reject(',$approval);
    }

    public function test_plot_endpoints_and_pending_rows_refuse_premature_actions(): void
    {
        $controller=$this->source('app/Http/Controllers/Finance/VoucherController.php');
        self::assertStringContainsString("'PPR'",$controller);
        self::assertStringContainsString("'PP'",$controller);
        self::assertStringContainsString('assertPlotApprovalNumberNotReserved(',$controller);
        self::assertStringContainsString('PlotVoucherApprovalDraftWriter::class',$controller);
        $receipt=$this->source('resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php');
        $purchase=$this->source('resources/views/admin/finance/voucher/plot_purchase_workspace.blade.php');
        self::assertStringContainsString('if(r.approval_draft_id){',$receipt);
        self::assertStringContainsString('if(x.approval_draft_id){',$purchase);
        self::assertStringContainsString('Awaiting approval',$receipt);
        self::assertStringContainsString('Awaiting approval',$purchase);
        $reader=$this->source('app/Services/Finance/VoucherApprovalDetailReader.php');
        self::assertStringContainsString("source_table==='finance_plot_approval_drafts'",$reader);
        self::assertStringContainsString("source_table==='commision_vouchers'",$reader);
        $permissions=$this->source('app/Support/Finance/FinanceActionPermission.php');
        self::assertStringContainsString("voucher_type === 'PPR' ? 'plot-receipt' : 'plot-purchase'",$permissions);
    }
}
