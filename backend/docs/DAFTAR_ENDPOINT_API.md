# DaetSys API - Daftar Endpoint

> Dokumen ini dibuat otomatis dari kode aplikasi.
> Jalankan `php artisan daetsys:docs` untuk memperbaruinya.
> Untuk mencoba request secara interaktif, buka `/docs` pada aplikasi yang sedang berjalan.

**Versi API:** 1.0.0

# DaetSys API

API untuk **Digital Asset & Evidence Tracking System** (DaetSys). Sistem ini melacak aset dan bukti fisik
beserta riwayat perpindahannya, dengan jejak audit yang tidak dapat dimanipulasi.

## Format Permintaan & Respons

Semua request dan respons memakai `Content-Type: application/json`.

**Respons sukses** dibungkus dengan `message` dan/atau `data`:

```json
{
    "message": "Assets retrieved successfully.",
    "data": [ ... ],
    "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 },
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

**Respons gagal** selalu memakai bentuk yang sama:

```json
{
    "message": "Email wajib diisi.",
    "errors": { "email": ["Email wajib diisi."] }
}
```

| Kode | Arti |
| --- | --- |
| `200 OK` | Permintaan berhasil. |
| `201 Created` | Data berhasil dibuat. |
| `401 Unauthorized` | Token hilang, kedaluwarsa, atau sudah dicabut. |
| `403 Forbidden` | Akun non-aktif, role tidak berwenang, atau aturan pemisahan tugas dilanggar. |
| `404 Not Found` | Data yang diminta tidak ada. |
| `409 Conflict` | Transfer ganda untuk aset yang sama sedang berjalan. |
| `422 Unprocessable Entity` | Validasi gagal. Rincian per field ada pada `errors`. |
| `429 Too Many Requests` | Melebihi batas rate limit. |

## Autentikasi

1. Kirim `POST /api/login` dengan `email` dan `password`.
2. Ambil `data.token` dari respons.
3. Sertakan pada setiap request: `Authorization: Bearer {token}`.

Token **tidak kedaluwarsa** dan dicabut pada `POST /api/logout` maupun saat akun dinonaktifkan.

## Role & Hak Akses

| Aksi | admin | officer | auditor |
| --- | :---: | :---: | :---: |
| Lihat aset, kategori, lokasi, transfer | ✅ | ✅ | ✅ |
| Buat / ubah aset | ✅ | ✅ | ❌ |
| Hapus aset | ✅ | ❌ | ❌ |
| Buat / ubah kategori & lokasi | ✅ | ❌ | ❌ |
| Ajukan transfer | ✅ | ✅ | ❌ |
| Setujui / tolak transfer | ✅ * | ❌ | ❌ |
| Serah terima / terima barang | ✅ | ✅ * | ❌ |
| Batalkan transfer | ✅ | ✅ * | ❌ |
| Baca jejak audit & laporan | ✅ | ❌ | ✅ |
| Kelola pengguna | ✅ | ❌ | ❌ |

`*` **Pemisahan tugas (separation of duties)** yang ditegakkan server-side:

- Approve/reject hanya oleh **admin**, dan **tidak boleh** atas transfer miliknya sendiri.
- Yang menyerahkan barang **tidak boleh** menjadi yang menerima.
- Officer hanya boleh membatalkan transfer miliknya sendiri; admin boleh membatalkan semua.
- Auditor tidak dapat mengubah data apa pun.

## Perpindahan Lokasi

Lokasi aset **hanya** berubah melalui workflow transfer. `PUT /api/assets/{id}` dengan `location_id` akan
ditolak `422` dan percobaannya dicatat pada jejak audit (`asset.location_change_attempt`).

## Idempotensi

Endpoint bertanda **Mendukung `Idempotency-Key`** menerima header `Idempotency-Key` berisi UUID unik.
Request dengan key yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda.
Header ini sangat disarankan untuk request POST agar aman ketika tombol diulang atau jaringan terputus.

## Rate Limit

| Limiter | Batas | Berlaku pada |
| --- | --- | --- |
| `login` | 5 / menit | `POST /api/login` |
| `api` | 120 / menit | Seluruh endpoint API |
| `reports` | 30 / menit | Seluruh endpoint laporan |

## Daftar Isi

### Autentikasi

Alur masuk, keluar, sesi pengguna, dan penggantian password. Seluruh endpoint di grup ini memakai token bearer bertipe Sanctum.

- `GET /health` - Cek kesehatan layanan
- `POST /login` - Masuk dan obtain token bearer
- `POST /logout` - Keluar dan cabut token
- `GET /me` - Profil pengguna saat ini
- `POST /change-password` - Ganti password sendiri

### Dashboard

Ringkasan metrik aset dan aktivitas yang dipakai untuk portrays pada halaman depan aplikasi.

- `GET /dashboard/summary` - Ringkasan dashboard
- `GET /dashboard/assets-by-location` - Jumlah aset per lokasi
- `GET /dashboard/assets-by-status` - Jumlah aset per status
- `GET /dashboard/recent-activities` - Aktivitas terbaru
- `GET /dashboard/pending-transfers` - Transfer yang menunggu keputusan

### Aset

Manajemen aset: registrasi, perubahan atribut, penghapusan (soft delete), pemindaian QR, dan riwayat aset.

- `GET /assets/qr/{qr_token}` - Cari aset lewat QR token
- `GET /assets/{asset}/history` - Riwayat aktivitas aset
- `POST /assets` - Daftarkan aset baru
- `GET /assets` - Daftar aset
- `GET /assets/{asset}` - Detail aset
- `PUT /assets/{asset}` - Perbarui aset
- `DELETE /assets/{asset}` - Hapus aset (soft delete)

### Kategori Aset

Master kategori aset, misalnya Elektronik, Furnitur, atau Peralatan Medis.

- `GET /asset-categories` - Daftar kategori aset
- `POST /asset-categories` - Buat kategori aset
- `GET /asset-categories/{asset_category}` - Detail kategori aset
- `PUT /asset-categories/{asset_category}` - Perbarui kategori aset
- `DELETE /asset-categories/{asset_category}` - Hapus kategori aset

### Lokasi

Master lokasi penyimpanan aset. Lokasi aset hanya berpindah melalui proses transfer.

- `GET /locations` - Daftar lokasi
- `POST /locations` - Buat lokasi
- `GET /locations/{location}` - Detail lokasi
- `PUT /locations/{location}` - Perbarui lokasi
- `DELETE /locations/{location}` - Hapus lokasi

### Transfer Aset

Alur perpindahan aset: pengajuan, persetujuan, serah terima, penerimaan, dan pembatalan. Grup ini menerapkan pemisahan tugas (separation of duties) secara server-side.

- `POST /transfers` - Ajukan perpindahan aset
- `GET /transfers` - Daftar transfer
- `GET /transfers/{transfer}` - Detail transfer beserta riwayatnya
- `POST /transfers/{transfer}/approve` - Setujui transfer
- `POST /transfers/{transfer}/reject` - Tolak transfer
- `POST /transfers/{transfer}/handover` - Catat serah terima barang
- `POST /transfers/{transfer}/receive` - Catat penerimaan barang
- `POST /transfers/{transfer}/cancel` - Batalkan transfer

### Jejak Audit

Catatan aktivitas append-only. Data tidak dapat diubah atau dihapus melalui API.

- `GET /activity-logs` - Daftar jejak audit
- `GET /activity-logs/{activityLog}` - Detail jejak audit

### Laporan

Rekap data aset, transfer, dan jejak audit dengan filter periode. Mendukung keluaran JSON atau CSV.

- `GET /reports/assets` - Laporan aset
- `GET /reports/transfers` - Laporan transfer
- `GET /reports/activity-logs` - Laporan jejak audit

### Notifikasi

Pemberitahuan internal untuk pengguna, misalnya pengajuan transfer baru yang menunggu persetujuan.

- `GET /notifications` - Daftar notifikasi
- `POST /notifications/{notification}/read` - Tandai notifikasi sudah dibaca
- `POST /notifications/read-all` - Tandai semua notifikasi sudah dibaca

### Manajemen Pengguna

Kelola akun, role, status aktif, dan reset password.

- `GET /users` - Daftar pengguna
- `POST /users` - Buat pengguna
- `GET /users/{user}` - Detail pengguna
- `PUT /users/{user}` - Perbarui pengguna
- `DELETE /users/{user}` - Hapus pengguna
- `POST /users/{user}/reset-password` - Reset password pengguna

## Detail Endpoint

### Aset

#### GET /assets/qr/{qr_token}

Cari aset lewat QR token

Mencari aset berdasarkan `qr_token` hasil pemindaian QR. Endpoint ini hanya untuk pembacaan, tidak memindahkan barang maupun mengubah lokasi.

**Catatan:**

- Dapat diakses semua role termasuk auditor.
- Aset tidak ditemukan akan menghasilkan `404`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `qr_token` | path | string | Ya | Token QR unik yang tertanam pada aset. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` |  |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### GET /assets/{asset}/history

