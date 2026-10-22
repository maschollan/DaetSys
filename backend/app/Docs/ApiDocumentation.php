<?php

namespace App\Docs;

use App\Providers\DocsServiceProvider;

/**
 * Sumber tunggal untuk seluruh teks dokumentasi API.
 *
 * Isi kelas ini dipakai oleh {@see DocsServiceProvider} untuk
 * mengisi ringkasan, deskripsi, pengelompokan tag, serta catatan header
 * idempotensi pada dokumen OpenAPI yang dihasilkan otomatis dari kode.
 */
final class ApiDocumentation
{
    /**
     * Informasi umum API yang ditampilkan pada halaman dokumentasi.
     *
     * @return array{title: string, version: string, description: string}
     */
    public static function info(): array
    {
        return [
            'title' => 'DaetSys API',
            'version' => '1.0.0',
            'description' => self::generalDescription(),
        ];
    }

    /**
     * Pengelompokan endpoint beserta penjelasannya.
     *
     * @return array<string, string>
     */
    public static function tags(): array
    {
        return [
            'Autentikasi' => 'Alur masuk, keluar, sesi pengguna, dan penggantian password. '
                .'Seluruh endpoint di grup ini memakai token bearer bertipe Sanctum.',
            'Dashboard' => 'Ringkasan metrik aset dan aktivitas yang dipakai untuk portrays pada halaman depan aplikasi.',
            'Aset' => 'Manajemen aset: registrasi, perubahan atribut, penghapusan (soft delete), pemindaian QR, dan riwayat aset.',
            'Kategori Aset' => 'Master kategori aset, misalnya Elektronik, Furnitur, atau Peralatan Medis.',
            'Lokasi' => 'Master lokasi penyimpanan aset. Lokasi aset hanya berpindah melalui proses transfer.',
            'Transfer Aset' => 'Alur perpindahan aset: pengajuan, persetujuan, serah terima, penerimaan, dan pembatalan. '
                .'Grup ini menerapkan pemisahan tugas (separation of duties) secara server-side.',
            'Jejak Audit' => 'Catatan aktivitas append-only. Data tidak dapat diubah atau dihapus melalui API.',
            'Laporan' => 'Rekap data aset, transfer, dan jejak audit dengan filter periode. Mendukung keluaran JSON atau CSV.',
            'Notifikasi' => 'Pemberitahuan internal untuk pengguna, misalnya pengajuan transfer baru yang menunggu persetujuan.',
            'Manajemen Pengguna' => 'Kelola akun, role, status aktif, dan reset password.',
        ];
    }

