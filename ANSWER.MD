# ANSWER.MD — Study Case: Digital Asset & Evidence Tracking System (DaetSys)

> **Repo:** <https://github.com/maschollan/DaetSys> — cabang utama `main`.
> **Stack:** Laravel 13 (API-only, Sanctum) · Vue 3 Options API (SPA) · MySQL (local) / PostgreSQL Supabase (production) · Docker + Render.
> **Kualitas:** 58 test feature hijau (298 assertions) — `composer test` / `php artisan test --compact`, semua PHP lolos `vendor/bin/pint`.
> **Dokumentasi interaktif API:** `/docs` (Scalar) — hanya admin, dengan halaman masuk `/docs/login`.

---

## Arsitektur ringkas

```
Vue 3 SPA (frontend/) ──fetch JSON──▶ Laravel API (backend/)
                                        ├─ Layer Controller  : tipis, hanya delegasi
                                        ├─ Layer Service     : seluruh aturan bisnis
                                        ├─ Layer FormRequest : validasi + whitelist filter
                                        ├─ Layer Policy      : otorisasi per objek
                                        └─ Layer Resource    : transformasi respons
```

Prinsip yang dipegang di seluruh kode:

- **Aturan bisnis tidak pernah tinggal di controller** — semua mutasi melewati `App\Services\*` sehingga bisa diuji dan dipakai ulang.
- **Setiap mutasi kritis = transaksi database + `lockForUpdate` + audit trail** ([backend/app/Services/TransferService.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L19-L29)).
- **Tanpa data dummy** — seeder demo hanya aktif di environment `local` ([backend/app/Providers/AppServiceProvider.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Providers/AppServiceProvider.php)).

---

## 1. Fitur

### 1.1 Login & Role (Admin / Petugas / Auditor)