Riwayat aktivitas aset

Menampilkan seluruh jejak audit yang terkait dengan satu aset, dari yang terbaru.

**Catatan:**

- Dapat diakses semua role termasuk auditor.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `asset` | path | integer | Ya | ID aset yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `ActivityLogResource` |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### POST /assets

Daftarkan aset baru

Mendaftarkan aset baru beserta kategori dan lokasi awal penempatannya. Sistem otomatis membuat `qr_token` unik untuk kebutuhan pemindaian QR.

**Catatan:**

- Hak akses: **admin** dan **officer**. Auditor mendapat `403`.
- Field `asset_code` dan `serial_number` harus unik. Format kode aset bebas, maksimal 100 karakter.
- Status yang boleh diinput: `available`, `maintenance`, `missing`. Status `in_transfer` **hanya** bisa diset oleh workflow transfer.
- Mendukung header `Idempotency-Key` untuk mencegah pembuatan ganda saat request diulang.
- Tercatat pada jejak audit dengan aksi `asset.created`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `Idempotency-Key` | header | string | Tidak | Kunci unik (UUID) untuk mencegah request dijalankan dua kali. Mengirim ulang dengan kunci yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `category_id` | integer | Ya |  |
| `location_id` | integer | null | Tidak |  |
| `asset_code` | string | Ya |  |
| `name` | string | Ya |  |
| `description` | string | null | Tidak |  |
| `serial_number` | string | null | Tidak |  |
| `status` | string | null (`available`, `maintenance`, `missing`, `null`) | Tidak |  |
| `acquisition_date` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `201` | Permintaan berhasil. Data baru dibuat dan dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### GET /assets

