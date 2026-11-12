<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\VoucherAttachmentRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class VoucherAttachmentRulesTest extends TestCase
{
    private function valid($upload): bool
    {
        return Validator::make(['attachment'=>$upload],[
            'attachment'=>VoucherAttachmentRules::optional(),
        ])->passes();
    }

    public function test_attachment_remains_optional_for_all_six_voucher_forms(): void
    {
        $this->assertTrue(Validator::make([],[
            'attachment'=>VoucherAttachmentRules::optional(),
        ])->passes());
    }

    public function test_pdf_image_and_five_megabyte_limit_are_centralized(): void
    {
        $rules=VoucherAttachmentRules::optional();
        $this->assertContains('mimes:pdf,jpg,jpeg,png,webp',$rules);
        $this->assertContains('max:5120',$rules);
        $this->assertTrue($this->valid(UploadedFile::fake()->create('evidence.pdf',120,'application/pdf')));
        $this->assertFalse($this->valid(UploadedFile::fake()->create('evidence.pdf',5121,'application/pdf')));
    }

    public function test_executable_attachment_is_rejected(): void
    {
        $this->assertFalse($this->valid(UploadedFile::fake()->create('script.exe',30,'application/octet-stream')));
    }
}
