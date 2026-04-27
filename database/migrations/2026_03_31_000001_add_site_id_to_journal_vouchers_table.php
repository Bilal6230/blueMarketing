<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('journal_vouchers', 'site_id')) {
            Schema::table('journal_vouchers', function (Blueprint $table) {
                $table->unsignedBigInteger('site_id')->nullable()->after('project_id');
                $table->index('site_id');
            });
        }

        if (
            Schema::hasColumn('journal_vouchers', 'project_id') &&
            Schema::hasTable('sites') &&
            Schema::hasTable('journal_voucher_details')
        ) {
            $sites = DB::table('sites')
                ->select('id', 'project_id', 'site_name', 'subhead_accounting_id')
                ->get();

            foreach ($sites as $site) {
                $voucherQuery = DB::table('journal_vouchers')
                    ->whereNull('site_id')
                    ->where('project_id', $site->project_id)
                    ->where('description', 'Labour payment for Site: ' . $site->site_name);

                if (!empty($site->subhead_accounting_id)) {
                    $voucherQuery->whereExists(function ($query) use ($site) {
                        $query->select(DB::raw(1))
                            ->from('journal_voucher_details')
                            ->whereColumn('journal_voucher_details.journal_voucher_id', 'journal_vouchers.id')
                            ->where('journal_voucher_details.account_id', $site->subhead_accounting_id)
                            ->where('journal_voucher_details.credit', '>', 0);
                    });
                }

                $voucherQuery->update(['site_id' => $site->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('journal_vouchers', 'site_id')) {
            Schema::table('journal_vouchers', function (Blueprint $table) {
                $table->dropIndex(['site_id']);
                $table->dropColumn('site_id');
            });
        }
    }
};
