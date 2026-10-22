# DaetSys V1 Backend

Digital Asset & Evidence Tracking System - API-only backend (Laravel 13 + Sanctum).

## Setup
1. Copy `.env.example` to `.env`, configure MySQL (`daetsys_v1`)
2. `composer install`
3. `php artisan key:generate`
4. `php artisan migrate --seed`
5. `php artisan serve`

## Dokumentasi API

Dokumentasi endpoint tersedia dalam dua bentuk, keduanya selalu mengikuti
kode terbaru karena sumbernya adalah dokumen OpenAPI yang sama.

| Bentuk | Alamat | Keterangan |
| --- | --- | --- |
| **Interaktif** | `/docs` | Halaman Scalar API Reference: penjelasan endpoint, parameter, form coba request, dan hasil respons secara langsung. |
| **Mesin** | `/docs/api.json` | Dokumen OpenAPI 3.1 (bisa diimpor ke Postman, Insomnia, atau generator SDK). |
| **Statis** | `docs/DAFTAR_ENDPOINT_API.md` | Daftar endpoint, parameter, dan respons dalam Markdown. |

```bash
# Perbarui dokumen Markdown setelah ada perubahan endpoint
php artisan daetsys:docs
```

Pada lingkungan `local` dokumentasi terbuka untuk semua orang. Pada
lingkungan lain hanya admin aktif yang boleh membukanya (gate `viewApiDocs`).

## Testing
```bash
php artisan test
```
Tests use MySQL `daetsys_v1_test` (configured in `phpunit.xml`).

## Features
- Role-based access (admin/officer/auditor), separation of duties
- Asset management with QR tracking
- Transfer workflow with approvals, handover/receive, location update on receive
- Activity audit trail (append-only)
- Notifications, reports, dashboard
- Idempotency for critical POSTs
- Interactive API documentation generated from the code
