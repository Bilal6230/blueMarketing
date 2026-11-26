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

    /** Old sidebar-only toggles have no real backend guards anymore. */
    public static function isUnusedModuleToggle(string $name): bool
    {
        return in_array(strtolower(trim($name)), ['stock module','labour module'], true);
    }

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
        if (self::isLegacyVoucher($name) || self::isUnusedModuleToggle($name)) return 'legacy';
        if (self::isFinanceAction($name)) return 'finance';
        return 'general';
    }

    /**
     * Group existing non-finance grants by their real resource, without
     * inventing or inserting any new permission names.
     */
    public static function generalModule(string $name): string
    {
        $name = strtolower(trim($name));
        if (preg_match('/^stock (items|parties|entries|billing) (view|create|edit|delete)$/',$name,$matches)) {
            return 'stock-'.$matches[1];
        }
        if (in_array($name,['read employee task','create employee task','update employee task','delete employee task'],true)) {
            return 'employee-tasks';
        }
        if ($name === 'read activity audit') return 'activity-audit';
        if ($name === 'filemanager') return 'filemanager';
        if ($name === 'send sms') return 'sms';
        // Exact historical names from the owner's actual permission audit:
        // keep spelling/DB grants unchanged, only put choices in useful tabs.
        $historical = [
            'add slip'=>'slip',
            'bank posting'=>'bank',
            'pass cheque'=>'cheque',
            'party_reprot'=>'report',
            'report cashbook'=>'report',
            'report party_ledger'=>'report',
            'report party_report'=>'report',
            'plot voucher'=>'plot',
            'plot voucher form'=>'plot',
            'plot voucher report'=>'plot',
            'print voucher'=>'voucher',
            'view inventory'=>'inventory',
            'assign lead'=>'lead',
            'stock module'=>'stock',
            'labour module'=>'labour',
        ];
        if (isset($historical[$name])) return $historical[$name];
        // Some real legacy permissions contain a multi-word resource, e.g.
        // "update booking price" and "view booking report". These still
        // belong to the resource tab, not the miscellaneous bucket.
        if (preg_match('/^(?:read|create|update|delete|approve|reject|edit|view) (booking|landing|plot|labour|stock|party)(?:[ _][a-z0-9_-]+)+$/', $name, $match)) {
            return $match[1];
        }
        if (in_array($name, ['labour module','stock module'], true)) {
            return strtok($name, ' ');
        }
        if (preg_match('/^(?:read|create|update|delete|approve|reject|edit) ([a-z][a-z0-9_-]*)$/', $name, $match)) {
            return $match[1];
        }
        if (preg_match('/^([a-z][a-z0-9_-]*) (?:details|search|report|pendings|history|print)$/', $name, $match)) {
            return $match[1];
        }
        return 'other';
    }

    public static function generalModuleLabel(string $module): string
    {
        return match ($module) {
            'stock-items' => 'Stock · Items',
            'stock-parties' => 'Stock · Parties',
            'stock-entries' => 'Stock · Entries',
            'stock-billing' => 'Stock · Billing',
            'employee-tasks' => 'Employee Tasks',
            'activity-audit' => 'Activity Audit',
            'filemanager' => 'File Manager',
            'sms' => 'SMS',
            'expence' => 'Expenses',
            'calllog' => 'Call Logs',
            'setting' => 'Settings',
            'user' => 'Users',
            'role' => 'Roles',
            'permission' => 'Permissions',
            'project' => 'Projects',
            'plot' => 'Plots',
            'area' => 'Areas',
            'sector' => 'Sectors',
            'lead' => 'Leads / CRM',
            'booking' => 'Bookings / Plot Sales',
            'labour' => 'Labour Master',
            'attendance' => 'Attendance',
            'payroll' => 'Payroll',
            'report' => 'Reports',
            'module' => 'Modules',
            'other' => 'Other Access',
            default => ucwords(str_replace(['-', '_'], ' ', $module)),
        };
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
            // No general browser Reverse action exists for JV/SV/CV. Those
            // posting lifecycles are separately guarded by the finance API.
            'jv','sv','cv' => ['view','create','edit','delete','approve','reject','resubmit','print'],
            'ppr','ecp' => ['view','create','edit','delete','approve','reject','print'],
            'bor','bop' => ['view','create','edit','delete','approve','reject','print','history','release','resubmit'],
            'ccr','cdr' => ['view','create','edit','delete','approve','reject','print','history','resubmit'],
            'cr','dr','cp' => [...$defaults,'history','resubmit'],
            default => $defaults,
        };
    }

    public static function isSelectableFinance(string $name): bool
    {
        if (!self::isFinanceAction($name)) return false;
        foreach (\App\Support\Finance\FinanceActionPermission::MODULES as $module) {
            $prefix='finance '.$module.' ';
            if (str_starts_with($name,$prefix)) {
                return in_array(substr($name,strlen($prefix)),
                    self::moduleActions($module),true);
            }
        }
        return false;
    }

    public static function moduleLabel(string $module): string
    {
        return match ($module) {
            'cr' => 'CR — Credit Voucher',
            'dr' => 'DR — Debit Voucher',
            'cp' => 'CP — Cash Payment Voucher',
            'ccr' => 'CCR — Cash Credit Voucher',
            'cdr' => 'CDR — Cash Debit Voucher',
            'bor' => 'BOR — Bank Receipt Voucher',
            'bop' => 'BOP — Bank Payment Voucher',
            'jv' => 'JV — Journal Voucher',
            'sv' => 'SV — Sales Voucher',
            'cv' => 'CV — Credit / Commission Voucher',
            'bank-deposit' => 'BD — Bank Deposit Voucher',
            'bank-withdrawal' => 'BW — Bank Withdrawal Voucher',
            'plot-receipt' => 'PPR — Plot Receive Payment Voucher',
            'plot-purchase' => 'PP — Plot Purchase Voucher',
            'extra-charge' => 'EC — Extra Charge Voucher',
            'ecp' => 'ECP — Extra Charge Payment Voucher',
            'ppr' => 'PPR — Plot Receive Payment Voucher',
            'cheque' => 'Cheque Management',
            'online' => 'Online Management',
            'draft' => 'Draft Vouchers',
            'directory' => 'Daily Entries Directory',
            default => strtoupper($module),
        };
    }
}
