# MASTER IMPLEMENTATION PROMPT — DAETSYS

## 1. Role dan tujuan

Bertindak sebagai Senior Full-Stack Engineer dan Software Architect yang berpengalaman dalam Laravel, Vue.js, Bootstrap, REST API, database design, application security, dan audit systems.

Bangun aplikasi bernama **DaetSys — Digital Asset & Evidence Tracking System**, yaitu aplikasi web untuk pengelolaan aset dan pelacakan barang bukti pada instansi pemerintah.

Aplikasi harus dapat:
- Mengelola data barang dan lokasi penyimpanan.
- Mencegah duplikasi identitas barang.
- Melacak perpindahan barang melalui proses persetujuan.
- Menghasilkan QR Code unik untuk setiap barang.
- Menyimpan audit trail yang dapat ditelusuri.
- Menampilkan dashboard, notifikasi, dan laporan.
- Mengendalikan akses Admin, Petugas, dan Auditor.
- Menjaga konsistensi data saat terjadi concurrent update, request manipulasi API, gangguan transaksi, dan pertumbuhan database.

Jangan hanya menghasilkan mockup atau source code contoh. Implementasikan aplikasi pada workspace yang sudah tersedia, jalankan pengujian yang relevan, dan dokumentasikan hasilnya.

## 2. Kondisi workspace yang harus dipertahankan

Struktur proyek saat ini:

DaetSys/
- package.json
- backend/
  - Laravel 13
  - Starter template Blade yang sudah tersedia
  - Digunakan sebagai backend API-only
- frontend/
  - Vue.js
  - Bootstrap sudah ditambahkan
  - Asset font tersedia di frontend/src/font/

Ketentuan:
1. Periksa seluruh struktur folder dan file konfigurasi sebelum mengubah kode.
2. Identifikasi package manager, versi dependency, script npm, konfigurasi database, dan kondisi Git.
3. Pertahankan dependency dan konfigurasi yang masih dapat digunakan.
4. Jangan membuat ulang proyek Laravel atau Vue.
5. Jangan menghapus starter template secara membabi buta.
6. Laravel tidak boleh merender halaman utama menggunakan Blade. Semua halaman aplikasi dibuat melalui Vue.
7. Root DaetSys harus dapat menjalankan backend dan frontend bersamaan menggunakan satu perintah npm.
8. Jangan mengubah atau menghapus data database yang sudah ada tanpa persetujuan.
9. Jangan menampilkan secret, token, password, atau isi file environment ke log.
10. Sebelum perubahan besar, rangkum temuan, rencana file yang akan diubah, serta potensi risiko.

## 3. Teknologi

### Backend
- Laravel 13.
- PHP versi yang kompatibel dengan instalasi Laravel yang tersedia.
- Laravel Sanctum untuk autentikasi API menggunakan Bearer Token.
- MySQL atau PostgreSQL sesuai database yang telah dikonfigurasi.
- Laravel Form Requests untuk validasi.
- Laravel Policies dan middleware untuk otorisasi.
- Eloquent ORM.
- Database transactions dan row-level locking.
- Laravel API Resources untuk response JSON.
- Laravel Queue apabila diperlukan untuk notifikasi dan laporan asinkron.
- PHPUnit atau Pest sesuai framework testing yang sudah tersedia.

### Frontend
- Vue.js sesuai versi proyek yang ada.
- Vue Router.
- Axios.
- Bootstrap 5 dan Bootstrap JavaScript components.
- Bootstrap Icons jika dependency tersedia atau dapat ditambahkan tanpa konflik.
- Font lokal di frontend/src/font/.
- Chart.js untuk visualisasi dashboard jika kompatibel dengan proyek.
- Library QR Code yang kompatibel untuk menghasilkan dan membaca QR Code.

Jangan mengganti Vue Options API atau Composition API yang sudah digunakan secara luas tanpa alasan teknis. Ikuti pola proyek yang sudah ada.

## 4. Arsitektur aplikasi

