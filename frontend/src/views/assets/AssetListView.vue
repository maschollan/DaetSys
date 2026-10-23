<script>
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import useUiStore from '@/stores/ui'
import { ASSET_STATUSES, formatDate } from '@/constants/status'

import AssetFormModal from './components/AssetFormModal.vue'
import ConfirmModal from '@/components/ModalConfirm.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import DataTable from '@/components/DataTable.vue'
import listQuery from '@/mixins/listQuery'

export default {
  name: 'AssetListView',

  components: {
    AssetFormModal,
    ConfirmModal,
    PageHeader,
    PaginationBar,
    SearchableSelect,
    StatusBadge,
    DataTable,
  },

  mixins: [listQuery],

  listConfig: {
    fetch: api.assets.list,
    filters: { category_id: '', location_id: '', status: '' },
    sort: { key: 'created_at', dir: 'desc' },
  },

  data() {
    return {
      columns: [
        { key: 'asset_code', label: 'Kode', sortable: true },
        { key: 'name', label: 'Nama Aset', sortable: true },
        { key: 'category', label: 'Kategori' },
        { key: 'location', label: 'Lokasi' },
        { key: 'status', label: 'Status', sortable: true },
        { key: 'acquisition_date', label: 'Perolehan', sortable: true },
      ],
      categories: [],
      locations: [],
      optionsLoading: false,
      showForm: false,
      editingAsset: null,
      deleteTarget: null,
      deleting: false,
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
    ui: () => useUiStore(),

    assetStatusOptions() {
      return ASSET_STATUSES
    },
  },

  mounted() {
    void Promise.all([this.refresh(), this.loadOptions()])
  },

  beforeUnmount() {
    window.clearTimeout(this.searchTimer)
  },

  methods: {
    formatDate,

    onSearchInput() {
      window.clearTimeout(this.searchTimer)
      this.searchTimer = window.setTimeout(() => this.applySearch(), 350)
    },

    openCreate() {
      this.editingAsset = null
      this.showForm = true
    },

    openEdit(asset) {
      this.editingAsset = asset
      this.showForm = true
    },

    confirmDelete(asset) {
      this.deleteTarget = asset
      this.$refs.deleteModal?.show()
    },

    async performDelete() {
      this.deleting = true

      try {
        await api.assets.remove(this.deleteTarget.id)
        this.ui.success('Aset dihapus.')
        this.$refs.deleteModal?.hide()
        await this.refresh()
      } catch (exception) {
        this.ui.error(this.ui.apiError(exception, 'Gagal menghapus aset.'))
      } finally {
        this.deleting = false
      }
    },

    async loadOptions() {
      this.optionsLoading = true

      try {
        const [categoryRes, locationRes] = await Promise.all([
          api.categories.all(),
          api.locations.all(),
        ])
        this.categories = categoryRes.data?.data ?? []
        this.locations = (locationRes.data?.data ?? []).filter((location) => location.is_active)
      } catch (exception) {
        this.ui.error(this.ui.apiError(exception, 'Gagal memuat filter kategori/lokasi.'))
      } finally {
        this.optionsLoading = false
      }
    },
  },
}
</script>

<template>
  <div>
    <PageHeader title="Aset" description="Daftar seluruh barang dan kondisinya saat ini.">
      <template #actions>
        <button
          v-if="auth.hasRole('admin', 'officer')"
          type="button"
          class="btn btn-fill-primary btn-sm"
          @click="openCreate"
        >
          <i class="bi bi-plus-lg me-1"></i> Tambah Aset
        </button>
      </template>
    </PageHeader>

    <div class="ds-filter-card px-3 py-3 mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="form-label small mb-1" for="asset-search">Cari</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input
              id="asset-search"
              v-model="searchQuery"
              type="text"
              class="form-control"
              placeholder="Kode, nama, atau nomor seri..."
              @input="onSearchInput"
            />
          </div>
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label small mb-1" for="filter-category">Kategori</label>
          <SearchableSelect
            id="filter-category"
            v-model="filters.category_id"
            sm
            placeholder="Kategori..."
            :options="categories.map((category) => ({ value: category.id, label: category.name }))"
            @change="applyFilters()"
          />
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label small mb-1" for="filter-location">Lokasi</label>
          <SearchableSelect
            id="filter-location"
            v-model="filters.location_id"
            sm
            placeholder="Lokasi..."
            :options="
              locations.map((location) => ({
                value: location.id,
                label: location.name,
                hint: location.code,
              }))
            "
            @change="applyFilters()"
          />
        </div>

        <div class="col-6 col-lg-2">
          <label class="form-label small mb-1" for="filter-status">Status</label>
          <SearchableSelect
            id="filter-status"
            v-model="filters.status"
            sm
            placeholder="Status..."
            :options="
              assetStatusOptions.map((status) => ({ value: status.value, label: status.label }))
            "
            @change="applyFilters()"
          />
        </div>

        <div class="col-6 col-lg-2 d-flex justify-content-lg-end">
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
      empty-message="Tidak ada aset yang cocok dengan filter."
      @sort="toggleSort"
      @retry="refresh"
    >
      <template #cell-asset_code="{ row }">
        <RouterLink
          :to="{ name: 'assets.detail', params: { qrToken: row.qr_token } }"
          class="ds-link"
        >
          {{ row.asset_code }}
        </RouterLink>
      </template>

      <template #cell-category="{ row }">
        {{ row.category?.name ?? '—' }}
      </template>

      <template #cell-location="{ row }">
        {{ row.location ? `${row.location.code} — ${row.location.name}` : 'Belum ditempatkan' }}
      </template>

      <template #cell-status="{ row }">
        <StatusBadge variant="asset" :value="row.status" />
      </template>

      <template #cell-acquisition_date="{ row }">
        {{ formatDate(row.acquisition_date) }}
      </template>

      <template #actions="{ row }">
        <div class="btn-group btn-group-sm">
          <RouterLink
            class="btn btn-outline-secondary"
            :to="{ name: 'assets.detail', params: { qrToken: row.qr_token } }"
            title="Detail"
          >
            <i class="bi bi-eye"></i>
          </RouterLink>
          <button
            v-if="auth.hasRole('admin', 'officer')"
            type="button"
            class="btn btn-outline-secondary"
            title="Edit"
            @click="openEdit(row)"
          >
            <i class="bi bi-pencil"></i>
          </button>
          <button
            v-if="auth.hasRole('admin')"
            type="button"
            class="btn btn-outline-danger"
            title="Hapus"
            @click="confirmDelete(row)"
          >
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      :disabled="loading"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    />

    <AssetFormModal
      v-model:show="showForm"
      :asset="editingAsset"
      :categories="categories"
      :locations="locations"
      @saved="refresh"
    />

    <ConfirmModal
      ref="deleteModal"
      title="Hapus aset?"
      :busy="deleting"
      confirm-text="Hapus"
      tone="danger"
      icon="bi-trash"
      @confirm="performDelete"
      @closed="deleteTarget = null"
    >
      <template v-if="deleteTarget">
        Aset <strong>{{ deleteTarget.asset_code }}</strong> — {{ deleteTarget.name }} akan dihapus.
        Tindakan ini tercatat pada jejak audit dan tidak bisa dikembalikan.
      </template>
    </ConfirmModal>
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
</style>
