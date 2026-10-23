# DaetSys — Digital Asset & Evidence Tracking System

Sistem pelacakan aset/evidence berbasis web: **cataloging** aset dengan kode & QR token unik,
**pencatatan alih lokasi** (transfer) dengan alur persetujuan berjenjang, serta **jejak audit
menyeluruh** untuk setiap perubahan penting.

Dibangun untuk kebutuhan pencatatan barang milik organisasi yang menuntut pertanggjawaban:
siapa mengajukan, siapa menyetujui, siapa menyerahkan, siapa menerima, dan kapan semuanya
terjadi — semuanya tercatat dan dapat ditelusuri.

> **Cara tercepat memahami sistem ini:**
> 1. Organisasi mencatat barangnya di halaman **Aset**, tiap aset mendapat kode unik + token QR
>    yang bisa dicetak dan ditempel pada fisik barang.
> 2. Barang perlu pindah ruangan? Petugas mengajukan **Transfer** → Admin menyetujui →
>    petugas merekam **Serah Terima** → penerima berbeda mengonfirmasi **Penerimaan**.
>    Lokasi barang di seluruh sistem hanya berubah pada saat penerimaan dikonfirmasi.
> 3. Setiap aksi di jalannya workflow terekam otomatis di **Jejak Audit**, dan pemilik
>    akun terkait menerima **notifikasi**.

---

## Fitur

| Modul | Isi |
| --- | --- |
| **Dashboard** | 4 endpoint ringkasan: jumlah aset per status & per lokasi (grafik Chart.js), aktivitas terbaru, transfer menunggu persetujuan. Hasil di-cache 60 detik dan otomatis diinvalidasi. |
| **Master Data** | Kategori aset & lokasi (kode unik, aktif/nonaktif, statistik pemakaian, proteksi penghapusan bila masih dirujuk transaksi). |
| **Aset** | CRUD aset, kode unik + QR token acak, status (`available` / `in_transfer` / `maintenance` / `missing`), nomor seri unik, detail + **riwayat per-aset**, unduh QR, perubahan lokasi **hanya** lewat transfer yang sah. |
| **Transfer** | Pengajuan → persetujuan → serah terima → penerimaan, plus tolak/batalkan dengan alasan. Enam aksi workflow masing-masing diverifikasi oleh Policy backend. |
| **Lacak QR** | Tempel token / unggah gambar QR / **pindai langsung kamera** (jsQR) → profil & lokasi barang terakhir. |
| **Notifikasi** | In-app notification per pengguna (badge unread, dropdown, halaman riwayat dengan infinite scroll), link langsung ke konteks terkait. |
| **Jejak Audit** | Log append-only semua aksi kritis (asset/transfer/auth/user) memuat aktor, IP, user-agent, nilai lama/baru; filter & pencarian; read-only untuk admin & auditor. |
| **Laporan** | Laporan aset, transfer, jejak audit dengan filter (status, kategori, lokasi, rentang tanggal) + unduh **CSV**. |
| **Pengguna** | Manajemen pengguna & role (admin), reset password (mencabut semua sesi), aktif/nonaktif; profil mandiri (ubah nama & password sendiri). |

## Konsep Utama

- **RBAC 3 role** — `admin` (penuh), `officer` (operasional aset/transfer), `auditor`
  (read-only termasuk jejak audit). UI menyembunyikan menu yang tak relevan, namun
  **otorisasi sesungguhnya ditegakkan di backend** (Policy + middleware) — selalu.
  Screenshot/detail halaman tidak pernah menjadi batas keamanan.
- **Separation of duties pada workflow transfer** — admin tidak boleh menyetujui
  transfer yang diajukan sendiri; pemberi serah terima tidak boleh jadi penerima;
  hanya satu transfer aktif per aset (unique DB constraint, bukan cek aplikasi).
- **Snapshot pada transaksi** — lokasi asal/tujuan transfer disimpan sebagai snapshot
  sehingga tetap telusuri walau data master lokasi diubah.
- **Idempotency-Key** — request create (aset/transfer) yang dikirim ulang karena
  gangguan jaringan tidak menghasilkan duplikat; key sama dengan payload berbeda → `409`.
- **Deep-link state** — filter/pencarian/sort/halaman disinkronkan ke URL query
  (mis. `/assets?status=missing`), sehingga tautan dapat dibagikan dan dibukanya
  langsung menampilkan kondisi yang sama.
- **Audit trail tunggal** — semua pencatatan melewati satu `AuditService`
  (format konsisten + scrubbing nilai sensitif seperti password/token).
