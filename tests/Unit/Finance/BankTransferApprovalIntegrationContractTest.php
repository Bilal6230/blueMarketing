<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class BankTransferApprovalIntegrationContractTest extends TestCase
{
    private function code(string $path): string
    {
        $content=file_get_contents(base_path($path));
        self::assertIsString($content);
        return $content;
    }

    public function test_bd_bw_use_project_series_numbers_not_global_batch_identity(): void
    {
        $writer=$this->code('app/Services/Finance/BankTransferWriter.php');
        self::assertStringContainsString("'BD' : 'BW'",$writer);
        self::assertStringContainsString('ProjectVoucherAllocator())->next($projectId,$series,$legacyMax)',$writer);
        self::assertStringContainsString("'voucher_number' => $voucherNumber",$writer);
        self::assertStringNotContainsString("'voucher_number' => null",$writer);
        self::assertStringContainsString('COALESCE(voucher_number,id)',$writer);
        $history=$this->code('app/Http/Controllers/Finance/VoucherController.php');
        self::assertStringContainsString("($r->voucher_number ?: $r->posting_batch_id)",$history);
    }

    public function test_approval_submission_has_no_posting_and_is_project_scoped(): void
    {
        $source=$this->code('app/Services/Finance/BankTransferApprovalDraftWriter.php');
        self::assertStringContainsString("'finance_bank_transfer_approval_drafts'",$source);
        self::assertStringContainsString('submitIfRequired(',$source);
        self::assertStringContainsString('where(\'project_id\',$projectId)',$source);
        self::assertStringNotContainsString('finance_posting_lines',$source);
        self::assertStringNotContainsString('BankTransferWriter())->post(',$source);
        self::assertStringContainsString('BANK_APPROVAL_REQUEST_KEY_CONFLICT',$source);
        $migration=$this->code('database/migrations/2026_11_21_120000_create_finance_bank_transfer_approval_drafts_table.php');
        self::assertStringContainsString("Schema::create('finance_bank_transfer_approval_drafts'",$migration);
        self::assertStringContainsString("unique(['project_id','request_key']",$migration);
    }

    public function test_approval_and_rejection_only_post_on_approval(): void
    {
        $workflow=$this->code('app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        self::assertStringContainsString("['SV','BD','BW']",$workflow);
        self::assertStringContainsString("'finance_bank_transfer_approval_drafts'",$workflow);
        self::assertStringContainsString('if($active){',$workflow);
        self::assertStringContainsString('$result=(new BankTransferWriter())->post(',$workflow);
        self::assertStringContainsString("'status'=>'rejected'",$workflow);
        self::assertStringContainsString("'status'=>'approved'",$workflow);
        $writer=$this->code('app/Services/Finance/BankTransferWriter.php');
        self::assertStringContainsString('BANK_APPROVAL_DRAFT_PAYLOAD_MISMATCH',$writer);
        self::assertStringContainsString('BANK_APPROVAL_DRAFT_NUMBER_ALREADY_USED',$writer);
        $controller=$this->code('app/Http/Controllers/Finance/VoucherController.php');
        self::assertStringContainsString('BankTransferApprovalDraftWriter::class',$controller);
        self::assertStringContainsString("'message'=>",$controller);
    }

    public function test_bd_bw_available_in_settings_dashboard_and_history(): void
    {
        $settings=$this->code('app/Http/Controllers/Finance/VoucherSettingsController.php');
        self::assertStringContainsString("'BD' => ['label'=>'Bank Deposit Voucher', 'supported'=>true]",$settings);
        self::assertStringContainsString("'BW' => ['label'=>'Bank Withdrawal Voucher', 'supported'=>true]",$settings);
        $dashboard=$this->code('app/Http/Controllers/Finance/DailyVoucherApprovalController.php');
        self::assertStringContainsString("'SV', 'BD', 'BW'",$dashboard);
        self::assertStringContainsString('in:CR,CP,CCR,CDR,BOR,BOP,JV,SV,BD,BW',$dashboard);
        $reader=$this->code('app/Services/Finance/VoucherApprovalDetailReader.php');
        self::assertStringContainsString("'finance_bank_transfer_approval_drafts'",$reader);
        $view=$this->code('resources/views/admin/finance/voucher/bank_transfer_workspace.blade.php');
        self::assertStringContainsString('isDraft',$view);
        self::assertStringContainsString('Awaiting approval',$view);
    }
}
