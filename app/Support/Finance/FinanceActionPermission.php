<?php

namespace App\Support\Finance;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'cp'=>'dr', // CP is the historical stored series of today's DR voucher.
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

    /** Resolve the persisted voucher series instead of trusting a submitted type. */
    public static function sourceModule(int $sourceId): string
    {
        $project = (int) getSelectedTown();
        abort_unless($project > 0 && $sourceId > 0, 403);
        $source = DB::table('customer_ledger')->where('project_id', $project)
            ->where('id', $sourceId)->first(['transaction_type','payment_type']);
        abort_unless($source, 404);

        $series = null;
        if (Schema::hasTable('project_voucher_display_numbers')) {
            $series = DB::table('project_voucher_display_numbers')
                ->where('project_id', $project)->where('source_kind', 'customer_ledger')
                ->where('source_id', $sourceId)->value('series');
        }
        $module = self::moduleForSeries((string)($series ?: $source->transaction_type));
        abort_unless($module !== null, 403, 'Unsupported voucher permission scope.');
        return $module;
    }

    public static function ledgerModule(int $ledgerId): string
    {
        $project = (int) getSelectedTown();
        abort_unless($project > 0 && $ledgerId > 0, 403);
        $ledger = DB::table('ledgers as ledger')
            ->join('project_head_subheads as account','account.id','=','ledger.project_head_subheads_id')
            ->where('ledger.id',$ledgerId)->where('account.project_id',$project)
            ->first(['ledger.type','ledger.customer_ledger_id']);
        abort_unless($ledger, 404);
        if ($ledger->customer_ledger_id) return self::sourceModule((int)$ledger->customer_ledger_id);
        $module = self::moduleForSeries((string)$ledger->type);
        abort_unless($module !== null,403,'Unsupported ledger voucher permission scope.');
        return $module;
    }

    public static function journalModule(int $id): string
    {
        if (!Schema::hasTable('journal_vouchers')) return 'jv';
        $project = (int) getSelectedTown();
        abort_unless($project > 0 && $id > 0, 403);
        $type = DB::table('journal_vouchers')->where('id',$id)->where('project_id',$project)->value('type');
        if (!$type) return 'jv';
        return self::moduleForSeries((string)$type) ?? 'jv';
    }

    public static function dailyApprovalModule(int $approvalId): string
    {
        $project = (int)getSelectedTown();
        abort_unless($project > 0 && $approvalId > 0,403);
        $approval = DB::table('daily_voucher_approvals')->where('project_id',$project)
            ->where('id',$approvalId)->first(['voucher_type','source_table','source_id']);
        abort_unless($approval,404);
        if ($approval->source_table === 'customer_ledger') return self::sourceModule((int)$approval->source_id);
        if ($approval->source_table === 'journal_vouchers') return self::journalModule((int)$approval->source_id);
        if ($approval->source_table === 'finance_plot_approval_drafts') {
            return $approval->voucher_type === 'PPR' ? 'plot-receipt' : 'plot-purchase';
        }
        return self::moduleForSeries((string)$approval->voucher_type) ?? 'directory';
    }

    public static function pendingModule(int $pendingId): ?string
    {
        $project = (int)getSelectedTown();
        abort_unless($project > 0 && $pendingId > 0,403);
        $pending = DB::table('pending_updates')->where('id',$pendingId)
            ->first(['table_name','record_id']);
        abort_unless($pending,404);
        return match ($pending->table_name) {
            'ledgers' => self::ledgerModule((int)$pending->record_id),
            'customer_ledger' => self::sourceModule((int)$pending->record_id),
            'draft_ledgers' => 'draft',
            default => null,
        };
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
            'sales.voucher.create'=>['sv','create'],
            'sales.voucher.store'=>['sv','create'],
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
        if (in_array($name, ['journal.voucher.edit','journal.voucher.update',
            'journal.voucher.resubmit','journal.voucher.delete','journal.voucher.print'], true)) {
            $action = match ($name) {
                'journal.voucher.edit', 'journal.voucher.update' => 'edit',
                'journal.voucher.resubmit' => 'resubmit',
                'journal.voucher.delete' => 'delete', default => 'print',
            };
            return [self::journalModule((int)$route->parameter('id')),$action];
        }
        if ($name === 'finance.daily-vouchers.approvals.decide') {
            $approvalParam = $route->parameter('approval');
            $approvalId = $approvalParam instanceof \App\Models\DailyVoucherApproval
                ? (int)$approvalParam->getKey() : (int)$approvalParam;
            $decision = (string)$route->parameter('decision');
            return [self::dailyApprovalModule($approvalId),$decision === 'reject' ? 'reject' : 'approve'];
        }
        if ($name === 'finance.daily-vouchers.approvals.policy') return ['directory','approve'];
        if ($name === 'finance.daily-vouchers.approvals.index') return ['directory','view'];
        if (in_array($name,['approvals.finance.approve','approvals.finance.reject',
            'finance.voucher.approveadmin','finance.voucher.rejectadmin'],true)) {
            $module = self::pendingModule((int)($route->parameter('pending') ?: $route->parameter('id')));
            return $module ? [$module,str_contains($name,'reject') ? 'reject' : 'approve'] : null;
        }
        if ($name === 'booking.customer.approve') {
            return [self::sourceModule((int)$request->input('id')),
                (int)$request->input('is_approve') === 0 ? 'reject' : 'approve'];
        }
        if ($name === 'booking.customer.destroy') {
            return [self::sourceModule((int)$request->input('id')),'delete'];
        }
        if ($name === 'booking.voucher.show') {
            return [self::sourceModule((int)$request->input('id')),'details'];
        }
        if ($name === 'booking.customer.print') {
            return [self::sourceModule((int)$route->parameter('id')),'print'];
        }
        if ($name === 'finance.bank_source.linked_print') {
            return [(string)$route->parameter('method') === 'online' ? 'online' : 'cheque','print'];
        }
        if ($name === 'finance.voucher.passed_sources') return ['directory','view'];
        if (in_array($name,['finance.voucher.approve','finance.voucher.reject'],true)) {
            $table = (string)$request->input('table');
            $record = (int)$route->parameter('id');
            $module = match ($table) {
                'customer_ledger' => self::sourceModule($record),
                'ledgers' => self::ledgerModule($record),
                'draft_ledgers' => 'draft',
                default => null,
            };
            return $module ? [$module,$name === 'finance.voucher.reject'?'reject':'approve'] : null;
        }
        if ($name === 'finance.daily-vouchers.source.print') {
            return [self::sourceModule((int)$route->parameter('customerLedger')),'print'];
        }
        if (isset($fixed[$name])) return $fixed[$name];
        if (in_array($name, ['finance.bank-management.edit',
            'finance.bank-management.update','finance.bank-management.delete'], true)) {
            $method = (string) $request->route('method');
            if (!in_array($method, ['online','cheque'],true)) return null;
            return [$method, $name === 'finance.bank-management.delete' ? 'delete' : 'edit'];
        }
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
            'finance.voucher.transfer.next-number'=>'view',
            'finance.voucher.transfer.history'=>'history',
            'finance.voucher.transfer.post'=>'create',
            'finance.voucher.transfer.details'=>'edit',
            'finance.voucher.transfer.update'=>'edit',
            'finance.voucher.transfer.delete'=>'delete',
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
            'finance.voucher.plot-receipt.update'=>'edit',
            'finance.voucher.plot-receipt.narration.update'=>'edit',
            'finance.voucher.plot-receipt.delete'=>'delete',
            'finance.voucher.plot-receipt.reverse'=>'reverse',
            'finance.voucher.plot-receipt.print'=>'print',
            'finance.voucher.plot-purchase.workspace'=>'view',
            'finance.voucher.plot-purchase.options'=>'view',
            'finance.voucher.plot-purchase.history'=>'history',
            'finance.voucher.plot-purchase.details'=>'details',
            'finance.voucher.plot-purchase.post'=>'create',
            'finance.voucher.plot-purchase.update'=>'edit',
            'finance.voucher.plot-purchase.narration.update'=>'edit',
            'finance.voucher.plot-purchase.delete'=>'delete',
            'finance.voucher.plot-purchase.reverse'=>'reverse',
            'finance.voucher.plot-purchase.print'=>'print',
        ];
        if ($name === 'finance.voucher.plot-next-number') {
            $series = (string) $route->parameter('series');
            return [$series === 'PPR' ? 'plot-receipt' : 'plot-purchase', 'view'];
        }
        if (isset($plot[$name])) {
            return [str_contains($name,'plot-receipt')?'plot-receipt':'plot-purchase',$plot[$name]];
        }

        if (in_array($name,['ledger.store','ledger.store.fixed_method'],true)) {
            $prefix = strtoupper((string)strtok((string)$request->input('voucher',''),'-'));
            return [self::moduleForSeries($prefix) ?? 'directory','create'];
        }
        if (in_array($name,['ledger.save_as_draft','ledger.save_as_draft.fixed_method'],true)) {
            return ['draft','create'];
        }
        if (in_array($name,['ledger.update','ledger.update.fixed_method'],true)) {
            return [self::ledgerModule((int)$request->input('id')),'edit'];
        }
        if ($name === 'ledger.destroy') return [self::ledgerModule((int)$request->input('id')),'delete'];
        if ($name === 'draft.ledger.update') return ['draft','edit'];
        if ($name === 'draft.ledger.destroy') return ['draft','delete'];

        if (in_array($name,['finance.voucher.in','finance.voucher.out',
            'finance.voucher.in.method','finance.voucher.out.method'],true)) {
            $incoming = str_contains($name,'.in');
            $default = $incoming ? 'CR' : 'DR';
            $series = strtoupper((string)$request->query('series',$default));
            $module = self::moduleForSeries($series);
            return [$module ?? strtolower($default), $request->boolean('embed') ? 'create' : 'view'];
        }
        if (in_array($name,['finance.voucher.bank.workspace','finance.voucher.bank.sources',
            'finance.voucher.bank.history','finance.voucher.bank.print'],true)) {
            $type = $route->parameter('direction') === 'receipt' ? 'bor' : 'bop';
            return [$type, str_ends_with($name,'.print') ? 'print'
                : (str_ends_with($name,'.history') ? 'history' : 'view')];
        }
        if (in_array($name,['finance.voucher.print','finance.voucher.print.method'],true)) {
            return [self::ledgerModule((int)$route->parameter('id')),'print'];
        }
        if ($name === 'finance.voucher.source.print') {
            return [self::sourceModule((int)$route->parameter('customerLedger')),'print'];
        }
        if (in_array($name,['finance.daily-vouchers.sources.reverse',
            'finance.daily-vouchers.bank-allocation.release',
            'finance.daily-vouchers.revise.edit','finance.daily-vouchers.revise.store'],true)) {
            $action = match (true) {
                str_ends_with($name,'.reverse') => 'reverse',
                str_ends_with($name,'.release') => 'release',
                default => 'edit',
            };
            return [self::sourceModule((int)$route->parameter('customerLedger')),$action];
        }
        if (in_array($name,['finance.voucher.cash-only.draft','finance.voucher.cash-only.store'],true)) {
            $series = strtoupper((string)$request->input('display_series',$request->input('type','')));
            return [in_array($series,['CCR','CDR'],true) ? strtolower($series) : 'directory',
                $name === 'finance.voucher.cash-only.draft' ? 'create' : 'create'];
        }
        if ($name === 'booking.customer.deposit' && $request->input('action') === 'extra_charge') {
            return ['extra-charge','create'];
        }

        $series = strtoupper((string)($route->parameter('series') ?: $request->input('type', $request->input('series', $request->query('series','')))));
        $daily = [
            'finance.daily-vouchers.index'=>'view',
            'finance.daily-vouchers.data'=>'view',
                    ];
        if (isset($daily[$name])) {
            return [self::moduleForSeries($series) ?? 'directory', $daily[$name]];
        }
        return null;
    }
}
