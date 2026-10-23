# Test Report

- Date: 2026-10-23 (final verification Tahap 8)
- Framework: Pest 4 (Laravel plugin)
- Result: **44/44 tests passed** (264 assertions)
- DB: `daetsys_v1_test` (MySQL, terpisah dari `daetsys_v1`)
- Commands: `vendor/bin/pint --dirty --format=agent` → **passed**; `php artisan test --compact` → **passed**
- Frontend: production build `npm run build` → **OK** (Vite 8, ✓ 243 modules, built in ~800 ms); tidak ada test suite frontend.

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

## Verifikasi Runtime Tahap 7–8 (Hardening & Final)

Dijalankan terhadap aplikasi yang berjalan: backend `php artisan serve :8000`, frontend dev `:5173`, token bearer admin. Semua hasil di bawah adalah pengujian nyata pada 2026-10-23.

### Backend test suite (Tahap 7: 5 skenario, role, validasi, transaksi & concurrency)

44/44 test / 264 assertions lulus, mencakup:

| Area bukti (dari test) | Cakupan |
| --- | --- |
| 5 skenario utama | Login/auth, CRUD aset, workflow transfer (proses→selesai), notifikasi, jejak audit. |
| Akses role | RBAC admin/officer/auditor: officer ditolak kelola users & audit log (403), auditor read-only, admin full. |
| Validasi API | FormRequest per endpoint: 422 dengan `errors` per field (termasuk uji payload tidak valid). |
| Transaksi & concurrency | Transfer ganda per aset ditolak (409, idempotency key), idempotent replay `201` + header `Idempotent-Replayed: true`, pisah tugas (approval/penerimaan), DB transactional test (RefreshDatabase). |
| Indeks & query | Pembatasan `LIMIT`, `per_page` whitelist, filter/sort whitelist; bulk annotation jangan N+1. |

### Evaluasi indeks & query

Indeks telah terpasang di migrasi dan dipetakan ke pola query aplikasi:

| Tabel | Indeks | Melayani |
| --- | --- | --- |
| `assets` | UNIQUE `asset_code`, `qr_token`, `serial_number`; idx `status`, `category_id`, `(location_id, status)`, `(status, created_at)`, `created_at` | lookup detail by kode/token/seri, filter status, dropdown lokasi aktif, sort terbaru. |
| `transfers` | UNIQUE `active_asset_id` (satu transfer aktif per aset), UNIQUE `uniqid`; idx `(status, requested_at)`, `requested_by`, `asset_id`, `(from_location_id, to_location_id)`, `created_at` | guard concurrency transfer aktif, deep-link uniqueid, filter tab status+tanggal, daftar per pengaju. |
| `activity_logs` | idx `(user_id, created_at)`, `(asset_id, created_at)`, `(transfer_id, created_at)`, `(action, created_at)`, `created_at` | filter audit per entitas + rentang waktu, sort waktu. |
| `locations` | UNIQUE `code`, idx `(is_active, name)` | dropdown lokasi aktif terurut. |
| `approval/handover` | UNIQUE `transfer_id`; idx `(transfer_id, created_at)`, `(approver_id, created_at)` | riwayat approval per transfer, audit per approver. |
| `notifications` | idx `(notifiable_type, notifiable_id, read_at)` | hitung unread + daftar per user. |
| `personal_access_tokens` | UNIQUE `token`, idx `expires_at` | autentikasi bearer (lookup O(1)) & pemutihan token kadaluarsa. |

### Probe runtime API (bearer admin, 2026-10-23)

| Probe | Hasil |
| --- | --- |
| `GET /api/assets?per_page=25&page=1` (admin) | `200` |
| `GET /api/transfers?per_page=25&page=1` (admin) | `200` |
| `GET /api/dashboard/summary` (admin) | `200` |
| `GET /api/reports/assets?per_page=5` (admin) | `200` |
| `GET /api/reports/activity-logs?format=csv` (admin) | `200 text/csv; charset=UTF-8` |
| `GET /api/assets` tanpa token | `401` |
| `GET /api/transfers/bogus-uniqid-xyz` | `404` |
| `POST /api/assets` payload kosong | `422` |
| `POST /api/transfers` payload tidak valid (aset 999999) | `422` |

### Frontend (Tahap 6–8)

| Verifikasi | Hasil |
| --- | --- |
| Refactor 30/30 file Vue dari `<script setup>` ke Options API | Selesai — grep `<script setup>` di `frontend/src` = 0 hasil. |
| Composable → Options API | `useListQuery` → mixin `src/mixins/listQuery.js` (opsi kustom `listConfig`), `useModalEscape` → mixin `src/mixins/modalEscape.js`, `useTheme.js` → modul murni; composable lama dihapus. |
| `npm run format` + `npm run build` | Lulus penuh setelah setiap batch (terakhir: ✓ 243 modules, built in 806 ms). |
| Frontend test suite | Tidak tersedia di proyek ini (Tahap 8 poin "jika tersedia" → N/A). |
