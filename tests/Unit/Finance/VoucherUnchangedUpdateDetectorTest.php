<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\VoucherUnchangedUpdateDetector;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class VoucherUnchangedUpdateDetectorTest extends TestCase
{
    private function header(): object
    {
        return (object)['date'=>'2026-11-18','reference'=>'REF-1',
            'description'=>'Existing narration'];
    }

    private function details(): Collection
    {
        return collect([
            (object)['id'=>12,'account_id'=>102,'debit'=>'0.00',
                'credit'=>'100.00','description'=>'Credit leg'],
            (object)['id'=>11,'account_id'=>101,'debit'=>'100.00',
                'credit'=>'0.00','description'=>'Debit leg'],
        ]);
    }

    private function submitted(): array
    {
        return [
            ['account_id'=>101,'debit'=>'100','credit'=>'0.00','description'=>'Debit leg'],
            ['account_id'=>102,'debit'=>'0','credit'=>'100','description'=>'Credit leg'],
        ];
    }

    public function test_unchanged_repeated_save_skips_new_history_with_stable_detail_identity(): void
    {
        $service=new VoucherUnchangedUpdateDetector();
        self::assertTrue($service->same($this->header(),$this->details(),[
            'date'=>'2026-11-18','reference'=>'REF-1',
            'description'=>'Existing narration',
        ],$this->submitted()));
    }

    public function test_material_line_or_header_change_must_rewrite_voucher(): void
    {
        $service=new VoucherUnchangedUpdateDetector();
        $header=['date'=>'2026-11-18','reference'=>'REF-1',
            'description'=>'Existing narration'];
        $changed=$this->submitted();
        $changed[1]['credit']='99.00';
        self::assertFalse($service->same($this->header(),$this->details(),$header,$changed));
        $changed=$this->submitted();
        $changed[0]['account_id']=103;
        self::assertFalse($service->same($this->header(),$this->details(),$header,$changed));
        self::assertFalse($service->same($this->header(),$this->details(),
            $header+[],array_reverse($this->submitted())));
        $header['description']='Changed';
        self::assertFalse($service->same($this->header(),$this->details(),$header,$this->submitted()));
    }

    public function test_both_legacy_controllers_use_detector_inside_source_transaction(): void
    {
        $root=dirname(__DIR__,3);
        foreach (['JournalVoucherController.php','CommissionVoucherController.php'] as $file) {
            $code=file_get_contents($root.'/app/Http/Controllers/'.$file);
            self::assertIsString($code);
            self::assertStringContainsString('VoucherUnchangedUpdateDetector::class',$code);
            self::assertStringContainsString('->same($voucher,$oldDetails,$data,$lines)',$code);
        }
    }
}
