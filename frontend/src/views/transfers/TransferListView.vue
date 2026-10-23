<script>
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import useUiStore from '@/stores/ui'
import { TRANSFER_STATUSES, formatDateTime } from '@/constants/status'

import TransferFormModal from './components/TransferFormModal.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import DataTable from '@/components/DataTable.vue'
import listQuery from '@/mixins/listQuery'

export default {
  name: 'TransferListView',

  components: {
    TransferFormModal,
    PageHeader,
    PaginationBar,
    SearchableSelect,
    StatusBadge,
    DataTable,
  },

  mixins: [listQuery],

  listConfig: {
    fetch: api.transfers.list,
    filters: { status: '', date_from: '', date_to: '' },
    sort: { key: 'requested_at', dir: 'desc' },
  },

  data() {
    return {
      columns: [
        { key: 'asset', label: 'Aset' },
        { key: 'from_location', label: 'Lokasi Asal' },
        { key: 'to_location', label: 'Lokasi Tujuan' },
        { key: 'requested_by', label: 'Pengaju' },
        { key: 'status', label: 'Status', sortable: true },
        { key: 'requested_at', label: 'Diajukan', sortable: true },
      ],
      showForm: false,
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
    ui: () => useUiStore(),

    transferStatusOptions() {
      return TRANSFER_STATUSES
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

    onSaved() {
      this.showForm = false
      this.ui.success('Pengajuan transfer dibuat.')
      this.refresh()
    },
  },
}
</script>

<template>
  <div>
    <PageHeader
      title="Transfer"
      description="Pengajuan perpindahan barang antar lokasi beserta statusnya."
    >
      <template #actions>
        <button
          v-if="auth.hasRole('admin', 'officer')"
          type="button"
          class="btn btn-fill-primary btn-sm"
          @click="showForm = true"
        >
          <i class="bi bi-plus-lg me-1"></i> Ajukan Transfer
        </button>
      </template>
    </PageHeader>

    <div class="ds-filter-card px-3 py-3 mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="form-label small mb-1" for="transfer-search">Cari aset</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input
              id="transfer-search"
              v-model="searchQuery"
              type="text"
              class="form-control"
              placeholder="Kode atau nama aset..."
              @input="onSearchInput"
            />
          </div>
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label small mb-1" for="filter-transfer-status">Status</label>
          <SearchableSelect
            id="filter-transfer-status"
            v-model="filters.status"
            sm
            placeholder="Status..."
            :options="
              transferStatusOptions.map((status) => ({ value: status.value, label: status.label }))
            "
            @change="applyFilters()"
          />
        </div>

        <div class="col-3 col-lg-2">
          <label class="form-label small mb-1" for="filter-date-from">Dari</label>
          <input
            id="filter-date-from"
            v-model="filters.date_from"
            type="date"
            class="form-control form-control-sm"
            @change="applyFilters()"
          />
        </div>

        <div class="col-3 col-lg-2">
          <label class="form-label small mb-1" for="filter-date-to">Sampai</label>
          <input
            id="filter-date-to"
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
      empty-message="Tidak ada pengajuan transfer yang cocok."
      @sort="toggleSort"
      @retry="refresh"
    >
      <template #cell-asset="{ row }">
        <RouterLink
          :to="{ name: 'transfers.detail', params: { uniqId: row.uniqid } }"
          class="ds-link"
        >
          {{ row.asset?.asset_code ?? '—' }}
        </RouterLink>
        <div class="small ds-muted-text">{{ row.asset?.name }}</div>
      </template>

      <template #cell-from_location="{ row }">
        {{ row.from_location?.name ?? '—' }}
      </template>

      <template #cell-to_location="{ row }">
        {{ row.to_location?.name ?? '—' }}
      </template>

      <template #cell-requested_by="{ row }">
        {{ row.requested_by?.name ?? '—' }}
      </template>

      <template #cell-status="{ row }">
        <StatusBadge variant="transfer" :value="row.status" />
      </template>

      <template #cell-requested_at="{ row }">
        {{ formatDateTime(row.requested_at) }}
      </template>

      <template #actions="{ row }">
        <RouterLink
          class="btn btn-outline-secondary btn-sm"
          :to="{ name: 'transfers.detail', params: { uniqId: row.uniqid } }"
        >
          Detail
        </RouterLink>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      :disabled="loading"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    />

    <TransferFormModal v-model:show="showForm" @saved="onSaved" />
  </div>
</template>

<style scoped>
.ds-filter-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
}

.ds-link {
  color: var(--ds-primary);
  font-weight: 700;
  text-decoration: none;
}

.ds-link:hover {
  color: var(--ds-primary-hover);
  text-decoration: underline;
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
