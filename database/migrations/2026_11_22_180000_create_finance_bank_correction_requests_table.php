<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One dedicated request table is necessary because daily_voucher_approvals has
 * a UNIQUE (source_table, source_id), while bank sources can be corrected repeatedly.
 * All per-voucher financial history still uses existing edit snapshots.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            foreach (['finance online correct','finance cheque correct',
                'finance online correction approve','finance cheque correction approve'] as $permission) {
                \Illuminate\Support\Facades\DB::table('permissions')->updateOrInsert(
                    ['name'=>$permission,'guard_name'=>'web'],
                    ['updated_at'=>now(), 'created_at'=>now()]
                );
            }
        }
        if (Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')
            && Schema::hasTable('permissions')) {
            $superAdminRoleIds=\Illuminate\Support\Facades\DB::table('roles')
                ->whereIn('name',['superadmin','super-admin'])
                ->where('guard_name','web')->pluck('id');
            $permissionIds=\Illuminate\Support\Facades\DB::table('permissions')
                ->where('guard_name','web')->whereIn('name',[
                    'finance online correct','finance cheque correct',
                    'finance online correction approve','finance cheque correction approve'
                ])->pluck('id');
            foreach($superAdminRoleIds as $roleId) foreach($permissionIds as $permissionId) {
                $exists=\Illuminate\Support\Facades\DB::table('role_has_permissions')
                    ->where('role_id',$roleId)->where('permission_id',$permissionId)->exists();
                if(!$exists) \Illuminate\Support\Facades\DB::table('role_has_permissions')->insert([
                    'role_id'=>$roleId,'permission_id'=>$permissionId
                ]);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
        if (Schema::hasTable('finance_bank_correction_requests')) return;
        Schema::create('finance_bank_correction_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('source_method', 12);
            $t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('submitted_by');
            $t->unsignedBigInteger('assigned_approver_id')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->string('status',16)->default('pending');
            $t->char('fingerprint',64);
            $t->decimal('proposed_amount',18,2);
            $t->longText('proposed_vouchers_json');
            $t->string('reason',500);
            $t->string('decision_reason',500)->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['project_id','source_method','source_id','status'],'bank_corr_source_status');
            $t->index(['project_id','assigned_approver_id','status'],'bank_corr_approver_queue');
        });
    }

    public function down(): void
    {
        // Financial correction requests and approval evidence are never silently dropped.
    }
};
