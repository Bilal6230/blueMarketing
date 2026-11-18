<?php

namespace App\Support\Finance;

use Illuminate\Http\Request;

/**
 * One source of truth for granular finance abilities. No wildcard permissions.
 */
final class FinanceActionPermission
{
    public const MODULES = [
        'cr', 'dr', 'cp', 'ccr', 'cdr', 'bor', 'bop', 'jv', 'sv', 'cv',
        'bank-deposit', 'bank-withdrawal', 'plot-receipt', 'plot-purchase', 'directory', 'ppr', 'ecp',
        'draft', 'extra-charge', 'cheque', 'online',
    ];
    public const ACTIONS = [
        'view', 'create', 'edit', 'delete', 'approve', 'reject', 'reverse', 'print',
        'history', 'details', 'confirm', 'clear', 'return', 'withdraw', 'release', 'resubmit',
    ];

    public static function moduleForSeries(string $type): ?string
    {
        $type = strtolower(trim($type));
        $alias = [
            'pr'=>'plot-receipt', 'pp'=>'plot-purchase', 'bd'=>'bank-deposit',
            'bw'=>'bank-withdrawal', 'br'=>'bor', 'bo'=>'bop',
            'ec'=>'extra-charge',
        ];
        $module = $alias[$type] ?? $type;
        return in_array($module, self::MODULES, true) ? $module : null;
    }

    public static function name(string $module, string $action): string
    {
        if (!in_array($module, self::MODULES, true) || !in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException('Unknown finance module or action.');
        }

        return 'finance '.$module.' '.$action;
    }

    /** @return array{0:string,1:string}|null */
    public static function forRequest(Request $request): ?array
    {
        $route = $request->route();
        if (!$route) return null;
        $name = (string) $route->getName();
        $fixed = [
            'finance.cheques.index'=>['cheque','view'],
            'finance.cheques.data'=>['cheque','view'],
            'finance.cheques.history'=>['cheque','history'],
            'finance.cheques.slip'=>['cheque','print'],
            'finance.cheques.memo'=>['cheque','print'],
            'finance.cheques.store'=>['cheque','create'],
            
            'finance.online.index'=>['online','view'],
            'finance.online.data'=>['online','view'],
            'finance.online.eligible'=>['online','view'],
            'finance.online.history'=>['online','history'],
            'finance.online.proof'=>['online','print'],
            'finance.online.store'=>['online','create'],
            'finance.online.confirm'=>['online','confirm'],
            'journal.voucher.index'=>['jv','view'],
            'journal.voucher.create'=>['jv','create'],
            'journal.voucher.store'=>['jv','create'],
            'journal.voucher.edit'=>['jv','edit'],
            'journal.voucher.update'=>['jv','edit'],
            'journal.voucher.resubmit'=>['jv','resubmit'],
            'journal.voucher.delete'=>['jv','delete'],
            'journal.voucher.print'=>['jv','print'],
            'sales.voucher.index'=>['sv','view'],
            'commision.voucher.index'=>['cv','view'],
            'commision.voucher.create'=>['cv','create'],
            'commision.voucher.store'=>['cv','create'],
            'commision.voucher.edit'=>['cv','edit'],
            'commision.voucher.update'=>['cv','edit'],
            'commision.voucher.delete'=>['cv','delete'],
            'commision.voucher.approve'=>['cv','approve'],
            'commision.voucher.print'=>['cv','print'],
            'finance.voucher.draft'=>['draft','view'],
            'payment_schedule.extra_charge'=>['extra-charge','view'],
        ];
        if (isset($fixed[$name])) return $fixed[$name];
        if ($name === 'finance.cheques.action') {
            return ['cheque', $request->input('action') === 'withdrawal' ? 'withdraw' : 'clear'];
        }
        if ($name === 'finance.cheques.finalize') {
            return ['cheque', match ($request->input('decision')) {
                'returned' => 'return', 'rejected' => 'reject', default => 'clear',
            }];
        }

        $transfers = [
            'finance.voucher.transfer.workspace'=>'view',
            'finance.voucher.transfer.sources'=>'view',
            'finance.voucher.transfer.history'=>'history',
            'finance.voucher.transfer.post'=>'create',
            'finance.voucher.transfer.reverse'=>'reverse',
            'finance.voucher.transfer.print'=>'print',
        ];
        if (isset($transfers[$name])) {
            $kind = (string)$route->parameter('kind');
            return in_array($kind, ['deposit','withdrawal'], true)
                ? ['bank-'.$kind,$transfers[$name]] : null;
        }
        $plot = [
            'finance.voucher.plot-receipt.workspace'=>'view',
            'finance.voucher.plot-receipt.options'=>'view',
            'finance.voucher.plot-receipt.history'=>'history',
            'finance.voucher.plot-receipt.details'=>'details',
            'finance.voucher.plot-receipt.post'=>'create',
            'finance.voucher.plot-receipt.reverse'=>'reverse',
            'finance.voucher.plot-receipt.print'=>'print',
            'finance.voucher.plot-purchase.workspace'=>'view',
            'finance.voucher.plot-purchase.options'=>'view',
            'finance.voucher.plot-purchase.history'=>'history',
            'finance.voucher.plot-purchase.details'=>'details',
            'finance.voucher.plot-purchase.post'=>'create',
            'finance.voucher.plot-purchase.reverse'=>'reverse',
            'finance.voucher.plot-purchase.print'=>'print',
        ];
        if (isset($plot[$name])) {
            return [str_contains($name,'plot-receipt')?'plot-receipt':'plot-purchase',$plot[$name]];
        }

        $series = strtoupper((string)($route->parameter('series') ?: $request->input('type', $request->input('series', $request->query('series','')))));
        $daily = [
            'finance.daily-vouchers.index'=>'view',
            'finance.daily-vouchers.data'=>'view',
            'finance.daily-vouchers.source.print'=>'print',
        ];
        if (isset($daily[$name])) {
            return [self::moduleForSeries($series) ?? 'directory', $daily[$name]];
        }
        return null;
    }
}
