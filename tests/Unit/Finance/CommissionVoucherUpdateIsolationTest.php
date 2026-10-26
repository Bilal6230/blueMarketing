<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * RED source-contract guard for Commission Voucher updates.
 *
 * Deliberately narrow: this detects the known JV/CV type confusion without
 * running financial writes. It is NOT a substitute for a database-backed
 * regression test with colliding JV and CV identifiers.
 */
#[Group('finance-safety-red')]
class CommissionVoucherUpdateIsolationTest extends TestCase
{
    private function updateMethodSource(): string
    {
        $source = file_get_contents(__DIR__ . '/../../../app/Http/Controllers/CommissionVoucherController.php');
        self::assertNotFalse($source);

        $start = strpos($source, 'public function update(');
        $end = strpos($source, 'public function print(', $start === false ? 0 : $start);

        self::assertNotFalse($start, 'Commission Voucher update action missing');
        self::assertNotFalse($end, 'Cannot isolate Commission Voucher update action');

        return substr($source, $start, $end - $start);
    }

    public function test_commission_creation_does_not_post_ledger_rows_as_journal_vouchers(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../app/Http/Controllers/CommissionVoucherController.php');
        self::assertNotFalse($source);

        $start = strpos($source, 'public function store(');
        $end = strpos($source, 'public function destroy(', $start === false ? 0 : $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $method = substr($source, $start, $end - $start);

        self::assertDoesNotMatchRegularExpression(
            '/[\\x27\\x22]type[\\x27\\x22]\\s*=>[^;]*[\\x27\\x22]JV[\\x27\\x22]/s',
            $method,
            'CV creation must not label commission posting as a Journal Voucher.'
        );
    }

    public function test_commission_update_links_new_ledgers_to_voucher_not_detail_ids(): void
    {
        $method = $this->updateMethodSource();

        self::assertDoesNotMatchRegularExpression(
            '/[\\x27\\x22]type_id[\\x27\\x22]\\s*=>\\s*\\$JvDetails->id/',
            $method,
            'Existing CV posting convention links ledger type_id to voucher id, not detail row id.'
        );
    }

    public function test_commission_update_does_not_select_journal_ledger_rows_for_deletion(): void
    {
        $method = $this->updateMethodSource();

        self::assertDoesNotMatchRegularExpression(
            '/Ledger::where\(\s*[\x27\x22]type_id[\x27\x22]\s*,\s*\$id\s*\)->whereIn\(\s*[\x27\x22]type[\x27\x22]\s*,\s*\[[^\]]*[\x27\x22]JV[\x27\x22]/s',
            $method,
            'CV update must never delete JV-type rows based on a colliding voucher ID.'
        );
    }

    public function test_commission_update_does_not_create_journal_ledger_rows(): void
    {
        $method = $this->updateMethodSource();

        self::assertDoesNotMatchRegularExpression(
            '/[\x27\x22]type[\x27\x22]\s*=>[^;]*[\x27\x22]JV[\x27\x22]/s',
            $method,
            'CV update must not create JV-type ledger rows; use the approved CV posting identity.'
        );
    }
}
