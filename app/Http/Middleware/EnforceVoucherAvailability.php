<?php

namespace App\Http\Middleware;

use App\Services\Finance\VoucherAvailability;
use Closure;
use Illuminate\Http\Request;

final class EnforceVoucherAvailability
{
    public function handle(Request $request, Closure $next)
    {
        $name=(string)optional($request->route())->getName();
        $project=(int)getSelectedTown();
        if ($project<1) return $next($request);

        $series=null;
        // Directory deep links are entry points; its unfiltered historical
        // directory, source details, print and approvals remain accessible.
        if (in_array($name,['finance.daily-vouchers.index','finance.daily-vouchers.data'],true)) {
            $series=strtoupper((string)$request->query('type',''));
        } elseif (in_array($name,['finance.voucher.in','finance.voucher.in.method'],true)) {
            $series='CR';
        } elseif (in_array($name,['finance.voucher.out','finance.voucher.out.method'],true)) {
            $series='DR';
        } elseif ($name==='finance.voucher.bank.workspace') {
            $series=$request->route('direction')==='receipt'?'BOR':'BOP';
        } elseif ($name==='finance.voucher.transfer.workspace') {
            $series=$request->route('kind')==='deposit'?'BD':'BW';
        } elseif ($name==='finance.voucher.plot-receipt.workspace') {
            $series='PPR';
        } elseif ($name==='finance.voucher.plot-purchase.workspace') {
            $series='PP';
        } elseif (str_starts_with($name,'journal.voucher.')
            && in_array($name,['journal.voucher.index','journal.voucher.create','journal.voucher.store','journal.voucher.edit','journal.voucher.update','journal.voucher.resubmit'],true)) {
            $series='JV';
        } elseif (in_array($name,['sales.voucher.index','sales.voucher.create','sales.voucher.store'],true)) {
            $series='SV';
        } elseif (in_array($name,['commision.voucher.index','commision.voucher.create','commision.voucher.store','commision.voucher.edit','commision.voucher.update'],true)) {
            $series='CV';
        } elseif (in_array($name,['finance.voucher.cash-only.store','finance.voucher.cash-only.draft'],true)) {
            $series=strtoupper((string)$request->input('display_series',''));
        } elseif (in_array($name,['ledger.store','ledger.store.fixed_method','ledger.save_as_draft','ledger.save_as_draft.fixed_method'],true)) {
            $series=strtoupper((string)$request->input('display_series',''));
            if (!$series && preg_match('/^(CR|DR|CP|CCR|CDR|BOR|BOP)-/',(string)$request->input('voucher'),$match)) {
                $series=$match[1];
            }
        } elseif (in_array($name,['finance.voucher.transfer.post','finance.voucher.plot-receipt.post','finance.voucher.plot-purchase.post'],true)) {
            $series=$name==='finance.voucher.transfer.post'
                ?($request->route('kind')==='deposit'?'BD':'BW')
                :($name==='finance.voucher.plot-receipt.post'?'PPR':'PP');
        }

        if ($series && array_key_exists($series,VoucherAvailability::FAMILIES)) {
            VoucherAvailability::enforce($project,$series);
        }
        return $next($request);
    }
}