Role disimpan sebagai enum, digerbang lewat middleware route-level + Policy per objek ([backend/app/Enums/UserRole.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Enums/UserRole.php#L5-L19)):

```php
enum UserRole: string
{
    case Admin = 'admin';
    case Officer = 'officer';
    case Auditor = 'auditor';
}
```

Middleware gerbang route ([backend/app/Http/Middleware/EnsureRole.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Middleware/EnsureRole.php#L17-L30)) — manajemen pengguna hanya admin:

```php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    $user = $request->user();
    $userRole = $user?->role?->name;

    if ($userRole === null || ! in_array($userRole, $roles, true)) {
        return response()->json(['message' => 'This action is unauthorized.'], 403);
    }

    return $next($request);
}
```

Policy dipanggil dari `authorize()` FormRequest, sehingga request yang tidak sah ditolak **sebelum** menyentuh service ([backend/app/Http/Requests/ApproveTransferRequest.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Requests/ApproveTransferRequest.php#L9-L12)):

```php
public function authorize(): bool
{
    return $this->user()?->can('approve', $this->route('transfer')) ?? false;
}
```

Aliasan middleware `role`, `active.user`, dan `idempotent` didaftarkan di kernel ([backend/bootstrap/app.php](https://github.com/maschollan/DaetSys/blob/main/backend/bootstrap/app.php#L33-L41)), dan semua route API terlindungi melewati grup `auth:sanctum` + `active.user` ([backend/routes/api.php](https://github.com/maschollan/DaetSys/blob/main/backend/routes/api.php#L28)):

Otorisasi per objek memakai Policy — contoh: **approval hanya admin dan tidak boleh transfer milik sendiri** ([backend/app/Policies/TransferPolicy.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Policies/TransferPolicy.php#L32-L36)):

```php
public function approve(User $user, Transfer $transfer): bool
{
    return $user->hasRole(UserRole::Admin)
        && $transfer->requested_by !== $user->id;
}
```

Akun nonaktif otomatis dicabut tokennya di setiap request API ([backend/app/Http/Middleware/EnsureUserIsActive.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Middleware/EnsureUserIsActive.php#L18-L31)).

### 1.2 Asset Management

CRUD aset lewat service dengan transaksi + audit otomatis untuk setiap perubahan ([backend/app/Services/AssetService.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/AssetService.php#L61-L98)):

```php
public function update(User $actor, Asset $asset, array $data, ?Request $request = null): Asset
{
    return DB::transaction(function () use ($actor, $asset, $data, $request): Asset {
        $locked = Asset::query()->lockForUpdate()->find($asset->id);

        if ($locked->status === AssetStatus::InTransfer) {
            throw BusinessRuleException::conflict('Asset is currently in transfer and cannot be modified.');
        }

        $oldValues = $this->auditableAttributes($locked);
        $locked->fill($data)->save();

        $this->audit->log(
            ActivityAction::AssetUpdated,
            user: $actor,
            asset: $locked,
            oldValues: $oldValues,
            newValues: $this->auditableAttributes($locked),
            request: $request,
        );
        ...
```

### 1.3 QR Code Tracking

Setiap aset punya `qr_token` acak 40 karakter (tidak dapat ditebak, unik di level database) — pembuatannya di [backend/app/Services/QrTokenService.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/QrTokenService.php#L21-L28):

```php
public function generate(): string
{
    do {
        $token = Str::random(self::TOKEN_LENGTH);
    } while (Asset::withTrashed()->where('qr_token', $token)->exists());

    return $token;
}
```

Kekhasan token dijaga di level database dengan unique index ([backend/database/migrations/2026_10_22_000005_create_assets_table.php](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000005_create_assets_table.php#L15-L19)):

```php
$table->string('asset_code')->unique();
$table->string('qr_token', 64)->unique();
$table->string('serial_number')->nullable()->unique();
```

Penggunaannya sebagai endpoint terbuka untuk deteksi QR — [backend/routes/api.php](https://github.com/maschollan/DaetSys/blob/main/backend/routes/api.php#L45):

```php
Route::get('/assets/qr/{qr_token}', [AssetController::class, 'byQrToken']);
```

### 1.4 Transfer Barang (dengan alur persetujuan)

Alur mesin-status transfer:

```
pending → approved → handover → completed
   ↘ rejected      ↘ cancelled
```

Perpindahan status dikawal aturan `enum` transisi yang sah ([backend/app/Services/TransferService.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L423-L437)):

```php
private function assertTransition(Transfer $transfer, TransferStatus $target): void
{
    $current = $transfer->status;

    if (! $current->canTransitionTo($target)) {
        throw BusinessRuleException::unprocessable(
            sprintf('Transfer cannot move from %s to %s.', $current->value, $target->value),
            ['status' => [$current->value]]
        );
    }
}
```

Alur lengkap: **request → approve/reject → handover → receive**, setiap langkah dilindungi `lockForUpdate` + audit ([backend/app/Services/TransferService.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L40-L124)) — dan lokasi aset **hanya berubah di titik penerimaan** ([receive, L340-343](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L340-L349)):

```php
$asset->update([
    'location_id' => $locked->to_location_id,
    'status' => AssetStatus::Available->value,
]);

$locked->update([
    'status' => TransferStatus::Completed->value,
    'completed_at' => now(),
    'active_asset_id' => null,
]);
```

### 1.5 Activity Log

Satu pintu pencatatan audit: [backend/app/Services/AuditService.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/AuditService.php#L41-L61) — menyimpan `user_id`, `asset_id`, `transfer_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`, dan waktu; nilai sensitif di-**redacted**:

```php
public function log(ActivityAction $action, ?User $user = null, ?Asset $asset = null,
                    ?Transfer $transfer = null, ?array $oldValues = null,
                    ?array $newValues = null, ?Request $request = null): ActivityLog
{
    return ActivityLog::create([
        'user_id' => $user?->id,
        'asset_id' => $asset?->id,
        'transfer_id' => $transfer?->id,
        'action' => $action->value,
        'old_values' => $oldValues === null ? null : $this->scrub($oldValues),
        'new_values' => $newValues === null ? null : $this->scrub($newValues),
        'ip_address' => $request?->ip(),
        'user_agent' => $request?->userAgent(),
        'created_at' => now(),
    ]);
}
```

### 1.6 Dashboard

Angka dashboard selalu dari **agregasi SQL** (bukan loop N+1) dan di-**cache hingga 60 detik** dengan invalidasi cepat setiap kali aset/transfer berubah — [backend/app/Services/DashboardService.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/DashboardService.php#L26-L62):

```php
public function summary(): array
{
    return Cache::remember(
        self::CACHE_KEY,
        (int) config('daetsys.dashboard_cache_ttl', 60),
        fn (): array => $this->buildSummary(),
    );
}

public function buildSummary(): array
{
    $assetsByStatus = Asset::query()
        ->selectRaw('status, COUNT(*) as aggregate')
        ->groupBy('status')
        ->pluck('aggregate', 'status');
    ...
}
```

Pembagian per lokasi juga agregat satu query ([assetsByLocation](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/DashboardService.php#L67-L81)).

### 1.7 Reporting

Laporan disusun dengan paginasi `paginate` dan filter **whitelisted** — input user tidak pernah diteruskan langsung menjadi query ([backend/app/Services/ReportService.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/ReportService.php#L23-L40)):

```php
public function assets(array $filters): LengthAwarePaginator
{
    $query = Asset::query()
        ->with(['category:id,name', 'location:id,code,name'])
        ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
        ->when($filters['location_id'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
        ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
        ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('acquisition_date', '>=', $v))
        ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('acquisition_date', '<=', $v));
    ...
    return $query->paginate($filters['per_page'] ?? 25)->withQueryString();
}
```

### 1.8 Data Validation (anti-duplikat)

Duplikasi ditutup dari dua sisi — validasi dan database. Cek unik pada model (contoh: kode aset, serial number) ([backend/app/Http/Requests/StoreAssetRequest.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Requests/StoreAssetRequest.php#L25-L28)):

```php
'asset_code' => ['required', 'string', 'max:100', 'unique:assets,asset_code'],
'serial_number' => ['sometimes', 'nullable', 'string', 'max:100', 'unique:assets,serial_number'],
```

dan **unique index di level database** sebagai pengaman terakhir ([migrasi aset](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000005_create_assets_table.php#L15-L19)) — bila dua petugas menyimpan kode aset yang sama pada saat bersamaan, database menolak salah satunya.

---

## 2. Skenario Sulit

### Scenario 1 — Concurrent Update (dua petugas memindahkan barang sama bersamaan)

**Solusi tiga lapis:** pessimistic lock → guard aplikasi → UNIQUE index database.

Dalam transaksi, baris aset **dikunci** terlebih dahulu — request kedua sampai akan **menunggu** lalu melihat status aset berubah:

```php
// TransferService::request (L48-L102)
return DB::transaction(function () use ($requester, $assetId, $toLocationId, ...): Transfer {
    $asset = Asset::query()->lockForUpdate()->find($assetId);

    if ($asset->status !== AssetStatus::Available) {
        throw BusinessRuleException::conflict('Asset is not available for transfer. ...');
    }
    ...
    // Guard aplikasi, tambahan pengaman
    if (Transfer::query()->where('active_asset_id', $asset->id)->exists()) {
        throw BusinessRuleException::conflict('Asset already has an active transfer.');
    }
```

Penjaga **terakhir di level database** — UNIQUE index ([backend/database/migrations/2026_10_22_000006_create_transfers_table.php](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000006_create_transfers_table.php#L23-L31)):

```php
// Kolom ini berisi `asset_id` selama transfer masih aktif
// (pending / approved / handover) dan NULL saat transfer selesai —
// UNIQUE index memastikan database yang menolak permintaan kedua.
$table->unsignedBigInteger('active_asset_id')->nullable()->unique();
```

Pelanggaran UNIQUE itu diterjemahkan menjadi 409 Conflict yang informatif, bukan error mentah ([TransferService::translateUniqueViolation](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L442-L454)):

```php
private function translateUniqueViolation(QueryException $exception): \Throwable
{
    if ($sqlState === '23000' && $driverCode === 1062) {
        return BusinessRuleException::conflict('Asset already has an active transfer. Please reload and try again.');
    }
    return $exception;
}
```

> **Bukti tes:** [tests/Feature/Workflow/TransferWorkflowTest.php](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Workflow/TransferWorkflowTest.php).

### Scenario 2 — Data Manipulation (petugas mengubah lokasi barang langsung lewat API)

**Solusi:** lokasi aset dinyatakan **tidak dapat diubah langsung dari API** — hanya bisa lewat proses transfer yang disetujui.

FormRequest menandai `location_id` sebagai `prohibited` ([backend/app/Http/Requests/UpdateAssetRequest.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Requests/UpdateAssetRequest.php#L29-L61)):

```php
'location_id' => ['prohibited'],
...
'location_id.prohibited' => 'Lokasi aset tidak dapat diubah langsung. Gunakan proses transfer.',
```

Selain itu, **percobaan manipulasi tetap dicatat di audit trail** sebelum request ditolak ([UpdateAssetRequest::failedValidation](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Requests/UpdateAssetRequest.php#L64-L83)):

```php
protected function failedValidation(Validator $validator): void
{
    if ($validator->errors()->has('location_id')) {
        app(AuditService::class)->log(
            ActivityAction::AssetLocationChangeAttempt,
            user: $this->user(),
            asset: $asset instanceof Asset ? $asset : null,
            newValues: ['location_id' => $this->input('location_id')],
            request: $this,
        );
    }
    parent::failedValidation($validator);
}
```

Ditambah dua gerbang tambahan: **service memverifikasi status** (aset dalam transfer tak bisa diubah — [AssetService::update](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/AssetService.php#L70-L74)) dan **kanal API hanya mengekspos route dengan middleware `auth:sanctum` + `active.user`** ([backend/bootstrap/app.php](https://github.com/maschollan/DaetSys/blob/main/backend/bootstrap/app.php#L34)) — tidak ada pintu belakang ke model lewat route lain.

### Scenario 3 — Audit Trail (barang hilang, riwayat perpindahan panjang)

Riwayat dibangun dari **aktivitas log** bertautan ke `asset_id`, `transfer_id`, dan `user_id` dengan waktu — tabel audit punya composite index agar filter cepat ([backend/database/migrations/2026_10_22_000009_create_activity_logs_table.php](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000009_create_activity_logs_table.php#L23-L27)):

```php
$table->index(['user_id', 'created_at']);
$table->index(['asset_id', 'created_at']);
$table->index(['transfer_id', 'created_at']);
$table->index(['action', 'created_at']);
```

Riwayat per aset disediakan endpoint khusus ([backend/routes/api.php](https://github.com/maschollan/DaetSys/blob/main/backend/routes/api.php#L46)):

```php
Route::get('/assets/{asset}/history', [AssetController::class, 'history']);
```

Dalam setiap entri transfer, `old_values`/`new_values` menangkap **siapa + apa + kapan** ([TransferService::receive — audit](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L351-L366)):

```php
$this->audit->log(
    ActivityAction::TransferReceived,
    user: $receiver,
    asset: $asset,
    transfer: $locked,
    oldValues: [
        'status' => TransferStatus::Handover->value,
        'location_id' => $locked->from_location_id,
    ],
    newValues: [
        'status' => TransferStatus::Completed->value,
        'location_id' => $locked->to_location_id,
        'received_by' => $receiver->id,
    ],
    request: $request,
);
```

Dengan transisi `request → approve/reject → handover → receive`, setiap langkah pengesahan diikat ke nama petugas yang melakukannya beserta waktunya — sehingga untuk barang yang hilang, riwayat kueri `/assets/{asset}/history` selalu dapat menunjukkan **pemegang terakhir** dan seluruh rantai perpindahannya.

### Scenario 4 — Data Integrity saat gangguan (transfer di tengah proses)

**Solusi: semua mutasi transfer dibungkus satu transaksi** — gagal di tengah = seluruh perubahan dibatalkan, aset tidak mungkin tercatat di dua lokasi ([TransferService::request](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/TransferService.php#L47-L121)):

```php
return DB::transaction(function () use (...): Transfer {
    $asset = Asset::query()->lockForUpdate()->find($assetId);   // kunci baris
    ...
    Transfer::create([... 'active_asset_id' => $asset->id]);
    $asset->update(['status' => AssetStatus::InTransfer->value]);
    $this->audit->log(...);                                     // log ikut transaksi
    DB::afterCommit(fn () => $this->notifications->notifyTransferRequested($transfer));
    return $transfer;
});
```

Gangguan di mana pun dijaga dua lapis:

- **UNIQUE `active_asset_id`** → hanya boleh ada satu transfer aktif per aset, sekaligus;
- Status aset tidak akan pernah "setengah terganti": mutasi status aset dan mutasi transfer dilakukan dalam transaksi yang sama, selalu setelah barisnya dikunci dengan `lockForUpdate` — bila transaksi rollback, status lama tetap.

Lapis tambahan untuk duplikasi klik/kirim ulang: middleware **Idempotency-Key** ([backend/app/Http/Middleware/EnsureIdempotency.php](https://github.com/maschollan/DaetSys/blob/main/backend/app/Http/Middleware/EnsureIdempotency.php#L13-L40)) — kirim ulang request yang sama mengembalikan respons **tersimpan sebelumnya**, bukan menggandakan transfer:

```php
try {
    $this->idempotency->reserve($key, $scope, $request->user(), $payload);
} catch (IdempotentReplay $replay) {
    return response($replay->body(), $replay->status())
        ->header('Idempotent-Replayed', 'true');
}
```

### Scenario 5 — Performance (500.000 aset, jutaan riwayat aktivitas)

Empat strategi di kode:

1. **Indexing komprehensif mengikuti pola query baca** — index dibuat pas dengan filter dan urutan yang benar-benar dipakai aplikasi ([backend/database/migrations/2026_10_22_000005_create_assets_table.php](https://github.com/maschollan/DaetSys/blob/main/backend/database/migrations/2026_10_22_000005_create_assets_table.php#L25-L29)):

    ```php
    $table->index('status');
    $table->index('category_id');
    $table->index(['location_id', 'status']);      // filter lokasi+status
    $table->index(['status', 'created_at']);       // sort waktu per status
    $table->index('created_at');
    ```

2. **Dashboard memakai agregat + cache**, bukan menghitung loop aplikasi ([DashboardService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/DashboardService.php#L26-L32)) — COUNT di level SQL via `selectRaw('status, COUNT(*)')`; ringkasan di-cache 60 detik:

    ```php
    return Cache::remember(self::CACHE_KEY, (int) config(...), fn () => $this->buildSummary());
    ```

3. **Reporting selalu `paginate` + `LengthAwarePaginator` + eager loading kolom selektif** ([ReportService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/ReportService.php#L25-L39)) — tidak pernah memuat seluruh 500k baris; `with(['category:id,name', 'location:id,code,name'])` menutup N+1:

    ```php
    ->with(['category:id,name', 'location:id,code,name'])
    ...
    ->paginate($filters['per_page'] ?? 25)->withQueryString();
    ```

4. **Filter ter-whitelist dan pencarian teks terbatas** ([ReportService](https://github.com/maschollan/DaetSys/blob/main/backend/app/Services/ReportService.php#L27-L39)) — filter dan pengurutan selalu berasal dari whitelist, tidak pernah diteruskan mentah dari input pengguna; pencarian `LIKE '%keyword%'` dibatasi hanya ke tiga kolom aset (`asset_code`, `name`, `serial_number`) dan dijalankan **di dalam query berpaginasi**, sehingga hasilnya dibatasi `per_page` dan tidak pernah memindai 500k baris ke memori aplikasi.

> **Bukti tes performa/paginasi:** Dashboard dan Report diaudit terpisah — [tests/Feature/Api/DashboardApiTest.php](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/DashboardApiTest.php), [tests/Feature/Api/ReportApiTest.php](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/ReportApiTest.php).

---

## 3. Uji & Verifikasi

| Bagian | Test |
|---|---|
| Auth & role | [tests/Feature/Api/AuthTest.php](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/AuthTest.php) |
| Asset CRUD + validasi duplikat | [tests/Feature/Api/AssetTest.php](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/AssetTest.php) |
| Alur Transfer lengkap + concurrency | [tests/Feature/Workflow/TransferWorkflowTest.php](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Workflow/TransferWorkflowTest.php) |
| Audit trail & privacy redaction | [tests/Feature/Api/AuditTest.php](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/AuditTest.php) |
| Dashboard & Report | [DashboardApiTest](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/DashboardApiTest.php) · [ReportApiTest](https://github.com/maschollan/DaetSys/blob/main/backend/tests/Feature/Api/ReportApiTest.php) |

Hasil terakhir: **58 test / 298 assertions lulus**, PHP styling bersih `Pint`.

## 4. Deployment

- **Backend:** Docker ([backend/Dockerfile](https://github.com/maschollan/DaetSys/blob/main/backend/Dockerfile)) di Render — boot menjalankan migrasi + seeder produksi + `config:cache` + ekspor OpenAPI, lalu `php artisan serve`; `/docs` terproteksi sesi admin.
- **Frontend:** SPA statik di Render ([render.yaml](https://github.com/maschollan/DaetSys/blob/main/render.yaml)) menunjuk `VITE_API_BASE_URL` ke layanan API.
