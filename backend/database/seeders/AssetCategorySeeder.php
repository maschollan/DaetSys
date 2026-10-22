<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use Illuminate\Database\Seeder;

class AssetCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Elektronik', 'description' => 'Perangkat elektronik dan komputer'],
            ['name' => 'Furnitur', 'description' => 'Meja, kursi, lemari'],
            ['name' => 'Kendaraan', 'description' => 'Kendaraan dinas'],
            ['name' => 'Alat Tulis', 'description' => 'Perlengkapan kantor'],
            ['name' => 'Peralatan Medis', 'description' => 'Peralatan kesehatan'],
        ];

        foreach ($categories as $category) {
            AssetCategory::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