    /**
     * Ringkasan dan deskripsi tiap operation.
     *
     * Kunci adalah `operationId` pada dokumen OpenAPI.
     *
     * @return array<string, array{summary: string, description: string, tags: list<string>}>
     */
    public static function operations(): array
    {
        return [
            // ------------------------------------------------------------------
            // Autentikasi
            // ------------------------------------------------------------------
            'health' => [
                'summary' => 'Cek kesehatan layanan',
                'description' => self::description(
                    'Endpoint publik untuk memastikan API sedang berjalan dan terhubung ke database.',
                    [
                        'Tidak memerlukan autentikasi.',
                        'Cocok digunakan sebagai *health check* pada load balancer atau monitoring.',
                    ],
                ),
                'tags' => ['Autentikasi'],
            ],
            'login' => [
                'summary' => 'Masuk dan obtain token bearer',
                'description' => self::description(
                    'Memverifikasi kredensial lalu menerbitkan token bearer baru (Laravel Sanctum). '
                    .'Token inilah yang dipakai pada seluruh request berikutnya melalui header `Authorization`.',
                    [
                        'Rate limit: **5 percobaan per menit** per kombinasi email + IP. Melebihi batas akan menghasilkan `429`.',
                        'Token **tidak memiliki masa kedaluwarsa**. Token dicabut saat pengguna memanggil `POST /api/logout`.',
                        'Akun non-aktif **tidak dapat** login dan akan mendapat `403`.',
                        'Percobaan login gagal tercatat pada jejak audit dengan aksi `auth.login_failed`.',
                    ],
                ),
                'tags' => ['Autentikasi'],
            ],
            'logout' => [
                'summary' => 'Keluar dan cabut token',
                'description' => self::description(
                    'Mencabut token yang sedang dipakai sehingga token tersebut tidak lagi valid untuk request berikutnya.',
                    [
                        'Hanya token milik permintaan ini yang dicabut, token lain milik pengguna yang sama tetap aktif.',
                        'Tercatat pada jejak audit dengan aksi `auth.logged_out`.',
                    ],
                ),
                'tags' => ['Autentikasi'],
            ],
            'me' => [
                'summary' => 'Profil pengguna saat ini',
                'description' => self::description(
                    'Mengembalikan data pengguna yang sedang login beserta role dan hak aksesnya.',
                    [
                        'Berguna untuk memverifikasi token dan menentukan tampilan antarmuka sesuai role.',
                    ],
                ),
                'tags' => ['Autentikasi'],
            ],
            'change-password' => [
                'summary' => 'Ganti password sendiri',
                'description' => self::description(
                    'Mengubah password pengguna yang sedang login.',
                    [
                        'Password baru minimal 8 karakter dan harus mengandung huruf serta angka.',
                        'Field `new_password` harus disertai `new_password_confirmation` dengan nilai yang sama.',
                        '**Seluruh token milik pengguna dicabut**, termasuk token yang sedang dipakai. Pengguna harus login ulang.',
                    ],
                ),
                'tags' => ['Autentikasi'],
            ],

            // ------------------------------------------------------------------
            // Dashboard
            // ------------------------------------------------------------------
            'dashboard.summary' => [
                'summary' => 'Ringkasan dashboard',
                'description' => self::description(
                    'Angka ringkas untuk portrays dashboard: total aset per status, total transfer aktif, serta aktivitas terbaru.',
                    [
                        'Hasil di-cache selama 60 detik dan otomatis diinvalidasi setiap ada perubahan aset atau transfer.',
                    ],
                ),
                'tags' => ['Dashboard'],
            ],
            'dashboard.assets-by-location' => [
                'summary' => 'Jumlah aset per lokasi',
                'description' => self::description(
                    'Distribusi aset berdasarkan lokasi penyimpanan, berguna untuk memonitor sebaran aset antar gedung.',
                ),
                'tags' => ['Dashboard'],
            ],
            'dashboard.assets-by-status' => [
                'summary' => 'Jumlah aset per status',
                'description' => self::description(
                    'Distribusi aset berdasarkan status: `available`, `in_transfer`, `maintenance`, dan `missing`.',
                ),
                'tags' => ['Dashboard'],
            ],
            'dashboard.recent-activities' => [
                'summary' => 'Aktivitas terbaru',
                'description' => self::description(
                    'Daftar jejak audit terbaru, berguna untuk memantau aktivitas yang baru saja terjadi pada sistem.',
                ),
                'tags' => ['Dashboard'],
            ],
            'dashboard.pending-transfers' => [
                'summary' => 'Transfer yang menunggu keputusan',
                'description' => self::description(
                    'Daftar transfer berstatus `pending` yang menunggu persetujuan admin, beserta pemohonnya.',
                ),
                'tags' => ['Dashboard'],
            ],

            // ------------------------------------------------------------------
            // Aset
            // ------------------------------------------------------------------
            'assets.index' => [
                'summary' => 'Daftar aset',
                'description' => self::description(
                    'Mengembalikan daftar aset dengan paginasi, pencarian, filter, dan pengurutan.',
                    [
                        'Pencarian (`search`) mencocokkan `asset_code`, `name`, dan `serial_number`.',
                        'Pengurutan hanya boleh memakai kolom yang tercantum pada `sort_by` (whitelist server-side).',
                        'Hanya aset yang belum dihapus (soft delete) yang ditampilkan.',
                    ],
                ),
                'tags' => ['Aset'],
            ],
            'assets.store' => [
                'summary' => 'Daftarkan aset baru',
                'description' => self::description(
                    'Mendaftarkan aset baru beserta kategori dan lokasi awal penempatannya. '
                    .'Sistem otomatis membuat `qr_token` unik untuk kebutuhan pemindaian QR.',
                    [
                        'Hak akses: **admin** dan **officer**. Auditor mendapat `403`.',
                        'Field `asset_code` dan `serial_number` harus unik. Format kode aset bebas, maksimal 100 karakter.',
                        'Status yang boleh diinput: `available`, `maintenance`, `missing`. Status `in_transfer` **hanya** bisa diset oleh workflow transfer.',
                        'Mendukung header `Idempotency-Key` untuk mencegah pembuatan ganda saat request diulang.',
                        'Tercatat pada jejak audit dengan aksi `asset.created`.',
                    ],
                ),
                'tags' => ['Aset'],
            ],
            'assets.show' => [
                'summary' => 'Detail aset',
                'description' => self::description(
                    'Mengembalikan detail satu aset lengkap dengan kategori dan lokasinya.',
                ),
                'tags' => ['Aset'],
            ],
            'assets.update' => [
                'summary' => 'Perbarui aset',
                'description' => self::description(
                    'Memperbarui atribut aset. Lokasi **tidak** termasuk bagian yang boleh diubah langsung di sini.',
                    [
                        'Hak akses: **admin** dan **officer**.',
                        'Field `location_id` bersifat `prohibited`. Mengirimnya akan menghasilkan `422` dengan pesan '
                        .'"Lokasi aset tidak dapat diubah langsung. Gunakan proses transfer."',
                        'Percobaan mengubah lokasi dicatat pada jejak audit dengan aksi `asset.location_change_attempt` sebelum request ditolak, '
                        .'sehingga jejak manipulasi data tetap terlihat.',
                        'Status yang boleh diinput: `available`, `maintenance`, `missing`.',
                        'Mendukung header `Idempotency-Key`.',
                    ],
                ),
                'tags' => ['Aset'],
            ],
            'assets.destroy' => [
                'summary' => 'Hapus aset (soft delete)',
                'description' => self::description(
                    'Menandai aset sebagai terhapus. Data tidak benar-benar dihapus dari database sehingga riwayat transfer tetap utuh.',
                    [
                        'Hak akses: **admin** saja.',
                        'Aset yang sedang berstatus `in_transfer` tidak boleh dihapus.',
                        'Tercatat pada jejak audit dengan aksi `asset.deleted`.',
                    ],
                ),
                'tags' => ['Aset'],
            ],
            'assets.qr' => [
                'summary' => 'Cari aset lewat QR token',
                'description' => self::description(
                    'Mencari aset berdasarkan `qr_token` hasil pemindaian QR. Endpoint ini hanya untuk pembacaan, '
                    .'tidak memindahkan barang maupun mengubah lokasi.',
                    [
                        'Dapat diakses semua role termasuk auditor.',
                        'Aset tidak ditemukan akan menghasilkan `404`.',
                    ],
                ),
                'tags' => ['Aset'],
            ],
            'assets.history' => [
                'summary' => 'Riwayat aktivitas aset',
                'description' => self::description(
                    'Menampilkan seluruh jejak audit yang terkait dengan satu aset, dari yang terbaru.',
                    [
                        'Dapat diakses semua role termasuk auditor.',
                    ],
                ),
                'tags' => ['Aset'],
            ],

            // ------------------------------------------------------------------
            // Kategori Aset
            // ------------------------------------------------------------------
            'asset-categories.index' => [
                'summary' => 'Daftar kategori aset',
                'description' => self::description(
                    'Mengembalikan daftar kategori aset dengan paginasi, pencarian, dan pengurutan. '
                    .'Kolom `assets_count` hanya terisi bila request menambahkan `with_assets_count=true`.',
                ),
                'tags' => ['Kategori Aset'],
            ],
            'asset-categories.store' => [
                'summary' => 'Buat kategori aset',
                'description' => self::description(
                    'Menambahkan kategori baru ke master kategori aset.',
                    [
                        'Hak akses: **admin** saja.',
                        'Nama kategori harus unik.',
                    ],
                ),
                'tags' => ['Kategori Aset'],
            ],
            'asset-categories.show' => [
                'summary' => 'Detail kategori aset',
                'description' => self::description(
                    'Mengembalikan detail satu kategori aset.',
                ),
                'tags' => ['Kategori Aset'],
            ],
            'asset-categories.update' => [
                'summary' => 'Perbarui kategori aset',
                'description' => self::description(
                    'Mengubah nama atau deskripsi kategori aset. Kategori yang masih dipakai aset atau transfer tidak dapat dihapus.',
                    [
                        'Hak akses: **admin** saja.',
                        'Nama tetap harus unik dan tidak boleh sama dengan kategori lain.',
                    ],
                ),
                'tags' => ['Kategori Aset'],
            ],
            'asset-categories.destroy' => [
                'summary' => 'Hapus kategori aset',
                'description' => self::description(
                    'Menghapus kategori aset. Kategori yang masih dipakai aset atau transfer tidak dapat dihapus.',
                    [
                        'Hak akses: **admin** saja.',
                    ],
                ),
                'tags' => ['Kategori Aset'],
            ],

            // ------------------------------------------------------------------
            // Lokasi
            // ------------------------------------------------------------------
            'locations.index' => [
                'summary' => 'Daftar lokasi',
                'description' => self::description(
                    'Mengembalikan daftar lokasi penyimpanan aset dengan paginasi, pencarian, dan filter `is_active`.',
                ),
                'tags' => ['Lokasi'],
            ],
            'locations.store' => [
                'summary' => 'Buat lokasi',
                'description' => self::description(
                    'Menambahkan lokasi penyimpanan baru.',
                    [
                        'Hak akses: **admin** saja.',
                        'Kode lokasi harus unik.',
                    ],
                ),
                'tags' => ['Lokasi'],
            ],
            'locations.show' => [
                'summary' => 'Detail lokasi',
                'description' => self::description(
                    'Mengembalikan detail satu lokasi penyimpanan aset.',
                ),
                'tags' => ['Lokasi'],
            ],
            'locations.update' => [
                'summary' => 'Perbarui lokasi',
                'description' => self::description(
                    'Mengubah kode, nama, alamat, atau status aktif sebuah lokasi. '
                    .'Perubahan ini **tidak** memindahkan aset yang sudah berada di lokasi tersebut.',
                    [
                        'Hak akses: **admin** saja.',
                        'Kode lokasi tetap harus unik.',
                    ],
                ),
                'tags' => ['Lokasi'],
            ],
            'locations.destroy' => [
                'summary' => 'Hapus lokasi',
                'description' => self::description(
                    'Menghapus lokasi penyimpanan. Lokasi yang sudah dipakai riwayat transaksi tidak dapat dihapus.',
                    [
                        'Hak akses: **admin** saja.',
                    ],
                ),
                'tags' => ['Lokasi'],
            ],

            // ------------------------------------------------------------------
            // Transfer
            // ------------------------------------------------------------------
            'transfers.index' => [
                'summary' => 'Daftar transfer',
                'description' => self::description(
                    'Mengembalikan daftar transfer aset dengan paginasi, pencarian, dan filter status, aset, pemohon, serta periode.',
                    [
                        'Pencarian (`search`) mencocokkan `asset_code` dan `name` aset yang dipindahkan.',
                        'Filter tanggal (`date_from` / `date_to`) disaring terhadap waktu pengajuan (`requested_at`).',
                    ],
                ),
                'tags' => ['Transfer Aset'],
            ],
            'transfers.store' => [
                'summary' => 'Ajukan perpindahan aset',
                'description' => self::description(
                    'Pemohon mengajukan perpindahan aset ke lokasi tujuan. '
                    .'Transfer baru berstatus `pending` dan aset otomatis berubah menjadi `in_transfer` sehingga tidak bisa dipindahkan lagi.',
                    [
                        'Hak akses: **admin** dan **officer**.',
                        'Aset harus berstatus `available`. Aset yang sedang `in_transfer` akan ditolak.',
                        'Lokasi tujuan harus berbeda dari lokasi saat ini.',
                        '**Pencegahan transfer ganda:** kolom `active_asset_id` pada tabel `transfers` memiliki indeks UNIQUE, '
                        .'sehingga hanya boleh ada satu transfer aktif untuk satu aset. Percobaan kedua pada aset yang sama '
                        .'akan menghasilkan `409 Conflict` (juga tercatat dengan aksi `transfer.conflict`).',
                        'Menggunakan `lockForUpdate` sehingga request bersamaan tetap aman.',
                        'Status awal adalah `pending` dan **wajib** disetujui admin sebelum serah terima bisa dilakukan.',
                        'Mendukung header `Idempotency-Key` untuk mencegah pengajuan ganda saat tombol diulang.',
                        'Tercatat pada jejak audit dengan aksi `transfer.requested`.',
                    ],
                ),
                'tags' => ['Transfer Aset'],
            ],
            'transfers.show' => [
                'summary' => 'Detail transfer beserta riwayatnya',
                'description' => self::description(
                    'Mengembalikan detail satu transfer lengkap dengan riwayat persetujuan (`approvals`) dan data serah terima (`handover`).',
                ),
                'tags' => ['Transfer Aset'],
            ],
            'transfers.approve' => [
                'summary' => 'Setujui transfer',
                'description' => self::description(
                    'Menyetujui pengajuan transfer sehingga status berpindah dari `pending` menjadi `approved`.',
                    [
                        'Hak akses: **admin** saja.',
                        '**Pemisahan tugas:** admin yang menjadi pemohon transfer tersebut **tidak boleh** menyetujui transfer miliknya sendiri. '
                        .'Percobaan akan menghasilkan `403`.',
                        'Hanya transfer berstatus `pending` yang bisa disetujui. Status lain menghasilkan `422`.',
                        'Tercatat pada jejak audit dengan aksi `transfer.approved`.',
                    ],
                ),
                'tags' => ['Transfer Aset'],
            ],
            'transfers.reject' => [
                'summary' => 'Tolak transfer',
                'description' => self::description(
                    'Menolak pengajuan transfer sehingga status berpindah dari `pending` menjadi `rejected` dan aset kembali bebas.',
                    [
                        'Hak akses: **admin** saja, dengan aturan pemisahan tugas yang sama seperti persetujuan.',
                        'Field `notes` (alasan penolakan) **wajib** diisi.',
                        'Tercatat pada jejak audit dengan aksi `transfer.rejected`.',
                    ],
                ),
                'tags' => ['Transfer Aset'],
            ],
            'transfers.handover' => [
                'summary' => 'Catat serah terima barang',
                'description' => self::description(
                    'Mencatat bahwa barang telah diserahkan dari lokasi asal. Status transfer berpindah dari `approved` menjadi `handover`.',
                    [
                        'Hak akses: **admin** dan **officer**.',
                        'Hanya transfer berstatus `approved` yang bisa diserahterimakan.',
                        'Data serah terima disimpan pada tabel `asset_handover` beserta petugas yang menyerahkan.',
                        'Tercatat pada jejak audit dengan aksi `transfer.handed_over`.',
                    ],
                ),
                'tags' => ['Transfer Aset'],
            ],
            'transfers.receive' => [
                'summary' => 'Catat penerimaan barang',
                'description' => self::description(
                    'Mencatat bahwa barang telah diterima di lokasi tujuan. '
                    .'Hanya pada endpoint inilah **lokasi aset diperbarui** ke lokasi tujuan dan status aset kembali menjadi `available`.',
                    [
                        'Hak akses: **admin** dan **officer**.',
                        '**Pemisahan tugas:** petugas yang melakukan serah terima **tidak boleh** menjadi penerima yang sama. Percobaan menghasilkan `403`.',
                        'Hanya transfer berstatus `handover` yang bisa diterima.',
                        'Status transfer final menjadi `completed` dan `active_asset_id` dilepas, sehingga aset boleh dipindahkan kembali.',
                        'Tercatat pada jejak audit dengan aksi `transfer.received`.',
                    ],
                ),
                'tags' => ['Transfer Aset'],
            ],
            'transfers.cancel' => [
                'summary' => 'Batalkan transfer',
                'description' => self::description(
                    'Membatalkan transfer yang sedang berjalan. Aset kembali ke status semula dan dilepas dari transfer aktif.',
                    [
                        'Hak akses: **admin** dapat membatalkan transfer mana pun; **officer** hanya dapat membatalkan transfer miliknya sendiri.',
                        'Hanya transfer berstatus `pending`, `approved`, atau `handover` yang dapat dibatalkan.',
                        'Tercatat pada jejak audit dengan aksi `transfer.cancelled`.',
                    ],
                ),
                'tags' => ['Transfer Aset'],
            ],

            // ------------------------------------------------------------------
            // Jejak Audit
            // ------------------------------------------------------------------
            'activity-logs.index' => [
                'summary' => 'Daftar jejak audit',
                'description' => self::description(
                    'Mengembalikan daftar catatan aktivitas dengan paginasi, pencarian, dan filter user, aset, transfer, aksi, serta periode.',
                    [
                        'Hak akses: **admin** dan **auditor** saja. Petugas mendapat `403`.',
                        'Jejak audit bersifat **append-only**: tidak ada endpoint untuk mengubah atau menghapus log.',
                        'Kolom `old_values` dan `new_values` berisi snapshot nilai sebelum dan sesudah perubahan.',
                    ],
                ),
                'tags' => ['Jejak Audit'],
            ],
            'activity-logs.show' => [
                'summary' => 'Detail jejak audit',
                'description' => self::description(
                    'Mengembalikan detail satu catatan jejak audit beserta user dan aset terkaitnya.',
                    [
                        'Hak akses: **admin** dan **auditor** saja.',
                    ],
                ),
                'tags' => ['Jejak Audit'],
            ],

            // ------------------------------------------------------------------
            // Laporan
            // ------------------------------------------------------------------
            'reports.assets' => [
                'summary' => 'Laporan aset',
                'description' => self::description(
                    'Rekap data aset dengan filter kategori, lokasi, status, periode, dan pencarian bebas.',
                    [
                        'Gunakan `format=csv` untuk mengunduh berkas CSV (bukan JSON).',
                        'Rate limit: **30 permintaan per menit** per pengguna.',
                    ],
                ),
                'tags' => ['Laporan'],
            ],
            'reports.transfers' => [
                'summary' => 'Laporan transfer',
                'description' => self::description(
                    'Rekap data transfer dengan filter status, aset, lokasi, pemohon, periode, dan pencarian bebas.',
                    [
                        'Gunakan `format=csv` untuk mengunduh berkas CSV.',
                        'Rate limit: **30 permintaan per menit** per pengguna.',
                    ],
                ),
                'tags' => ['Laporan'],
            ],
            'reports.activity-logs' => [
                'summary' => 'Laporan jejak audit',
                'description' => self::description(
                    'Rekap jejak audit dengan filter user, aset, transfer, jenis aksi, dan periode.',
                    [
                        'Hanya tersedia untuk **admin** dan **auditor**.',
                        'Rate limit: **30 permintaan per menit** per pengguna.',
                    ],
                ),
                'tags' => ['Laporan'],
            ],

            // ------------------------------------------------------------------
            // Notifikasi
            // ------------------------------------------------------------------
            'notifications.index' => [
                'summary' => 'Daftar notifikasi',
                'description' => self::description(
                    'Mengembalikan notifikasi milik pengguna yang sedang login.',
                    [
                        'Gunakan `unread_only=true` untuk hanya menampilkan notifikasi yang belum dibaca.',
                        'Field `meta.unread_count` berisi jumlah notifikasi yang belum dibaca.',
                    ],
                ),
                'tags' => ['Notifikasi'],
            ],
            'notifications.read' => [
                'summary' => 'Tandai notifikasi sudah dibaca',
                'description' => self::description(
                    'Menandai satu notifikasi milik pengguna yang sedang login sebagai sudah dibaca.',
                    [
                        'Notifikasi milik pengguna lain tidak dapat diakses dan menghasilkan `404`.',
                    ],
                ),
                'tags' => ['Notifikasi'],
            ],
            'notifications.read-all' => [
                'summary' => 'Tandai semua notifikasi sudah dibaca',
                'description' => self::description(
                    'Menandai seluruh notifikasi yang belum dibaca milik pengguna yang sedang login sebagai sudah dibaca.',
                ),
                'tags' => ['Notifikasi'],
            ],

            // ------------------------------------------------------------------
            // Manajemen Pengguna
            // ------------------------------------------------------------------
            'users.index' => [
                'summary' => 'Daftar pengguna',
                'description' => self::description(
                    'Mengembalikan daftar pengguna dengan paginasi, pencarian, dan filter role serta status aktif.',
                    [
                        'Hak akses: **admin** saja.',
                    ],
                ),
                'tags' => ['Manajemen Pengguna'],
            ],
            'users.store' => [
                'summary' => 'Buat pengguna',
                'description' => self::description(
                    'Menambahkan akun pengguna baru beserta role dan status aktifnya.',
                    [
                        'Hak akses: **admin** saja.',
                        'Email harus unik.',
                        'Field `password` harus disertai `password_confirmation` dengan nilai yang sama, minimal 8 karakter, '
                        .'mengandung huruf dan angka.',
                        'Nilai `role_id` harus menunjuk role yang dikenal (`admin`, `officer`, atau `auditor`).',
                    ],
                ),
                'tags' => ['Manajemen Pengguna'],
            ],
            'users.show' => [
                'summary' => 'Detail pengguna',
                'description' => self::description(
                    'Mengembalikan detail satu pengguna beserta role-nya.',
                    [
                        'Hak akses: **admin** saja.',
                    ],
                ),
                'tags' => ['Manajemen Pengguna'],
            ],
            'users.update' => [
                'summary' => 'Perbarui pengguna',
                'description' => self::description(
                    'Mengubah nama, email, role, status aktif, atau password pengguna.',
                    [
                        'Hak akses: **admin** saja.',
                        'Menonaktifkan akun (`is_active=false`) **otomatis mencabut seluruh token** milik pengguna tersebut, '
                        .'sehingga akses langsung terputus. Tercatat dengan aksi `user.deactivated`.',
                        'Mengubah password melalui endpoint ini juga mencabut seluruh token pengguna.',
                    ],
                ),
                'tags' => ['Manajemen Pengguna'],
            ],
            'users.destroy' => [
                'summary' => 'Hapus pengguna',
                'description' => self::description(
                    'Menghapus akun pengguna dari sistem.',
                    [
                        'Hak akses: **admin** saja.',
                        'Admin **tidak boleh** menghapus akunnya sendiri.',
                    ],
                ),
                'tags' => ['Manajemen Pengguna'],
            ],
            'users.reset-password' => [
                'summary' => 'Reset password pengguna',
                'description' => self::description(
                    'Mengatur ulang password pengguna dan mencabut seluruh token miliknya.',
                    [
                        'Hak akses: **admin** saja.',
                        'Pengguna harus melakukan login ulang dengan password baru.',
                    ],
                ),
                'tags' => ['Manajemen Pengguna'],
            ],
        ];
    }

    /**
     * Operation yang mendukung header `Idempotency-Key`.
     *
     * @return list<string>
     */
    public static function idempotentOperations(): array
    {
        return [
            'assets.store',
            'transfers.store',
            'transfers.approve',
            'transfers.reject',
            'transfers.handover',
            'transfers.receive',
            'transfers.cancel',
        ];
    }

    /**
     * Penjelasan umum API yang tampil di bagian atas halaman dokumentasi.
     */
    private static function generalDescription(): string
    {
        return <<<'MD'
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
        MD;
    }

    /**
     * Merakit deskripsi endpoint dari judul dan daftar catatan.
     *
     * @param  list<string>  $notes
     */
    private static function description(string $intro, array $notes = []): string
    {
        $body = $intro;

        if ($notes !== []) {
            $body .= "\n\n**Catatan:**\n\n";
            $body .= implode("\n", array_map(
                static fn (string $note): string => '- '.$note,
                $notes,
            ));
        }

        return $body;
    }
}