Gunakan arsitektur yang terpisah berdasarkan tanggung jawab.

Backend:
- routes/api.php
- app/Http/Controllers/Api/
- app/Http/Requests/
- app/Http/Resources/
- app/Models/
- app/Policies/
- app/Services/
- app/Enums/ jika sesuai
- database/migrations/
- database/seeders/
- tests/Feature/
- tests/Unit/

Frontend:
- src/assets/
- src/components/
- src/layouts/
- src/views/
- src/router/
- src/services/
- src/stores/ jika Pinia sudah tersedia atau dibutuhkan
- src/composables/ jika menggunakan Composition API
- src/styles/
- src/font/

Sesuaikan nama direktori dengan struktur yang benar-benar ada.

Controller harus tipis. Tempatkan alur bisnis yang kompleks di service layer. Jangan menaruh seluruh proses persetujuan, perpindahan, audit, dan transaksi di dalam controller.

## 5. Desain database dan ERD

Implementasikan sembilan tabel inti berikut:

1. roles
2. users
3. asset_categories
4. locations
5. assets
6. transfers
7. transfer_approvals
8. asset_handover
9. activity_logs

### A. roles

Kolom:
- id
- name: unique
- timestamps

Role awal:
- admin
- officer
- auditor

Role harus disimpan dalam format yang konsisten dan digunakan melalui backend authorization.

### B. users

Kolom:
- id
- role_id: foreign key
- name
- email: unique
- password
- is_active
- timestamps

Gunakan model User Laravel yang sudah tersedia. Tambahkan relasi role.

Password wajib disimpan dalam bentuk hash.

Pengguna nonaktif tidak boleh mengakses endpoint terlindungi meskipun memiliki token yang belum kedaluwarsa.

### C. asset_categories

Kolom:
- id
- name: unique
- description: nullable
- timestamps

### D. locations

Kolom:
- id
- code: unique
- name
- address: nullable
- is_active
- timestamps

Lokasi yang masih direferensikan transaksi historis tidak boleh dihapus secara fisik sembarangan.

### E. assets

Kolom:
- id
- category_id: foreign key
- location_id: foreign key, nullable hanya jika barang memang belum ditempatkan
- asset_code: unique
- qr_token: unique dan tidak mudah ditebak
- name
- description: nullable
- serial_number: nullable, dengan unique constraint jika aturan bisnis mengharuskannya
- status
- acquisition_date: nullable
- timestamps
- deleted_at jika soft delete diperlukan

Gunakan status yang eksplisit dan konsisten, misalnya available, in_transfer, maintenance, dan missing.

Jangan mengizinkan perubahan location_id melalui endpoint update aset biasa. Perubahan lokasi hanya dilakukan melalui proses transfer yang sah.

### F. transfers

Kolom:
- id
- asset_id: foreign key
- from_location_id: foreign key
- to_location_id: foreign key
- requested_by: foreign key ke users
- status
- reason
- requested_at
- completed_at: nullable
- timestamps

Status minimal:
- pending
- approved
- rejected
- completed
- cancelled

Validasi bahwa lokasi asal berbeda dari lokasi tujuan.

Simpan lokasi asal dan tujuan sebagai snapshot transaksi yang tetap dapat ditelusuri meskipun data master lokasi berubah.

### G. transfer_approvals

Kolom:
- id
- transfer_id: foreign key
- approver_id: foreign key ke users
- decision
- notes: nullable
- decided_at: nullable
- timestamps

Decision:
- approved
- rejected

Simpan setiap keputusan secara historis. Jangan menimpa keputusan lama ketika dibutuhkan riwayat persetujuan.

Petugas tidak boleh menyetujui perpindahannya sendiri jika kebijakan pemisahan tugas diterapkan.

### H. asset_handover

Kolom:
- id
- transfer_id: foreign key
- handed_over_by: foreign key ke users
- received_by: foreign key ke users, nullable sebelum penerimaan
- handed_over_at: nullable
- received_at: nullable
- notes: nullable
- timestamps

