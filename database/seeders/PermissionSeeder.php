<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Permission::create(['name' => 'filemanager']);
        Permission::create(['name' => 'read module']);


        Permission::create(['name' => 'delete setting']);
        Permission::create(['name' => 'update setting']);
        Permission::create(['name' => 'read setting']);
        Permission::create(['name' => 'create setting']);

        Permission::create(['name' => 'delete user']);
        Permission::create(['name' => 'update user']);
        Permission::create(['name' => 'read user']);
        Permission::create(['name' => 'create user']);

        Permission::create(['name' => 'delete role']);
        Permission::create(['name' => 'update role']);
        Permission::create(['name' => 'read role']);
        Permission::create(['name' => 'create role']);

        Permission::create(['name' => 'delete permission']);
        Permission::create(['name' => 'update permission']);
        Permission::create(['name' => 'read permission']);
        Permission::create(['name' => 'create permission']);

        Permission::create(['name' => 'delete lead']);
        Permission::create(['name' => 'update lead']);
        Permission::create(['name' => 'read lead']);
        Permission::create(['name' => 'create lead']);

        Permission::create(['name' => 'delete sector']);
        Permission::create(['name' => 'update sector']);
        Permission::create(['name' => 'read sector']);
        Permission::create(['name' => 'create sector']);

        Permission::create(['name' => 'delete project']);
        Permission::create(['name' => 'update project']);
        Permission::create(['name' => 'read project']);
        Permission::create(['name' => 'create project']);

        Permission::create(['name' => 'delete plot']);
        Permission::create(['name' => 'update plot']);
        Permission::create(['name' => 'read plot']);
        Permission::create(['name' => 'create plot']);

        Permission::create(['name' => 'delete area']);
        Permission::create(['name' => 'update area']);
        Permission::create(['name' => 'read area']);
        Permission::create(['name' => 'create area']);

        Permission::create(['name' => 'delete expence']);
        Permission::create(['name' => 'update expence']);
        Permission::create(['name' => 'read expence']);
        Permission::create(['name' => 'create expence']);

        Permission::create(['name' => 'send sms']);
        Permission::create(['name' => 'read report']);
        Permission::create(['name' => 'read calllog']);
        Permission::create(['name' => 'create calllog']);

        Permission::create(['name' => 'lead details']);
        Permission::create(['name' => 'lead search']);
        Permission::create(['name' => 'lead report']);
        Permission::create(['name' => 'lead pendings']);





    }
}
