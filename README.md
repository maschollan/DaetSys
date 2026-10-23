# DaetSys

DaetSys adalah aplikasi untuk mencatat barang milik organisasi dan mengetahui siapa yang bertanggung jawab atas barang tersebut. Aplikasi ini membantu pengguna melihat data barang, mengetahui lokasinya, mencatat perpindahan, dan menelusuri riwayat kegiatannya.

## Apa yang bisa dilakukan?

- **Mencatat barang** — simpan nama, kode, kategori, nomor seri, kondisi, dan lokasi barang. Setiap barang memiliki kode dan QR unik.
- **Melihat ringkasan** — dashboard menampilkan jumlah barang, status, lokasi, kegiatan terbaru, dan perpindahan yang masih menunggu tindakan.
- **Mencatat perpindahan barang** — perpindahan harus diajukan dan disetujui. Setelah barang diserahkan dan penerima mengonfirmasi, lokasi barang diperbarui.
- **Mencari barang dengan QR** — masukkan kode QR, unggah gambar QR, atau pindai dengan kamera perangkat.
- **Melihat riwayat** — catatan menunjukkan kegiatan penting pada barang dan perpindahan, termasuk pihak yang mengajukan, menyetujui, menyerahkan, atau menerima.
- **Menerima pemberitahuan** — pengguna mendapat pemberitahuan tentang tindakan yang berkaitan dengan akun mereka.
- **Membuat laporan** — tampilkan data barang, perpindahan, atau riwayat kegiatan dengan filter dan unduh hasilnya sebagai CSV.
- **Mengelola data pendukung** — admin dapat mengatur pengguna, kategori barang, dan lokasi.

## Alur perpindahan barang

1. Petugas memilih barang dan mengajukan perpindahan dengan lokasi tujuan serta alasan.
2. Admin meninjau permintaan, lalu menyetujui atau menolaknya.
3. Setelah disetujui, petugas mencatat bahwa barang telah diserahkan.
4. Petugas lain yang menerima barang mengonfirmasi penerimaan.
5. Setelah penerimaan dikonfirmasi, lokasi barang berubah ke lokasi tujuan dan riwayatnya tersimpan.

Pemisahan tugas ini membantu memastikan perpindahan tidak hanya tercatat sebagai permintaan. Barang baru dianggap berpindah setelah penerimaan dikonfirmasi.

## Jenis akun

| Akun | Kegunaan |
| --- | --- |
| **Admin** | Mengelola pengguna dan data utama, memantau aplikasi, serta menangani persetujuan perpindahan sesuai aturan. |
| **Petugas** | Mencatat dan mencari barang, mengajukan perpindahan, serta melakukan serah terima sesuai tugasnya. |
| **Auditor** | Melihat data, laporan, dan riwayat kegiatan untuk keperluan pemeriksaan. Akun ini tidak mengubah data operasional. |

Hak setiap akun diperiksa oleh server. Menu yang terlihat di layar bukan satu-satunya pembatas akses.

## Status barang

- **Tersedia** — barang berada di lokasi tercatat dan tidak sedang dalam proses perpindahan.
- **Dalam perpindahan** — ada proses perpindahan yang belum selesai.
- **Dalam perawatan** — barang sedang dirawat atau diperbaiki.
- **Hilang** — barang ditandai hilang.

## Memulai aplikasi

Bagian ini ditujukan untuk pengelola atau pengembang yang menyiapkan aplikasi di komputer. Untuk masuk ke aplikasi yang sudah dipasang, gunakan alamat dan akun yang diberikan admin.

### Persiapan

Pastikan komputer memiliki PHP 8.3 atau lebih baru, Composer, Node.js versi 22.18 atau lebih baru, dan MySQL. Siapkan juga database MySQL kosong untuk DaetSys.

### Menyiapkan backend

```bash
cd backend
composer install
cp .env.example .env
```

Atur koneksi database di `backend/.env`, lalu jalankan:

```bash
php artisan key:generate
php artisan migrate --seed
```

### Menyiapkan frontend

Di terminal lain, dari folder utama proyek:

```bash
cd frontend
npm install
```

Pastikan URL API di `frontend/.env` mengarah ke backend, biasanya `http://localhost:8000/api`.

### Menjalankan aplikasi

Dari folder utama proyek, jalankan:

```bash
npm run dev
```

Perintah ini menjalankan backend dan frontend. Secara bawaan, backend tersedia di `http://localhost:8000` dan frontend di `http://localhost:5173`.

### Akun contoh untuk pengembangan

Seeder menyediakan akun contoh berikut untuk lingkungan lokal:

| Jenis akun | Email | Kata sandi |
| --- | --- | --- |
| Admin | `admin@daetsys.local` | `password` |
| Petugas | `officer@daetsys.local` | `password` |
| Petugas kedua | `officer2@daetsys.local` | `password` |
| Auditor | `auditor@daetsys.local` | `password` |

Akun dan kata sandi contoh hanya untuk pengembangan lokal. Jangan gunakan di lingkungan produksi. Untuk akses ke aplikasi yang sudah dipasang, mintalah akun kepada admin.

## Panduan singkat penggunaan

- **Mulai dari Dashboard** untuk melihat ringkasan dan daftar yang memerlukan tindakan.
- **Buka Aset** untuk mencari, menambahkan, atau melihat detail barang dan riwayatnya.
- **Gunakan Lacak QR** untuk menemukan barang melalui kode QR.
- **Buka Transfer** untuk mengajukan atau memproses perpindahan barang.
- **Buka Notifikasi** untuk melihat pemberitahuan yang masuk.
- **Buka Laporan** untuk memilih data, menerapkan filter, dan mengunduh CSV.
- **Buka Jejak Audit** untuk menelusuri kegiatan. Halaman ini tersedia bagi admin dan auditor.
- **Buka Profil** untuk memperbarui nama atau kata sandi akun sendiri.

## Dokumentasi API

Untuk pengelola teknis, backend menyediakan dokumentasi API di `/docs` dan berkas OpenAPI di `/docs/api.json`. Akses dokumentasi interaktif di luar lingkungan lokal memerlukan akun admin aktif.

## Teknologi

DaetSys menggunakan Vue untuk tampilan aplikasi, Laravel untuk layanan dan API, serta MySQL untuk menyimpan data. Antarmuka mendukung tema terang dan gelap.