Daftar aset

Mengembalikan daftar aset dengan paginasi, pencarian, filter, dan pengurutan.

**Catatan:**

- Pencarian (`search`) mencocokkan `asset_code`, `name`, dan `serial_number`.
- Pengurutan hanya boleh memakai kolom yang tercantum pada `sort_by` (whitelist server-side).
- Hanya aset yang belum dihapus (soft delete) yang ditampilkan.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `sort_by` | query | string | null (`id`, `asset_code`, `name`, `status`, `acquisition_date`, `created_at`, `updated_at`, `null`) | Tidak |  |
| `sort_direction` | query | string | null (`asc`, `desc`, `null`) | Tidak |  |
| `category_id` | query | integer | null | Tidak |  |
| `location_id` | query | integer | null | Tidak |  |
| `status` | query | string | null (`available`, `in_transfer`, `maintenance`, `missing`, `null`) | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `AssetResource` |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |

#### GET /assets/{asset}

Detail aset

Mengembalikan detail satu aset lengkap dengan kategori dan lokasinya.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `asset` | path | integer | Ya | ID aset yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### PUT /assets/{asset}

Perbarui aset

Memperbarui atribut aset. Lokasi **tidak** termasuk bagian yang boleh diubah langsung di sini.

**Catatan:**

- Hak akses: **admin** dan **officer**.
- Field `location_id` bersifat `prohibited`. Mengirimnya akan menghasilkan `422` dengan pesan "Lokasi aset tidak dapat diubah langsung. Gunakan proses transfer."
- Percobaan mengubah lokasi dicatat pada jejak audit dengan aksi `asset.location_change_attempt` sebelum request ditolak, sehingga jejak manipulasi data tetap terlihat.
- Status yang boleh diinput: `available`, `maintenance`, `missing`.
- Mendukung header `Idempotency-Key`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `asset` | path | integer | Ya | ID aset yang akan diproses. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `category_id` | integer | Tidak |  |
| `location_id` | string | Tidak |  |
| `asset_code` | string | Tidak |  |
| `name` | string | Tidak |  |
| `description` | string | null | Tidak |  |
| `serial_number` | string | null | Tidak |  |
| `status` | string (`available`, `maintenance`, `missing`) | Tidak |  |
| `acquisition_date` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### DELETE /assets/{asset}

Hapus aset (soft delete)

Menandai aset sebagai terhapus. Data tidak benar-benar dihapus dari database sehingga riwayat transfer tetap utuh.

**Catatan:**

- Hak akses: **admin** saja.
- Aset yang sedang berstatus `in_transfer` tidak boleh dihapus.
- Tercatat pada jejak audit dengan aksi `asset.deleted`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `asset` | path | integer | Ya | ID aset yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

### Autentikasi

#### GET /health

Cek kesehatan layanan

Endpoint publik untuk memastikan API sedang berjalan dan terhubung ke database.

**Catatan:**

- Tidak memerlukan autentikasi.
- Cocok digunakan sebagai *health check* pada load balancer atau monitoring.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |

#### POST /login

Masuk dan obtain token bearer

Memverifikasi kredensial lalu menerbitkan token bearer baru (Laravel Sanctum). Token inilah yang dipakai pada seluruh request berikutnya melalui header `Authorization`.

**Catatan:**

- Rate limit: **5 percobaan per menit** per kombinasi email + IP. Melebihi batas akan menghasilkan `429`.
- Token **tidak memiliki masa kedaluwarsa**. Token dicabut saat pengguna memanggil `POST /api/logout`.
- Akun non-aktif **tidak dapat** login dan akan mendapat `403`.
- Percobaan login gagal tercatat pada jejak audit dengan aksi `auth.login_failed`.

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `email` | string | Ya |  |
| `password` | string | Ya |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |

#### POST /logout

Keluar dan cabut token

Mencabut token yang sedang dipakai sehingga token tersebut tidak lagi valid untuk request berikutnya.

**Catatan:**

