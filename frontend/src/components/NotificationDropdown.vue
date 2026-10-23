<script>
import { Dropdown } from 'bootstrap'

import useNotificationStore from '@/stores/notification'
import { formatDateTime } from '@/constants/status'
import { notificationTarget } from '@/utils/notifications'

const ICONS = {
  asset: 'bi-box-seam',
  transfer: 'bi-arrow-left-right',
  auth: 'bi-shield-lock',
  location: 'bi-geo-alt',
  user: 'bi-person-gear',
}

export default {
  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    notifications: () => useNotificationStore(),
  },

  mounted() {
    this.dropdownInstance = new Dropdown(
      this.$refs.root.querySelector('[data-bs-toggle="dropdown"]'),
    )
    this.showHandler = () => this.handleOpen()
    this.$refs.root.addEventListener('show.bs.dropdown', this.showHandler)
  },

  beforeUnmount() {
    this.$refs.root?.removeEventListener('show.bs.dropdown', this.showHandler)
    this.dropdownInstance?.dispose()
    this.dropdownInstance = null
  },

  methods: {
    formatDateTime,

    notificationTarget,

    iconFor(type) {
      if (type?.includes('Transfer')) return ICONS.transfer
      if (type?.includes('Asset')) return ICONS.asset
      return ICONS.transfer
    },

    async handleOpen() {
      this.notifications.isOpen = true
      await this.notifications.fetch({ perPage: 10 })
    },

    async onOpenTarget(item) {
      await this.notifications.markAsRead(item.id)
      this.dropdownInstance?.hide()
    },

    async onMarkAllRead() {
      await this.notifications.markAllAsRead()
    },
  },
}
</script>

<template>
  <div ref="root" class="dropdown">
    <button
      type="button"
      class="btn btn-outline-secondary btn-sm position-relative"
      data-bs-toggle="dropdown"
      data-bs-auto-close="outside"
      aria-label="Notifikasi"
    >
      <i class="bi bi-bell"></i>
      <span v-if="notifications.unreadCount" class="badge text-bg-danger rounded-pill ds-badge-dot">
        {{ notifications.unreadCount > 99 ? '99+' : notifications.unreadCount }}
      </span>
    </button>

    <div class="dropdown-menu dropdown-menu-end ds-menu">
      <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
        <strong class="small">Notifikasi</strong>
        <button
          type="button"
          class="btn ds-link-btn btn-sm p-0 border-0"
          :disabled="!notifications.unreadCount"
          @click="onMarkAllRead"
        >
          Tandai dibaca semua
        </button>
      </div>

      <div class="ds-list">
        <p
          v-if="notifications.loading && !notifications.items.length"
          class="text-center ds-muted-text py-3 mb-0"
        >
          <span
            class="spinner-border spinner-border-sm me-2"
            role="status"
            aria-hidden="true"
          ></span>
          Memuat...
        </p>
        <div v-else-if="!notifications.items.length" class="text-center ds-muted-text py-4">
          <i class="bi bi-bell-slash d-block mb-2"></i>
          Belum ada notifikasi.
        </div>
        <template v-else>
          <RouterLink
            v-for="item in notifications.items"
            :key="item.id"
            :to="notificationTarget(item) ?? { name: 'notifications' }"
            type="button"
            class="dropdown-item ds-item d-flex gap-2"
            :class="{ 'ds-item-unread': !item.read_at }"
            @click="onOpenTarget(item)"
          >
            <i :class="`bi ${iconFor(item.type)} ds-item-icon`"></i>
            <span class="min-vw-0">
              <span class="d-block ds-item-title text-truncate">{{
                item.data?.title ?? item.type
              }}</span>
              <span class="d-block ds-item-body text-wrap">{{ item.data?.message ?? '' }}</span>
              <small class="ds-muted-text">{{ formatDateTime(item.created_at) }}</small>
            </span>
          </RouterLink>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ds-menu {
  width: 340px;
  max-height: 480px;
  padding: 0;
}

.ds-list {
  max-height: 400px;
  overflow-y: auto;
}

.ds-item {
  display: flex;
  gap: 0.6rem;
  padding: 0.7rem 1rem;
  border-bottom: 1px solid var(--ds-border);
}

.ds-item:last-child {
  border-bottom: 0;
}

.ds-item-unread {
  background: rgba(var(--ds-primary-rgb), 0.07);
}

.ds-item:hover {
  color: var(--ds-text);
  background: rgba(var(--ds-primary-rgb), 0.12);
}

.ds-item-icon {
  color: var(--ds-primary);
  flex-shrink: 0;
}

.ds-item-title {
  font-size: 0.82rem;
  font-weight: 700;
}

.ds-item-body {
  font-size: 0.8rem;
  color: var(--ds-text);
}

.ds-link-btn {
  color: var(--ds-primary);
  cursor: pointer;
}

.ds-link-btn:disabled {
  color: var(--ds-muted);
}

.ds-muted-text {
  color: var(--ds-muted);
}

.ds-badge-dot {
  position: absolute;
  top: -4px;
  right: -6px;
  font-size: 0.6rem;
}

/* dropdown-menu warna mengikuti tema Bootstrap (data-bs-theme) */
</style>
