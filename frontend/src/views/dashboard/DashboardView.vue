<script>
import Chart from 'chart.js/auto'

import api from '@/services/api'
import useUiStore from '@/stores/ui'
import { ASSET_STATUS_LABELS, formatDateTime } from '@/constants/status'

import useAuthStore from '@/stores/auth'

const summaryDefaults = {
  total_assets: 0,
  assets_available: 0,
  assets_in_transfer: 0,
  assets_maintenance: 0,
  assets_missing: 0,
  pending_approvals: 0,
  active_transfers: 0,
  total_transfers: 0,
  total_users: 0,
}

const summaryCards = [
  { key: 'total_assets', label: 'Total Aset', icon: 'bi-boxes', to: { name: 'assets' } },
  {
    key: 'assets_available',
    label: 'Tersedia',
    icon: 'bi-check2-square',
    to: { name: 'assets', query: { status: 'available' } },
  },
  {
    key: 'assets_in_transfer',
    label: 'Dalam Transfer',
    icon: 'bi-arrow-left-right',
    to: { name: 'assets', query: { status: 'in_transfer' } },
  },
  {
    key: 'pending_approvals',
    label: 'Menunggu Persetujuan',
    icon: 'bi-hourglass-split',
    to: { name: 'transfers', query: { status: 'pending' } },
  },
  {
    key: 'assets_maintenance',
    label: 'Perawatan',
    icon: 'bi-tools',
    to: { name: 'assets', query: { status: 'maintenance' } },
  },
  {
    key: 'assets_missing',
    label: 'Hilang',
    icon: 'bi-exclamation-triangle',
    to: { name: 'assets', query: { status: 'missing' } },
  },
]

const PALETTE = [
  '#0d9488',
  '#14b8a6',
  '#0f766e',
  '#2dd4bf',
  '#5eead4',
  '#99f6e4',
  '#134e4a',
  '#115e59',
]

