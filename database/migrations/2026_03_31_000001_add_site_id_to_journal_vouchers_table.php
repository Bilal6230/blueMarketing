<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        if (!Schema::hasTable('journal_vouchers')) {
            throw new \LogicException('Cannot add journal_vouchers.site_id because journal_vouchers does not exist.');
        }

        if (Schema::hasColumn('journal_vouchers', 'site_id')) {
            throw new \LogicException(
                'journal_vouchers.site_id already exists; reconcile schema and migration history manually.'
            );
        }

        if (Schema::hasIndex('journal_vouchers', 'journal_vouchers_site_id_index')) {
            throw new \LogicException(
                'journal_vouchers_site_id_index already exists; reconcile schema and migration history manually.'
            );
        }

        Schema::table('journal_vouchers', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable()->after('project_id');
            $table->index('site_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        throw new \LogicException(
            'Automatic rollback is disabled because column ownership cannot be proven after migration-history drift.'
        );
    }
};
