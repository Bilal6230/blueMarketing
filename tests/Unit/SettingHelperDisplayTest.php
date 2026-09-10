<?php

namespace Tests\Unit;

use App\Helpers\SettingHelper;
use PHPUnit\Framework\TestCase;

class SettingHelperDisplayTest extends TestCase
{
    /** @dataProvider invalidDisplayValueProvider */
    public function test_display_helpers_return_safe_strings_for_invalid_values($value): void
    {
        $this->assertSame('btn-secondary', SettingHelper::getProjectColorClass($value));
        $this->assertSame('badge-secondary', SettingHelper::getColorClass($value));
        $this->assertSame('Unknown', SettingHelper::getCallStatus($value));
    }

    public function test_valid_display_mappings_are_preserved(): void
    {
        $this->assertSame('btn-success', SettingHelper::getProjectColorClass(2));
        $this->assertSame('badge-parpal', SettingHelper::getColorClass(2));
        $this->assertSame('Interested', SettingHelper::getCallStatus(2));
        $this->assertSame('New Lead', SettingHelper::getCallStatus(6));
    }

    public function invalidDisplayValueProvider(): array
    {
        return [
            'null' => [null],
            'zero integer' => [0],
            'zero string' => ['0'],
            'unknown identifier' => [999],
            'array' => [[1]],
            'object' => [(object) ['id' => 1]],
        ];
    }
}
