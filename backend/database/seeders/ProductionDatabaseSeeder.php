<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Seeder untuk lingkungan production: hanya menyiapkan struktur dasar
 * yang dibutuhkan aplikasi (roles) dan SATU akun admin awal agar
 * platform bisa diakses pertama kali.
 *
 * Jalankan: php artisan db:seed --class=ProductionDatabaseSeeder --force
 *
 * - Tidak membuat data dummy apa pun (aset, kategori, lokasi, transfer).
 * - Kredensial admin awal dibaca dari env BOOTSTRAP_ADMIN_*, sehingga
 *   tidak ada credential hardcoded di repository.
 * - Idempotent: memakai firstOrCreate / updateOrCreate.
 *
 * Setelah login pertama, segera ganti password admin melalui halaman
 * Profil (POST change-password mencabut seluruh token).
 */
class ProductionDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment('production')) {
            $this->command?->warn('ProductionDatabaseSeeder hanya dijalankan di lingkungan production.');

            return;
        }

        $this->call(RoleSeeder::class);

        $this->createBootstrapAdmin();
    }

    /**
     * Membuat (atau memperbarui) akun admin awal dari konfigurasi env.
     */
    private function createBootstrapAdmin(): void
    {
        $email = (string) env('BOOTSTRAP_ADMIN_EMAIL', '');
        $password = (string) env('BOOTSTRAP_ADMIN_PASSWORD', '');
        $name = (string) env('BOOTSTRAP_ADMIN_NAME', 'Administrator');

        if ($email === '' || $password === '') {
            throw new RuntimeException(
                'BOOTSTRAP_ADMIN_EMAIL dan BOOTSTRAP_ADMIN_PASSWORD wajib diisi '.PHP_EOL.
                'di environment sebelum menjalankan ProductionDatabaseSeeder.'
            );
        }

        $this->assertPasswordPolicy($password);

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        $this->command?->info(sprintf('Admin awal siap: %s', $email));
        $this->command?->warn('Segera ganti password admin setelah login pertama via halaman Profil.');
    }

    /**
     * Menegakkan kebijakan password yang sama dengan FormRequest
     * aplikasi (minimal 8 karakter, memuat huruf dan angka).
     */
    private function assertPasswordPolicy(string $password): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => [Password::min(8)->letters()->numbers()]]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
