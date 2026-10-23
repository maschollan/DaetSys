<script>
import api from '@/services/api'
import useUiStore from '@/stores/ui'
import { ASSET_STATUSES, TRANSFER_STATUSES, formatDate, formatDateTime } from '@/constants/status'

import AuditChangeList from '@/components/AuditChangeList.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const TABS = [
  { key: 'assets', label: 'Aset' },
  { key: 'transfers', label: 'Transfer' },
  { key: 'activity-logs', label: 'Jejak Audit' },
]
const DEFAULT_PER_PAGE = 25
const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100]

const ASSET_COLUMNS = [
  { key: 'asset_code', label: 'Kode' },
  { key: 'name', label: 'Nama Aset' },
  { key: 'category', label: 'Kategori' },
  { key: 'location', label: 'Lokasi' },
  { key: 'status', label: 'Status' },
  { key: 'acquisition_date', label: 'Perolehan' },
]

const TRANSFER_COLUMNS = [
  { key: 'asset', label: 'Aset' },
  { key: 'from_location', label: 'Dari' },
  { key: 'to_location', label: 'Ke' },
  { key: 'requested_by', label: 'Pengaju' },
  { key: 'status', label: 'Status' },
  { key: 'requested_at', label: 'Diajukan' },
]

const LOG_COLUMNS = [
  { key: 'created_at', label: 'Waktu' },
  { key: 'action', label: 'Tindakan' },
  { key: 'user', label: 'Pengguna' },
  { key: 'asset', label: 'Aset' },
  { key: 'transfer_id', label: 'Transfer #' },
  { key: 'changes', label: 'Perubahan' },
]

