<script>
import api from '@/services/api'
import useUiStore from '@/stores/ui'
import useTransferOptionsStore from '@/stores/transferOptions'

import SearchableSelect from '@/components/SearchableSelect.vue'
import modalEscape from '@/mixins/modalEscape'

export default {
  name: 'TransferFormModal',

  components: { SearchableSelect },

  mixins: [modalEscape],

  props: {
    show: { type: Boolean, default: false },
    /** Opsional: ID aset yang sudah dipilih sebelumnya (quick action). */
    presetAssetId: { type: null, default: null },
  },

  emits: ['update:show', 'saved'],

  data() {
    return {
      form: {
        asset_id: '',
        to_location_id: '',
        reason: '',
      },
      errors: {},
      submitting: false,
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    ui: () => useUiStore(),

    /**
     * Daftar opsi dikelola store bersama agar modal tidak memanggil API
     * berulang-ulang setiap kali dibuka (cache dengan TTL per jenis data).
     */
    optionsStore: () => useTransferOptionsStore(),

    assetOptions() {
      return this.optionsStore.assetOptions
    },

    assetLoading() {
      return this.optionsStore.assetLoading
    },

    assetHasMore() {
      return this.optionsStore.assetHasMore
    },

    assetLoadingMore() {
      return this.optionsStore.assetLoadingMore
    },

    locationOptions() {
      return this.optionsStore.locationOptions
    },
  },

  watch: {
    show(visible) {
      if (visible) {
        this.form = { asset_id: this.presetAssetId ?? '', to_location_id: '', reason: '' }
        this.errors = {}

        void this.optionsStore.ensureLocations()
        void this.optionsStore.ensureAssets()
      }
    },
  },

  beforeUnmount() {
    window.clearTimeout(this.searchTimer)
  },

  methods: {
    close() {
      this.$emit('update:show', false)
    },

    onAssetSearch(query) {
      window.clearTimeout(this.searchTimer)
      this.searchTimer = window.setTimeout(() => void this.optionsStore.searchAssets(query), 300)
    },

    /** Infinite scroll: daftar digulir sampai dasar — muat halaman berikutnya. */
    onAssetLoadMore() {
      void this.optionsStore.loadMoreAssets()
    },

    validate() {
      this.errors = {}

      if (!this.form.asset_id) this.errors = { ...this.errors, asset_id: 'Aset wajib dipilih.' }
      if (!this.form.to_location_id)
        this.errors = { ...this.errors, to_location_id: 'Lokasi tujuan wajib dipilih.' }

      return Object.keys(this.errors).length === 0
    },

    async submit() {
      if (!this.validate()) return
      this.submitting = true

      try {
        await api.transfers.create(
          {
            asset_id: Number(this.form.asset_id),
            to_location_id: Number(this.form.to_location_id),
            reason: this.form.reason.trim() || null,
          },
          crypto.randomUUID(), // Idempotency-Key (PLAN.md Skenario 4)
        )

        this.close()
        // Cache aset dianggap stelah ketersediaan berubah; pembukaan modal
        // berikutnya akan mengambil daftar fresh dari API.
        this.optionsStore.invalidateAssets()
        this.$emit('saved')
      } catch (exception) {
        const response = exception.response

        if (response?.status === 422) {
          this.errors = { ...(response.data?.errors ?? {}) }
          this.ui.error(response.data?.message ?? 'Pengajuan tidak valid.')
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal mengajukan transfer.'))
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
          <h5 class="modal-title h6 mb-0">Ajukan Transfer</h5>
          <button type="button" class="btn-close" aria-label="Tutup" @click="close"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info py-2 small d-flex gap-2">
            <i class="bi bi-info-circle mt-1"></i>
            <span>
              Aset akan ditandai <em>In Transfer</em> sampai penerimaan di lokasi tujuan
              dikonfirmasi. Perpindahan lokasi aset terjadi hanya setelah serah terima.
            </span>
          </div>

          <form class="row g-3" novalidate @submit.prevent="submit">
            <div class="col-12">
              <label class="form-label" for="transfer-asset"
                >Aset <span class="text-danger" aria-hidden="true">*</span></label
              >
              <SearchableSelect
                id="transfer-asset"
                v-model="form.asset_id"
                placeholder="Pilih aset..."
                search-placeholder="Cari kode atau nama aset..."
                :filterable="false"
                :loading="assetLoading"
                :has-more="assetHasMore"
                :loading-more="assetLoadingMore"
                :invalid="Boolean(errors.asset_id)"
                :clearable="false"
                not-found-text="Aset tersedia tidak ditemukan. Ubah kata kunci pencarian."
                :options="assetOptions"
                @search="onAssetSearch"
                @load-more="onAssetLoadMore"
              />
              <div v-if="errors.asset_id" class="invalid-feedback d-block">
                {{ errors.asset_id }}
              </div>
            </div>

            <div class="col-12">
              <label class="form-label" for="transfer-target"
                >Lokasi Tujuan <span class="text-danger" aria-hidden="true">*</span></label
              >
              <SearchableSelect
                id="transfer-target"
                v-model="form.to_location_id"
                placeholder="Pilih lokasi tujuan..."
                :invalid="Boolean(errors.to_location_id)"
                :clearable="false"
                :options="locationOptions"
              />
              <div v-if="errors.to_location_id" class="invalid-feedback d-block">
                {{ errors.to_location_id }}
              </div>
            </div>

            <div class="col-12">
              <label class="form-label" for="transfer-reason">Alasan</label>
              <textarea
                id="transfer-reason"
                v-model="form.reason"
                class="form-control"
                rows="2"
                :class="{ 'is-invalid': errors.reason }"
              ></textarea>
              <div v-if="errors.reason" class="invalid-feedback d-block">{{ errors.reason }}</div>
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
            Kirim Pengajuan
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
