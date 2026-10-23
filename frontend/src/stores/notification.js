import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import api from '@/services/api'

export const useNotificationStore = defineStore('notification', () => {
  const items = ref([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const error = ref('')
  const isOpen = ref(false)

  /** State paginasi untuk infinite scroll halaman notifikasi. */
  const page = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const loadingMore = ref(false)
  let pollTimer = null

  const hasMore = computed(() => page.value < lastPage.value)

  /**
   * @param {object} [options]
   * @param {boolean} [options.unreadOnly] Hanya notifikasi belum dibaca.
   * @param {number} [options.page] Nomor halaman (mulai 1).
   * @param {number} [options.perPage] Ukuran halaman.
   * @param {boolean} [options.append] `true` = gabungkan ke daftar (page berikutnya); default mengganti seluruh daftar.
   * @param {boolean} [options.onlyCount] `true` = hanya segarkan `unreadCount` tanpa menyentuh `items` (dipakai polling).
   */
  async function fetch({
    unreadOnly = false,
    page: requestedPage = 1,
    perPage = 10,
    append = false,
    onlyCount = false,
  } = {}) {
    if (onlyCount) {
      loading.value = true
    } else if (append) {
      loadingMore.value = true
    } else {
      loading.value = true
      error.value = ''
    }

    try {
      const { data } = await api.notifications.list({
        unread_only: unreadOnly ? 1 : undefined,
        page: requestedPage,
        per_page: perPage,
      })

      unreadCount.value = data.meta?.unread_count ?? 0
      page.value = data.meta?.current_page ?? requestedPage
      lastPage.value = data.meta?.last_page ?? 1
      total.value = data.meta?.total ?? 0

      if (!onlyCount) {
        const freshItems = data.data ?? []
        // Guard race: respons halaman lama yang tiba belakangan tidak boleh
        // menimpa daftar halaman terbaru.
        if (append && requestedPage > 1) {
          const known = new Set(items.value.map((item) => item.id))
          items.value = [...items.value, ...freshItems.filter((item) => !known.has(item.id))]
        } else {
          items.value = freshItems
        }
      }

      return data
    } catch (exception) {
      if (!onlyCount) {
        error.value = exception.response?.data?.message ?? 'Gagal memuat notifikasi.'
      }
      return null
    } finally {
      loading.value = false
      loadingMore.value = false
    }
  }

  /** Ambil halaman berikutnya (append ke daftar yang sudah ada). */
  async function loadMore(perPage = 10) {
    if (!hasMore.value || loadingMore.value) return
    return fetch({ page: page.value + 1, perPage, append: true })
  }

  async function markAsRead(id) {
    try {
      await api.notifications.markAsRead(id)
      const item = items.value.find((notification) => notification.id === id)
      if (item && !item.read_at) {
        item.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch {
      // Dropdown tetap berfungsi walau penandaan gagal.
    }
  }

  async function markAllAsRead() {
    try {
      await api.notifications.markAllAsRead()
      items.value.forEach((item) => {
        if (!item.read_at) item.read_at = new Date().toISOString()
      })
      unreadCount.value = 0
    } catch {
      // Abaikan; refresh akan dilakukan saat dropdown dibuka lagi.
    }
  }

  function startPolling(intervalMs = 60000) {
    stopPolling()
    pollTimer = window.setInterval(() => {
      if (!isOpen.value) {
        // Polling hanya menyegarkan badge unread agar daftar yang sedang
        // ditampilkan (mis. hasil infinite scroll) tidak ikut terpotong.
        fetch({ onlyCount: true, perPage: 1 })
      }
    }, intervalMs)
  }

  function stopPolling() {
    if (pollTimer) {
      window.clearInterval(pollTimer)
      pollTimer = null
    }
  }

  const hasUnread = computed(() => unreadCount.value > 0)

  return {
    items,
    unreadCount,
    loading,
    error,
    isOpen,
    hasUnread,
    page,
    lastPage,
    total,
    hasMore,
    loadingMore,
    fetch,
    loadMore,
    markAsRead,
    markAllAsRead,
    startPolling,
    stopPolling,
  }
})

export default useNotificationStore
