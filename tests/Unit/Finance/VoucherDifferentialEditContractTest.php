<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class VoucherDifferentialEditContractTest extends TestCase
{
    public function test_jv_and_cv_edits_submit_source_detail_ids_including_blank_ids_for_new_rows(): void
    {
        $root=dirname(__DIR__,3);
        foreach ([
            'edit_journal_voucher.blade.php',
            'edit_commision_voucher.blade.php',
        ] as $view) {
            $code=file_get_contents($root.'/resources/views/admin/reports/vouchers/'.$view);
            self::assertIsString($code);
            self::assertStringContainsString('name="detail_ids[]" value="{{ $detail->id }}"',$code);
            self::assertStringContainsString('name="detail_ids[]" value=""',$code);
        }
        $controllers=['JournalVoucherController.php','CommissionVoucherController.php'];
        foreach ($controllers as $file) {
            $code=file_get_contents($root.'/app/Http/Controllers/'.$file);
            self::assertIsString($code);
            $start=strpos($code,'public function update(Request $request');
            self::assertNotFalse($start);
            $update=substr($code,$start);
            self::assertStringContainsString("'detail_ids' => ['required'",$update);
            self::assertStringContainsString("'detail_id' => \$data['detail_ids'][\$index] ?? null",$update);
        }
    }

    public function test_commission_edit_uses_ajax_parent_refresh_not_native_form_submit(): void
    {
        $root=dirname(__DIR__,3);
        $code=file_get_contents($root.'/resources/views/admin/reports/vouchers/edit_commision_voucher.blade.php');
        self::assertStringContainsString("type:'POST'",$code);
        self::assertStringContainsString('finance:commission-saved',$code);
        self::assertStringNotContainsString("$('#journalForm')[0].submit()",$code);
        self::assertStringContainsString('cvUpdateBusy',$code);
        self::assertStringContainsString("table.ajax.reload(null,false)",$code);
        self::assertStringContainsString("parentJQ(this).modal('hide')",$code);
    }

    public function test_journal_legacy_edit_checks_detail_ledger_foreign_key_and_updates_only_changed_lines(): void
    {
        $root=dirname(__DIR__,3);
        $code=file_get_contents($root.'/app/Http/Controllers/JournalVoucherController.php');
        $start=strpos($code,'public function update(Request $request');
        $end=strpos($code,'private function voucherWriteResponse(',$start);
        self::assertNotFalse($start);self::assertNotFalse($end);
        $method=substr($code,$start,$end-$start);
        self::assertStringContainsString("groupBy('journal_voucher_detail_id')",$method);
        self::assertStringContainsString('Journal detail-to-ledger FK or amount mismatch',$method);
        self::assertStringContainsString('if($prior&&!$headerChanged',$method);
        self::assertStringContainsString("->where('is_active',1)",$method);
        self::assertStringContainsString('$prior->delete();',$method);
        self::assertStringNotContainsString('saveQuietly()',$method);
    }
}
