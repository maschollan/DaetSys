<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Production: hanya struktur dasar + satu admin awal (tanpa data dummy).
     * Local/testing: data contoh lengkap untuk pengembangan.
     */
    public function run(): void
    {
        if (App::environment('production')) {
            $this->call([
                ProductionDatabaseSeeder::class,
            ]);

            return;
        }

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            AssetCategorySeeder::class,
            LocationSeeder::class,
        ]);
    }
}
