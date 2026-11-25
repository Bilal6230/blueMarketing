<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 12);
            $table->string('route_name', 160)->nullable();
            $table->string('request_path', 255);
            $table->unsignedSmallInteger('response_status');
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['actor_id', 'created_at']);
            $table->index(['route_name', 'created_at']);
            $table->index('created_at');
        });
        Permission::findOrCreate('read activity audit', 'web');
        // Only designated administrators receive access by default.
        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if (in_array(strtolower($role->name), ['admin', 'superadmin', 'super-admin'], true)) {
                $role->givePermissionTo('read activity audit');
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep audit evidence and access grants intact on rollback.
    }
};
