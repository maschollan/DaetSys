<script>
import api from '@/services/api'
import useUiStore from '@/stores/ui'

import modalEscape from '@/mixins/modalEscape'

export default {
  name: 'CategoryFormModal',

  mixins: [modalEscape],

  props: {
    show: { type: Boolean, default: false },
    category: { type: Object, default: null },
  },

  emits: ['update:show', 'saved'],

  data() {
    return {
      form: {
        name: '',
        description: '',
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
        if (this.category) {
          this.form.name = this.category.name ?? ''
          this.form.description = this.category.description ?? ''
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
      if (!this.form.name.trim())
        this.errors = { ...this.errors, name: 'Nama kategori wajib diisi.' }
      return Object.keys(this.errors).length === 0
    },

    resetForm() {
      this.form.name = ''
      this.form.description = ''
      this.clearErrors()
    },

    async submit() {
      if (!this.validate()) return
      this.submitting = true

      const payload = {
        name: this.form.name.trim(),
        description: this.form.description.trim() || null,
      }

      try {
        if (this.category) {
          await api.categories.update(this.category.id, payload)
          this.ui.success('Kategori diperbarui.')
        } else {
          await api.categories.create(payload)
          this.ui.success('Kategori dibuat.')
        }
        this.close()
        this.$emit('saved')
      } catch (exception) {
        const response = exception.response
        if (response?.status === 422) {
          this.errors = { ...(response.data?.errors ?? {}) }
          this.ui.error(response.data?.message ?? 'Registrasi tidak valid.')
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal menyimpan kategori.'))
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
          <h5 class="modal-title h6 mb-0">{{ category ? 'Edit Kategori' : 'Tambah Kategori' }}</h5>
          <button type="button" class="btn-close" aria-label="Tutup" @click="close"></button>
        </div>

        <div class="modal-body">
          <form class="row g-3" novalidate @submit.prevent="submit">
            <div class="col-12">
              <label class="form-label" for="cat-name"
                >Nama <span class="text-danger" aria-hidden="true">*</span></label
              >
              <input
                id="cat-name"
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.name }"
                placeholder="Peralatan Kantor"
              />
              <div v-if="errors.name" class="invalid-feedback d-block">{{ errors.name }}</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="cat-desc">Deskripsi</label>
              <textarea
                id="cat-desc"
                v-model="form.description"
                class="form-control"
                rows="2"
              ></textarea>
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