Catat bukti serah terima dan pihak yang menyerahkan serta menerima barang.

Jangan menandai perpindahan selesai hanya karena permintaan disetujui. Ikuti proses serah terima yang berlaku.

### I. activity_logs

Kolom:
- id
- user_id: foreign key
- asset_id: nullable foreign key
- transfer_id: nullable foreign key
- action
- old_values: JSON nullable
- new_values: JSON nullable
- ip_address: nullable
- user_agent: nullable
- created_at

Log harus mencatat aktivitas penting, termasuk pembuatan aset, pembaruan data, pengajuan transfer, persetujuan, penolakan, serah terima, pembatalan, dan percobaan akses yang tidak sah jika relevan.

Gunakan append-only behavior pada tingkat aplikasi dan batasi hak modifikasi database. Jangan menyimpan password, token autentikasi, atau secret ke dalam log.

### Relasi ERD

ROLES 1:N USERS

ASSET_CATEGORIES 1:N ASSETS

LOCATIONS 1:N ASSETS

ASSETS 1:N TRANSFERS

USERS 1:N TRANSFERS sebagai pengaju

LOCATIONS 1:N TRANSFERS sebagai lokasi asal dan lokasi tujuan

TRANSFERS 1:N TRANSFER_APPROVALS

USERS 1:N TRANSFER_APPROVALS sebagai pemberi keputusan

TRANSFERS 1:N ASSET_HANDOVER

USERS 1:N ASSET_HANDOVER sebagai penyerah dan penerima

USERS 1:N ACTIVITY_LOGS

ASSETS 1:N ACTIVITY_LOGS

TRANSFERS 1:N ACTIVITY_LOGS

Implementasikan foreign key, unique constraints, indeks, dan aturan nullability sesuai database yang digunakan.

## 6. Autentikasi Bearer Token

Gunakan Laravel Sanctum untuk API token authentication.

Periksa terlebih dahulu apakah Sanctum sudah tersedia. Jika belum, instal dan konfigurasi sesuai dokumentasi serta versi Laravel yang terpasang.

Endpoint:
- POST /api/login
- POST /api/logout
- GET /api/me
- POST /api/change-password

Login menerima email dan password, memvalidasi akun aktif, lalu mengembalikan token sesuai format API yang konsisten.

Frontend mengirim header:
Authorization: Bearer <token>

Token hanya boleh dikembalikan melalui respons login dan tidak boleh dicatat ke activity log.

Aturan:
- Token disimpan dengan mempertimbangkan risiko XSS dan karakteristik deployment.
- Untuk SPA first-party dalam produksi, evaluasi penggunaan autentikasi cookie Sanctum dengan CSRF.
- Jika memilih Bearer Token, gunakan mekanisme penyimpanan yang sesuai dengan model ancaman aplikasi dan jangan menganggap localStorage aman terhadap XSS.
- Jangan menyimpan token di repository.
- Logout harus mencabut token aktif yang digunakan.
- Periksa akun aktif dan hak akses pada setiap request yang relevan.
- Terapkan rate limiting untuk login dan endpoint sensitif.
- Jangan membuat endpoint yang memungkinkan pengguna memperoleh token tanpa autentikasi yang sah.

Seed akun demo hanya untuk lingkungan development/testing. Jangan membuat password default yang dapat digunakan pada produksi.

## 7. Role-Based Access Control

Admin:
- Mengelola pengguna, role, kategori, lokasi, dan aset sesuai kebijakan.
- Melihat dashboard dan laporan.
- Mengelola permintaan transfer sesuai kewenangan.
- Tidak boleh mengubah atau menghapus audit trail secara langsung.

Officer:
- Melihat aset dan lokasi yang diizinkan.
- Mengajukan transfer.
- Melakukan serah terima sesuai penugasan.
- Melihat riwayat aktivitas yang diizinkan.
- Tidak dapat menyetujui transfer sendiri jika separation of duties diwajibkan.

