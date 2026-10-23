/**
 * Peta notifikasi → halaman tujuan.
 *
 * Data notifikasi (payload database) memuat:
 * - transfer.*: `transfer_id`, `asset_id`, `status` → detail transfer.
 * - AssetStatusNotification: `asset_id`, `asset_code`, `status` → daftar aset
 *   (daftar memakai qr_token untuk URL detailnya, bukan id numerik).
 */

const TRANSFER_TYPES = [
  'TransferRequested',
  'TransferApproved',
  'TransferRejected',
  'TransferHandoverRequested',
  'TransferReceived',
  'TransferCompleted',
]

/**
 * @param {{ type?: string, data?: Object }} notification
 * @returns {Object|null} Objek target RouterLink ({ name, params/query }) atau null.
 */
export function notificationTarget(notification) {
  const type = String(notification.type ?? '')
  const data = notification.data ?? {}

  const isTransfer = TRANSFER_TYPES.some((transferType) => type.includes(transferType))

  if (isTransfer && data.transfer_id) {
    // Detail transfer memakai uniqid; payload notifikasi lama masih
    // membawa id numerik — backend menerima keduanya pada binding rute.
    return { name: 'transfers.detail', params: { uniqId: data.transfer_id } }
  }

  if (data.asset_id && data.asset_code) {
    return { name: 'assets', query: { search: data.asset_code } }
  }

  return null
}
