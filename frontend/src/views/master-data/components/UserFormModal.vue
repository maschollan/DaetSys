<script>
import api from '@/services/api'
import useUiStore from '@/stores/ui'
import useAuthStore from '@/stores/auth'

import SearchableSelect from '@/components/SearchableSelect.vue'
import modalEscape from '@/mixins/modalEscape'

export default {
  name: 'UserFormModal',

  components: { SearchableSelect },

  mixins: [modalEscape],

  props: {
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
  },

  emits: ['update:show', 'saved'],

  data() {
    return {
      submitting: false,
      form: {
        name: '',
        email: '',
        role_id: '',
        is_active: true,
        // Password hanya saat create; admin tak boleh set password login via form
        // ini demi keamanan (password di-reset lewat endpoint reset-password
        // tersendiri).
        password: '',
        password_confirmation: '',
      },
      errors: {},
    }
  },

  computed: {
    auth: () => useAuthStore(),
    ui: () => useUiStore(),
    isEdit() {
      return Boolean(this.user)
    },
  },

  watch: {
    show(visible) {
      if (visible) {
        if (this.user) {
          this.form = {
            ...this.form,
            name: this.user.name ?? '',
            email: this.user.email ?? '',
            role_id: this.user.role?.id ?? '',
            is_active: Boolean(this.user.is_active),
            password: '',
            password_confirmation: '',
          }
        } else {
          this.resetForm()
        }
      }
    },
  },

  methods: {
    close() {
      this.$emit('update:show', false)
    },

    clearErrors() {
      this.errors = {}
    },

    setError(key, message) {
      this.errors = { ...this.errors, [key]: message }
    },

    validate() {
      this.clearErrors()

      if (!this.form.name.trim()) this.setError('name', 'Nama wajib diisi.')
      if (!this.form.email.trim()) this.setError('email', 'Email wajib diisi.')
      else if (!/.+@.+\..+/.test(this.form.email.trim()))
        this.setError('email', 'Format email tidak valid.')
      if (!this.form.role_id) this.setError('role_id', 'Role wajib dipilih.')

      if (!this.isEdit) {
        if (!this.form.password) this.setError('password', 'Password wajib diisi.')
        else if (this.form.password.length < 8)
          this.setError('password', 'Password minimal 8 karakter.')
        else if (!/[a-zA-Z]/.test(this.form.password) || !/[0-9]/.test(this.form.password)) {
          this.setError('password', 'Password harus mengandung huruf dan angka.')
        } else if (this.form.password !== this.form.password_confirmation) {
          this.setError('password_confirmation', 'Konfirmasi password tidak sama.')
        }
      }

      return Object.keys(this.errors).length === 0
    },

    resetForm() {
      this.form = {
        name: '',
        email: '',
        role_id: '',
        is_active: true,
        password: '',
        password_confirmation: '',
      }
      this.clearErrors()
    },

    async submit() {
      if (!this.validate()) return
      this.submitting = true

      try {
        if (this.isEdit) {
          const payload = {
            name: this.form.name.trim(),
            email: this.form.email.trim(),
            role_id: Number(this.form.role_id),
            is_active: this.form.is_active,
          }
          await api.users.update(this.user.id, payload)
          this.ui.success('Pengguna diperbarui.')
        } else {
          await api.users.create({
            name: this.form.name.trim(),
            email: this.form.email.trim(),
            role_id: Number(this.form.role_id),
            is_active: this.form.is_active,
            password: this.form.password,
            password_confirmation: this.form.password_confirmation,
          })
          this.ui.success('Pengguna dibuat.')
        }

        this.close()
        this.$emit('saved')
      } catch (exception) {
        const response = exception.response
        if (response?.status === 422) {
          this.errors = { ...(response.data?.errors ?? {}) }
          this.ui.error(response.data?.message ?? 'Registrasi tidak valid.')
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal menyimpan pengguna.'))
        }
      } finally {
        this.submitting = false
      }
    },
  },
}
</script>

<template>
  <div
    class="modal fade show"
    tabindex="-1"
    :style="show ? 'display:block' : 'display:none'"
    aria-hidden="true"
    @click.self="close"
  >
    <div class="modal-dialog modal-dialog-centered" :aria-hidden="!show">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h5 class="modal-title h6 mb-0">{{ isEdit ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h5>
          <button type="button" class="btn-close" aria-label="Tutup" @click="close"></button>
        </div>

        <div class="modal-body">
          <form class="row g-3" novalidate @submit.prevent="submit">
            <div class="col-md-6">
              <label class="form-label" for="user-name"
                >Nama <span class="text-danger" aria-hidden="true">*</span></label
              >
              <input
                id="user-name"
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.name }"
              />
              <div v-if="errors.name" class="invalid-feedback d-block">{{ errors.name }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="user-email"
                >Email <span class="text-danger" aria-hidden="true">*</span></label
              >
              <input
                id="user-email"
                v-model="form.email"
                type="email"
                class="form-control"
                :class="{ 'is-invalid': errors.email }"
              />
              <div v-if="errors.email" class="invalid-feedback d-block">{{ errors.email }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="user-role"
                >Role <span class="text-danger" aria-hidden="true">*</span></label
              >
              <SearchableSelect
                id="user-role"
                v-model="form.role_id"
                placeholder="Pilih role..."
                :invalid="Boolean(errors.role_id)"
                :clearable="false"
                :options="roles.map((role) => ({ value: role.id, label: role.name }))"
              />
              <div v-if="errors.role_id" class="invalid-feedback d-block">{{ errors.role_id }}</div>
            </div>

            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check form-switch">
                <input
                  id="user-active"
                  v-model="form.is_active"
                  type="checkbox"
                  class="form-check-input"
                  role="switch"
                />
                <label class="form-check-label" for="user-active">
                  Akun aktif
                  <span v-if="isEdit" class="form-text d-block"
                    >Menonaktifkan mencabut seluruh token sesi.</span
                  >
                </label>
              </div>
            </div>

            <template v-if="!isEdit">
              <div class="col-md-6">
                <label class="form-label" for="user-password"
                  >Password <span class="text-danger" aria-hidden="true">*</span></label
                >
                <input
                  id="user-password"
                  v-model="form.password"
                  type="password"
                  class="form-control"
                  :class="{ 'is-invalid': errors.password || errors.password_confirmation }"
                  autocomplete="new-password"
                />
                <div v-if="errors.password" class="invalid-feedback d-block">
                  {{ errors.password }}
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="user-password-confirm"
                  >Konfirmasi Password <span class="text-danger" aria-hidden="true">*</span></label
                >
                <input
                  id="user-password-confirm"
                  v-model="form.password_confirmation"
                  type="password"
                  class="form-control"
                  :class="{ 'is-invalid': errors.password_confirmation }"
                  autocomplete="new-password"
                />
                <div v-if="errors.password_confirmation" class="invalid-feedback d-block">
                  {{ errors.password_confirmation }}
                </div>
              </div>
            </template>
          </form>
        </div>

        <div class="modal-footer pt-2">
          <button
            type="button"
            class="btn btn-outline-secondary"
            :disabled="submitting"
            @click="close"
          >
            Batal
          </button>
          <button type="button" class="btn btn-fill-primary" :disabled="submitting" @click="submit">
            <span
              v-if="submitting"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"
            ></span>
            Simpan
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal {
  background: rgba(0, 0, 0, 0.5);
}
</style>