Auditor:
- Melihat aset, transfer, audit trail, dan laporan.
- Tidak dapat mengubah data operasional.
- Tidak dapat menyetujui atau menyelesaikan transfer kecuali terdapat kebijakan eksplisit yang mengizinkannya.

Terapkan authorization di backend menggunakan middleware dan Policies. Menyembunyikan tombol di Vue tidak dianggap sebagai pengamanan.

## 8. API endpoint

Gunakan prefix /api dan response JSON yang konsisten.

Authentication:
- POST /api/login
- POST /api/logout
- GET /api/me
- POST /api/change-password

Dashboard:
- GET /api/dashboard/summary
- GET /api/dashboard/assets-by-location
- GET /api/dashboard/assets-by-status
- GET /api/dashboard/recent-activities

Assets:
- GET /api/assets
- POST /api/assets
- GET /api/assets/{asset}
- PUT /api/assets/{asset}
- DELETE /api/assets/{asset} sesuai kebijakan
- GET /api/assets/{asset}/history
- GET /api/assets/qr/{qr_token}

Categories:
- CRUD /api/asset-categories

Locations:
- CRUD /api/locations

Transfers:
- GET /api/transfers
- POST /api/transfers
- GET /api/transfers/{transfer}
- POST /api/transfers/{transfer}/approve
- POST /api/transfers/{transfer}/reject
- POST /api/transfers/{transfer}/handover
- POST /api/transfers/{transfer}/receive
- POST /api/transfers/{transfer}/cancel

Activity logs:
- GET /api/activity-logs
- GET /api/activity-logs/{activityLog}

Reports:
- GET /api/reports/assets
- GET /api/reports/transfers
- GET /api/reports/activity-logs

Sesuaikan route dengan kebijakan role dan status workflow.

Semua list endpoint harus mendukung pagination, filter yang divalidasi, pengurutan yang diizinkan, dan pencarian yang efisien.

Jangan menerima nama kolom order atau filter SQL mentah dari pengguna.

## 9. Lima skenario wajib

### Scenario 1 — Concurrent Update

Dua petugas memindahkan barang yang sama secara bersamaan.

Implementasi:
- Gunakan DB::transaction.
- Gunakan lockForUpdate pada baris aset dan transaksi yang relevan.
- Validasi ulang lokasi asal, status aset, dan status transfer di dalam transaksi.
- Pastikan hanya satu transfer aktif yang dapat menguasai aset pada satu waktu.
- Gunakan unique constraint atau mekanisme database yang sesuai untuk mencegah duplikasi transfer aktif.
- Tangani deadlock dan konflik secara aman.
- Kembalikan HTTP 409 Conflict jika kondisi barang sudah berubah.

### Scenario 2 — Data Manipulation

Petugas mencoba mengubah lokasi melalui endpoint update aset.

Implementasi:
- Endpoint update aset tidak menerima location_id.
- Semua perpindahan hanya melalui service workflow transfer.
- Validasi autentikasi, role, ownership atau scope akses, dan approval di backend.
- Catat percobaan akses tidak sah tanpa merekam secret.
- Gunakan HTTP 403 untuk akses terlarang dan HTTP 422 untuk input tidak valid.

### Scenario 3 — Audit Trail

Auditor perlu menelusuri seluruh perpindahan barang.

Implementasi:
- Simpan transfer secara historis.
- Simpan keputusan persetujuan.
- Simpan serah terima dan pihak yang terlibat.
- Catat aktivitas penting dalam activity_logs.
- Tampilkan timeline kronologis yang dapat difilter.
- Batasi perubahan atau penghapusan log.
- Jangan menyimpulkan tanggung jawab kehilangan hanya dari pengguna yang tercatat terakhir; tampilkan bukti dan serah terima sebagai dasar investigasi.

### Scenario 4 — Data Integrity

Gangguan terjadi ketika perpindahan berlangsung.

