<?php

namespace App\Support\Stock;

/**
 * Stock has four real workspaces. Rights are intentionally a small 4x4
 * matrix. Old slip grants must never authorize Stock: modules stay isolated.
 */
final class StockAbility
{
    public const MODULES = ['items','parties','entries','billing'];
    public const ACTIONS = ['view','create','edit','delete'];

    public static function name(string $module, string $action): string
    {
        if (!in_array($module,self::MODULES,true) || !in_array($action,self::ACTIONS,true)) {
            throw new \InvalidArgumentException('Unsupported stock permission');
        }
        return 'stock '.$module.' '.$action;
    }

    public static function allows($user, string $module, string $action): bool
    {
        if (!$user || !in_array($action,self::ACTIONS,true)) return false;
        if ($module==='workspace' && $action==='view') {
            foreach(self::MODULES as $candidate) {
                if(self::allows($user,$candidate,'view')) return true;
            }
            return false;
        }
        if (!in_array($module,self::MODULES,true)) return false;
        // Superadmin is the immutable system owner role. All other roles,
        // including "admin", need the exact Stock module/action permission.
        if ($user->hasAnyRole(['superadmin','super-admin','Super Admin'])) return true;
        return $user->can(self::name($module,$action));
    }

    public static function authorize(string $module,string $action): void
    {
        abort_unless(self::allows(auth()->user(),$module,$action),403,
            'You do not have permission to '.$action.' stock '.$module.'.');
    }
}
