<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['users', 'plots', 'labours', 'settings', 'dasticash', 'draft_ledgers',
            'booking_details', 'stock_items', 'stock_parties', 'project_head_subheads',
            'roles', 'permissions', 'hold_plots'] as $name) {
            if (Schema::hasTable($name) && !Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        throw new \LogicException('Archive history must be preserved; use a reviewed forward migration.');
    }
};
