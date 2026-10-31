<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\V2VoucherLifecycleService;
use PHPUnit\Framework\TestCase;

final class V2VoucherLifecycleServiceTest extends TestCase
{
    public function test_typed_lifecycle_does_not_expose_mutable_document_update_or_delete(): void
    {
        $methods = get_class_methods(V2VoucherLifecycleService::class);
        self::assertContains('reverse', $methods);
        self::assertNotContains('update', $methods);
        self::assertNotContains('delete', $methods);
        self::assertNotContains('destroy', $methods);
    }
}