- Hanya token milik permintaan ini yang dicabut, token lain milik pengguna yang sama tetap aktif.
- Tercatat pada jejak audit dengan aksi `auth.logged_out`.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### GET /me

Profil pengguna saat ini

Mengembalikan data pengguna yang sedang login beserta role dan hak aksesnya.

**Catatan:**

- Berguna untuk memverifikasi token dan menentukan tampilan antarmuka sesuai role.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### POST /change-password

Ganti password sendiri

Mengubah password pengguna yang sedang login.

**Catatan:**

- Password baru minimal 8 karakter dan harus mengandung huruf serta angka.
- Field `new_password` harus disertai `new_password_confirmation` dengan nilai yang sama.
- **Seluruh token milik pengguna dicabut**, termasuk token yang sedang dipakai. Pengguna harus login ulang.

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `current_password` | string | Ya |  |
| `new_password` | string | Ya |  |
| `new_password_confirmation` | string | Ya |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |

### Dashboard

#### GET /dashboard/summary

Ringkasan dashboard

Angka ringkas untuk portrays dashboard: total aset per status, total transfer aktif, serta aktivitas terbaru.

**Catatan:**

- Hasil di-cache selama 60 detik dan otomatis diinvalidasi setiap ada perubahan aset atau transfer.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### GET /dashboard/assets-by-location

Jumlah aset per lokasi

Distribusi aset berdasarkan lokasi penyimpanan, berguna untuk memonitor sebaran aset antar gedung.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### GET /dashboard/assets-by-status

Jumlah aset per status

Distribusi aset berdasarkan status: `available`, `in_transfer`, `maintenance`, dan `missing`.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### GET /dashboard/recent-activities

Aktivitas terbaru

Daftar jejak audit terbaru, berguna untuk memantau aktivitas yang baru saja terjadi pada sistem.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### GET /dashboard/pending-transfers

Transfer yang menunggu keputusan

Daftar transfer berstatus `pending` yang menunggu persetujuan admin, beserta pemohonnya.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

### Jejak Audit

#### GET /activity-logs

Daftar jejak audit

Mengembalikan daftar catatan aktivitas dengan paginasi, pencarian, dan filter user, aset, transfer, aksi, serta periode.

**Catatan:**

- Hak akses: **admin** dan **auditor** saja. Petugas mendapat `403`.
- Jejak audit bersifat **append-only**: tidak ada endpoint untuk mengubah atau menghapus log.
- Kolom `old_values` dan `new_values` berisi snapshot nilai sebelum dan sesudah perubahan.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `sort_by` | query | string | null (`id`, `action`, `created_at`, `null`) | Tidak |  |
| `sort_direction` | query | string | null (`asc`, `desc`, `null`) | Tidak |  |
| `user_id` | query | integer | null | Tidak |  |
| `asset_id` | query | integer | null | Tidak |  |
| `transfer_id` | query | integer | null | Tidak |  |
| `action` | query | string | null (`asset.created`, `asset.updated`, `asset.deleted`, `asset.location_change_attempt`, `transfer.requested`, `transfer.approved`, `transfer.rejected`, `transfer.handed_over`, `transfer.received`, `transfer.cancelled`, `transfer.conflict`, `location.created`, `location.updated`, `location.deleted`, `category.created`, `category.updated`, `category.deleted`, `user.created`, `user.updated`, `user.deleted`, `user.deactivated`, `auth.login_failed`, `auth.login_succeeded`, `auth.logged_out`, `auth.password_changed`, `auth.unauthorized_access`, `null`) | Tidak |  |
| `date_from` | query | string | null | Tidak |  |
| `date_to` | query | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `ActivityLogResource` |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |

#### GET /activity-logs/{activityLog}

Detail jejak audit

Mengembalikan detail satu catatan jejak audit beserta user dan aset terkaitnya.

**Catatan:**

- Hak akses: **admin** dan **auditor** saja.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `activityLog` | path | integer | Ya | ID jejak audit yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

### Kategori Aset

#### GET /asset-categories

Daftar kategori aset

Mengembalikan daftar kategori aset dengan paginasi, pencarian, dan pengurutan. Kolom `assets_count` hanya terisi bila request menambahkan `with_assets_count=true`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `sort_by` | query | string | null (`id`, `name`, `created_at`, `updated_at`, `null`) | Tidak |  |
| `sort_direction` | query | string | null (`asc`, `desc`, `null`) | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `AssetCategoryResource` |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |

#### POST /asset-categories

Buat kategori aset

Menambahkan kategori baru ke master kategori aset.

**Catatan:**

- Hak akses: **admin** saja.
- Nama kategori harus unik.

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `name` | string | Ya |  |
| `description` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `201` | Permintaan berhasil. Data baru dibuat dan dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### GET /asset-categories/{asset_category}

Detail kategori aset

Mengembalikan detail satu kategori aset.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `asset_category` | path | integer | Ya | ID kategori aset yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### PUT /asset-categories/{asset_category}

Perbarui kategori aset