Implementasi:
- Pembaruan lokasi, penyelesaian transfer, serah terima, dan activity log yang saling terkait harus atomik dalam transaksi database yang sesuai.
- Jika proses gagal, lakukan rollback.
- Gunakan idempotency key atau identitas transaksi unik untuk request yang dapat dikirim ulang.
- Jangan membuat side effect eksternal yang tidak dapat di-rollback di tengah transaksi.
- Jika notifikasi harus dikirim setelah commit, gunakan after-commit dispatch atau transactional outbox bila diperlukan.
- Uji skenario exception dan koneksi gagal.

### Scenario 5 — Performance

Database berisi 500.000 aset dan jutaan aktivitas.

Implementasi:
- Indeks pada asset_code, qr_token, lokasi, status, foreign key, dan kolom tanggal yang sering difilter.
- Evaluasi composite index berdasarkan query aktual.
- Pagination atau cursor pagination.
- Hindari N+1 query.
- Gunakan agregasi database untuk dashboard.
- Cache ringkasan dashboard dan invalidasi setelah transaksi terkait berhasil.
- Gunakan queue untuk laporan berat.
- Evaluasi arsip atau partisi activity_logs jika diperlukan.
- Gunakan EXPLAIN dan pengujian beban untuk memvalidasi hasil.

Jangan mengklaim performa telah memenuhi target tanpa hasil benchmark.

## 10. Workflow transfer

Gunakan alur berikut:

Draft atau pengajuan
→ Pending
→ Approved atau Rejected
→ Handover
→ Received
→ Completed

Pembatalan hanya boleh dilakukan jika status dan kebijakan mengizinkan.

Tetapkan secara eksplisit kapan lokasi barang berubah. Untuk rancangan awal, lokasi aktif tetap menunjuk lokasi asal sampai proses penerimaan di lokasi tujuan dikonfirmasi. Setelah penerimaan sah, lakukan pembaruan lokasi, status transfer, catatan handover, dan audit log dalam satu transaksi.

Selama transfer berjalan, tandai aset sebagai in_transfer agar pengajuan lain tidak dapat memindahkan aset yang sama.

Semua transisi status harus divalidasi di backend.

## 11. Desain UI dan design system

Buat antarmuka modern dengan inspirasi terminal, command center, dan sistem monitoring pemerintah.

Karakter desain:
- Dominan hijau turquoise/teal.
- Tampilan bersih, profesional, dan informatif.
- Mendukung light mode dan dark mode.
- Button menggunakan outline style.
- Seluruh tombol memiliki sudut persegi atau tanpa corner radius.
- Hindari gradient berlebihan, efek glassmorphism, dan animasi dekoratif.
- Gunakan typography yang konsisten dan hierarki informasi yang jelas.
- Gunakan font lokal dari frontend/src/font/ setelah memeriksa nama file, format, dan lisensinya.
- Jangan mengunduh font pengganti jika font lokal dapat digunakan.
- Gunakan spacing, grid, dan breakpoint Bootstrap.
- Semua halaman harus responsif.

Buat CSS variables:
- --ds-primary
- --ds-primary-hover
- --ds-bg
- --ds-surface
- --ds-text
- --ds-muted
- --ds-border
- --ds-success
- --ds-warning
- --ds-danger

Turquoise dapat dimulai dari #14B8A6 atau #0D9488. Verifikasi kontras teks dan komponen dalam kedua mode.

Tetapkan border-radius menjadi 0 untuk tombol, input, card, modal, badge, dan komponen lain sesuai arah desain. Gunakan CSS variables agar aturan dapat diubah dari satu tempat.

Jangan menggunakan inline style berulang untuk setiap komponen jika dapat diatur melalui design tokens.

### Komponen Bootstrap yang digunakan

