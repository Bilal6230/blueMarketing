<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class CommissionVoucherSchemaGateTest extends TestCase
{
    public function test_commission_schema_repair_refuses_without_approved_v2_pilot(): void
    {
        config()->set('finance.v2_pilot_api_enabled', false);
        config()->set('finance.v2_pilot_write_enabled', false);
        $this->assertSame(1, Artisan::call('finance:repair-v2-commission-status', ['--apply' => true]));
    }
}
