<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\VoucherLifecycleService;
use PHPUnit\Framework\TestCase;

final class VoucherLifecycleServiceTest extends TestCase
{
    public function test_typed_lifecycle_does_not_expose_mutable_document_update_or_delete(): void
    {
        $methods = get_class_methods(VoucherLifecycleService::class);
        self::assertContains('reverse', $methods);
        self::assertNotContains('update', $methods);
        self::assertNotContains('delete', $methods);
        self::assertNotContains('destroy', $methods);
    }
}