Gunakan komponen Bootstrap sesuai kebutuhan nyata di aplikasi, termasuk:
- Navbar dan responsive navigation.
- Sidebar berbasis layout Bootstrap.
- Dropdown.
- Offcanvas untuk navigasi mobile.
- Cards.
- Tables.
- Responsive table containers.
- Forms dan input groups.
- Floating labels jika sesuai.
- Validation feedback.
- Buttons dan outline buttons.
- Button groups.
- Badges.
- Alerts.
- Toast notifications.
- Modal konfirmasi.
- Tooltips.
- Popovers.
- Tabs dan navs.
- Breadcrumb.
- Pagination.
- Accordion untuk filter atau detail.
- Progress bars.
- Spinners dan loading states.
- List groups.
- Collapse.
- Bootstrap grid dan responsive utilities.

Jangan memaksakan seluruh komponen ke setiap halaman. Buat showcase atau halaman UI components terpisah untuk memvalidasi komponen Bootstrap yang tersedia dan dibutuhkan.

Jangan menginisialisasi Bootstrap JavaScript berulang kali pada setiap render Vue.

## 12. Struktur halaman frontend

### A. Login
- Layout sederhana dan profesional.
- Form email dan password.
- Show/hide password.
- Validasi input.
- Loading state.
- Error message yang jelas.
- Branding DaetSys.
- Background dengan aksen terminal turquoise.
- Tidak menampilkan kredensial demo di produksi.

### B. Main dashboard
- Sidebar.
- Top navbar.
- Search bar.
- Notification dropdown.
- User profile dropdown.
- Breadcrumb.
- Theme switcher.
- Summary cards:
  - Total assets.
  - Assets available.
  - Assets in transfer.
  - Assets under maintenance.
  - Missing assets.
  - Pending approvals.
- Grafik aset berdasarkan lokasi dan status.
- Tabel aktivitas terbaru.
- Daftar transfer yang menunggu tindakan.
- Quick action untuk menambah barang atau mengajukan transfer.

Semua angka dan grafik harus berasal dari API. Jangan menggunakan angka statis setelah endpoint tersedia.

### C. Asset management
- Data table.
- Pencarian.
- Filter kategori, lokasi, dan status.
- Pagination.
- Form create/edit.
- Detail barang.
- QR Code.
- Riwayat perpindahan.
- Konfirmasi tindakan melalui modal.
- Empty state dan error state.

### D. Locations
- Daftar lokasi.
- Kode lokasi.
- Jumlah aset per lokasi.
- Status lokasi.
- CRUD sesuai role.
- Detail barang di lokasi.

### E. Transfer management
- Daftar permintaan.
- Status badge.
- Filter periode dan status.
- Form pengajuan.
- Detail transfer.
- Riwayat approval.
- Aksi approve/reject sesuai role.
- Serah terima dan konfirmasi penerimaan.
- Timeline proses.

### F. Audit trail
- Search berdasarkan kode aset.
- Filter pengguna, tindakan, transfer, dan periode.
- Timeline.
- Perbandingan old_values dan new_values.
- Detail approval dan handover.
- Pagination server-side.
- Read-only untuk Auditor.

### G. Reports
- Filter periode.
- Filter lokasi dan status.
- Ringkasan hasil.
- Tabel hasil.
- Export CSV atau format lain yang dibutuhkan.
- Proses export harus menggunakan data backend dan hak akses yang benar.

### H. Users dan master data
- Manajemen pengguna.
- Aktivasi/nonaktivasi akun.
- Role management sesuai kebijakan.
- Kategori.
- Lokasi.

## 13. Notification system

Implementasikan notifikasi aplikasi untuk:
- Pengajuan transfer baru.
- Transfer disetujui atau ditolak.
- Permintaan serah terima.
- Penerimaan barang.
- Transfer selesai.
- Peringatan terkait barang hilang atau masalah operasional yang relevan.

Mulai dengan database-backed notifications jika sesuai dengan versi Laravel yang terpasang.

Tambahkan:
- Notification dropdown.
- Unread count.
- Mark as read.
- Mark all as read.
- Halaman notifikasi.
- Link ke halaman detail terkait.

Pastikan notifikasi hanya dapat dibaca dan diubah oleh penerima yang berhak. Jangan mengirim notifikasi sukses sebelum transaksi bisnis berhasil commit.

