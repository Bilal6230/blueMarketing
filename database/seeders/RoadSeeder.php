<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Road;

class RoadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Road::create([
            'name'       => '60ft Road',
            'is_active'     => '1',
            'create_by'      => '1',

        ]);
        Road::create([
            'name'       => '80ft Road',
            'is_active'     => '1',
            'create_by'      => '1',

        ]);
        Road::create([
            'name'       => '55ft Road',
            'is_active'     => '1',
            'create_by'      => '1',

        ]);
        Road::create([
            'name'       => '40ft Road',
            'is_active'     => '1',
            'create_by'      => '1',
        ]);
        Road::create([
            'name'       => '35ft Road',
            'is_active'     => '1',
            'create_by'      => '1',
        ]);
        Road::create([
            'name'       => '30ft Road',
            'is_active'     => '1',
            'create_by'      => '1',
        ]);
        Road::create([
            'name'       => '20ft Road',
            'is_active'     => '1',
            'create_by'      => '1',
        ]);
    }
}
