<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $superadmin = User::create([
            'name'      => 'Superadmin',
            'email'     => 'superadmin@superadmin.com',
            'avatar'     => 'avatar/img_avatar.png',
            'password'  => bcrypt('superadmin')
        ]);
        $superadmin->assignRole('superadmin');

        $admin = User::create([
            'name'      => 'Admin',
            'email'     => 'admin@admin.com',
            'avatar'     => 'avatar/img_avatar.png',
            'password'  => bcrypt('admin')
        ]);
        $admin->assignRole('admin');

        $operator = User::create([
            'name'      => 'Operator',
            'email'     => 'operator@operator.com',
            'avatar'     => 'avatar/img_avatar2.png',
            'password'  => bcrypt('operator')
        ]);
        $operator->assignRole('operator');
    }
}
