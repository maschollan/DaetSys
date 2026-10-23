import { ASSET_STATUS_LABELS, TRANSFER_STATUS_LABELS } from '@/constants/status'

/**
 * Menyusun `old_values`/`new_values` jejak audit menjadi daftar perubahan
 * yang mudah dibaca (bukan JSON mentah).
 *
 * Nilai enum (status aset/transfer) diterjemahkan ke label Indonesia;
 * kolom relasional numerik ditandai sebagai `Lokasi #id` / `Pengguna #id`.
 */

const FIELD_LABELS = {
  status: 'Status',
  location_id: 'Lokasi',
  notes: 'Catatan',
  reason: 'Alasan',
  received_by: 'Penerima',
  handed_over_by: 'Penyerah',
}

/**
 * @param {Object|null} oldValues
 * @param {Object|null} newValues
 * @returns {list<{key: string, label: string, old: any, new: any}>}
 */
export function describeChanges(oldValues, newValues) {
  const oldObject = oldValues ?? {}
  const newObject = newValues ?? {}

  return [...new Set([...Object.keys(oldObject), ...Object.keys(newObject)])].map((key) => ({
    key,
    label: FIELD_LABELS[key] ?? key,
    old: oldObject[key] ?? null,
    new: newObject[key] ?? null,
  }))
}

/**
 * @param {string} key
 * @param {any} value
 */
export function formatChangeValue(key, value) {
  if (value === null || value === undefined || value === '') return '—'

  if (key === 'status') {
    return ASSET_STATUS_LABELS[value] ?? TRANSFER_STATUS_LABELS[value] ?? value
  }

  if (key === 'location_id') return `Lokasi #${value}`
  if (key === 'received_by' || key === 'handed_over_by') return `Pengguna #${value}`

  return String(value)
}
