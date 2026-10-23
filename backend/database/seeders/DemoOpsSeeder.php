<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Role;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Generator data demo operasional: 30 aset dengan segala kondisi dan
 * 10 transfer yang menyentuh seluruh status workflow.
 *
 * Jalankan manual: php artisan db:seed --class=DemoOpsSeeder
 *
 * - Aset: 24 tersedia (dengan lokasi) yang sebagian berubah menjadi
 *   in_transfer oleh workflow, 2 perawatan, 2 hilang, 2 tanpa penempatan.
 * - Transfer: 3 pending, 2 approved, 1 handover, 2 completed, 1 rejected,
 *   1 cancelled — dibuat lewat TransferService sehingga audit trail,
 *   notifikasi, perubahan lokasi, dan penjaga concurrency semuanya nyata.
 *
 * Seeder ini idempotent: memakai updateOrCreate untuk user demo dan
 * menghitung urutan kode aset yang sudah ada (prefix DMO-) sebelum membuat.
 */
class DemoOpsSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment('local')) {
            $this->command?->warn('Seeder demo hanya dijalankan di lingkungan local.');

            return;
        }

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            AssetCategorySeeder::class,
            LocationSeeder::class,
        ]);

        $admin = $this->ensureUser('admin@daetsys.local', 'Administrator', 'admin');
        $officer = $this->ensureUser('officer@daetsys.local', 'Petugas Lapangan', 'officer');
        $receiver = $this->ensureUser('officer2@daetsys.local', 'Petugas Gudang', 'officer');

        // ---------- 30 ASET DENGAN SEGALA KONDISI ----------

        $locations = Location::query()->where('is_active', true)->orderBy('id')->get();
        $categories = AssetCategory::query()->orderBy('id')->get();

        $assetPlans = [
            ['name' => 'Laptop Dell Latitude 5420'],
            ['name' => 'Laptop Lenovo ThinkPad E14'],
            ['name' => 'Monitor LED 24 inci IPS'],
            ['name' => 'Printer Inkjet Epson L3210'],
            ['name' => 'Pemindai Dokumen Fujitsu fi-7160'],
            ['name' => 'Proyektor Epson EB-X51'],
            ['name' => 'Server Rak 12U'],
            ['name' => 'Meja Kerja 140 cm'],
            ['name' => 'Kursi Kerja Ergonomis'],
            ['name' => 'Lemari Arsip 4 Pintu'],
            ['name' => 'Rak Dokumen Steel'],
            ['name' => 'Sofa Ruang Tunggu'],
            ['name' => 'Motor Operasional Honda BeAT'],
            ['name' => 'Sepeda Dinas Polygon'],
            ['name' => 'Kursi Lipat Gudang'],
            ['name' => 'Switch Managed 48 Port'],
            ['name' => 'Access Point Ubiquiti UniFi'],
            ['name' => 'UPS APC 1500VA'],
            ['name' => 'Kamera CCTV Outdoor'],
            ['name' => 'Sound System Ruang Meeting'],
            ['name' => 'Mikrofon Wireless Shure'],
            ['name' => 'Mixer Audio 8 Channel'],
            ['name' => 'Genset Portable 2 kVA'],
            ['name' => 'Alat Ukur Multimeter Digital'],
            ['name' => 'Laptop Rusak Menunggu Perbaikan', 'status' => AssetStatus::Maintenance],
            ['name' => 'Printer Tinta Bocor 3, Menunggu Perbaikan', 'status' => AssetStatus::Maintenance],
            ['name' => 'Kamera Mirrorless Hilang di Wisata', 'status' => AssetStatus::Missing],
            ['name' => 'Harddisk Eksternal Hilang', 'status' => AssetStatus::Missing],
            ['name' => 'Perangkat Demo Lapangan', 'withoutLocation' => true],
            ['name' => 'Tablet Inventaris Pilot', 'withoutLocation' => true],
        ];

        $startSequence = Asset::withTrashed()
            ->where('asset_code', 'like', 'DMO-2026-%')
            ->count();

        $availablePool = collect();

        foreach ($assetPlans as $offset => $plan) {
            $code = sprintf('DMO-2026-%04d', $startSequence + $offset + 1);
            $status = $plan['status'] ?? AssetStatus::Available;
            $locationId = null;

            if ($status === AssetStatus::Available && empty($plan['withoutLocation'])) {
                $locationId = $locations[$offset % $locations->count()]->id;
            }

            $asset = Asset::create([
                'category_id' => $categories[$offset % $categories->count()]->id,
                'location_id' => $locationId,
                'asset_code' => $code,
                'qr_token' => Str::random(40),
                'name' => $plan['name'],
                'description' => 'Data demo peserta perencanaan.',
                'serial_number' => $status === AssetStatus::Missing ? null : strtoupper(Str::random(8)),
                'status' => $status->value,
                'acquisition_date' => now()->subMonths($offset + 3)->toDateString(),
            ]);

            // Asikron: aset tersedia dengan lokasi siap untuk dipindahkan.
            if ($status === AssetStatus::Available && $locationId !== null) {
                $availablePool->push($asset);
            }
        }

        // ---------- 10 TRANSFER DENGAN SEGALA KONDISI ----------
        $transfers = app(TransferService::class);

        $reasons = [
            'Pindahan unit kerja baru',
            'Penyeimbangan stok antar gedung',
            'Kebutuhan operasional harian',
            'Respon permintaan divisi',
            'Konsolidasi gudang',
        ];

        $planNext = function () use (&$availablePool): Asset {
            $asset = $availablePool->shift();

            if ($asset === null) {
                throw new \RuntimeException('Aset demo tersedia habis.');
            }

            return $asset;
        };

        $nextLocation = function (Asset $asset) use ($locations): int {
            $current = $locations->search(fn (Location $location) => $location->id === $asset->location_id);

            return $locations[($current + 1) % $locations->count()]->id;
        };

        $step = 0;

        // 3 transfer pending
        foreach ([0, 1, 2] as $index) {
            $asset = $planNext();
            $transfers->request($officer, $asset->id, $nextLocation($asset), $reasons[$index % count($reasons)], null);
        }

        // 2 transfer approved
        foreach ([3, 4] as $index) {
            $asset = $planNext();
            $transfer = $transfers->request($officer, $asset->id, $nextLocation($asset), $reasons[$index % count($reasons)], null);
            $transfers->approve($admin, $transfer, 'Menunggu serah terima oleh pihak tujuan.', null);
        }

        // 1 transfer di tahap serah terima
        $asset = $planNext();
        $transfer = $transfers->request($officer, $asset->id, $nextLocation($asset), 'Barang telah diserahkan di lokasi.', null);
        $transfers->approve($admin, $transfer, 'Menunggu serah terima oleh pihak tujuan.', null);
        $transfers->handover($admin, $transfer, 'Serah terima fisik di gudang tujuan.', null);

        // 2 transfer completed lokasi aset sudah berganti
        foreach ([7, 8] as $index) {
            $asset = $planNext();
            $transfer = $transfers->request($officer, $asset->id, $nextLocation($asset), $reasons[$index % count($reasons)], null);
            $transfers->approve($admin, $transfer, 'Menunggu serah terima oleh pihak tujuan.', null);
            $transfers->handover($admin, $transfer, 'Serah terima fisik di gudang tujuan.', null);
            $transfers->receive($receiver, $transfer, 'Penerimaan dipastikan oleh petugas tujuan.', null);
        }

        // 1 transfer rejected (aset kembali available)
        $asset = $planNext();
        $transfer = $transfers->request($officer, $asset->id, $nextLocation($asset), 'Tidak ada shelf space gedung tujuan.', null);
        $transfers->reject($admin, $transfer, 'Ditolak: aset lebih dibutuhkan di lokasi asal.', null);

        // 1 transfer dibatalkan pengajunya (aset kembali available)
        $asset = $planNext();
        $transfer = $transfers->request($officer, $asset->id, $nextLocation($asset), 'Rencana sempat berubah.', null);
        $transfers->cancel($officer, $transfer, 'Permintaan pembatalan terhadap pengaju sendiri.', null);

        // Ragam tanggal pengajuan agar daftar terurut menarik di UI.
        Transfer::query()
            ->where('status', '!=', TransferStatus::Completed->value)
            ->orderBy('id')
            ->take(3)
            ->get()
            ->each(fn (Transfer $pending, int $index) => $pending->update([
                'requested_at' => now()->subDays($index + 4),
            ]));

        $this->printSummary();
    }

    private function ensureUser(string $email, string $name, string $roleName): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role_id' => Role::query()->where('name', $roleName)->value('id'),
                'is_active' => true,
            ],
        );
    }

    private function printSummary(): void
    {
        $counts = Asset::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->command?->info(sprintf(
            'Demo selesai: 30 aset baru (available=%d, in_transfer=%d, maintenance=%d, missing=%d) dan 10 transfer (3 pending, 2 approved, 1 handover, 2 completed, 1 rejected, 1 cancelled).',
            $counts->get('available', 0),
            $counts->get('in_transfer', 0),
            $counts->get('maintenance', 0),
            $counts->get('missing', 0),
        ));
    }
}
