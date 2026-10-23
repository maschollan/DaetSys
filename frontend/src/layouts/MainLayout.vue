<script>
import useAuthStore from '@/stores/auth'
import useNotificationStore from '@/stores/notification'
import { currentTheme, toggleTheme as toggleThemePreference } from '@/composables/useTheme'

import NotificationDropdown from '@/components/NotificationDropdown.vue'
import ToastHost from '@/components/ToastHost.vue'

export default {
  name: 'MainLayout',

  components: {
    NotificationDropdown,
    ToastHost,
  },

  data() {
    return {
      theme: currentTheme(),
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),

    notifications: () => useNotificationStore(),

    breadcrumb() {
      return this.$route.meta?.breadcrumb ?? ''
    },

    /**
     * Navigasi lengkap per PLAN.md §12. Item khusus role disembunyikan
     * sesuai sesi; otorisasi sesungguhnya tetap di backend.
     */
    navItems() {
      const items = [
        { label: 'Dashboard', icon: 'bi-speedometer2', to: { name: 'dashboard' } },
        { label: 'Aset', icon: 'bi-box-seam', to: { name: 'assets' } },
        { label: 'Transfer', icon: 'bi-arrow-left-right', to: { name: 'transfers' } },
        { label: 'Lacak QR', icon: 'bi-qr-code', to: { name: 'track' } },
        { label: 'Notifikasi', icon: 'bi-bell', to: { name: 'notifications' } },
        { label: 'Laporan', icon: 'bi-file-earmark-bar-graph', to: { name: 'reports' } },
        { label: 'Lokasi', icon: 'bi-geo-alt', to: { name: 'locations' } },
        { label: 'Kategori', icon: 'bi-tags', to: { name: 'categories' }, adminOnly: true },
        { label: 'Pengguna', icon: 'bi-people', to: { name: 'users' }, adminOnly: true },
        { label: 'Profil', icon: 'bi-person-gear', to: { name: 'profile' } },
        {
          label: 'Jejak Audit',
          icon: 'bi-journal-text',
          to: { name: 'audit-logs' },
          roles: ['admin', 'auditor'],
        },
      ]

      return items.filter((item) => {
        if (item.adminOnly) return this.auth.isAdmin
        if (item.roles) return item.roles.includes(this.auth.role)
        return true
      })
    },

    roleBadgeClass() {
      switch (this.auth.role) {
        case 'admin':
          return 'text-bg-warning'
        case 'officer':
          return 'text-bg-success'
        default:
          return 'text-bg-secondary'
      }
    },
  },

  mounted() {
    this.notifications.startPolling()
  },

  beforeUnmount() {
    this.notifications.stopPolling()
  },

  methods: {
    async handleLogout() {
      this.notifications.stopPolling()
      await this.auth.logout()
      await this.$router.replace({ name: 'login' })
    },

    toggleTheme() {
      this.theme = toggleThemePreference()
    },

    isActive(item) {
      const route = this.$route
      return (
        route.name === item.to.name ||
        route.path.startsWith(String(item.to.path ?? `/${item.to.name}`))
      )
    },
  },
}
</script>

