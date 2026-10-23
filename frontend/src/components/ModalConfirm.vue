<script>
import { Modal } from 'bootstrap'

/**
 * Wrapper programmatic Bootstrap Modal (bukan per-render duplikat JS):
 * instance Modal dibuat sekali di mounted dan dimusnahkan di unmount.
 * Method `show()`/`hide()` otomatis terekspos ke template ref parent.
 */
export default {
  props: {
    title: { type: String, default: 'Konfirmasi' },
    message: { type: [String, Object], default: '' },
    icon: { type: String, default: 'bi-exclamation-triangle' },
    confirmText: { type: String, default: 'Ya, lanjutkan' },
    tone: { type: String, default: 'primary' }, // primary | fill | danger
    busy: { type: Boolean, default: false },
  },

  emits: ['confirm', 'closed'],

  mounted() {
    // keyboard=true & backdrop=true memungkinkan tutup via Esc dan klik latar
    // (default Bootstrap, dibuat eksplisit agar tidak berubah diam-diam).
    this.modalInstance = new Modal(this.$refs.root, { keyboard: true, backdrop: true })
    this.hiddenHandler = () => this.$emit('closed')
    this.$refs.root.addEventListener('hidden.bs.modal', this.hiddenHandler)
  },

  beforeUnmount() {
    this.$refs.root?.removeEventListener('hidden.bs.modal', this.hiddenHandler)
    this.modalInstance?.dispose()
    this.modalInstance = null
  },

  methods: {
    show() {
      this.modalInstance?.show()
    },

    hide() {
      this.modalInstance?.hide()
    },
  },
}
</script>

<template>
  <div ref="root" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h5 class="modal-title h6 mb-0">{{ title }}</h5>
          <button
            type="button"
            class="btn-close"
            data-bs-dismiss="modal"
            aria-label="Tutup"
          ></button>
        </div>
        <div class="modal-body">
          <div class="d-flex gap-3">
            <i :class="`bi ${icon} ds-tone-icon`"></i>
            <div class="min-vw-0">
              <slot>{{ message }}</slot>
            </div>
          </div>
        </div>
        <div class="modal-footer pt-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            Batal
          </button>
          <button
            type="button"
            class="btn"
            :class="
              tone === 'danger'
                ? 'btn-outline-danger'
                : tone === 'fill'
                  ? 'btn-fill-primary'
                  : 'btn-primary'
            "
            :disabled="busy"
            @click="$emit('confirm')"
          >
            <span
              v-if="busy"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"
            ></span>
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ds-tone-icon {
  font-size: 1.5rem;
  color: var(--ds-warning);
  flex-shrink: 0;
}
</style>
