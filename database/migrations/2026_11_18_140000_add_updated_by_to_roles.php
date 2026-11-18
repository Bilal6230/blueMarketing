<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles') || Schema::hasColumn('roles','updated_by')) return;
        Schema::table('roles',function(Blueprint $table){
            $table->unsignedBigInteger('updated_by')->nullable()->after('updated_at');
            $table->index('updated_by','roles_updated_by_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles','updated_by')) {
            Schema::table('roles',function(Blueprint $table){
                $table->dropIndex('roles_updated_by_index');
                $table->dropColumn('updated_by');
            });
        }
    }
};
