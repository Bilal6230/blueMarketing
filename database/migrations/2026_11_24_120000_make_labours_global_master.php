<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('labours') || !Schema::hasColumn('labours','project_id')) {
            return;
        }
        // Only worker identity becomes global. Attendance, payroll and
        // sites continue to own their selected-project links.
        foreach (Schema::getForeignKeys('labours') as $foreign) {
            if (in_array('project_id', $foreign['columns'] ?? [], true)) {
                Schema::table('labours', function (Blueprint $table) use ($foreign): void {
                    $table->dropForeign($foreign['name']);
                });
            }
        }
        Schema::table('labours', function (Blueprint $table): void {
            $table->dropColumn('project_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('labours') && !Schema::hasColumn('labours','project_id')) {
            Schema::table('labours', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_id')->nullable();
            });
        }
    }
};
