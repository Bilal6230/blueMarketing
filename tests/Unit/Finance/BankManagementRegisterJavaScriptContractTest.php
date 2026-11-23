<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

/**
 * Both finance register pages must compile a valid JavaScript delete guard.
 * A spare/missing parenthesis here prevents document-ready execution and
 * leaves BOTH the KPI and register skeleton in perpetual "Loading" state.
 */
final class BankManagementRegisterJavaScriptContractTest extends TestCase
{
    public function test_cheque_and_online_register_delete_guards_are_balanced(): void
    {
        foreach ([
            'cheque' => 'cheque',
            'online' => 'online',
        ] as $directory => $method) {
            $path=__DIR__.'/../../../resources/views/admin/finance/'.$directory.'/intake.blade.php';
            $source=file_get_contents($path);
            self::assertIsString($source);
            $start=strpos($source,"if(@json(auth()->user()->can('finance {$method} delete')) &&");
            self::assertNotFalse($start,"Missing {$method} delete guard.");
            $end=strpos($source,".text('Delete').appendTo(\$actions);",$start);
            self::assertNotFalse($end,"Missing {$method} delete action.");
            $guard=substr($source,$start,$end-$start);
            $last=strrpos($guard,'VoucherApproverAccess::isSuperAdmin(auth()->user())');
            self::assertNotFalse($last,"Missing super-admin verification for {$method} deletion.");
            $tail=substr($guard,$last+strlen('VoucherApproverAccess::isSuperAdmin(auth()->user())'));
            // From the close of isSuperAdmin(), exactly 4 more closers:
            // @json, inner condition, outer condition, if.
            self::assertMatchesRegularExpression('/^\)\)\)\)\s*\{\s*$/',
                explode("\n",$tail,2)[0], "Unbalanced {$method} delete guard blocks listing JS.");
            self::assertStringContainsString('load(1);',$source);
        }
    }
}
