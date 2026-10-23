<script>
import useNotificationStore from '@/stores/notification'
import { formatDateTime } from '@/constants/status'
import { notificationTarget } from '@/utils/notifications'

import PageHeader from '@/components/PageHeader.vue'

/** Ukuran halaman sesuai kebutuhan: 15 notifikasi per pengambilan. */
const PER_PAGE = 15

export default {
  name: 'NotificationsView',

  components: { PageHeader },

  data() {
    return {
      perPage: PER_PAGE,
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    notifications: () => useNotificationStore(),
  },

  mounted() {
    void this.notifications.fetch({ perPage: this.perPage })
    void this.syncObserver()
  },

  beforeUnmount() {
    this.destroyObserver()
  },

  methods: {
    formatDateTime,

    notificationTarget,

    iconFor(type) {
      if (type?.includes('Transfer')) return 'bi-arrow-left-right'
      if (type?.includes('Asset')) return 'bi-box-seam'
      return 'bi-bell'
    },

    async onMarkAllRead() {
      await this.notifications.markAllAsRead()
      // Reset ke halaman pertama agar daftar kembali ringkas dan konsisten
      // dengan state paginasi store.
      await this.notifications.fetch({ perPage: this.perPage })
      await this.syncObserver()
    },

    /** Muat halaman berikutnya (dipicu sentinel maupun tombol manual). */
    async loadMore() {
      if (!this.notifications.hasMore || this.notifications.loadingMore) return
      await this.notifications.loadMore(this.perPage)
      this.syncObserver()
    },

    /**
     * Infinite scroll: sentinel di dasar daftar dipantau IntersectionObserver
     * — memicu loadMore saat terlihat. Tombol "Muat halaman berikutnya"
     * tetap tersedia sebagai fallback manual.
     */
    destroyObserver() {
      if (this.scrollObserver) {
        this.scrollObserver.disconnect()
        this.scrollObserver = null
      }
    },

    syncObserver() {
      this.destroyObserver()

      const shouldObserve =
        this.notifications.items.length > 0 &&
        this.notifications.hasMore &&
        !this.notifications.error
      if (!shouldObserve || !this.$refs.scrollRef || !this.$refs.sentinelRef) return

      // Root = wrapper yang digulir sendiri, karena body tidak menggulir
      // (layout shell: konten dan sidebar punya scroll masing-masing).
      this.scrollObserver = new IntersectionObserver(
        (entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            void this.loadMore()
          }
        },
        { root: this.$refs.scrollRef, rootMargin: '0px 0px 120px 0px' },
      )
      this.scrollObserver.observe(this.$refs.sentinelRef)
    },
  },
}
</script>

<template>
  <div class="d-flex flex-column h-100">
    <PageHeader title="Notifikasi" description="Riwayat pengumuman yang dikirimkan ke akun Anda.">
      <template #actions>
        <button
          type="button"
          class="btn btn-outline-secondary btn-sm"
          :disabled="!notifications.unreadCount"
          @click="onMarkAllRead"
        >
          <i class="bi bi-check2-all me-1"></i> Tandai semua dibaca
        </button>
      </template>
    </PageHeader>

    <!-- Wrapper gulir tunggal: seluruh daftar + sentinel hidup di satu kontainer
         dengan scroll independen; konten lain di luar wrapper ikut menyesuaikan. -->
    <div ref="scrollRef" class="ds-card ds-scroll flex-grow-1">
      <div v-if="notifications.loading && !notifications.items.length" class="text-center py-5">
        <div class="spinner-border ds-loader" role="status" aria-hidden="true"></div>
      </div>

      <div v-else-if="!notifications.items.length" class="text-center ds-muted-text py-5">
        <i class="bi bi-bell-slash h1 d-block mb-2"></i>
        Belum ada notifikasi.
      </div>

      <template v-else>
        <ul class="list-unstyled mb-0 ds-list">
          <li
            v-for="item in notifications.items"
            :key="item.id"
            class="ds-item"
            :class="{ 'ds-item-unread': !item.read_at }"
          >
            <i :class="`bi ${iconFor(item.type)} ds-item-icon`"></i>
            <div class="min-vw-0">
              <div class="d-flex flex-wrap justify-content-between gap-2">
                <strong class="small">{{ item.data?.title ?? item.type }}</strong>
                <small class="ds-muted-text text-nowrap">{{
                  formatDateTime(item.created_at)
                }}</small>
              </div>
              <div class="small">{{ item.data?.message ?? '' }}</div>
              <RouterLink
                v-if="notificationTarget(item)"
                class="small ds-redirect"
                :to="notificationTarget(item)"
                @click="notifications.markAsRead(item.id)"
              >
                Lihat detail <i class="bi bi-box-arrow-up-right"></i>
              </RouterLink>
            </div>
            <button
              v-if="!item.read_at"
              type="button"
              class="btn btn-outline-secondary btn-sm"
              @click="notifications.markAsRead(item.id)"
            >
              Tandai dibaca
            </button>
          </li>
        </ul>

        <!-- Footer paginasi: indikator loading, sentinel, dan tombol manual -->
        <div class="ds-more text-center py-3">
          <template v-if="notifications.loadingMore">
            <div class="spinner-border spinner-border-sm ds-loader" role="status">
              <span class="visually-hidden">Memuat...</span>
            </div>
          </template>
          <template v-else-if="notifications.hasMore">
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="loadMore">
              <i class="bi bi-arrow-down-circle me-1"></i> Muat halaman berikutnya
            </button>
            <div ref="sentinelRef" class="ds-sentinel" aria-hidden="true"></div>
          </template>
          <p v-else-if="notifications.items.length" class="ds-muted-text small mb-0">
            Semua notifikasi sudah dimuat ({{ notifications.total }}).
          </p>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.ds-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
}

/* Wrapper gulir tunggal untuk seluruh daftar notifikasi. */
.ds-scroll {
  overflow-y: auto;
  overscroll-behavior: contain;
  min-height: 240px;
}

.ds-list {
  max-height: none;
}

.ds-item {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.9rem 1rem;
  border-bottom: 1px solid var(--ds-border);
}

.ds-item:last-child {
  border-bottom: 0;
}

.ds-item-unread {
  background: rgba(var(--ds-primary-rgb), 0.07);
}

.ds-item-icon {
  color: var(--ds-primary);
}

.ds-redirect {
  color: var(--ds-primary);
  text-decoration: none;
  font-weight: 700;
}

.ds-redirect:hover {
  text-decoration: underline;
}

.ds-item-unread {
  position: relative;
}

/* Sentinel 1px untuk IntersectionObserver infinite scroll. */
.ds-sentinel {
  height: 1px;
}

.ds-more {
  border-top: 1px dashed var(--ds-border);
}

.ds-loader {
  color: var(--ds-primary);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
