<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce true one-target source relationships.
 * Polymorphic ledgers.type + ledgers.type_id are NOT eligible for an FK.
 * Abort on historical orphans; never silently delete/reparent financial rows.
 */
return new class extends Migration
{
    private const RELATIONS = [
        ['journal_voucher_details','journal_voucher_id','journal_vouchers','id','fk_journal_details_voucher'],
        ['commision_voucher_details','commision_voucher_id','commision_vouchers','id','fk_commission_details_voucher'],
        ['ledgers','journal_voucher_detail_id','journal_voucher_details','id','fk_ledger_journal_detail'],
        ['ledgers','customer_ledger_id','customer_ledger','id','fk_ledger_customer_source'],
        ['ledgers','project_head_subheads_id','project_head_subheads','id','fk_ledger_account'],
        ['journal_voucher_details','account_id','project_head_subheads','id','fk_journal_detail_account'],
        ['commision_voucher_details','account_id','project_head_subheads','id','fk_commission_detail_account'],
        ['ledger_source_attributions','ledger_id','ledgers','id','fk_attribution_ledger'],
    ];

    public function up(): void
    {
        $database=DB::connection()->getDatabaseName();
        $plan=[];
        foreach (self::RELATIONS as [$table,$column,$parent,$pk,$constraint]) {
            foreach ([$table,$parent] as $target) {
                if (!Schema::hasTable($target)) {
                    throw new \RuntimeException('Voucher FK prerequisite missing table: '.$target);
                }
            }
            if (!Schema::hasColumn($table,$column)||!Schema::hasColumn($parent,$pk)) {
                throw new \RuntimeException('Voucher FK prerequisite missing column: '.$table.'.'.$column);
            }
            $existing=DB::selectOne('SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?
                AND REFERENCED_TABLE_NAME=? AND REFERENCED_COLUMN_NAME=? LIMIT 1',
                [$database,$table,$column,$parent,$pk]);
            if ($existing) continue;
            $otherFk=DB::selectOne('SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
                [$database,$table,$column]);
            if ($otherFk) {
                throw new \RuntimeException('Existing incompatible FK on '.$table.'.'.$column);
            }
            $orphanCount=(int)DB::table($table.' as child')
                ->leftJoin($parent.' as parent','parent.'.$pk,'=','child.'.$column)
                ->whereNotNull('child.'.$column)->whereNull('parent.'.$pk)->count();
            if ($orphanCount) {
                throw new \RuntimeException('Voucher FK blocked: '.$table.'.'.$column.' has '.
                    $orphanCount.' orphan references. Run php artisan finance:audit-voucher-schema; repair deliberately first.');
            }
            $plan[]=[$table,$column,$parent,$pk,$constraint];
        }
        // Cross-project accounts are logically invalid even if all referenced
        // row IDs exist. Block any DDL rather than legitimizing bad relations.
        foreach ([
            ['journal_voucher_details','journal_voucher_id','journal_vouchers'],
            ['commision_voucher_details','commision_voucher_id','commision_vouchers'],
        ] as [$detailTable,$headerId,$headerTable]) {
            $mismatch=(int)DB::table($detailTable.' as d')
                ->join($headerTable.' as h','h.id','=','d.'.$headerId)
                ->join('project_head_subheads as a','a.id','=','d.account_id')
                ->whereColumn('a.project_id','!=','h.project_id')->count();
            if ($mismatch) throw new \RuntimeException(
                $detailTable.' has '.$mismatch.' cross-project account links; FK installation stopped.'
            );
        }
        foreach ($plan as [$table,$column,$parent,$pk,$constraint]) {
            // RESTRICT: logical deletion must never cascade a physical delete.
            Schema::table($table,function($blueprint)use($column,$parent,$pk,$constraint) {
                $blueprint->foreign($column,$constraint)->references($pk)->on($parent)
                    ->restrictOnDelete()->restrictOnUpdate();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::RELATIONS) as [$table,$column,$parent,$pk,$constraint]) {
            if (!Schema::hasTable($table)) continue;
            $database=DB::connection()->getDatabaseName();
            $ours=DB::selectOne('SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND COLUMN_NAME=?
                AND REFERENCED_TABLE_NAME=? LIMIT 1',
                [$database,$table,$constraint,$column,$parent]);
            if ($ours) Schema::table($table,fn($blueprint)=>$blueprint->dropForeign($constraint));
        }
    }
};
