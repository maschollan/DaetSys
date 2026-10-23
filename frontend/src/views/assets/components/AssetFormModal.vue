<script>
import useUiStore from '@/stores/ui'
import api from '@/services/api'
import { ASSET_ASSIGNABLE_STATUSES } from '@/constants/status'

import SearchableSelect from '@/components/SearchableSelect.vue'
import modalEscape from '@/mixins/modalEscape'

export default {
  name: 'AssetFormModal',

  components: { SearchableSelect },

  mixins: [modalEscape],

  props: {
    /** Komponen menampilkan diri sendiri melalui v-model `show`. */
    show: { type: Boolean, default: false },
    /** Aset untuk mode edit (null = create). */
    asset: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
  },

  emits: ['update:show', 'saved'],

  data() {
    return {
      submitting: false,
      form: {
        category_id: '',
        location_id: '',
        asset_code: '',
        name: '',
        serial_number: '',
        description: '',
        status: 'available',
        acquisition_date: '',
      },
      errors: {},
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    ui: () => useUiStore(),

    isEdit() {
      return Boolean(this.asset)
    },

    assetsAssignableStatuses() {
      return ASSET_ASSIGNABLE_STATUSES
    },
  },

  watch: {
    show(visible) {
      if (visible) {
        if (this.asset) this.fillEdit(this.asset)
        else this.resetForm()
      }
    },
  },

  methods: {
    close() {
      this.$emit('update:show', false)
    },

    fillEdit(asset) {
      this.form = {
        ...this.form,
        category_id: asset.category?.id ?? '',
        // location_id hanya set untuk create; saat edit backend melarang
        // perubahan lokasi (divalidasi prohibited) sehingga field disembunyikan.
        location_id: asset.location?.id ?? '',
        asset_code: asset.asset_code ?? '',
        name: asset.name ?? '',
        serial_number: asset.serial_number ?? '',
        description: asset.description ?? '',
        status: asset.status ?? 'available',
        acquisition_date: asset.acquisition_date ?? '',
      }
    },

    resetForm() {
      this.form = {
        category_id: '',
        location_id: '',
        asset_code: '',
        name: '',
        serial_number: '',
        description: '',
        status: 'available',
        acquisition_date: '',
      }
      this.errors = {}
    },

    validate() {
      this.errors = {}

      if (!this.form.category_id)
        this.errors = { ...this.errors, category_id: 'Kategori wajib dipilih.' }
      if (!this.form.asset_code.trim())
        this.errors = { ...this.errors, asset_code: 'Kode aset wajib diisi.' }
      if (!this.form.name.trim()) this.errors = { ...this.errors, name: 'Nama aset wajib diisi.' }

      return Object.keys(this.errors).length === 0
    },

    buildPayload() {
      const data = {
        category_id: Number(this.form.category_id),
        asset_code: this.form.asset_code.trim(),
        name: this.form.name.trim(),
      }

      if (this.form.serial_number.trim()) data.serial_number = this.form.serial_number.trim()
      if (this.form.description.trim()) data.description = this.form.description.trim()
      if (this.form.status) data.status = this.form.status
      if (this.form.acquisition_date) data.acquisition_date = this.form.acquisition_date

      // Location_id hanya dikirim pada create (backend menolaknya saat update).
      if (!this.isEdit && this.form.location_id) {
        data.location_id = Number(this.form.location_id)
      }

      return data
    },

    applyServerErrors(serverErrors) {
      this.errors = { ...(serverErrors ?? {}) }
    },

    async submit() {
      if (!this.validate()) return
      this.submitting = true

      try {
        if (this.isEdit) {
          await api.assets.update(this.asset.id, this.buildPayload())
          this.ui.success('Aset berhasil diperbarui.')
        } else {
          // Idempotency-Key mencegah duplikasi bila request dikirim ulang
          // karena gangguan jaringan (PLAN.md §Skenario 4).
          await api.assets.create(this.buildPayload(), crypto.randomUUID())
          this.ui.success('Aset berhasil dibuat.')
        }

        this.close()
        this.$emit('saved')
      } catch (exception) {
        const response = exception.response

        if (response?.status === 422) {
          this.applyServerErrors(response.data?.errors)
          this.ui.error(response.data?.message ?? 'Registrasi tidak valid.')
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal menyimpan aset.'))
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
    <div class="modal-dialog modal-dialog-centered modal-lg" :aria-hidden="!show">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h5 class="modal-title h6 mb-0">
            {{ isEdit ? 'Edit Aset' : 'Tambah Aset' }}
          </h5>
          <button type="button" class="btn-close" aria-label="Tutup" @click="close"></button>
        </div>

        <div class="modal-body">
          <div v-if="!isEdit && !categories.length" class="alert alert-warning py-2">
            Tidak ada kategori. Tambahkan kategori terlebih dahulu di menu Kategori.
          </div>

          <form class="row g-3" novalidate @submit.prevent="submit">
            <div class="col-md-6">
              <label class="form-label" for="asset-code"
                >Kode Aset <span class="text-danger" aria-hidden="true">*</span></label
              >
              <input
                id="asset-code"
                v-model="form.asset_code"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.asset_code }"
                placeholder="Contoh: KBM-2026-0001"
              />
              <div v-if="errors.asset_code" class="invalid-feedback d-block">
                {{ errors.asset_code }}
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="asset-name"
                >Nama Aset <span class="text-danger" aria-hidden="true">*</span></label
              >
              <input
                id="asset-name"
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.name }"
                placeholder="Nama barang"
              />
              <div v-if="errors.name" class="invalid-feedback d-block">{{ errors.name }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="asset-category"
                >Kategori <span class="text-danger" aria-hidden="true">*</span></label
              >
              <SearchableSelect
                id="asset-category"
                v-model="form.category_id"
                placeholder="Pilih kategori..."
                :invalid="Boolean(errors.category_id)"
                :clearable="false"
                :options="
                  categories.map((category) => ({ value: category.id, label: category.name }))
                "
              />
              <div v-if="errors.category_id" class="invalid-feedback d-block">
                {{ errors.category_id }}
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="asset-location">Lokasi</label>
              <SearchableSelect
                id="asset-location"
                v-model="form.location_id"
                placeholder="Belum ditempatkan"
                :disabled="isEdit"
                :empty-label="isEdit ? 'Belum ditempatkan' : 'Belum ditempatkan'"
                :options="
                  locations.map((location) => ({
                    value: location.id,
                    label: location.name,
                    hint: location.code,
                  }))
                "
              />
              <div v-if="errors.location_id" class="invalid-feedback d-block">
                {{ errors.location_id }}
              </div>
              <div v-if="isEdit" class="form-text">
                Lokasi hanya boleh berubah melalui proses transfer.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="asset-serial">Nomor Seri</label>
              <input
                id="asset-serial"
                v-model="form.serial_number"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.serial_number }"
                placeholder="Kosongkan bila tidak ada"
              />
              <div v-if="errors.serial_number" class="invalid-feedback d-block">
                {{ errors.serial_number }}
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label" for="asset-status">Status</label>
              <SearchableSelect
                id="asset-status"
                v-model="form.status"
                :clearable="false"
                search-placeholder="Cari status..."
                :invalid="Boolean(errors.status)"
                :options="
                  assetsAssignableStatuses.map((status) => ({
                    value: status.value,
                    label: status.label,
                  }))
                "
              />
              <div v-if="errors.status" class="invalid-feedback d-block">{{ errors.status }}</div>
            </div>

            <div class="col-md-3">
              <label class="form-label" for="asset-date">Tgl. Perolehan</label>
              <input
                id="asset-date"
                v-model="form.acquisition_date"
                type="date"
                class="form-control"
                :class="{ 'is-invalid': errors.acquisition_date }"
              />
              <div v-if="errors.acquisition_date" class="invalid-feedback d-block">
                {{ errors.acquisition_date }}
              </div>
            </div>

            <div class="col-12">
              <label class="form-label" for="asset-desc">Deskripsi</label>
              <textarea
                id="asset-desc"
                v-model="form.description"
                class="form-control"
                rows="2"
                :class="{ 'is-invalid': errors.description }"
              ></textarea>
              <div v-if="errors.description" class="invalid-feedback d-block">
                {{ errors.description }}
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
            {{ isEdit ? 'Simpan Perubahan' : 'Simpan Aset' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Backdrop ditangani manual agar konsisten dengan modal programmatic lain. */
.modal {
  background: rgba(0, 0, 0, 0.5);
}
</style>
