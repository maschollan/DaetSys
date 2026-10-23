<script>
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import useUiStore from '@/stores/ui'
import { formatDate } from '@/constants/status'

import ConfirmModal from '@/components/ModalConfirm.vue'
import DataTable from '@/components/DataTable.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import UserFormModal from '@/views/master-data/components/UserFormModal.vue'
import listQuery from '@/mixins/listQuery'

export default {
  name: 'UserListView',

  components: {
    ConfirmModal,
    DataTable,
    PageHeader,
    PaginationBar,
    SearchableSelect,
    UserFormModal,
  },

  mixins: [listQuery],

  listConfig: {
    fetch: api.users.list,
    filters: { role_id: '', is_active: '' },
    sort: { key: 'name', dir: 'asc' },
  },

  data() {
    return {
      columns: [
        { key: 'name', label: 'Nama', sortable: true },
        { key: 'email', label: 'Email', sortable: true },
        { key: 'role', label: 'Role' },
        { key: 'is_active', label: 'Status' },
        { key: 'created_at', label: 'Dibuat' },
      ],
      roles: [],
      showForm: false,
      editing: null,
      deleteTarget: null,
      deleting: false,
      resetTarget: null,
      resetPassword: '',
      resetPasswordConfirmation: '',
      resetError: '',
      resetBusy: false,
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
    ui: () => useUiStore(),
  },

  mounted() {
    this.refresh()
    this.loadRoles()
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
      this.editing = null
      this.showForm = true
    },

    openEdit(user) {
      this.editing = user
      this.showForm = true
    },

    confirmDelete(user) {
      this.deleteTarget = user
      this.$refs.deleteModal?.show()
    },

    async performDelete() {
      this.deleting = true

      try {
        await api.users.remove(this.deleteTarget.id)
        this.ui.success('Pengguna dihapus.')
        this.$refs.deleteModal?.hide()
        await this.refresh()
      } catch (exception) {
        this.ui.error(this.ui.apiError(exception, 'Gagal menghapus pengguna.'))
      } finally {
        this.deleting = false
      }
    },

    openReset(user) {
      this.resetTarget = user
      this.resetPassword = ''
      this.resetPasswordConfirmation = ''
      this.resetError = ''
      this.$refs.resetModal?.show()
    },

    async performReset() {
      if (
        this.resetPassword.length < 8 ||
        !/[a-zA-Z]/.test(this.resetPassword) ||
        !/[0-9]/.test(this.resetPassword)
      ) {
        this.resetError = 'Password minimal 8 karakter, mengandung huruf dan angka.'
        return
      }
      if (this.resetPassword !== this.resetPasswordConfirmation) {
        this.resetError = 'Konfirmasi password tidak sama.'
        return
      }

      this.resetBusy = true

      try {
        await api.users.resetPassword(this.resetTarget.id, {
          password: this.resetPassword,
          password_confirmation: this.resetPasswordConfirmation,
        })
        this.ui.success('Password direset; seluruh sesi pengguna dicabut.')
        this.$refs.resetModal?.hide()
      } catch (exception) {
        const response = exception.response
        if (response?.status === 422 && response.data?.errors?.password) {
          this.resetError = response.data.errors.password[0]
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal mereset password.'))
        }
      } finally {
        this.resetBusy = false
      }
    },

    async toggleActive(user) {
      try {
        await api.users.update(user.id, {
          name: user.name,
          email: user.email,
          role_id: user.role?.id,
          is_active: !user.is_active,
        })
        this.ui.success(`Akun ${user.name} ${user.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`)
        await this.refresh()
      } catch (exception) {
        this.ui.error(this.ui.apiError(exception, 'Gagal mengubah status akun.'))
      }
    },

    async loadRoles() {
      try {
        // Roles tersedia sedikit; ambil dari daftar users (role object on each row)
        const { data } = await api.users.list({ per_page: 100 })
        const roleMap = new Map()
        ;(data.data ?? []).forEach((user) => {
          if (user.role?.id) roleMap.set(user.role.id, user.role)
        })
        this.roles = [...roleMap.values()].sort((a, b) => a.name.localeCompare(b.name))
      } catch {
        // Daftar role opsional untuk form.
      }
    },
  },
}
</script>

