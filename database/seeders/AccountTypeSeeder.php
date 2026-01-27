<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $timestamp = now();
        $types = [
            ['id' => 0, 'name' => 'Please Update', 'status' => 1],
            ['id' => 1, 'name' => 'Assets', 'status' => 1],
            ['id' => 2, 'name' => 'Owner', 'status' => 1],
            ['id' => 3, 'name' => 'Recovery', 'status' => 1],
            ['id' => 4, 'name' => 'Expense', 'status' => 1],
            ['id' => 5, 'name' => 'Amanat Payments', 'status' => 1],
        ];

        foreach ($types as $type) {
            DB::table('account_types')->updateOrInsert(
                ['id' => $type['id']],
                [
                    'name' => $type['name'],
                    'status' => $type['status'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );
        }
    }
}
