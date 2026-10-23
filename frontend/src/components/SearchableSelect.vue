<script>
/**
 * SearchableSelect: dropdown bisa dicari sebagai pengganti <select> natif.
 *
 * Dua mode pemakaian:
 * - filterable (default): opsi difilter lokal berdasarkan query ketikan.
 * - filterable=false: parent melakukan pencarian server — setiap ketikan
 *   mengirim event `search`, daftar opsi dikelola parent (properties `options`).
 *
 * Opsi: { value: String|Number, label: String, hint?: String }.
 */
export default {
  props: {
    id: { type: String, default: '' },
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih...' },
    searchPlaceholder: { type: String, default: 'Ketik untuk mencari...' },
    emptyLabel: { type: String, default: 'Semua' },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    /** Ukuran kecil (class `form-select-sm`) — serasi dengan input `sm` di bar filter. */
    sm: { type: Boolean, default: false },
    /** Sertakan pilihan kosong (reset/pilih semua). */
    clearable: { type: Boolean, default: true },
    /** true = filter lokal; false = pencarian server via event `search`. */
    filterable: { type: Boolean, default: true },
    /** Server mode: masih ada halaman berikutnya — aktifkan infinite scroll. */
    hasMore: { type: Boolean, default: false },
    /** Server mode: sedang mengambil halaman berikutnya. */
    loadingMore: { type: Boolean, default: false },
    notFoundText: { type: String, default: 'Tidak ada data yang cocok.' },
  },

  emits: ['update:modelValue', 'change', 'search', 'load-more'],

  data() {
    return {
      open: false,
      query: '',
    }
  },

  computed: {
    selected() {
      return this.options.find((option) => String(option.value) === String(this.modelValue)) ?? null
    },

    filtered() {
      if (!this.filterable) return this.options

      const keyword = this.query.trim().toLowerCase()
      if (!keyword) return this.options

      return this.options.filter((option) =>
        `${option.label} ${option.hint ?? ''}`.toLowerCase().includes(keyword),
      )
    },

    /** Sentinel observer di-sync ulang saat salah satu dependensinya berubah. */
    dsMoreState() {
      return `${this.open}|${this.hasMore}|${this.filterable}`
    },
  },

  watch: {
    open(isOpen) {
      if (isOpen) {
        this.query = ''
        // Mode pencarian server: daftar opsi dikelola parent, jadi saat
        // dibuka ulang kirimi event search kosong agar parent mengembalikan
        // daftar tanpa sisa keyword pencarian sebelumnya.
        if (!this.filterable) this.$emit('search', '')
        this.$nextTick(() => this.$refs.inputRef?.focus())
      }
    },

    modelValue() {
      // Pemilihan eksternal menutup daftar bila terbuka.
      if (this.open) this.close()
    },

    dsMoreState() {
      // $nextTick: sentinel baru ada di DOM setelah render selesai.
      this.$nextTick(() => this.syncMoreObserver())
    },
  },

  created() {
    // field non-reaktif: instance IntersectionObserver.
    this.moreObserver = null
  },

  mounted() {
    document.addEventListener('mousedown', this.onOutsideClick)
  },

  beforeUnmount() {
    document.removeEventListener('mousedown', this.onOutsideClick)
    this.destroyMoreObserver()
  },

  methods: {
    toggle() {
      if (this.disabled) return
      this.open = !this.open
    },

    close() {
      this.open = false
    },

    select(optionOrEmpty) {
      if (optionOrEmpty === null) {
        this.$emit('update:modelValue', '')
        this.$emit('change', '')
      } else {
        this.$emit('update:modelValue', optionOrEmpty.value)
        this.$emit('change', optionOrEmpty.value)
      }
      this.close()
    },

    selectEmpty() {
      this.$emit('update:modelValue', '')
      this.$emit('change', '')
      this.close()
    },

    onQueryInput() {
      if (!this.filterable) this.$emit('search', this.query.trim())
    },

    onEnter() {
      if (this.filtered.length) this.select(this.filtered[0])
    },

    onOutsideClick(event) {
      if (this.open && this.$refs.root && !this.$refs.root.contains(event.target)) this.close()
    },

    /**
     * Infinite scroll (server mode): sentinel di dasar daftar dipantau
     * IntersectionObserver — lebih andal daripada menghitung scrollTop karena
     * tetap memicu walau ukuran konten berubah (append selanjutnya langsung
     * terdeteksi). Tombol "Muat halaman berikutnya" tetap tersedia sebagai
     * fallback manual.
     */
    destroyMoreObserver() {
      if (this.moreObserver) {
        this.moreObserver.disconnect()
        this.moreObserver = null
      }
    },

    createMoreObserver() {
      if (this.moreObserver || !this.$refs.sentinelRef) return

      this.moreObserver = new IntersectionObserver(
        (entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            this.$emit('load-more')
          }
        },
        { rootMargin: '0px 0px 120px 0px' },
      )
      this.moreObserver.observe(this.$refs.sentinelRef)
    },

    syncMoreObserver() {
      const sentinel = this.$refs.sentinelRef
      if (this.open && this.hasMore && !this.filterable && sentinel) {
        // Sentinel re-mount (mis. daftar diganti hasil search) — sambung ulang.
        this.destroyMoreObserver()
        this.createMoreObserver()
      } else {
        this.destroyMoreObserver()
      }
    },
  },
}
</script>