Mengubah nama atau deskripsi kategori aset. Kategori yang masih dipakai aset atau transfer tidak dapat dihapus.

**Catatan:**

- Hak akses: **admin** saja.
- Nama tetap harus unik dan tidak boleh sama dengan kategori lain.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `asset_category` | path | integer | Ya | ID kategori aset yang akan diproses. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `name` | string | Tidak |  |
| `description` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### DELETE /asset-categories/{asset_category}

Hapus kategori aset

Menghapus kategori aset. Kategori yang masih dipakai aset atau transfer tidak dapat dihapus.

**Catatan:**

- Hak akses: **admin** saja.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `asset_category` | path | integer | Ya | ID kategori aset yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `409` |  |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

### Laporan

#### GET /reports/assets

Laporan aset

Rekap data aset dengan filter kategori, lokasi, status, periode, dan pencarian bebas.

**Catatan:**

- Gunakan `format=csv` untuk mengunduh berkas CSV (bukan JSON).
- Rate limit: **30 permintaan per menit** per pengguna.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `type` | query | string | null (`assets`, `transfers`, `activity-logs`, `null`) | Tidak |  |
| `format` | query | string | null (`json`, `csv`, `null`) | Tidak |  |
| `category_id` | query | integer | null | Tidak |  |
| `location_id` | query | integer | null | Tidak |  |
| `asset_id` | query | integer | null | Tidak |  |
| `transfer_id` | query | integer | null | Tidak |  |
| `user_id` | query | integer | null | Tidak |  |
| `requested_by` | query | integer | null | Tidak |  |
| `status` | query | string | null (`available`, `in_transfer`, `maintenance`, `missing`, `pending`, `approved`, `rejected`, `handover`, `completed`, `cancelled`, `null`) | Tidak |  |
| `action` | query | string | null (`asset.created`, `asset.updated`, `asset.deleted`, `asset.location_change_attempt`, `transfer.requested`, `transfer.approved`, `transfer.rejected`, `transfer.handed_over`, `transfer.received`, `transfer.cancelled`, `transfer.conflict`, `location.created`, `location.updated`, `location.deleted`, `category.created`, `category.updated`, `category.deleted`, `user.created`, `user.updated`, `user.deleted`, `user.deactivated`, `auth.login_failed`, `auth.login_succeeded`, `auth.logged_out`, `auth.password_changed`, `auth.unauthorized_access`, `null`) | Tidak |  |
| `date_from` | query | string | null | Tidak |  |
| `date_to` | query | string | null | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `AssetResource` |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### GET /reports/transfers

Laporan transfer

Rekap data transfer dengan filter status, aset, lokasi, pemohon, periode, dan pencarian bebas.

**Catatan:**

- Gunakan `format=csv` untuk mengunduh berkas CSV.
- Rate limit: **30 permintaan per menit** per pengguna.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `type` | query | string | null (`assets`, `transfers`, `activity-logs`, `null`) | Tidak |  |
| `format` | query | string | null (`json`, `csv`, `null`) | Tidak |  |
| `category_id` | query | integer | null | Tidak |  |
| `location_id` | query | integer | null | Tidak |  |
| `asset_id` | query | integer | null | Tidak |  |
| `transfer_id` | query | integer | null | Tidak |  |
| `user_id` | query | integer | null | Tidak |  |
| `requested_by` | query | integer | null | Tidak |  |
| `status` | query | string | null (`available`, `in_transfer`, `maintenance`, `missing`, `pending`, `approved`, `rejected`, `handover`, `completed`, `cancelled`, `null`) | Tidak |  |
| `action` | query | string | null (`asset.created`, `asset.updated`, `asset.deleted`, `asset.location_change_attempt`, `transfer.requested`, `transfer.approved`, `transfer.rejected`, `transfer.handed_over`, `transfer.received`, `transfer.cancelled`, `transfer.conflict`, `location.created`, `location.updated`, `location.deleted`, `category.created`, `category.updated`, `category.deleted`, `user.created`, `user.updated`, `user.deleted`, `user.deactivated`, `auth.login_failed`, `auth.login_succeeded`, `auth.logged_out`, `auth.password_changed`, `auth.unauthorized_access`, `null`) | Tidak |  |
| `date_from` | query | string | null | Tidak |  |
| `date_to` | query | string | null | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `TransferResource` |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### GET /reports/activity-logs

Laporan jejak audit

Rekap jejak audit dengan filter user, aset, transfer, jenis aksi, dan periode.

**Catatan:**