## 14. QR Code

Setiap barang memiliki qr_token yang unik dan tidak mudah ditebak.

QR Code mengidentifikasi barang melalui token atau URL aplikasi yang tidak membocorkan informasi sensitif.

Saat QR dipindai:
- Ambil data melalui API yang terlindungi.
- Periksa autentikasi dan authorization.
- Tampilkan identitas, lokasi, status, dan riwayat yang boleh dilihat pengguna.
- Jangan izinkan perubahan lokasi hanya dengan memindai QR.
- Catat tindakan yang relevan.

QR Code bukan bukti persetujuan dan bukan pengganti autentikasi.

## 15. Root npm scripts

Periksa package.json root dan konfigurasi npm-run-all atau concurrently yang sudah ada.

Pastikan tersedia satu perintah, misalnya:

npm run dev

Perintah tersebut menjalankan:
- Laravel backend dengan artisan serve atau konfigurasi server development yang sudah digunakan.
- Vue frontend dengan development server yang tersedia.

Gunakan nama script yang konsisten, output terminal yang mudah dibaca, dan penanganan proses ketika salah satu server gagal.

Jangan membuat konflik port. Periksa port dan script yang sudah ada sebelum mengubah konfigurasi.

Tambahkan script tambahan yang relevan, misalnya:
- npm run dev
- npm run build
- npm run test:frontend jika test runner tersedia

Jangan menghapus script existing yang masih digunakan.

## 16. Environment dan keamanan

Buat atau perbarui dokumentasi environment variables tanpa menampilkan secret aktual.

Backend:
- DB_CONNECTION
- DB_HOST
- DB_PORT
- DB_DATABASE
- DB_USERNAME
- DB_PASSWORD
- APP_URL
- FRONTEND_URL jika diperlukan
- pengaturan token dan queue sesuai implementasi

Frontend:
- URL API melalui environment variable yang sesuai dengan build tool yang sudah digunakan.

Aturan:
- Jangan menaruh secret backend di frontend.
- Jangan menggunakan database root atau kredensial produksi untuk testing.
- Konfigurasikan CORS untuk origin frontend yang benar.
- Gunakan HTTPS pada produksi.
- Terapkan authorization dan validasi di backend.
- Gunakan CSRF protection jika arsitektur autentikasi cookie memerlukannya.
- Jangan mengembalikan stack trace atau detail internal saat production.
- Lindungi mass assignment dan validasi seluruh input.
- Jangan membuat endpoint untuk menjalankan SQL atau perintah sistem bebas.

## 17. Testing wajib

Buat dan jalankan test untuk:

Authentication:
- Login berhasil.
- Password salah.
- User nonaktif.
- Token tidak valid.
- Logout dan pencabutan token.
- Endpoint terlindungi tanpa token.

Authorization:
- Admin.
- Officer.
- Auditor.
- Pengguna yang mencoba tindakan terlarang.
- Percobaan approval sendiri jika dilarang.

Assets:
- Pembuatan aset.
- Duplikasi asset_code.
- Duplikasi qr_token.
- Validasi lokasi dan kategori.
- Penolakan perubahan lokasi langsung.

Transfers:
- Pengajuan valid.
- Persetujuan valid.
- Penolakan.
- Serah terima.
- Penerimaan.
- Pembatalan.
- Pengajuan transfer kedua ketika aset sedang ditransfer.

Concurrency dan integrity:
- Dua request bersamaan pada aset yang sama.
- Perubahan lokasi yang sudah stale.
- Exception di tengah transaksi.
- Rollback data.
- Retry request dan idempotency.
- Konsistensi audit log.

Audit:
- Seluruh perubahan kritis menghasilkan log.
- User tidak dapat mengubah log melalui endpoint umum.
- Riwayat aset tersusun kronologis.

Performance:
- Pagination.
- Query dashboard.
- Filter dan indeks.
- Pengujian beban pada dataset representatif.