export default {
  name: 'ReportsView',

  components: {
    AuditChangeList,
    DataTable,
    PaginationBar,
    SearchableSelect,
    StatusBadge,
  },

  data() {
    return {
      activeTab: 'assets',
      loading: false,
      error: '',
      rows: [],
      meta: { current_page: 1, last_page: 1, total: 0, per_page: 25 },

      // Filter tersedia per jenis laporan (disinkron dari whitelist ReportRequest).
      dateFrom: '',
      dateTo: '',
      search: '',
      status: '',
      categoryId: '',
      locationId: '',

      locations: [],
      categories: [],
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    ui: () => useUiStore(),

    tabs() {
      return TABS
    },

    assetStatusOptions() {
      return ASSET_STATUSES
    },

    transferStatusOptions() {
      return TRANSFER_STATUSES
    },

    columns() {
      if (this.activeTab === 'assets') return ASSET_COLUMNS
      if (this.activeTab === 'transfers') return TRANSFER_COLUMNS
      return LOG_COLUMNS
    },
  },

  mounted() {
    this.hydrateFromQuery()
    this.load()
    this.loadMasterOptions()
  },

  beforeUnmount() {
    window.clearTimeout(this.searchTimer)
  },

  methods: {
    formatDate,
    formatDateTime,

    /** Muat state dari $route.query agar deep-link (mis. /reports?tab=transfers&status=pending) bekerja. */
    hydrateFromQuery() {
      const query = this.$route.query

      if (TABS.some((tab) => tab.key === query.tab)) this.activeTab = query.tab
      if (typeof query.search === 'string') this.search = query.search
      if (typeof query.status === 'string') this.status = query.status
      if (typeof query.category_id === 'string') this.categoryId = query.category_id
      if (typeof query.location_id === 'string') this.locationId = query.location_id
      if (typeof query.date_from === 'string') this.dateFrom = query.date_from
      if (typeof query.date_to === 'string') this.dateTo = query.date_to

      const perPage = Number(query.per_page)
      if (PER_PAGE_OPTIONS.includes(perPage)) {
        this.meta.per_page = perPage
      }
    },

    /** Tuliskan state filter/tab kembali ke URL. */
    syncQuery() {
      const query = {}
      if (this.activeTab !== 'assets') query.tab = this.activeTab
      if (this.search.trim()) query.search = this.search.trim()
      if (this.status) {
        query.status = String(this.status)
      }
      if (this.activeTab === 'assets' && this.categoryId) {
        query.category_id = String(this.categoryId)
      }
      if (this.activeTab === 'assets' && this.locationId) {
        query.location_id = String(this.locationId)
      }
      if (this.dateFrom) query.date_from = this.dateFrom
      if (this.dateTo) query.date_to = this.dateTo
      if (this.meta.per_page !== DEFAULT_PER_PAGE && this.meta.per_page) {
        query.per_page = String(this.meta.per_page)
      }
      if (this.meta.current_page > 1) query.page = String(this.meta.current_page)

      void this.$router.replace({ query }).catch(() => {})
    },

    buildParams(page) {
      const params = {
        page,
        per_page: this.meta.per_page ?? 25,
        date_from: this.dateFrom || undefined,
        date_to: this.dateTo || undefined,
      }

      if (this.search.trim()) params.search = this.search.trim()
      if (this.status) params.status = this.status

      if (this.activeTab === 'assets') {
        if (this.categoryId) params.category_id = this.categoryId
        if (this.locationId) params.location_id = this.locationId
      }

      return params
    },

    async load(page = 1) {
      this.loading = true
      this.error = ''

      try {
        let response

        if (this.activeTab === 'assets') {
          response = await api.reports.assets(this.buildParams(page))
        } else if (this.activeTab === 'transfers') {
          response = await api.reports.transfers(this.buildParams(page))
        } else {
          response = await api.reports.activityLogs(this.buildParams(page))
        }

        this.rows = response.data.data ?? []
        this.meta = { ...this.meta, ...(response.data.meta ?? {}) }
        this.syncQuery()
      } catch (exception) {
        this.error = this.ui.apiError(exception, 'Gagal memuat laporan.')
        this.rows = []
      } finally {
        this.loading = false
      }
    },

    switchTab(tab) {
      if (this.activeTab === tab) return
      this.activeTab = tab
      this.rows = []
      this.meta = { current_page: 1, last_page: 1, total: 0, per_page: 25 }
      this.load(1)
    },

    onSearchInput() {
      window.clearTimeout(this.searchTimer)
      this.searchTimer = window.setTimeout(() => this.load(1), 350)
    },

    async downloadCsv() {
      try {
        const response = await api.reports.csv(
          this.activeTab === 'activity-logs' ? 'activity-logs' : this.activeTab,
          { ...this.buildParams(1), page: undefined },
        )

        const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8' })
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = `${this.activeTab}-report.csv`
        link.click()
        URL.revokeObjectURL(url)

        this.ui.success('CSV Laporan diunduh.')
      } catch (exception) {
        this.ui.error(this.ui.apiError(exception, 'Gagal mengunduh CSV.'))
      }
    },

    async loadMasterOptions() {
      try {
        const [locationRes, categoryRes] = await Promise.all([
          api.locations.all(),
          api.categories.all(),
        ])
        this.locations = locationRes.data?.data ?? []
        this.categories = categoryRes.data?.data ?? []
      } catch {
        // Filter master opsional.
      }
    },
  },
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
      <div>
        <h1 class="h5 mb-1">Laporan</h1>
        <p class="ds-muted-text mb-0">Laporan operasional dengan filter periode dan ekspor CSV.</p>
      </div>
      <button
        type="button"
        class="btn btn-fill-primary btn-sm"
        :disabled="loading || !rows.length"
        @click="downloadCsv"
      >
        <i class="bi bi-download me-1"></i> Unduh CSV
      </button>
    </div>

    <!-- Tab -->
    <ul class="nav nav-tabs ds-tabs mb-3">
      <li v-for="tab in tabs" :key="tab.key" class="nav-item">
        <button
          type="button"
          class="nav-link"
          :class="{ active: activeTab === tab.key }"
          @click="switchTab(tab.key)"
        >
          {{ tab.label }}
        </button>
      </li>
    </ul>

    <div class="ds-filter-card px-3 py-3 mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="form-label small mb-1" for="report-search">Cari</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input
              id="report-search"
              v-model="search"
              type="text"
              class="form-control"
              placeholder="Kata kunci..."
              @input="onSearchInput"
            />
          </div>
        </div>

        <div v-if="activeTab !== 'activity-logs'" class="col-6 col-lg-2">
          <label class="form-label small mb-1" for="report-status">Status</label>
          <SearchableSelect
            id="report-status"
            v-model="status"
            sm
            placeholder="Status..."
            :options="
              (activeTab === 'assets' ? assetStatusOptions : transferStatusOptions).map((item) => ({
                value: item.value,
                label: item.label,
              }))
            "
            @change="load(1)"
          />
        </div>

        <template v-if="activeTab === 'assets'">
          <div class="col-6 col-lg-2">
            <label class="form-label small mb-1" for="report-category">Kategori</label>
            <SearchableSelect
              id="report-category"
              v-model="categoryId"
              sm
              placeholder="Kategori..."
              :options="
                categories.map((category) => ({ value: category.id, label: category.name }))
              "
              @change="load(1)"
            />
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label small mb-1" for="report-location">Lokasi</label>
            <SearchableSelect
              id="report-location"
              v-model="locationId"
              sm
              placeholder="Lokasi..."
              :options="
                locations.map((location) => ({
                  value: location.id,
                  label: location.name,
                  hint: location.code,
                }))
              "
              @change="load(1)"
            />
          </div>
        </template>

        <div class="col-3 col-lg-2">
          <label class="form-label small mb-1" for="report-date-from">Dari</label>
          <input
            id="report-date-from"
            v-model="dateFrom"
            type="date"
            class="form-control form-control-sm"
            @change="load(1)"
          />
        </div>

        <div class="col-3 col-lg-2">
          <label class="form-label small mb-1" for="report-date-to">Sampai</label>
          <input
            id="report-date-to"
            v-model="dateTo"
            type="date"
            class="form-control form-control-sm"
            @change="load(1)"
          />
        </div>

        <div class="col-12 d-flex justify-content-between align-items-center gap-2">
          <span class="small ds-muted-text">Total data: {{ meta.total }}</span>
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="load(1)">
            <i class="bi bi-arrow-clockwise me-1"></i> Terapkan
          </button>
        </div>
      </div>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :error="error"
      :key="activeTab"
      empty-message="Tidak ada data laporan untuk filter ini."
      @retry="load()"
    >
      <!-- Aset -->
      <template v-if="activeTab === 'assets'" #cell-asset_code="{ row }">
        <RouterLink
          :to="{ name: 'assets.detail', params: { qrToken: row.qr_token } }"
          class="ds-link"
        >
          {{ row.asset_code }}
        </RouterLink>
      </template>

      <template v-if="activeTab === 'assets'" #cell-category="{ row }">
        {{ row.category?.name ?? '—' }}
      </template>

      <template v-if="activeTab === 'assets'" #cell-location="{ row }">
        {{ row.location ? `${row.location.code} — ${row.location.name}` : 'Belum ditempatkan' }}
      </template>

      <template v-if="activeTab === 'assets'" #cell-acquisition_date="{ row }">
        {{ formatDate(row.acquisition_date) }}
      </template>

      <template v-if="activeTab === 'assets'" #cell-status="{ row }">
        <StatusBadge variant="asset" :value="row.status" />
      </template>

      <!-- Transfer -->
      <template v-if="activeTab === 'transfers'" #cell-asset="{ row }">
        <RouterLink
          :to="{ name: 'transfers.detail', params: { uniqId: row.uniqid } }"
          class="ds-link"
        >
          {{ row.asset?.asset_code ?? '—' }}
        </RouterLink>
        <div class="small ds-muted-text">{{ row.asset?.name }}</div>
      </template>

      <template v-if="activeTab === 'transfers'" #cell-from_location="{ row }">
        {{ row.from_location?.name ?? '—' }}
      </template>

      <template v-if="activeTab === 'transfers'" #cell-to_location="{ row }">
        {{ row.to_location?.name ?? '—' }}
      </template>

      <template v-if="activeTab === 'transfers'" #cell-requested_by="{ row }">
        {{ row.requested_by?.name ?? '—' }}
      </template>

      <template v-if="activeTab === 'transfers'" #cell-status="{ row }">
        <StatusBadge variant="transfer" :value="row.status" />
      </template>

      <template v-if="activeTab === 'transfers'" #cell-requested_at="{ row }">
        {{ formatDateTime(row.requested_at) }}
      </template>

      <!-- Jejak Audit -->
      <template v-if="activeTab === 'activity-logs'" #cell-created_at="{ row }">
        {{ formatDateTime(row.created_at) }}
      </template>

      <template v-if="activeTab === 'activity-logs'" #cell-user="{ row }">
        {{ row.user?.name ?? 'Sistem' }}
      </template>

      <template v-if="activeTab === 'activity-logs'" #cell-asset="{ row }">
        <RouterLink
          v-if="row.asset"
          :to="{ name: 'assets.detail', params: { qrToken: row.asset.qr_token } }"
          class="ds-link"
        >
          {{ row.asset.asset_code }}
        </RouterLink>
        <span v-else>—</span>
      </template>

      <template v-if="activeTab === 'activity-logs'" #cell-transfer_id="{ row }">
        <RouterLink
          v-if="row.transfer_id"
          class="ds-link"
          :to="{ name: 'transfers.detail', params: { uniqId: row.transfer_id } }"
        >
          #{{ row.transfer_id }}
        </RouterLink>
        <span v-else>—</span>
      </template>

      <template v-if="activeTab === 'activity-logs'" #cell-changes="{ row }">
        <AuditChangeList :old-values="row.old_values" :new-values="row.new_values" />
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      :disabled="loading"
      @page-change="load"
      @per-page-change="
        (perPage) => {
          meta.per_page = perPage
          load(1)
        }
      "
    />
  </div>
</template>

<style scoped>
.ds-filter-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
}

.ds-tabs .nav-link {
  color: var(--ds-muted);
  border: 1px solid transparent;
  border-bottom-color: var(--ds-border);
  font-weight: 700;
}

.ds-tabs .nav-link.active {
  color: var(--ds-primary);
  background: var(--ds-surface);
  border-color: var(--ds-border) var(--ds-border) var(--ds-surface);
}

.ds-link {
  color: var(--ds-primary);
  font-weight: 700;
  text-decoration: none;
}

.ds-link:hover {
  text-decoration: underline;
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
