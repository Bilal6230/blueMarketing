<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\facing;

class FacingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Facing::create([
            'name'       => 'Nothing',
            'is_active'     => '1',
            'create_by'      => '1',

        ]);
        Facing::create([
            'name'       => 'Park Facing',
            'is_active'     => '1',
            'create_by'      => '1',

        ]);
        Facing::create([
            'name'       => 'Mosque Facing',
            'is_active'     => '1',
            'create_by'      => '1',
        ]);
        Facing::create([
            'name'       => 'Doubal Road',
            'is_active'     => '1',
            'create_by'      => '1',
        ]);


    }
}