Gunakan database testing yang mendukung transaksi dan locking sesuai perilaku database target. Jangan menganggap SQLite membuktikan seluruh perilaku concurrency MySQL/PostgreSQL.

## 18. Implementation plan bertahap

Tahap 1 — Repository inspection:
- Periksa struktur backend, frontend, package.json, dependencies, dan environment.
- Laporkan kondisi existing dan risiko perubahan.

Tahap 2 — Backend foundation:
- Setup API-only.
- Setup Sanctum.
- Setup roles dan users.
- Setup response format dan error handling.
- Implementasikan login, logout, dan me.

Tahap 3 — Database:
- Buat migration sembilan tabel.
- Buat foreign keys, unique constraints, indexes, dan seeder.
- Pastikan urutan migration benar.
- Jalankan migration hanya pada database yang telah diverifikasi aman.

Tahap 4 — Business logic:
- Implementasikan asset management.
- Implementasikan locations dan categories.
- Implementasikan transfer workflow.
- Implementasikan approvals dan handover.
- Implementasikan audit trail dan notifications.

Tahap 5 — Frontend foundation:
- Setup router dan auth guard.
- Setup Axios.
- Setup login.
- Setup design tokens.
- Setup local fonts.
- Setup light/dark theme.
- Setup responsive application layout.

Tahap 6 — Frontend modules:
- Dashboard.
- Assets.
- QR tracking.
- Transfers.
- Notifications.
- Audit logs.
- Reports.
- Users dan master data.

Tahap 7 — Hardening:
- Uji lima skenario.
- Uji akses role.
- Uji validasi API.
- Uji transaksi dan concurrency.
- Evaluasi indeks dan query.

Tahap 8 — Final verification:
- Jalankan backend tests.
- Jalankan frontend tests jika tersedia.
- Jalankan production build.
- Jalankan npm run dev dan verifikasi kedua server.
- Perbaiki error yang ditemukan.
- Dokumentasikan hasil yang benar-benar diuji.

## 19. Deliverables

Hasil akhir harus mencakup:
1. Backend API yang berfungsi.
2. Migration dan ERD terdokumentasi.
3. Seeder role dan data demo yang aman.
4. Autentikasi Bearer Token.
5. REST API dengan authorization.
6. Frontend Vue dengan Bootstrap.
7. Dashboard, login, notifications, dan halaman operasional.
8. Light/dark theme.
9. Audit trail dan workflow transfer.
10. Automated tests.
11. README instalasi dan penggunaan.
12. Dokumentasi endpoint API.
13. Laporan test yang membedakan test berhasil, gagal, dan belum dijalankan.

## 20. Aturan kerja coding agent

- Mulai dari inspeksi workspace, bukan langsung menulis ulang semua file.
- Implementasikan dalam tahap kecil yang dapat diverifikasi.
- Sebelum membuat perubahan besar, jelaskan file yang akan diubah dan alasannya.
- Gunakan kode yang maintainable, typed jika tooling mendukung, dan konsisten dengan style proyek.
- Hindari duplikasi logic.
- Jangan menggunakan dummy data untuk menggantikan API yang seharusnya sudah tersedia.
- Jangan mengklaim fitur selesai jika hanya berupa tampilan statis.
- Jangan mengklaim test berhasil tanpa menjalankannya.
- Jangan menghapus file, data, migration, atau konfigurasi existing tanpa memastikan dampaknya.
- Jika ada asumsi penting yang belum jelas, dokumentasikan asumsi dan pilih default yang aman.
- Jika terjadi error, telusuri penyebabnya sebelum melakukan perubahan tambahan.

Mulai sekarang dari Tahap 1: inspeksi workspace DaetSys, verifikasi kondisi backend dan frontend, lalu berikan laporan singkat tentang struktur existing, dependensi, potensi konflik, dan rencana implementasi yang sesuai dengan kondisi nyata proyek. Jangan langsung melakukan rewrite seluruh aplikasi dalam satu langkah.