# Test Report

- Date: 2026-10-22
- Framework: Pest 4 (Laravel plugin)
- Result: **26/26 tests passed** (208 assertions)
- DB: `daetsys_v1_test` (MySQL, terpisah dari `daetsys_v1`)
- Command: `php artisan test`

## Cakupan

| Berkas | Cakupan |
| --- | --- |
| `tests/Feature/Api/AuthTest.php` | Login, penolakan akun non-aktif, pencabutan token saat logout, profil pengguna. |
| `tests/Feature/Api/AssetTest.php` | RBAC aset (admin/officer/auditor), larangan hapus aset oleh non-admin, larangan ubah `location_id`. |
| `tests/Feature/Api/AuditTest.php` | Pencatatan percobaan ubah lokasi, hak akses jejak audit, penolakan officer. |
| `tests/Feature/Api/DocumentationTest.php` | Halaman `/docs`, dokumen `/docs/api.json`, skema bearer, cakupan route, ringkasan/deskripsi per endpoint, header `Idempotency-Key`, akses terbatas admin aktif. |
| `tests/Feature/Workflow/TransferWorkflowTest.php` | Pencegahan transfer ganda, pemisahan tugas, larangan pemberi serah terima menjadi penerima, pembaruan lokasi hanya setelah penerimaan. |

## Verifikasi Manual Dokumentasi Interaktif

Seluruh klaim pada dokumentasi diverifikasi langsung terhadap aplikasi yang berjalan:

| Skenario | Hasil |
| --- | --- |
| `POST /api/login` tanpa token | `401 Unauthenticated` |
| `GET /api/me` dengan token | `200` + profil pengguna |
| `POST /api/assets` dengan `Idempotency-Key` | `201 Created` |
| Pengulangan request dengan key sama | `201` replay + header `Idempotent-Replayed: true` |
| Key sama dengan payload berbeda | `409 Conflict` |
| `PUT /api/assets/{id}` dengan `location_id` | `422` + tercatat `asset.location_change_attempt` |
| Transfer kedua untuk aset yang sama | `409 Conflict` |
| Admin menyetujui transfer miliknya sendiri | `403 Forbidden` |
| Officer menyetujui transfer | `403 Forbidden` |
| Pemberi serah terima menerima sendiri | `403 Forbidden` |
| Penerimaan oleh pihak berbeda | `200`, lokasi aset berubah ke lokasi tujuan, status kembali `available` |
| Officer membaca jejak audit | `403 Forbidden` |
| Auditor membaca jejak audit | `200 OK` |
| `GET /api/reports/assets?format=csv` | `200 text/csv` + header `Content-Disposition` |
| Validasi gagal | `422` dengan `errors` per field |
| Endpoint tidak ditemukan | `404 Resource not found.` |

## Perbaikan yang Ditemukan Saat Verifikasi

| Temuan | Perbaikan |
| --- | --- |
| `IdempotencyService` melempar `IdempotentReplay` pada namespace yang salah sehingga retry idempoten menghasilkan `500`. | Ditambahkan `use App\Exceptions\IdempotentReplay;`. |
| Penghapusan pengguna yang masih dirujuk riwayat transaksi menghasilkan `500` (FK violation). | ditangkap dan dipetakan menjadi `409 Conflict` yang ramah. |
| Dokumentasi tidak memeriksa status aktif akun. | Gate `viewApiDocs` kini mensyaratkan akun aktif. |
