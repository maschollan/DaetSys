/**
 * Konstanta status dan label yang sinkron dengan enum backend
 * (App\Enums\AssetStatus, TransferStatus, ActivityAction).
 */

export const ASSET_STATUSES = [
  { value: 'available', label: 'Tersedia' },
  { value: 'in_transfer', label: 'Dalam Transfer', disabled: true },
  { value: 'maintenance', label: 'Perawatan' },
  { value: 'missing', label: 'Hilang' },
]

export const ASSET_ASSIGNABLE_STATUSES = ASSET_STATUSES.filter((status) => !status.disabled)

export const ASSET_STATUS_BADGES = {
  available: 'ds-badge-success',
  in_transfer: 'ds-badge-info',
  maintenance: 'ds-badge-warning',
  missing: 'ds-badge-danger',
}

export const TRANSFER_STATUSES = [
  { value: 'pending', label: 'Menunggu' },
  { value: 'approved', label: 'Disetujui' },
  { value: 'rejected', label: 'Ditolak' },
  { value: 'handover', label: 'Serah Terima' },
  { value: 'completed', label: 'Selesai' },
  { value: 'cancelled', label: 'Dibatalkan' },
]

export const TRANSFER_STATUS_BADGES = {
  pending: 'ds-badge-warning',
  approved: 'ds-badge-info',
  rejected: 'ds-badge-danger',
  handover: 'ds-badge-info',
  completed: 'ds-badge-success',
  cancelled: 'ds-badge-secondary',
}

export const ACTIVITY_ACTIONS = [
  'asset.created',
  'asset.updated',
  'asset.deleted',
  'asset.location_change_attempt',
  'transfer.requested',
  'transfer.approved',
  'transfer.rejected',
  'transfer.handed_over',
  'transfer.received',
  'transfer.cancelled',
  'transfer.conflict',
  'location.created',
  'location.updated',
  'location.deleted',
  'category.created',
  'category.updated',
  'category.deleted',
  'user.created',
  'user.updated',
  'user.deleted',
  'user.deactivated',
  'auth.login_failed',
  'auth.login_succeeded',
  'auth.logged_out',
  'auth.password_changed',
  'auth.unauthorized_access',
]

export const ASSET_STATUS_LABELS = Object.fromEntries(
  ASSET_STATUSES.map((status) => [status.value, status.label]),
)

export const TRANSFER_STATUS_LABELS = Object.fromEntries(
  TRANSFER_STATUSES.map((status) => [status.value, status.label]),
)

/**
 * Label tindakan audit: `asset.created` -> `Asset created` (backend memakai
 * dot notation; tampilkan apa adanya agar cocok dengan filter).
 */
export function formatAction(action) {
  return action
}

export function formatDateTime(iso) {
  if (!iso) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return iso
  return date.toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

export function formatDate(iso) {
  if (!iso) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return iso
  return date.toLocaleDateString('id-ID', { dateStyle: 'medium' })
}
