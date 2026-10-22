<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => UserRole::Admin->value,
                'description' => 'Administrator - dapat mengelola seluruh data dan pengguna',
            ],
            [
                'name' => UserRole::Officer->value,
                'description' => 'Petugas - dapat mengajukan transfer dan melakukan serah terima',
            ],
            [
                'name' => UserRole::Auditor->value,
                'description' => 'Auditor - hanya dapat melihat audit trail dan laporan',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