- **uniqid sejak upgrade v1** — detail transfer dan penyertaan audit memakai
  identifier `uniqid` yang tidak terurut/tertebak, menghindari enumerasi ID
  berurutan bahkan dari orang dalam.

## Teknologi

| Lapisan | Teknologi |
| --- | --- |
| **Frontend** | Vue 3 (**Options API** murni: `data/computed/methods/watch` + mixins) + Vite, Bootstrap 5 (tema light/dark), Pinia, Chart.js, qrcode + jsQR, Bootstrap Icons |
| **Backend** | Laravel (PHP 8.3), Laravel Sanctum (Bearer token), MySQL |
| **API** | REST JSON — format paginator Laravel `{ data, links, meta }`, validasi `422` dengan `errors` per field, error `403/404/409` yang ramah |

## Struktur Repo

```
DaetSys/
├── PLAN.MD        # Spesifikasi implementasi master (sumber kebenaran desain)
├── README.md
├── backend/       # API Laravel (app, routes/api.php, database, tests)
│   └── TEST_REPORT.md   # Hasil pengujian yang benar-benar dijalankan
├── frontend/      # SPA Vue 3 (src: components, layouts, views, stores, mixins)
├── package.json   # Skrip root: menjalankan backend + frontend sekaligus
└── node_modules/
```

Halaman frontend: Dashboard, Aset (list & detail+QR), Transfer (list & detail+timeline),
Lacak QR, Notifikasi, Laporan, Lokasi/Kategori/Pengguna, Jejak Audit, Profil.

## Menjalankan Proyek

**Prasyarat:** PHP 8.3 + Composer, Node.js 20+, MySQL.

### 1. Backend

```bash
cd backend
composer install
cp .env.example .env        # sesuaikan DB_*, FRONTEND_URL (CORS)
php artisan key:generate
php artisan migrate --seed  # seed role + akun contoh
php artisan serve           # http://localhost:8000
```

Seeder demo (hanya aktif di environment `local`) mengisi data contoh: 30 aset
dengan 10 workflow transfer berbagai status supaya grafik & daftar tidak kosong.

### 2. Frontend

```bash
cd frontend
npm install
cp .env.example .env        # VITE_API_BASE_URL=http://localhost:8000/api
npm run dev                 # http://localhost:5173
```

### 3. Sekaligus

Gunakan `npm run dev` dari folder root (menjalankan `php artisan serve` dan
`npm run dev` bersamaan via `concurrently`).

### Akun demo (seed)

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@daetsys.local` | `password` |
| Petugas | `officer@daetsys.local` | `password` |
| Petugas 2 | `officer2@daetsys.local` | `password` |
| Auditor | `auditor@daetsys.local` | `password` |

Contoh alur transfer: `officer@` mengajukan → `admin@` menyetujui → salah satu petugas
merekam serah terima → petugas **lain** mengonfirmasi penerimaan.

## API Dokumentasi Interaktif

Backend menyajikan halaman dokumentasi interaktif langsung dari aplikasi yang
sama — setiap endpoint (skema, query param, contoh respons, header `Idempotency-Key`
untuk create) selalu cocok dengan route yang terdaftar:

```
GET http://localhost:8000/docs          (UI HTML, butuh token admin aktif)
GET http://localhost:8000/docs/api.json
```

Cakupan route diverifikasi otomatis oleh test suite (`DocumentationTest`).

## Pengujian & Kualitas

```bash
# Backend — Pest: 44 test / 267 assertions (skenario wajib, RBAC, validasi, concurrency)
cd backend && php artisan test --compact
cd backend && vendor/bin/pint --dirty --format=agent

# Frontend — build produksi (belum ada test suite JS)
cd frontend && npm run build
```

Cakupan test backend mencakup: login/token/logout, lima skenario wajib,
RBAC per endpoint, validasi FormRequest, idempotent replay & payload konflik,
proteksi transactional terhadap transfer ganda, serta penghapusan data yang masih
dirujuk riwayat. Ringkasan terkini ada di `backend/TEST_REPORT.md`.

## Catatan Kerja

- Perubahan lokasi aset **hanya bisa** melalui transfer yang sah — endpoint update
  aset melarang field `location_id` dan percobaan ilegal terekam di audit
  (`asset.location_change_attempt`).
- Password pengguna wajib 8+ karakter, mengandung huruf & angka; reset password
  mencabut **semua** token pengguna terkait.
- Hindari data dummy di produksi: seeder demo hanya berjalan di environment `local`.