- Hanya tersedia untuk **admin** dan **auditor**.
- Rate limit: **30 permintaan per menit** per pengguna.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `type` | query | string | null (`assets`, `transfers`, `activity-logs`, `null`) | Tidak |  |
| `format` | query | string | null (`json`, `csv`, `null`) | Tidak |  |
| `category_id` | query | integer | null | Tidak |  |
| `location_id` | query | integer | null | Tidak |  |
| `asset_id` | query | integer | null | Tidak |  |
| `transfer_id` | query | integer | null | Tidak |  |
| `user_id` | query | integer | null | Tidak |  |
| `requested_by` | query | integer | null | Tidak |  |
| `status` | query | string | null (`available`, `in_transfer`, `maintenance`, `missing`, `pending`, `approved`, `rejected`, `handover`, `completed`, `cancelled`, `null`) | Tidak |  |
| `action` | query | string | null (`asset.created`, `asset.updated`, `asset.deleted`, `asset.location_change_attempt`, `transfer.requested`, `transfer.approved`, `transfer.rejected`, `transfer.handed_over`, `transfer.received`, `transfer.cancelled`, `transfer.conflict`, `location.created`, `location.updated`, `location.deleted`, `category.created`, `category.updated`, `category.deleted`, `user.created`, `user.updated`, `user.deleted`, `user.deactivated`, `auth.login_failed`, `auth.login_succeeded`, `auth.logged_out`, `auth.password_changed`, `auth.unauthorized_access`, `null`) | Tidak |  |
| `date_from` | query | string | null | Tidak |  |
| `date_to` | query | string | null | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `ActivityLogResource` |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

### Lokasi

#### GET /locations

Daftar lokasi

Mengembalikan daftar lokasi penyimpanan aset dengan paginasi, pencarian, dan filter `is_active`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `sort_by` | query | string | null (`id`, `code`, `name`, `is_active`, `created_at`, `null`) | Tidak |  |
| `sort_direction` | query | string | null (`asc`, `desc`, `null`) | Tidak |  |
| `is_active` | query | boolean | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `LocationResource` |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |

#### POST /locations

Buat lokasi

Menambahkan lokasi penyimpanan baru.

**Catatan:**

- Hak akses: **admin** saja.
- Kode lokasi harus unik.

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `code` | string | Ya |  |
| `name` | string | Ya |  |
| `address` | string | null | Tidak |  |
| `is_active` | boolean | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `201` | Permintaan berhasil. Data baru dibuat dan dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### GET /locations/{location}

Detail lokasi

Mengembalikan detail satu lokasi penyimpanan aset.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `location` | path | integer | Ya | ID lokasi penyimpanan yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### PUT /locations/{location}

Perbarui lokasi

Mengubah kode, nama, alamat, atau status aktif sebuah lokasi. Perubahan ini **tidak** memindahkan aset yang sudah berada di lokasi tersebut.

**Catatan:**

- Hak akses: **admin** saja.
- Kode lokasi tetap harus unik.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `location` | path | integer | Ya | ID lokasi penyimpanan yang akan diproses. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `code` | string | Tidak |  |
| `name` | string | Tidak |  |
| `address` | string | null | Tidak |  |
| `is_active` | boolean | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### DELETE /locations/{location}

Hapus lokasi

Menghapus lokasi penyimpanan. Lokasi yang sudah dipakai riwayat transaksi tidak dapat dihapus.

**Catatan:**

- Hak akses: **admin** saja.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `location` | path | integer | Ya | ID lokasi penyimpanan yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `409` |  |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

### Manajemen Pengguna

#### GET /users

Daftar pengguna

Mengembalikan daftar pengguna dengan paginasi, pencarian, dan filter role serta status aktif.

**Catatan:**

- Hak akses: **admin** saja.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `sort_by` | query | string | null (`id`, `name`, `email`, `is_active`, `created_at`, `null`) | Tidak |  |
| `sort_direction` | query | string | null (`asc`, `desc`, `null`) | Tidak |  |
| `role_id` | query | integer | null | Tidak |  |
| `is_active` | query | boolean | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `UserResource` |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |

#### POST /users

Buat pengguna

Menambahkan akun pengguna baru beserta role dan status aktifnya.

**Catatan:**

- Hak akses: **admin** saja.
- Email harus unik.
- Field `password` harus disertai `password_confirmation` dengan nilai yang sama, minimal 8 karakter, mengandung huruf dan angka.
- Nilai `role_id` harus menunjuk role yang dikenal (`admin`, `officer`, atau `auditor`).

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `name` | string | Ya |  |
| `email` | string | Ya |  |
| `password` | string | Ya |  |
| `role_id` | integer | Ya |  |
| `is_active` | boolean | Tidak |  |
| `password_confirmation` | string | Ya |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `201` | Permintaan berhasil. Data baru dibuat dan dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### GET /users/{user}

Detail pengguna

Mengembalikan detail satu pengguna beserta role-nya.

**Catatan:**

- Hak akses: **admin** saja.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `user` | path | integer | Ya | ID pengguna yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### PUT /users/{user}

Perbarui pengguna

Mengubah nama, email, role, status aktif, atau password pengguna.

**Catatan:**

