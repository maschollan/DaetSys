# Technical Case Study: Digital Asset & Evidence Tracking System

## Gambaran Sistem

DaetSys adalah aplikasi web untuk mencatat barang milik organisasi, mengetahui lokasi barang, dan menelusuri perpindahannya. Aplikasi membantu menggantikan pencatatan yang tersebar di lembar kerja dan dokumen dengan riwayat yang dapat dicari.

Kode lengkap tersedia di [repositori DaetSys di GitHub](https://github.com/maschollan/DaetSys). Aplikasi menggunakan [Laravel untuk backend](https://github.com/maschollan/DaetSys/tree/main/backend), [Vue untuk antarmuka](https://github.com/maschollan/DaetSys/tree/main/frontend), dan database relasional. Konfigurasi lokal menggunakan MySQL; konfigurasi deployment produksi di repo menggunakan PostgreSQL.

### Cara kerja dan peran

Setiap barang memiliki kode dan QR unik. Petugas dapat mengajukan perpindahan barang. Admin meninjau permintaan, lalu proses serah terima dicatat. Petugas penerima mengonfirmasi penerimaan. Lokasi barang baru berubah setelah konfirmasi tersebut.

- **Admin** mengelola pengguna dan data utama, serta meninjau permintaan perpindahan.
- **Petugas** mencatat dan mencari barang, mengajukan perpindahan, serta melakukan serah terima sesuai tugasnya.
- **Auditor** meninjau data dan riwayat tanpa mengubah data operasional.

Hak akses diperiksa oleh backend. Contohnya, [middleware peran](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Middleware/EnsureRole.php#L17-L30) menolak tindakan yang tidak sesuai dengan akun, sedangkan [Policy transfer](https://github.com/maschollan/DaetSys/blob/main/backend/app/Policies/TransferPolicy.php#L32-L36) mencegah admin menyetujui permintaan perpindahannya sendiri.

## Fitur utama

- **Login dan peran:** route terlindungi memeriksa token serta status akun aktif ([route API](https://github.com/maschollan/DaetSys/blob/main/backend/routes/api.php#L28-L33), [middleware akun aktif](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Middleware/EnsureUserIsActive.php#L18-L31)).
- **Data barang:** kode aset dan nomor seri divalidasi agar tidak duplikat ([validasi aset](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Requests/StoreAssetRequest.php#L20-L35)); indeks unik database menjadi pemeriksaan tambahan ([migrasi aset](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000005_create_assets_table.php#L15-L19)).
- **QR barang:** token acak dibuat oleh [QrTokenService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/QrTokenService.php#L21-L28) dan dapat dipakai untuk mencari barang.
- **Perpindahan:** alur pengajuan, persetujuan, serah terima, dan penerimaan dikelola oleh [TransferService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L40-L124).
- **Riwayat dan laporan:** [AuditService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/AuditService.php#L41-L60) mencatat pengguna, barang atau transfer, waktu, dan perubahan. Kata sandi serta token disamarkan ([daftar nilai sensitif](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/AuditService.php#L21-L35)).
- **Dashboard:** jumlah barang dihitung dengan agregasi database dan ringkasan disimpan dalam cache selama waktu yang dikonfigurasi ([DashboardService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/DashboardService.php#L26-L61)).

## Skenario 1 — Dua petugas mengajukan perpindahan barang yang sama

**Masalah:** dua permintaan bersamaan dapat mencoba memindahkan barang yang sama.

**Solusi:** aplikasi mengunci catatan barang selama proses transaksi, memeriksa statusnya, lalu membuat transfer. Database juga memiliki indeks unik pada `active_asset_id` untuk membatasi satu transfer aktif per barang.

```php
// Bagian ini dijalankan di dalam transaksi pengajuan transfer.
$asset = Asset::query()->lockForUpdate()->find($assetId);

if ($asset->status !== AssetStatus::Available) {
    throw BusinessRuleException::conflict('Asset is not available for transfer.');
}

// Transfer aktif dibuat setelah pemeriksaan ini.
```

Contoh ini berasal dari [TransferService, pengajuan transfer](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L47-L103). Pengaman database ada di [migrasi transfers](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000006_create_transfers_table.php#L23-L37).

**Hasil yang diharapkan:** hanya satu transfer aktif yang dapat tercatat untuk satu barang. Permintaan yang bertabrakan harus ditolak sebagai konflik. Catatan teknis: penerjemahan error indeks unik di kode saat ini mengenali kode MySQL; pada konfigurasi PostgreSQL produksi, status respons konflik `409` perlu dipastikan dan disesuaikan.

## Skenario 2 — Petugas mencoba mengubah lokasi langsung lewat API

**Masalah:** perubahan lokasi langsung dapat melewati persetujuan dan tidak memiliki catatan serah terima.

**Solusi:** Form Request melarang `location_id` pada endpoint pembaruan aset. Jika field itu dikirim, request ditolak dan upaya tersebut dicatat.

```php
return [
    'category_id' => ['sometimes', 'integer', 'exists:asset_categories,id'],
    'location_id' => ['prohibited'],
    'asset_code' => ['sometimes', 'string', 'max:100', /* validasi unik */],
];
```

Lihat [aturan validasi UpdateAssetRequest](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Requests/UpdateAssetRequest.php#L29-L61) dan [pencatatan upaya perubahan lokasi](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Requests/UpdateAssetRequest.php#L64-L83).

**Hasil yang diharapkan:** lokasi hanya berubah melalui proses transfer yang sah, dan upaya melewati proses itu dapat ditinjau di riwayat audit.

## Skenario 3 — Auditor perlu menelusuri barang yang hilang

**Masalah:** lokasi barang telah berubah beberapa kali dan perlu diketahui siapa yang mengajukan, menyetujui, menyerahkan, serta menerima barang.

**Solusi:** setiap aktivitas penting ditautkan ke akun, barang, dan transfer. Riwayat barang diurutkan menurut waktu dan dapat ditelusuri melalui endpoint riwayat.

```php
$this->audit->log(
    ActivityAction::TransferReceived,
    user: $receiver,
    asset: $asset,
    transfer: $locked,
    oldValues: ['location_id' => $locked->from_location_id],
    newValues: [
        'location_id' => $locked->to_location_id,
        'received_by' => $receiver->id,
    ],
    request: $request,
);
```

Contoh pencatatan penerimaan ada di [TransferService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L351-L366). Riwayat per barang dilayani oleh [AssetController](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Controllers/Api/AssetController.php#L118-L132), dan tabel audit memiliki indeks untuk pencarian berdasarkan barang, transfer, pengguna, dan waktu ([migrasi activity logs](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000009_create_activity_logs_table.php#L23-L27)).

**Hasil yang diharapkan:** auditor dapat melihat rantai perpindahan dan pihak yang tercatat pada setiap langkah. Riwayat membantu investigasi, tetapi tidak otomatis membuktikan siapa yang secara fisik memegang barang ketika barang hilang.

## Skenario 4 — Gangguan terjadi saat transfer berlangsung

**Masalah:** jika aplikasi berhenti di tengah proses, data transfer, lokasi barang, dan catatan audit dapat berbeda satu sama lain.

**Solusi:** perubahan terkait dilakukan dalam satu transaksi database. Jika transaksi gagal sebelum selesai, database membatalkan perubahan di dalam transaksi. Pemberitahuan dijalankan setelah transaksi berhasil di-commit.

```php
// Di dalam DB::transaction():
$asset = Asset::query()->lockForUpdate()->find($assetId);
$transfer = Transfer::create([/* data perpindahan */]);
$asset->update(['status' => AssetStatus::InTransfer->value]);
$this->audit->log(
    ActivityAction::TransferRequested,
    user: $requester,
    asset: $asset,
    transfer: $transfer,
);

DB::afterCommit(fn () => $this->notifications->notifyTransferRequested($transfer));
```

Contoh lengkap tersedia di [TransferService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L47-L119). Request yang terkirim ulang juga dapat memakai `Idempotency-Key`; [middleware idempotensi](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Middleware/EnsureIdempotency.php#L13-L40) mengembalikan respons yang tersimpan untuk request yang sama.

**Hasil yang diharapkan:** data transfer dan barang tidak tertinggal dalam keadaan setengah diperbarui. Pengiriman ulang request yang sama tidak membuat transfer duplikat.

## Skenario 5 — 500.000 barang dan jutaan aktivitas

**Masalah:** tabel yang besar dapat membuat pencarian, dashboard, dan laporan lambat.

**Langkah yang sudah ada di kode:**

1. **Indeks database** pada kolom yang umum dipakai untuk filter, seperti status, kategori, lokasi, dan tanggal ([migrasi aset](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000005_create_assets_table.php#L25-L29)).
2. **Agregasi database dan cache** untuk ringkasan dashboard ([DashboardService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/DashboardService.php#L26-L61)).
3. **Paginasi hasil JSON** supaya daftar biasa tidak mengirim semua baris sekaligus ([ReportService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/ReportService.php#L23-L40)).

**Yang masih perlu dibuktikan:** optimasi tersebut belum membuktikan target kecepatan pada 500.000 barang. Pencarian yang memakai pola “mengandung kata” dapat tetap memeriksa banyak baris, dan ekspor CSV aset saat ini mengambil seluruh hasil dengan `get()` ([ReportService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/ReportService.php#L95-L105)). Sebelum mengklaim siap untuk skala itu, jalankan pengujian beban dan ukur query pada database serta infrastruktur target. Ekspor besar dapat dipertimbangkan untuk diproses bertahap atau melalui antrean pekerjaan.

## Pengujian dan deployment

Repository memiliki pengujian fitur untuk autentikasi, aset, transfer, audit, dashboard, dan laporan. Contohnya dapat dilihat pada [pengujian workflow transfer](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Workflow/TransferWorkflowTest.php), [pengujian dashboard](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/DashboardApiTest.php), dan [pengujian laporan](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/ReportApiTest.php). Pengujian yang ada membantu memeriksa aturan dan alur, tetapi belum menjadi bukti bahwa dua request benar-benar berjalan pada saat yang sama atau bahwa sistem memenuhi target performa skala besar.

Konfigurasi deployment menggunakan PostgreSQL melalui [Render Blueprint](https://github.com/maschollan/DaetSys/blob/main/render.yaml#L20-L37). Karena sebagian penanganan konflik kode masih spesifik MySQL, kompatibilitas error database dan pengujian integritas perlu dirapikan sebelum mengandalkan perilaku konflik yang sama di produksi.

### Lingkungan produksi (live)

| Layanan | URL |
|---------|-----|
| Aplikasi web (frontend) | https://daetsys.onrender.com |
| API backend | https://daetsysapi.onrender.com |
| Dokumentasi API (hanya admin) | https://daetsysapi.onrender.com/docs |

Akun admin default untuk mengakses dokumen API di `/docs` (disediakan oleh seeder produksi):

- **Email:** `admin@daetsys.prod`
- **Kata sandi:** `AdminTerus99!`

Kredensial diambil dari variabel lingkungan produksi (`BOOTSTRAP_ADMIN_*` di `.env.production`) dan dapat diganti setelah deployment pertama.

## Kesimpulan

DaetSys menyediakan pencatatan aset, QR, persetujuan perpindahan, serah terima, riwayat audit, dashboard, dan laporan. Perlindungan utamanya berasal dari pemeriksaan akses di backend, transaksi database, penguncian data saat transfer, dan batasan unik database.

Sebelum menyatakan seluruh skenario produksi telah tuntas, pekerjaan lanjutan yang paling penting adalah menguji concurrency dengan request paralel sungguhan, menyamakan penanganan konflik MySQL dan PostgreSQL, serta mengukur pencarian dan ekspor pada volume data besar.

## Tautan untuk reviewer

- [Repositori lengkap DaetSys](https://github.com/maschollan/DaetSys)
- [Route API](https://github.com/maschollan/DaetSys/blob/main/backend/routes/api.php)
- [TransferService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php)
- [AuditService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/AuditService.php)
- [Frontend Vue](https://github.com/maschollan/DaetSys/tree/main/frontend/src)
