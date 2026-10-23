<script>
import api from '@/services/api'
import { ACTIVITY_ACTIONS, formatDateTime } from '@/constants/status'

import AuditChangeList from '@/components/AuditChangeList.vue'
import DataTable from '@/components/DataTable.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import listQuery from '@/mixins/listQuery'

export default {
  name: 'AuditLogListView',

  components: {
    AuditChangeList,
    DataTable,
    PageHeader,
    PaginationBar,
    SearchableSelect,
  },

  mixins: [listQuery],

  // Halaman read-only: hanya admin & auditor dapat membaca (backend Policy menjaga).
  listConfig: {
    fetch: api.activityLogs.list,
    filters: { action: '', date_from: '', date_to: '' },
    sort: { key: 'created_at', dir: 'desc' },
  },

  data() {
    return {
      columns: [
        { key: 'created_at', label: 'Waktu', sortable: true },
        { key: 'action', label: 'Tindakan', sortable: true },
        { key: 'user', label: 'Pengguna' },
        { key: 'asset', label: 'Aset' },
        { key: 'transfer_id', label: 'Transfer #' },
        { key: 'changes', label: 'Perubahan' },
        { key: 'ip', label: 'IP' },
      ],
    }
  },

  computed: {
    activityActionOptions() {
      return ACTIVITY_ACTIONS
    },
  },

  mounted() {
    this.refresh()
  },

  beforeUnmount() {
    window.clearTimeout(this.searchTimer)
  },

  methods: {
    formatDateTime,

    onSearchInput() {
      window.clearTimeout(this.searchTimer)
      this.searchTimer = window.setTimeout(() => this.applySearch(), 350)
    },
  },
}
</script>

<template>
  <div>
    <PageHeader
      title="Jejak Audit"
      description="Append-only: seluruh aktivitas penting terekam dan tidak bisa diubah."
    />

    <div class="ds-filter-card px-3 py-3 mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-3">
          <label class="form-label small mb-1" for="audit-search">Cari</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input
              id="audit-search"
              v-model="searchQuery"
              type="text"
              class="form-control"
              placeholder="Kode aset atau action..."
              @input="onSearchInput"
            />
          </div>
        </div>

        <div class="col-6 col-lg-3">
          <label class="form-label small mb-1" for="filter-action">Tindakan</label>
          <SearchableSelect
            id="filter-action"
            v-model="filters.action"
            sm
            placeholder="Tindakan..."
            search-placeholder="Cari tindakan..."
            :options="activityActionOptions.map((action) => ({ value: action, label: action }))"
            @change="applyFilters()"
          />
        </div>

        <div class="col-3 col-lg-2">
          <label class="form-label small mb-1" for="audit-date-from">Dari</label>
          <input
            id="audit-date-from"
            v-model="filters.date_from"
            type="date"
            class="form-control form-control-sm"
            @change="applyFilters()"
          />
        </div>

        <div class="col-3 col-lg-2">
          <label class="form-label small mb-1" for="audit-date-to">Sampai</label>
          <input
            id="audit-date-to"
            v-model="filters.date_to"
            type="date"
            class="form-control form-control-sm"
            @change="applyFilters()"
          />
        </div>

        <div class="col-12 col-lg-2 d-flex justify-content-lg-end">
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="refresh()">
            <i class="bi bi-arrow-clockwise me-1"></i> Muat ulang
          </button>
        </div>
      </div>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :error="error"
      :sort-key="sortKey ?? ''"
      :sort-dir="sortDir"
      empty-message="Tidak ada log yang cocok dengan filter."
      @sort="toggleSort"
      @retry="refresh"
    >
      <template #cell-created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>

      <template #cell-action="{ row }">
        <code class="ds-action">{{ row.action }}</code>
      </template>

      <template #cell-user="{ row }">{{ row.user?.name ?? 'Sistem' }}</template>

      <template #cell-asset="{ row }">
        <RouterLink
          v-if="row.asset"
          class="ds-link"
          :to="{ name: 'assets.detail', params: { qrToken: row.asset.qr_token } }"
        >
          {{ row.asset.asset_code }}
        </RouterLink>
        <span v-else>—</span>
      </template>

      <template #cell-transfer_id="{ row }">
        <RouterLink
          v-if="row.transfer_id"
          class="ds-link"
          :to="{ name: 'transfers.detail', params: { uniqId: row.transfer_id } }"
        >
          #{{ row.transfer_id }}
        </RouterLink>
        <span v-else>—</span>
      </template>

      <template #cell-changes="{ row }">
        <AuditChangeList :old-values="row.old_values" :new-values="row.new_values" />
      </template>

      <template #cell-ip="{ row }">
        <span class="small ds-muted-text">{{ row.ip_address ?? '—' }}</span>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      :disabled="loading"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    />
  </div>
</template>

<style scoped>
.ds-filter-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
}

.ds-action {
  font-size: 0.75rem;
  color: var(--ds-primary);
}

.ds-link {
  color: var(--ds-primary);
  font-weight: 700;
  text-decoration: none;
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