- Hak akses: **admin** saja.
- Menonaktifkan akun (`is_active=false`) **otomatis mencabut seluruh token** milik pengguna tersebut, sehingga akses langsung terputus. Tercatat dengan aksi `user.deactivated`.
- Mengubah password melalui endpoint ini juga mencabut seluruh token pengguna.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `user` | path | integer | Ya | ID pengguna yang akan diproses. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `name` | string | Tidak |  |
| `email` | string | Tidak |  |
| `password` | string | null | Tidak |  |
| `role_id` | integer | Tidak |  |
| `is_active` | boolean | Tidak |  |
| `password_confirmation` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### DELETE /users/{user}

Hapus pengguna

Menghapus akun pengguna dari sistem.

**Catatan:**

- Hak akses: **admin** saja.
- Admin **tidak boleh** menghapus akunnya sendiri.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `user` | path | integer | Ya | ID pengguna yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### POST /users/{user}/reset-password

Reset password pengguna

Mengatur ulang password pengguna dan mencabut seluruh token miliknya.

**Catatan:**

- Hak akses: **admin** saja.
- Pengguna harus melakukan login ulang dengan password baru.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `user` | path | integer | Ya | ID pengguna yang akan diproses. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `password` | string | Ya |  |
| `password_confirmation` | string | Ya |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

### Notifikasi

#### GET /notifications

Daftar notifikasi

Mengembalikan notifikasi milik pengguna yang sedang login.

**Catatan:**

- Gunakan `unread_only=true` untuk hanya menampilkan notifikasi yang belum dibaca.
- Field `meta.unread_count` berisi jumlah notifikasi yang belum dibaca.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### POST /notifications/{notification}/read

Tandai notifikasi sudah dibaca

Menandai satu notifikasi milik pengguna yang sedang login sebagai sudah dibaca.

**Catatan:**

- Notifikasi milik pengguna lain tidak dapat diakses dan menghasilkan `404`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `notification` | path | string | Ya | ID notifikasi milik pengguna yang sedang login. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### POST /notifications/read-all

Tandai semua notifikasi sudah dibaca

Menandai seluruh notifikasi yang belum dibaca milik pengguna yang sedang login sebagai sudah dibaca.

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

### Transfer Aset

#### POST /transfers

Ajukan perpindahan aset

Pemohon mengajukan perpindahan aset ke lokasi tujuan. Transfer baru berstatus `pending` dan aset otomatis berubah menjadi `in_transfer` sehingga tidak bisa dipindahkan lagi.

**Catatan:**

- Hak akses: **admin** dan **officer**.
- Aset harus berstatus `available`. Aset yang sedang `in_transfer` akan ditolak.
- Lokasi tujuan harus berbeda dari lokasi saat ini.
- **Pencegahan transfer ganda:** kolom `active_asset_id` pada tabel `transfers` memiliki indeks UNIQUE, sehingga hanya boleh ada satu transfer aktif untuk satu aset. Percobaan kedua pada aset yang sama akan menghasilkan `409 Conflict` (juga tercatat dengan aksi `transfer.conflict`).
- Menggunakan `lockForUpdate` sehingga request bersamaan tetap aman.
- Status awal adalah `pending` dan **wajib** disetujui admin sebelum serah terima bisa dilakukan.
- Mendukung header `Idempotency-Key` untuk mencegah pengajuan ganda saat tombol diulang.
- Tercatat pada jejak audit dengan aksi `transfer.requested`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `Idempotency-Key` | header | string | Tidak | Kunci unik (UUID) untuk mencegah request dijalankan dua kali. Mengirim ulang dengan kunci yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `asset_id` | integer | Ya |  |
| `to_location_id` | integer | Ya |  |
| `reason` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `201` | Permintaan berhasil. Data baru dibuat dan dikembalikan pada `data`. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### GET /transfers

Daftar transfer

Mengembalikan daftar transfer aset dengan paginasi, pencarian, dan filter status, aset, pemohon, serta periode.

**Catatan:**

- Pencarian (`search`) mencocokkan `asset_code` dan `name` aset yang dipindahkan.
- Filter tanggal (`date_from` / `date_to`) disaring terhadap waktu pengajuan (`requested_at`).

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `page` | query | integer | Tidak |  |
| `per_page` | query | integer | Tidak |  |
| `search` | query | string | null | Tidak |  |
| `sort_by` | query | string | null (`id`, `status`, `requested_at`, `completed_at`, `created_at`, `null`) | Tidak |  |
| `sort_direction` | query | string | null (`asc`, `desc`, `null`) | Tidak |  |
| `status` | query | string | null (`pending`, `approved`, `rejected`, `handover`, `completed`, `cancelled`, `null`) | Tidak |  |
| `asset_id` | query | integer | null | Tidak |  |
| `requested_by` | query | integer | null | Tidak |  |
| `date_from` | query | string | null | Tidak |  |
| `date_to` | query | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Paginated set of `TransferResource` |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |

#### GET /transfers/{transfer}

Detail transfer beserta riwayatnya

