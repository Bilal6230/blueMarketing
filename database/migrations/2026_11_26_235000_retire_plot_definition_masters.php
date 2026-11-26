<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plot Definitions was retired: plots keep their original type, size, unit,
 * amount, project, booking and accounting identifiers.
 *
 * CAUTION: definition masters/rate history/audit rows are intentionally deleted.
 * Back up the database before running this irreversible cleanup on staging/live.
 */
return new class extends Migration {
    private const COLUMNS = [
        'plot_category_definition_id',
        'plot_type_definition_id',
        'plot_size_definition_id',
        'plot_unit_definition_id',
    ];

    public function up(): void
    {
        if (Schema::hasTable('plots')) {
            $columns = array_values(array_filter(self::COLUMNS, fn ($name) => Schema::hasColumn('plots', $name)));
            if ($columns) {
                if (DB::getDriverName() === 'mysql') {
                    $foreignKeys = DB::select(
                        "SELECT DISTINCT CONSTRAINT_NAME AS constraint_name
                           FROM information_schema.KEY_COLUMN_USAGE
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = 'plots'
                            AND REFERENCED_TABLE_NAME IS NOT NULL
                            AND COLUMN_NAME IN ('plot_category_definition_id',
                                                'plot_type_definition_id',
                                                'plot_size_definition_id',
                                                'plot_unit_definition_id')"
                    );
                    foreach ($foreignKeys as $foreignKey) {
                        Schema::table('plots', function (Blueprint $table) use ($foreignKey) {
                            $table->dropForeign($foreignKey->constraint_name);
                        });
                    }
                }
                Schema::table('plots', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        // Dependents first, master last. No plot, booking, voucher, or ledger
        // rows are deleted; only unused definition tables are retired.
        Schema::dropIfExists('plot_definition_rate_revisions');
        Schema::dropIfExists('plot_definition_audits');
        Schema::dropIfExists('plot_definitions');
    }

    public function down(): void
    {
        // Intentionally irreversible, to avoid creating fake historical
        // definition and audit records on rollback.
    }
};