<template>
  <div>
    <PageHeader title="Pengguna" description="Kelola akun pengguna, status aktif, dan role.">
      <template #actions>
        <button
          v-if="auth.hasRole('admin')"
          type="button"
          class="btn btn-fill-primary btn-sm"
          @click="openCreate"
        >
          <i class="bi bi-plus-lg me-1"></i> Tambah Pengguna
        </button>
      </template>
    </PageHeader>

    <div class="ds-filter-card px-3 py-3 mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-5">
          <label class="form-label small mb-1" for="user-search">Cari</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input
              id="user-search"
              v-model="searchQuery"
              type="text"
              class="form-control"
              placeholder="Nama atau email..."
              @input="onSearchInput"
            />
          </div>
        </div>

        <div class="col-6 col-lg-3">
          <label class="form-label small mb-1" for="filter-role">Role</label>
          <SearchableSelect
            id="filter-role"
            v-model="filters.role_id"
            sm
            placeholder="Role..."
            :options="roles.map((role) => ({ value: role.id, label: role.name }))"
            @change="applyFilters()"
          />
        </div>

        <div class="col-6 col-lg-3">
          <label class="form-label small mb-1" for="filter-active">Status Akun</label>
          <SearchableSelect
            id="filter-active"
            v-model="filters.is_active"
            sm
            placeholder="Status akun..."
            :options="[
              { value: 'true', label: 'Aktif' },
              { value: 'false', label: 'Nonaktif' },
            ]"
            @change="applyFilters()"
          />
        </div>

        <div class="col-12 col-lg-1 d-flex justify-content-lg-end">
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="refresh()">
            <i class="bi bi-arrow-clockwise"></i>
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
      empty-message="Tidak ada pengguna yang cocok."
      @sort="toggleSort"
      @retry="refresh"
    >
      <template #cell-role="{ row }">
        <span class="badge text-bg-secondary">{{ row.role?.name ?? '—' }}</span>
      </template>

      <template #cell-is_active="{ row }">
        <div
          v-if="auth.hasRole('admin') && row.id !== auth.user.id"
          class="form-check form-switch"
          style="padding-left: 2.4rem"
        >
          <input
            class="form-check-input"
            type="checkbox"
            role="switch"
            :checked="row.is_active"
            :aria-label="row.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'"
            @change="toggleActive(row)"
          />
          <span class="small ms-1">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <span v-else :class="row.is_active ? 'ds-on' : 'ds-off'" class="badge">{{
          row.is_active ? 'Aktif' : 'Nonaktif'
        }}</span>
      </template>

      <template #cell-created_at="{ row }">
        {{ formatDate(row.created_at) }}
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
            class="btn btn-outline-secondary"
            title="Reset password"
            @click="openReset(row)"
          >
            <i class="bi bi-key"></i>
          </button>
          <button
            type="button"
            v-if="row.id !== auth.user.id"
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

    <UserFormModal
      v-model:show="showForm"
      :user="editing"
      :roles="roles"
      @saved="
        () => {
          refresh()
          loadRoles()
        }
      "
    />

    <ConfirmModal
      ref="deleteModal"
      title="Hapus pengguna?"
      :busy="deleting"
      confirm-text="Hapus"
      tone="danger"
      icon="bi-person-x"
      @confirm="performDelete"
      @closed="deleteTarget = null"
    >
      <template v-if="deleteTarget">
        Akun <strong>{{ deleteTarget.email }}</strong> akan dihapus. Pengguna yang masih dirujuk
        riwayat transaksi/audit tidak dapat dihapus (backend akan menolak 409).
      </template>
    </ConfirmModal>

    <ConfirmModal
      ref="resetModal"
      title="Reset password?"
      confirm-text="Reset"
      icon="bi-key"
      :busy="resetBusy"
      @confirm="performReset"
      @closed="resetTarget = null"
    >
      <div v-if="resetTarget" class="mb-2">
        <p class="small ds-muted-text mb-2">
          Reset password untuk <strong>{{ resetTarget.email }}</strong
          >. Seluruh token aktif pengguna akan dicabut.
        </p>
        <div v-if="resetError" class="alert alert-danger py-2">{{ resetError }}</div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small">Password baru</label>
            <input
              v-model="resetPassword"
              type="password"
              class="form-control"
              autocomplete="new-password"
            />
          </div>
          <div class="col-6">
            <label class="form-label small">Konfirmasi</label>
            <input
              v-model="resetPasswordConfirmation"
              type="password"
              class="form-control"
              autocomplete="new-password"
            />
          </div>
        </div>
      </div>
    </ConfirmModal>
  </div>
</template>

<style scoped>
.ds-filter-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
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
