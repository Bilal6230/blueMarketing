<?php

namespace App\Support\Stock;

/**
 * Stock has four real workspaces. Rights are intentionally a small 4x4
 * matrix; existing slip grants remain valid during the compatibility rollout.
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
        if (!$user || !in_array($module,self::MODULES,true) || !in_array($action,self::ACTIONS,true)) return false;
        if ($user->hasAnyRole(['superadmin','super-admin','Super Admin','admin'])) return true;
        if ($user->can(self::name($module,$action))) return true;
        // The old live roles were granted slip rights, not CRUD stock rights.
        $fallback=match($action) {
            'view'=>'read slip',
            'create'=>'add slip',
            'edit'=>'add slip',
            'delete'=>'delete slip',
        };
        return $user->can($fallback);
    }

    public static function authorize(string $module,string $action): void
    {
        abort_unless(self::allows(auth()->user(),$module,$action),403,
            'You do not have permission to '.$action.' stock '.$module.'.');
    }
}
