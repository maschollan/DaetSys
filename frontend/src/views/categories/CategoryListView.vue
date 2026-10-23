<script>
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import useUiStore from '@/stores/ui'

import ConfirmModal from '@/components/ModalConfirm.vue'
import DataTable from '@/components/DataTable.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import CategoryFormModal from '@/views/master-data/components/CategoryFormModal.vue'
import listQuery from '@/mixins/listQuery'

export default {
  name: 'CategoryListView',

  components: {
    ConfirmModal,
    DataTable,
    PageHeader,
    PaginationBar,
    CategoryFormModal,
  },

  mixins: [listQuery],

  listConfig: {
    fetch: api.categories.list,
    filters: {},
    sort: { key: 'name', dir: 'asc' },
  },

  data() {
    return {
      columns: [
        { key: 'name', label: 'Nama Kategori', sortable: true },
        { key: 'description', label: 'Deskripsi' },
        { key: 'assets_count', label: 'Jumlah Aset' },
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

    openEdit(category) {
      this.editing = category
      this.showForm = true
    },

    confirmDelete(category) {
      this.deleteTarget = category
      this.$refs.deleteModal?.show()
    },

    async performDelete() {
      this.deleting = true

      try {
        await api.categories.remove(this.deleteTarget.id)
        this.ui.success('Kategori dihapus.')
        this.$refs.deleteModal?.hide()
        await this.refresh()
      } catch (exception) {
        this.ui.error(this.ui.apiError(exception, 'Gagal menghapus kategori.'))
      } finally {
        this.deleting = false
      }
    },
  },
}
</script>

<template>
  <div>
    <PageHeader title="Kategori" description="Klasifikasi aset untuk pengelompokan dan laporan.">
      <template #actions>
        <button
          v-if="auth.hasRole('admin')"
          type="button"
          class="btn btn-fill-primary btn-sm"
          @click="openCreate"
        >
          <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
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
      empty-message="Belum ada kategori terdaftar."
      @sort="toggleSort"
      @retry="refresh"
    >
      <template #cell-description="{ row }">
        {{ row.description ?? '—' }}
      </template>

      <template #cell-assets_count="{ row }">
        <RouterLink
          v-if="row.assets_count"
          class="ds-link"
          :to="{ name: 'assets', query: { category_id: row.id } }"
        >
          {{ row.assets_count }} aset
        </RouterLink>
        <span v-else class="ds-muted-text">0 aset</span>
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

    <CategoryFormModal v-model:show="showForm" :category="editing" @saved="refresh" />

    <ConfirmModal
      ref="deleteModal"
      title="Hapus kategori?"
      :busy="deleting"
      confirm-text="Hapus"
      tone="danger"
      icon="bi-trash"
      @confirm="performDelete"
      @closed="deleteTarget = null"
    >
      <template v-if="deleteTarget">
        Kategori <strong>{{ deleteTarget.name }}</strong> akan dihapus. Kategori yang masih dipakai
        aset akan ditolak backend (409).
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
  text-decoration: underline;
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