export default {
  name: 'DashboardView',

  data() {
    return {
      summary: { ...summaryDefaults },
      recentActivities: [],
      pendingTransfers: [],
      loading: true,

      /** Error per bagian dashboard agar satu endpoint gagal tidak memblanko semua. */
      sectionErrors: {
        summary: '',
        charts: '',
        activities: '',
        pending: '',
      },
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
    ui: () => useUiStore(),

    cards() {
      return summaryCards
    },
  },

  mounted() {
    this.loadAll()
  },

  beforeUnmount() {
    this.locationChart?.destroy()
    this.statusChart?.destroy()
  },

  methods: {
    formatDateTime,

    async loadAll() {
      this.loading = true
      this.sectionErrors = { summary: '', charts: '', activities: '', pending: '' }

      const [summaryTask, locationTask, statusTask, activitiesTask, pendingTask] =
        await Promise.allSettled([
          api.dashboard.summary(),
          api.dashboard.assetsByLocation(),
          api.dashboard.assetsByStatus(),
          api.dashboard.recentActivities(),
          api.dashboard.pendingTransfers(),
        ])

      if (summaryTask.status === 'fulfilled') {
        Object.assign(this.summary, summaryTask.value.data?.data ?? {})
      } else {
        this.sectionErrors.summary = this.ui.apiError(
          summaryTask.reason,
          'Gagal memuat ringkasan angka.',
        )
      }

      const byLocation =
        locationTask.status === 'fulfilled' ? (locationTask.value.data?.data ?? []) : []
      const byStatus = statusTask.status === 'fulfilled' ? (statusTask.value.data?.data ?? []) : []

      if (locationTask.status === 'rejected' && statusTask.status === 'rejected') {
        this.sectionErrors.charts = 'Gagal memuat grafik.'
      } else if (locationTask.status === 'rejected') {
        this.sectionErrors.charts = 'Gagal memuat grafik aset per lokasi.'
      } else if (statusTask.status === 'rejected') {
        this.sectionErrors.charts = 'Gagal memuat grafik aset per status.'
      }

      if (activitiesTask.status === 'fulfilled') {
        // Batasi tampilan 5 aktivitas terbaru agar panel tetap ringkas.
        this.recentActivities = (activitiesTask.value.data?.data ?? []).slice(0, 5)
      } else {
        this.sectionErrors.activities = this.ui.apiError(
          activitiesTask.reason,
          'Gagal memuat aktivitas terbaru.',
        )
      }

      if (pendingTask.status === 'fulfilled') {
        this.pendingTransfers = pendingTask.value.data?.data ?? []
      } else {
        this.sectionErrors.pending = this.ui.apiError(
          pendingTask.reason,
          'Gagal memuat transfer menunggu.',
        )
      }

      const firstError = Object.entries(this.sectionErrors).find(([, message]) => message)
      if (firstError) this.ui.error(firstError[1])

      // Canvas chart baru tersedia setelah loading berubah false dan Vue
      // selesai mem-patch DOM (nextTick) — renderCharts dari seluruh siklus
      // muat dulu sehingga ref tidak lagi null.
      this.loading = false
      await this.$nextTick()

      this.renderCharts(byLocation, byStatus)
    },

    renderCharts(byLocation, byStatus) {
      const style = getComputedStyle(document.documentElement)
      const textColor = style.getPropertyValue('--ds-muted').trim() || '#666'
      const borderColor = style.getPropertyValue('--ds-border').trim() || '#ddd'

      const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: textColor } } },
        scales: {
          x: { ticks: { color: textColor }, grid: { color: borderColor } },
          y: {
            ticks: { precision: 0, color: textColor },
            grid: { color: borderColor },
            beginAtZero: true,
          },
        },
      }

      const primaryColor = style.getPropertyValue('--ds-primary').trim() || '#0d9488'

      if (this.locationChart) this.locationChart.destroy()
      if (this.statusChart) this.statusChart.destroy()

      if (this.$refs.locationChartRef) {
        this.locationChart = new Chart(this.$refs.locationChartRef, {
          type: 'bar',
          data: {
            labels: byLocation.map((row) => row.location_name),
            datasets: [
              {
                label: 'Jumlah aset',
                data: byLocation.map((row) => row.total),
                backgroundColor: primaryColor,
                borderWidth: 0,
              },
            ],
          },
          options: {
            ...commonOptions,
            plugins: { legend: { display: false } },
          },
        })
      }

      if (this.$refs.statusChartRef) {
        this.statusChart = new Chart(this.$refs.statusChartRef, {
          type: 'bar',
          data: {
            labels: byStatus.map((row) => ASSET_STATUS_LABELS[row.status] ?? row.status),
            datasets: [
              {
                label: 'Jumlah aset',
                data: byStatus.map((row) => row.total),
                backgroundColor: PALETTE.slice(0, byStatus.length),
                borderWidth: 0,
              },
            ],
          },
          options: {
            ...commonOptions,
            plugins: { legend: { display: false } },
          },
        })
      }
    },
  },
}
</script>

