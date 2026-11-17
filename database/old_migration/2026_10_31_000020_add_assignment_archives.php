<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['lead_user', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'] as $name) {
            if (Schema::hasTable($name) && !Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table): void { $table->softDeletes(); });
            }
        }
    }

    public function down(): void
    {
        throw new \LogicException('Assignment history must be retained; use a reviewed forward migration.');
    }
};