Mengembalikan detail satu transfer lengkap dengan riwayat persetujuan (`approvals`) dan data serah terima (`handover`).

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `transfer` | path | integer | Ya | ID transfer yang akan diproses. |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |

#### POST /transfers/{transfer}/approve

Setujui transfer

Menyetujui pengajuan transfer sehingga status berpindah dari `pending` menjadi `approved`.

**Catatan:**

- Hak akses: **admin** saja.
- **Pemisahan tugas:** admin yang menjadi pemohon transfer tersebut **tidak boleh** menyetujui transfer miliknya sendiri. Percobaan akan menghasilkan `403`.
- Hanya transfer berstatus `pending` yang bisa disetujui. Status lain menghasilkan `422`.
- Tercatat pada jejak audit dengan aksi `transfer.approved`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `transfer` | path | integer | Ya | ID transfer yang akan diproses. |
| `Idempotency-Key` | header | string | Tidak | Kunci unik (UUID) untuk mencegah request dijalankan dua kali. Mengirim ulang dengan kunci yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `notes` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### POST /transfers/{transfer}/reject

Tolak transfer

Menolak pengajuan transfer sehingga status berpindah dari `pending` menjadi `rejected` dan aset kembali bebas.

**Catatan:**

- Hak akses: **admin** saja, dengan aturan pemisahan tugas yang sama seperti persetujuan.
- Field `notes` (alasan penolakan) **wajib** diisi.
- Tercatat pada jejak audit dengan aksi `transfer.rejected`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `transfer` | path | integer | Ya | ID transfer yang akan diproses. |
| `Idempotency-Key` | header | string | Tidak | Kunci unik (UUID) untuk mencegah request dijalankan dua kali. Mengirim ulang dengan kunci yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `notes` | string | Ya |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### POST /transfers/{transfer}/handover

Catat serah terima barang

Mencatat bahwa barang telah diserahkan dari lokasi asal. Status transfer berpindah dari `approved` menjadi `handover`.

**Catatan:**

- Hak akses: **admin** dan **officer**.
- Hanya transfer berstatus `approved` yang bisa diserahterimakan.
- Data serah terima disimpan pada tabel `asset_handover` beserta petugas yang menyerahkan.
- Tercatat pada jejak audit dengan aksi `transfer.handed_over`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `transfer` | path | integer | Ya | ID transfer yang akan diproses. |
| `Idempotency-Key` | header | string | Tidak | Kunci unik (UUID) untuk mencegah request dijalankan dua kali. Mengirim ulang dengan kunci yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `notes` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### POST /transfers/{transfer}/receive

Catat penerimaan barang

Mencatat bahwa barang telah diterima di lokasi tujuan. Hanya pada endpoint inilah **lokasi aset diperbarui** ke lokasi tujuan dan status aset kembali menjadi `available`.

**Catatan:**

- Hak akses: **admin** dan **officer**.
- **Pemisahan tugas:** petugas yang melakukan serah terima **tidak boleh** menjadi penerima yang sama. Percobaan menghasilkan `403`.
- Hanya transfer berstatus `handover` yang bisa diterima.
- Status transfer final menjadi `completed` dan `active_asset_id` dilepas, sehingga aset boleh dipindahkan kembali.
- Tercatat pada jejak audit dengan aksi `transfer.received`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `transfer` | path | integer | Ya | ID transfer yang akan diproses. |
| `Idempotency-Key` | header | string | Tidak | Kunci unik (UUID) untuk mencegah request dijalankan dua kali. Mengirim ulang dengan kunci yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `notes` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

#### POST /transfers/{transfer}/cancel

Batalkan transfer

Membatalkan transfer yang sedang berjalan. Aset kembali ke status semula dan dilepas dari transfer aktif.

**Catatan:**

- Hak akses: **admin** dapat membatalkan transfer mana pun; **officer** hanya dapat membatalkan transfer miliknya sendiri.
- Hanya transfer berstatus `pending`, `approved`, atau `handover` yang dapat dibatalkan.
- Tercatat pada jejak audit dengan aksi `transfer.cancelled`.

**Parameter**

| Nama | Lokasi | Tipe | Wajib | Keterangan |
| --- | --- | --- | :---: | --- |
| `transfer` | path | integer | Ya | ID transfer yang akan diproses. |
| `Idempotency-Key` | header | string | Tidak | Kunci unik (UUID) untuk mencegah request dijalankan dua kali. Mengirim ulang dengan kunci yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda. |

**Body Request** (`application/json`)

| Field | Tipe | Wajib | Keterangan |
| --- | --- | :---: | --- |
| `reason` | string | null | Tidak |  |

**Respons**

| Kode | Keterangan |
| --- | --- |
| `200` | Permintaan berhasil. Hasil dikembalikan pada `data`. |
| `404` | Data yang diminta tidak ditemukan. |
| `401` | Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut. |
| `422` | Validasi gagal. Rincian per field tersedia pada `errors`. |
| `403` | Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar. |

