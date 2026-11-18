<?php

namespace App\Support\Finance;

/**
 * The legacy names must remain in the database until a route/code/role audit
 * verifies that no current feature still depends on them. Hide duplicates in
 * the role editor, but never revoke them implicitly when editing a role.
 */
final class RolePermissionCatalog
{
    public const LEGACY_VOUCHER_NAMES = [
        'read voucher', 'create voucher', 'update voucher', 'delete voucher',
        'read jv', 'create jv', 'edit jv', 'delete jv', 'print jv',
        'voucher approver', 'can approve',
    ];

    public static function isLegacyVoucher(string $name): bool
    {
        return in_array(strtolower(trim($name)), self::LEGACY_VOUCHER_NAMES, true);
    }

    public static function isFinanceAction(string $name): bool
    {
        return str_starts_with($name, 'finance ');
    }

    public static function category(string $name): string
    {
        if (self::isLegacyVoucher($name)) return 'legacy';
        if (self::isFinanceAction($name)) return 'finance';
        return 'general';
    }

    /**
     * Only real module actions are shown. A single selectable source
     * prevents 'create voucher' and 'finance cr create' appearing twice.
     */
    public static function moduleActions(string $module): array
    {
        $defaults = ['view','create','edit','delete','approve','reject','reverse','print'];
        return match ($module) {
            'directory' => ['view','print','approve','reject'],
            'draft' => ['view','create','edit','delete','print'],
            'cheque' => ['view','create','edit','delete','clear','withdraw','return','reject','history','print'],
            'online' => ['view','create','edit','delete','confirm','history','print'],
            'bank-deposit', 'bank-withdrawal' => ['view','create','edit','delete','history','reverse','print'],
            'plot-receipt','plot-purchase' => ['view','create','edit','delete','details','history','reverse','print'],
            'extra-charge' => ['view','create','edit','delete','approve','reject','print'],
            'jv','sv','cv' => ['view','create','edit','delete','approve','reject','reverse','resubmit','print'],
            'ppr','ecp' => ['view','create','edit','delete','approve','reject','print'],
            'bor','bop' => [...$defaults,'history','release','resubmit'],
            'cr','dr','cp','ccr','cdr' => [...$defaults,'history','resubmit'],
            default => $defaults,
        };
    }

    public static function moduleLabel(string $module): string
    {
        return match ($module) {
            'bank-deposit' => 'Bank Deposit',
            'bank-withdrawal' => 'Bank Withdrawal',
            'plot-receipt' => 'Plot Receive Payment',
            'plot-purchase' => 'Plot Purchase Voucher',
            'extra-charge' => 'Extra Charges',
            'cheque' => 'Cheque Management',
            'online' => 'Online Management',
            'draft' => 'Draft Vouchers',
            'directory' => 'Daily Entries Directory',
            default => strtoupper($module),
        };
    }
}
