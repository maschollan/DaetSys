# Database Schema (DaetSys V1)

## Core Tables
- `roles`, `users` (Sanctum personal_access_tokens)
- `asset_categories`, `locations`
- `assets` (soft deletes, qr_token unique, status enum)
- `transfers` (active_asset_id nullable UNIQUE for concurrency guard)
- `transfer_approvals`
- `asset_handover` (table name)
- `activity_logs` (append-only)
- `notifications`
- `idempotency_keys`
