<script>
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import useUiStore from '@/stores/ui'

import ConfirmModal from '@/components/ModalConfirm.vue'
import DataTable from '@/components/DataTable.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import LocationFormModal from '@/views/master-data/components/LocationFormModal.vue'
import listQuery from '@/mixins/listQuery'

export default {
  name: 'LocationListView',

  components: {
    ConfirmModal,
    DataTable,
    PageHeader,
    PaginationBar,
    LocationFormModal,
  },

  mixins: [listQuery],

  listConfig: {
    fetch: api.locations.list,
    filters: {},
    sort: { key: 'name', dir: 'asc' },
  },

  data() {
    return {
      columns: [
        { key: 'code', label: 'Kode', sortable: true },
        { key: 'name', label: 'Nama Lokasi', sortable: true },
        { key: 'address', label: 'Alamat' },
        { key: 'assets_count', label: 'Jumlah Aset', sortable: true },
        { key: 'is_active', label: 'Status' },
      ],
      showForm: false,
      editing: null,
      deleteTarget: null,
      deleting: false,
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
    ui: () => useUiStore(),
  },

  mounted() {
    this.refresh()
  },

  methods: {
    openCreate() {
      this.editing = null
      this.showForm = true
    },

    openEdit(location) {
      this.editing = location
      this.showForm = true
    },

    confirmDelete(location) {
      this.deleteTarget = location
      this.$refs.deleteModal?.show()
    },

    async performDelete() {
      this.deleting = true

      try {
        await api.locations.remove(this.deleteTarget.id)
        this.ui.success('Lokasi dihapus.')
        this.$refs.deleteModal?.hide()
        await this.refresh()
      } catch (exception) {
        this.ui.error(this.ui.apiError(exception, 'Gagal menghapus lokasi.'))
      } finally {
        this.deleting = false
      }
    },
  },
}
</script>

<template>
  <div>
    <PageHeader title="Lokasi" description="Ruang penyimpanan aset beserta jumlah barangnya.">
      <template #actions>
        <button
          v-if="auth.hasRole('admin')"
          type="button"
          class="btn btn-fill-primary btn-sm"
          @click="openCreate"
        >
          <i class="bi bi-plus-lg me-1"></i> Tambah Lokasi
        </button>
      </template>
    </PageHeader>

    <DataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :error="error"
      :sort-key="sortKey ?? ''"
      :sort-dir="sortDir"
      empty-message="Belum ada lokasi terdaftar."
      @sort="toggleSort"
      @retry="refresh"
    >
      <template #cell-name="{ row }">
        {{ row.name }}
        <div v-if="row.address" class="small ds-muted-text">{{ row.address }}</div>
      </template>

      <template #cell-assets_count="{ row }">
        <RouterLink
          class="ds-link"
          :to="{ name: 'assets', query: { location_id: row.id } }"
          title="Lihat aset di lokasi ini"
        >
          {{ row.assets_count ?? 0 }}
        </RouterLink>
      </template>

      <template #cell-is_active="{ row }">
        <span class="badge" :class="row.is_active ? 'ds-on' : 'ds-off'">
          {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
        </span>
      </template>

      <template #actions="{ row }">
        <div class="btn-group btn-group-sm" v-if="auth.hasRole('admin')">
          <button
            type="button"
            class="btn btn-outline-secondary"
            title="Edit"
            @click="openEdit(row)"
          >
            <i class="bi bi-pencil"></i>
          </button>
          <button
            type="button"
            class="btn btn-outline-danger"
            title="Hapus"
            @click="confirmDelete(row)"
          >
            <i class="bi bi-trash"></i>
          </button>
        </div>
        <span v-else class="small ds-muted-text">—</span>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      :disabled="loading"
      @page-change="goToPage"
      @per-page-change="setPerPage"
    />

    <LocationFormModal v-model:show="showForm" :location="editing" @saved="refresh" />

    <ConfirmModal
      ref="deleteModal"
      title="Hapus lokasi?"
      :busy="deleting"
      confirm-text="Hapus"
      tone="danger"
      icon="bi-trash"
      @confirm="performDelete"
      @closed="deleteTarget = null"
    >
      <template v-if="deleteTarget">
        Lokasi <strong>{{ deleteTarget.code }}</strong> — {{ deleteTarget.name }} akan dihapus.
        Lokasi yang masih dipakai aset atau riwayat transfer akan ditolak backend (409).
      </template>
    </ConfirmModal>
  </div>
</template>

<style scoped>
.ds-link {
  color: var(--ds-primary);
  font-weight: 700;
  text-decoration: none;
}

.ds-link:hover {
  color: var(--ds-primary-hover);
  text-decoration: underline;
}

.ds-on {
  color: var(--ds-success);
  border: 1px solid currentColor;
  background: rgba(var(--bs-success-rgb), 0.08);
}

.ds-off {
  color: var(--ds-muted);
  border: 1px solid currentColor;
  background: rgba(128, 128, 128, 0.08);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
