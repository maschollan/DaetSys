<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            ['code' => 'GD-A', 'name' => 'Gedung A - Lantai 1', 'address' => 'Jl. Merdeka No. 1'],
            ['code' => 'GD-B', 'name' => 'Gedung B - Lantai 2', 'address' => 'Jl. Merdeka No. 2'],
            ['code' => 'GD-C', 'name' => 'Gedung C - Gudang', 'address' => 'Jl. Merdeka No. 3'],
            ['code' => 'GD-D', 'name' => 'Gedung D - Arsip', 'address' => 'Jl. Merdeka No. 4'],
            ['code' => 'GD-E', 'name' => 'Gedung E - Pelayanan', 'address' => 'Jl. Merdeka No. 5'],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(['code' => $location['code']], $location + ['is_active' => true]);
        }
    }
}
