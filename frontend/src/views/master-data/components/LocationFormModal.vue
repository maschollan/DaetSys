<script>
import api from '@/services/api'
import useUiStore from '@/stores/ui'

import modalEscape from '@/mixins/modalEscape'

export default {
  name: 'LocationFormModal',

  mixins: [modalEscape],

  props: {
    show: { type: Boolean, default: false },
    location: { type: Object, default: null },
  },

  emits: ['update:show', 'saved'],

  data() {
    return {
      form: {
        code: '',
        name: '',
        address: '',
        is_active: true,
      },
      errors: {},
      submitting: false,
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    ui: () => useUiStore(),
  },

  watch: {
    show(visible) {
      if (visible) {
        if (this.location) {
          this.form = {
            code: this.location.code ?? '',
            name: this.location.name ?? '',
            address: this.location.address ?? '',
            is_active: Boolean(this.location.is_active),
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

    validate() {
      this.clearErrors()

      if (!this.form.code.trim()) this.errors = { ...this.errors, code: 'Kode lokasi wajib diisi.' }
      if (!this.form.name.trim()) this.errors = { ...this.errors, name: 'Nama lokasi wajib diisi.' }

      return Object.keys(this.errors).length === 0
    },

    resetForm() {
      this.form = { code: '', name: '', address: '', is_active: true }
      this.clearErrors()
    },

    async submit() {
      if (!this.validate()) return
      this.submitting = true

      const payload = {
        code: this.form.code.trim().toUpperCase(),
        name: this.form.name.trim(),
        address: this.form.address.trim() || null,
      }

      try {
        if (this.location) {
          if (this.form.is_active !== Boolean(this.location.is_active)) {
            payload.is_active = this.form.is_active
          }
          await api.locations.update(this.location.id, payload)
          this.ui.success('Lokasi diperbarui.')
        } else {
          await api.locations.create(payload)
          this.ui.success('Lokasi dibuat.')
        }
        this.close()
        this.$emit('saved')
      } catch (exception) {
        const response = exception.response
        if (response?.status === 422) {
          this.errors = { ...(response.data?.errors ?? {}) }
          this.ui.error(response.data?.message ?? 'Registrasi tidak valid.')
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal menyimpan lokasi.'))
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
          <h5 class="modal-title h6 mb-0">{{ location ? 'Edit Lokasi' : 'Tambah Lokasi' }}</h5>
          <button type="button" class="btn-close" aria-label="Tutup" @click="close"></button>
        </div>

        <div class="modal-body">
          <form class="row g-3" novalidate @submit.prevent="submit">
            <div class="col-md-5">
              <label class="form-label" for="loc-code"
                >Kode <span class="text-danger" aria-hidden="true">*</span></label
              >
              <input
                id="loc-code"
                v-model="form.code"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.code }"
                placeholder="GDT-A"
              />
              <div v-if="errors.code" class="invalid-feedback d-block">{{ errors.code }}</div>
            </div>

            <div class="col-md-7">
              <label class="form-label" for="loc-name"
                >Nama <span class="text-danger" aria-hidden="true">*</span></label
              >
              <input
                id="loc-name"
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.name }"
                placeholder="Gudang A"
              />
              <div v-if="errors.name" class="invalid-feedback d-block">{{ errors.name }}</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="loc-address">Alamat</label>
              <textarea
                id="loc-address"
                v-model="form.address"
                class="form-control"
                rows="2"
              ></textarea>
            </div>

            <div class="col-12" v-if="location">
              <div class="form-check form-switch">
                <input
                  id="loc-active"
                  v-model="form.is_active"
                  type="checkbox"
                  class="form-check-input"
                  role="switch"
                />
                <label class="form-check-label" for="loc-active">Lokasi aktif</label>
              </div>
            </div>
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