<template>
  <!-- Shell viewport penuh: sidebar dan area konten punya tinggi sama
       (100vh) dengan scroll independen — memanjangnya konten tidak ikut
       menggulirkan sidebar. -->
  <div class="d-flex vh-100 overflow-hidden">
    <!-- Sidebar desktop -->
    <aside class="ds-sidebar d-none d-lg-flex flex-column flex-shrink-0 overflow-y-auto">
      <div class="ds-brand px-4 py-3">
        <span class="ds-brand-mark">&gt;_</span>
        <span class="ds-brand-name">DAETSYS</span>
        <span class="ds-brand-sub d-block">Digital Asset &amp; Evidence Tracking</span>
      </div>

      <nav class="nav flex-column gap-1 px-3 py-2">
        <RouterLink
          v-for="item in navItems"
          :key="item.label"
          class="ds-nav-link d-flex align-items-center gap-2"
          :class="{ active: isActive(item) }"
          :to="item.to"
        >
          <i :class="`bi ${item.icon}`"></i>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <div class="mt-auto px-4 py-3 ds-sidebar-footer">
        <div class="d-flex align-items-center gap-2">
          <div class="ds-avatar d-inline-flex align-items-center justify-content-center">
            <i class="bi bi-person-fill"></i>
          </div>
          <div class="overflow-hidden">
            <div class="text-truncate">{{ auth.userName }}</div>
            <small class="ds-muted-text text-truncate">{{ auth.userEmail }}</small>
          </div>
        </div>
      </div>
    </aside>

    <!-- Area konten: tinggi viewport sama dengan sidebar, header tetap
         terlihat, dan hanya <main> yang menggulir panjangnya sendiri. -->
    <div class="flex-grow-1 d-flex flex-column min-vw-0">
      <header class="ds-topbar flex-shrink-0">
        <nav class="navbar navbar-dark px-3 gap-2">
          <!-- Toggler khusus mobile: membuka offcanvas berisi nav yang sama -->
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm d-lg-none"
            data-bs-toggle="offcanvas"
            data-bs-target="#dsNavOffcanvas"
            aria-label="Buka menu navigasi"
          >
            <i class="bi bi-list"></i>
          </button>

          <span class="navbar-brand d-lg-none m-0 ds-brand-name">DAETSYS</span>

          <div class="d-none d-lg-flex align-items-center gap-2 flex-grow-1 min-vw-0">
            <i class="bi bi-slash-square ds-muted-icon"></i>
            <span class="ds-breadcrumb-item text-truncate">{{ breadcrumb || 'Dasbor' }}</span>
          </div>

          <div class="d-flex align-items-center gap-2 ms-lg-auto">
            <NotificationDropdown />

            <button
              type="button"
              class="btn btn-outline-secondary btn-sm"
              :aria-label="theme === 'dark' ? 'Ubah ke tema terang' : 'Ubah ke tema gelap'"
              @click="toggleTheme"
            >
              <i :class="theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars'"></i>
            </button>

            <div class="dropdown">
              <button
                type="button"
                class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2"
                data-bs-toggle="dropdown"
                data-bs-auto-close="true"
                aria-expanded="false"
              >
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-md-inline text-truncate" style="max-width: 12rem">
                  {{ auth.userName }}
                </span>
                <i class="bi bi-chevron-down small"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <span class="dropdown-item-text">
                    <div class="fw-bold">{{ auth.userName }}</div>
                    <small class="ds-muted-text">{{ auth.userEmail }}</small>
                    <div class="mt-1">
                      <span class="badge" :class="roleBadgeClass">{{ auth.role }}</span>
                    </div>
                  </span>
                </li>
                <li><hr class="dropdown-divider" /></li>
                <li>
                  <button type="button" class="dropdown-item" @click="handleLogout">
                    <i class="bi bi-box-arrow-right me-2"></i> Keluar
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </nav>
      </header>

      <main class="flex-grow-1 p-3 p-lg-4 overflow-y-auto">
        <RouterView />
      </main>
    </div>
  </div>

  <!-- Offcanvas navigasi mobile -->
  <div id="dsNavOffcanvas" class="offcanvas offcanvas-start" tabindex="-1" style="max-width: 280px">
    <div class="offcanvas-header">
      <span class="offcanvas-title ds-brand-name">DAETSYS</span>
      <button
        type="button"
        class="btn-close"
        data-bs-dismiss="offcanvas"
        aria-label="Tutup"
      ></button>
    </div>
    <div class="offcanvas-body">
      <nav class="nav flex-column gap-1">
        <a
          v-for="item in navItems"
          :key="item.label"
          class="nav-link d-flex align-items-center gap-2"
          :class="{ active: isActive(item) }"
          href="#"
          data-bs-dismiss="offcanvas"
          @click.prevent="$router.push(item.to)"
        >
          <i :class="`bi ${item.icon}`"></i>
          <span>{{ item.label }}</span>
        </a>
      </nav>

      <div class="mt-4 border-top pt-3">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="ds-avatar d-inline-flex align-items-center justify-content-center">
            <i class="bi bi-person-fill"></i>
          </div>
          <div class="overflow-hidden">
            <div class="text-truncate">{{ auth.userName }}</div>
            <small class="ds-muted-text text-truncate">{{ auth.userEmail }}</small>
          </div>
        </div>
        <button type="button" class="btn btn-outline-secondary w-100" @click="handleLogout">
          <i class="bi bi-box-arrow-right me-2"></i> Keluar
        </button>
      </div>
    </div>
  </div>

  <!-- Toast global -->
  <ToastHost />
</template>

<style scoped>
.ds-sidebar {
  width: 264px;
  height: 100vh;
  background: var(--ds-surface);
  border-right: 1px solid var(--ds-border);
}

.ds-brand-mark {
  color: var(--ds-primary);
  font-weight: 700;
  letter-spacing: 0.05em;
}

.ds-brand-name {
  font-weight: 700;
  letter-spacing: 0.18em;
  color: var(--ds-text);
}

.ds-brand-sub {
  font-size: 0.68rem;
  color: var(--ds-muted);
  letter-spacing: 0.08em;
}

.ds-nav-link {
  color: var(--ds-text);
  border-left: 3px solid transparent;
  padding: 0.55rem 0.75rem;
  text-decoration: none;
}

.ds-nav-link:hover {
  background: rgba(var(--ds-primary-rgb), 0.08);
  border-left-color: var(--ds-primary);
  color: var(--ds-text);
}

.ds-nav-link.active {
  background: rgba(var(--ds-primary-rgb), 0.12);
  border-left-color: var(--ds-primary);
  color: var(--ds-primary);
  font-weight: 700;
}

.ds-nav-link.disabled {
  color: var(--ds-muted);
  cursor: not-allowed;
}

.ds-topbar {
  background: var(--ds-surface);
  border-bottom: 1px solid var(--ds-border);
}

.ds-muted-text {
  color: var(--ds-muted);
}

.ds-muted-icon {
  color: var(--ds-primary);
}

.ds-avatar {
  width: 34px;
  height: 34px;
  border: 1px solid var(--ds-border);
  color: var(--ds-muted);
  flex-shrink: 0;
}

.ds-breadcrumb-item {
  color: var(--ds-muted);
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  font-size: 0.8rem;
}
</style>
