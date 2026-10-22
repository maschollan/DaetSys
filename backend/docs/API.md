# DaetSys API Documentation

Dokumentasi lengkap (endpoint, parameter, aturan bisnis, dan contoh respons)
tersedia pada:

| Bentuk | Alamat | Keterangan |
| --- | --- | --- |
| Interaktif | `/docs` | Halaman Scalar API Reference dengan form coba request dan hasil respons. |
| OpenAPI | `/docs/api.json` | Dokumen OpenAPI 3.1. |
| Markdown | `docs/DAFTAR_ENDPOINT_API.md` | Hasil ekspor `php artisan daetsys:docs`. |

## Base URL
`/api`

## Authentication
- Public: `POST /api/login`
- Authenticated: use `Authorization: Bearer {token}` (Laravel Sanctum). Tokens have no expiry and are revoked on logout.

## Core Endpoints
- `GET /api/health`
- `POST /api/login` (throttle:login)
- `POST /api/logout`, `GET /api/me`, `POST /api/change-password`
- Dashboard: `/api/dashboard/{summary|assets-by-location|assets-by-status|recent-activities|pending-transfers}`
- Assets: CRUD + `GET /api/assets/qr/{qr_token}`, `GET /api/assets/{asset}/history`
- Asset Categories, Locations: CRUD
- Transfers: `GET/POST /api/transfers`, `GET /api/transfers/{id}`, actions `POST /api/transfers/{id}/{approve|reject|handover|receive|cancel}` (idempotent)
- Activity Logs: `GET /api/activity-logs`, `GET /api/activity-logs/{id}` (auditor/admin only per policy)
- Reports: `/api/reports/{assets|transfers|activity-logs}` (throttle:reports)
- Notifications: list/read/mark-all-read
- Users: CRUD + `POST /api/users/{user}/reset-password`

## Notes
- Location cannot be changed via `PUT/PATCH /api/assets/{asset}` (returns 422 and logs attempt)
- Transfer workflow enforces separation of duties, DB guard on active transfers (409 on conflict), location updated only after receive
- Idempotency supported for retryable POSTs via `Idempotency-Key` header
- Activity logs are append-only
