<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('finance_cheque_intakes')) {
            throw new \RuntimeException('Apply original cheque intake migrations first.');
        }
        Schema::table('finance_cheque_intakes', function (Blueprint $table): void {
            if (!Schema::hasColumn('finance_cheque_intakes','cheque_title')) {
                $table->string('cheque_title',200)->nullable();
            }
            if (!Schema::hasColumn('finance_cheque_intakes','direction')) {
                $table->string('direction',3)->default('in');
            }
            if (!Schema::hasColumn('finance_cheque_intakes','account_mapping_id')) {
                $table->unsignedBigInteger('account_mapping_id')->nullable();
            }
        });

        // Previous receipts used a mandatory Lead. Preserve its historical ID,
        // but allow vouchers for legitimate non-customer project accounts.
        Schema::table('finance_cheque_intakes', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
        });

        // No historical text is discarded: carry previous Remarks into Purpose first.
        // If any cannot fit, stop and let the owner review the data before dropping.
        if (Schema::hasColumn('finance_cheque_intakes','remarks')) {
            DB::table('finance_cheque_intakes')->whereNotNull('remarks')
                ->where('remarks','!=','')->orderBy('id')->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $purpose = trim((string) $row->purpose);
                        $remark = trim((string) $row->remarks);
                        if ($remark === '' || str_contains($purpose,$remark)) continue;
                        $combined = $purpose." | Previous remarks: ".$remark;
                        if (mb_strlen($combined) > 500) {
                            throw new \RuntimeException('Cheque #'.$row->id.' has legacy remarks longer than Purpose capacity. Review before migration.');
                        }
                        DB::table('finance_cheque_intakes')->where('id',$row->id)->update(['purpose'=>$combined]);
                    }
                });
            Schema::table('finance_cheque_intakes', function (Blueprint $table): void {
                $table->dropColumn('remarks');
            });
        }
    }
    public function down(): void
    {
        // Retain financial evidence; do not roll back to a lossy legacy form.
    }
};
