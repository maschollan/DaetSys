<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            return;
        }

        $adminRole = Role::where('name', 'admin')->first();
        $officerRole = Role::where('name', 'officer')->first();
        $auditorRole = Role::where('name', 'auditor')->first();

        User::updateOrCreate(
            ['email' => 'admin@daetsys.local'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'officer@daetsys.local'],
            [
                'name' => 'Petugas Lapangan',
                'password' => Hash::make('password'),
                'role_id' => $officerRole?->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'auditor@daetsys.local'],
            [
                'name' => 'Auditor Internal',
                'password' => Hash::make('password'),
                'role_id' => $auditorRole?->id,
                'is_active' => true,
            ]
        );
    }
}
