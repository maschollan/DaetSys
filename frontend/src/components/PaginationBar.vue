<script>
export default {
  props: {
    meta: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
  },

  emits: ['page-change', 'per-page-change'],

  computed: {
    from() {
      return this.meta.total ? (this.meta.current_page - 1) * this.meta.per_page + 1 : 0
    },

    to() {
      if (!this.meta.total) return 0
      return Math.min(this.meta.current_page * this.meta.per_page, this.meta.total)
    },

    perPageOptions() {
      return [10, 15, 25, 50, 100]
    },
  },
}
</script>

<template>
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 ds-bar">
    <div class="ds-muted-text small">
      Menampilkan {{ from }}
      -
      {{ to }}
      dari {{ meta.total }} data
    </div>

    <div class="d-flex align-items-center gap-2">
      <select
        class="form-select form-select-sm ds-per-page"
        :value="meta.per_page"
        :disabled="disabled"
        aria-label="Data per halaman"
        @change="$emit('per-page-change', Number($event.target.value))"
      >
        <option v-for="option in perPageOptions" :key="option" :value="option">
          {{ option }} / hal.
        </option>
      </select>

      <button
        type="button"
        class="btn btn-outline-secondary btn-sm"
        :disabled="disabled || meta.current_page <= 1"
        @click="$emit('page-change', meta.current_page - 1)"
      >
        <i class="bi bi-chevron-left"></i>
      </button>

      <span class="small ds-muted-text px-1"
        >Hal. {{ meta.current_page }} / {{ meta.last_page }}</span
      >

      <button
        type="button"
        class="btn btn-outline-secondary btn-sm"
        :disabled="disabled || meta.current_page >= meta.last_page"
        @click="$emit('page-change', meta.current_page + 1)"
      >
        <i class="bi bi-chevron-right"></i>
      </button>
    </div>
  </div>
</template>

<style scoped>
.ds-bar {
  background: var(--ds-surface);
  border-top: 1px solid var(--ds-border);
}

.ds-per-page {
  width: auto;
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