<template>
  <div>
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
      <div>
        <h1 class="h5 mb-1">Dashboard</h1>
        <p class="ds-muted-text mb-0">Ringkasan aset dan aktivitas terkini — data dari API.</p>
      </div>
      <div class="d-flex gap-2" v-if="auth.hasRole('admin', 'officer')">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'assets' }">
          <i class="bi bi-plus-lg me-1"></i> Tambah Barang
        </RouterLink>
        <button type="button" class="btn btn-fill-primary btn-sm" @click="loadAll">
          <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border ds-loader" role="status" aria-hidden="true"></div>
      <p class="ds-muted-text mt-2">Memuat ringkasan...</p>
    </div>

    <template v-else>
      <!-- Kartu ringkasan -->
      <div v-if="sectionErrors.summary" class="alert alert-warning py-2">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ sectionErrors.summary }}
      </div>
      <div class="row g-3 mb-3">
        <div v-for="card in cards" :key="card.key" class="col-6 col-lg-4 col-xxl-2">
          <RouterLink :to="card.to" class="text-decoration-none">
            <div class="ds-card ds-summary p-3 h-100">
              <i :class="`bi ${card.icon} ds-card-icon`"></i>
              <div class="ds-card-value">{{ summary[card.key] }}</div>
              <div class="ds-card-label">{{ card.label }}</div>
            </div>
          </RouterLink>
        </div>
      </div>

      <!-- Grafik -->
      <div class="row g-3 mb-3">
        <div class="col-lg-6">
          <div class="ds-card p-3 h-100">
            <h3 class="h6 mb-3">Aset per Lokasi</h3>
            <div v-if="sectionErrors.charts" class="ds-muted-text py-3 text-center">
              <i class="bi bi-exclamation-triangle d-block mb-1"></i>
              {{ sectionErrors.charts }}
            </div>
            <div v-else class="ds-chart"><canvas ref="locationChartRef"></canvas></div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="ds-card p-3 h-100">
            <h3 class="h6 mb-3">Aset per Status</h3>
            <div v-if="sectionErrors.charts" class="ds-muted-text py-3 text-center">
              <i class="bi bi-exclamation-triangle d-block mb-1"></i>
              {{ sectionErrors.charts }}
            </div>
            <div v-else class="ds-chart"><canvas ref="statusChartRef"></canvas></div>
          </div>
        </div>
      </div>

      <!-- Aktivitas terbaru & transfer menunggu -->
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="ds-card p-3 h-100">
            <h3 class="h6 mb-3">Aktivitas Terbaru</h3>
            <div v-if="sectionErrors.activities" class="ds-muted-text py-3 text-center">
              <i class="bi bi-exclamation-triangle d-block mb-1"></i>
              {{ sectionErrors.activities }}
            </div>
            <div v-else-if="!recentActivities.length" class="ds-muted-text py-3 text-center">
              Belum ada aktivitas.
            </div>
            <ul v-else class="ds-activity list-unstyled mb-0 ds-scroll">
              <li v-for="activity in recentActivities" :key="activity.id" class="ds-activity-item">
                <div class="d-flex justify-content-between gap-2">
                  <strong class="small">{{ activity.action }}</strong>
                  <small class="ds-muted-text text-nowrap">{{
                    formatDateTime(activity.created_at)
                  }}</small>
                </div>
                <div class="small ds-muted-text">
                  {{ activity.user ?? 'Sistem' }}
                  <span v-if="activity.asset_code"> — {{ activity.asset_code }}</span>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="ds-card p-3 h-100">
            <h3 class="h6 mb-3">Transfer Menunggu Tindakan</h3>
            <div v-if="sectionErrors.pending" class="ds-muted-text py-3 text-center">
              <i class="bi bi-exclamation-triangle d-block mb-1"></i>
              {{ sectionErrors.pending }}
            </div>
            <div v-else-if="!pendingTransfers.length" class="ds-muted-text py-3 text-center">
              Tidak ada transfer aktif.
            </div>
            <ul v-else class="ds-activity list-unstyled mb-0">
              <li v-for="item in pendingTransfers" :key="item.id" class="ds-activity-item">
                <RouterLink
                  :to="{ name: 'transfers.detail', params: { uniqId: item.uniqid } }"
                  class="ds-link small"
                >
                  #{{ item.id }} — {{ item.asset_code }}
                </RouterLink>
                <div class="small ds-muted-text">
                  {{ item.from_location }} → {{ item.to_location }} ·
                  {{ formatDateTime(item.requested_at) }}
                  <span class="badge text-bg-secondary ms-1">{{ item.status }}</span>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.ds-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
  color: var(--ds-text);
}

.ds-summary {
  transition: border-color 0.15s ease;
}

.ds-summary:hover {
  border-color: var(--ds-primary);
}

.ds-card-icon {
  color: var(--ds-primary);
  font-size: 1.1rem;
}

.ds-card-value {
  font-size: 1.6rem;
  font-weight: 700;
  line-height: 1.2;
}

.ds-card-label {
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--ds-muted);
}

.ds-chart {
  position: relative;
  height: 260px;
}

.ds-activity-item {
  padding: 0.55rem 0;
  border-bottom: 1px solid var(--ds-border);
}

/* Kontainer daftar yang panjang tetap ringkas dengan scroll. */
.ds-scroll {
  max-height: 320px;
  overflow-y: auto;
  padding-right: 0.25rem;
}

.ds-activity-item:last-child {
  border-bottom: 0;
}

.ds-link {
  color: var(--ds-primary);
  font-weight: 700;
  text-decoration: none;
}

.ds-loader {
  color: var(--ds-primary);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