<template>
  <div ref="root" class="ds-ss dropdown d-block">
    <button
      :id="id"
      type="button"
      class="form-select text-start d-flex align-items-center justify-content-between gap-2 ds-ss-toggle"
      :class="{ 'form-select-sm': sm, 'is-invalid': invalid }"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="toggle"
    >
      <span class="text-truncate" :class="{ 'ds-placeholder': !selected }">
        {{ selected?.label ?? placeholder }}
      </span>
      <span
        v-if="loading"
        class="spinner-border spinner-border-sm flex-shrink-0"
        aria-hidden="true"
      ></span>
      <i v-else class="bi bi-chevron-down small flex-shrink-0" aria-hidden="true"></i>
    </button>

    <div v-if="open" class="ds-ss-menu" role="listbox">
      <div class="p-1 border-bottom">
        <input
          ref="inputRef"
          v-model="query"
          type="text"
          class="form-control"
          :class="{ 'form-control-sm': sm }"
          :placeholder="searchPlaceholder"
          aria-label="Cari opsi"
          @input="onQueryInput"
          @keydown.esc.prevent="close"
          @keydown.enter.prevent="onEnter"
        />
      </div>

      <div class="ds-ss-list" role="presentation">
        <button
          v-if="clearable"
          type="button"
          class="ds-ss-item"
          :class="{ active: String(modelValue) === '' }"
          role="option"
          :aria-selected="String(modelValue) === ''"
          @click="selectEmpty"
        >
          {{ emptyLabel }}
        </button>

        <button
          v-for="option in filtered"
          :key="option.value"
          type="button"
          class="ds-ss-item"
          :class="{ active: String(option.value) === String(modelValue) }"
          role="option"
          :aria-selected="String(option.value) === String(modelValue)"
          @click="select(option)"
        >
          {{ option.label }}
          <small v-if="option.hint" class="d-block ds-muted-text">{{ option.hint }}</small>
        </button>

        <p
          v-if="!filtered.length && (!clearable || query)"
          class="ds-muted-text small text-center mb-0 py-2"
        >
          <span v-if="loading">Memuat...</span>
          <span v-else>{{ notFoundText }}</span>
        </p>

        <!-- Sentinel infinite scroll + tombol manual sebagai fallback -->
        <button
          v-if="hasMore"
          type="button"
          class="ds-ss-more"
          :disabled="loadingMore"
          @click="$emit('load-more')"
        >
          <span
            v-if="loadingMore"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"
          ></span>
          {{ loadingMore ? 'Memuat halaman berikutnya...' : 'Muat halaman berikutnya' }}
        </button>
        <div
          v-if="hasMore && !filterable"
          ref="sentinelRef"
          class="ds-ss-sentinel"
          aria-hidden="true"
        ></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ds-ss-toggle {
  position: relative;
  background: var(--ds-surface);
  color: var(--ds-text);
  align-items: center;
  overflow: hidden;
  padding: 0.5rem;
}

.ds-ss-toggle.form-select-sm {
  padding: 0.25rem 0.5rem;
}

.ds-ss-toggle.is-invalid {
  border-color: var(--bs-danger);
}

.ds-placeholder {
  color: var(--ds-muted);
}

.ds-ss-menu {
  position: absolute;
  top: calc(100% + 2px);
  left: 0;
  right: 0;
  z-index: 1065;
  min-width: 100%;
  background: var(--ds-surface, var(--bs-body-bg));
  border: 1px solid var(--ds-border, rgba(0, 0, 0, 0.175));
  border-radius: 0.375rem;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

:global([data-bs-theme='dark']) .ds-ss-menu {
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.5);
}

.ds-ss-list {
  max-height: 240px;
  overflow-y: auto;
  padding: 0.15rem 0;
}

.ds-ss-item {
  display: block;
  width: 100%;
  padding: 0.35rem 0.75rem;
  text-align: left;
  background: none;
  border: 0;
  font-size: 0.85rem;
  color: inherit;
}

.ds-ss-item:hover {
  background: rgba(var(--ds-primary-rgb, 13, 148, 136), 0.1);
}

.ds-ss-item.active {
  font-weight: 700;
  color: var(--ds-primary);
}

/* Sentinel 1px untuk IntersectionObserver infinite scroll. */
.ds-ss-sentinel {
  height: 1px;
}

.ds-ss-more {
  display: block;
  width: 100%;
  padding: 0.45rem 0.75rem;
  text-align: center;
  background: none;
  border: 0;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--ds-primary);
}

.ds-ss-more:not(:disabled):hover {
  background: rgba(var(--ds-primary-rgb, 13, 148, 136), 0.08);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
