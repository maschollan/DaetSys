import { ref } from 'vue'
import { defineStore } from 'pinia'

import api from '@/services/api'
import useUiStore from '@/stores/ui'

/*
 * Cache opsi untuk modal ajukan transfer. Tujuan: modal tidak membombardir
 * API saat setiap kali dibuka, tetapi data aset tetap cukup segar untuk
 * mencegah race condition pengajuan ganda (aset yang sedang berstatus
 * in_transfer tidak boleh ikut terpilih).
 *
 * - Aset: cache 60 detik; pencarian server tetap langsung memanggil API,
 *   dan hasil dibuang bila sudah tergantikan oleh query terbaru.
 * - Lokasi: cache 5 menit (data master yang jarang berubah).
 */

const ASSET_TTL_MS = 60_000
const LOCATION_TTL_MS = 300_000

export const useTransferOptionsStore = defineStore('transferOptions', () => {
  const assetOptions = ref([])
  const assetLoading = ref(false)
  const assetHasMore = ref(false)
  const assetLoadingMore = ref(false)
  const locationOptions = ref([])

  let assetsLoadedAt = 0
  let assetPage = 1
  let locationsLoadedAt = 0
  let latestAssetQuery = ''

  const ASSET_PER_PAGE = 25

  async function loadAssets(query = '', { append = false } = {}) {
    const ui = useUiStore()

    if (append) {
      // Guard: tidak ada halaman berikutnya, sudah ada proses berjalan,
      // atau query sudah diganti (inline-scroll usang).
      if (!assetHasMore.value || assetLoadingMore.value || latestAssetQuery !== query) {
        return
      }
      assetLoadingMore.value = true
    } else {
      // Ketik-langsung: query terbaru menang — respons telat diabaikan.
      latestAssetQuery = query
      assetLoading.value = true
    }

    const page = append ? assetPage + 1 : 1

    try {
      const { data } = await api.assets.list({
        search: query || undefined,
        per_page: ASSET_PER_PAGE,
        page,
        sort_by: 'asset_code',
        sort_direction: 'asc',
      })

      if (append && latestAssetQuery !== query) {
        return // Respons usang dari query yang sudah ditinggalkan.
      }

      // Hanya aset tersedia yang boleh diajukan; workflow backend menolak
      // aset yang sedang dipindahkan (PLAN.md Skenario 1).
      const mapped = (data.data ?? [])
        .filter((asset) => asset.status === 'available')
        .map((asset) => ({ value: asset.id, label: `${asset.asset_code} — ${asset.name}` }))

      if (append) {
        const existing = new Set(assetOptions.value.map((option) => String(option.value)))
        assetOptions.value = [
          ...assetOptions.value,
          ...mapped.filter((option) => !existing.has(String(option.value))),
        ]
      } else {
        if (latestAssetQuery !== query) {
          return // Respons telat dari query lama — abaikan.
        }
        assetOptions.value = mapped
        assetsLoadedAt = Date.now()
      }

      const meta = data.meta ?? {}
      assetPage = meta.current_page ?? page
      assetHasMore.value = (meta.current_page ?? page) < (meta.last_page ?? page)
    } catch (exception) {
      ui.error(ui.apiError(exception, 'Gagal memuat daftar aset.'))
    } finally {
      assetLoading.value = false
      assetLoadingMore.value = false
    }
  }

  /** Muat ulang daftar aset hanya bila cache sudah kedaluwarsa. */
  async function ensureAssets() {
    if (Date.now() - assetsLoadedAt < ASSET_TTL_MS) {
      return
    }

    await loadAssets('')
  }

  /** Pencarian server selalu dimulai dari halaman 1 dengan data fresh. */
  function searchAssets(query) {
    return loadAssets(query.trim())
  }

  /** Infinite scroll: ambil halaman berikutnya dari query yang sedang aktif. */
  function loadMoreAssets() {
    return loadAssets(latestAssetQuery, { append: true })
  }

  /** Setelah aksi transfer, cache dianggap tidak valid. */
  function invalidateAssets() {
    assetsLoadedAt = 0
    assetPage = 1
    assetHasMore.value = false
  }

  async function loadLocations() {
    const ui = useUiStore()

    try {
      const { data } = await api.locations.all()

      locationOptions.value = (data.data ?? [])
        .filter((location) => location.is_active)
        .map((location) => ({ value: location.id, label: location.name, hint: location.code }))

      locationsLoadedAt = Date.now()
    } catch (exception) {
      ui.error(ui.apiError(exception, 'Gagal memuat daftar lokasi.'))
    }
  }

  async function ensureLocations() {
    if (Date.now() - locationsLoadedAt < LOCATION_TTL_MS) {
      return
    }

    await loadLocations()
  }

  return {
    assetOptions,
    assetLoading,
    assetHasMore,
    assetLoadingMore,
    locationOptions,
    ensureAssets,
    searchAssets,
    loadMoreAssets,
    invalidateAssets,
    ensureLocations,
  }
})

export default useTransferOptionsStore
