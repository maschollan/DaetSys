<script>
/**
 * Tabel data generik dengan kolom dinamis, slot sel per kolom
 * (`#cell-<key>`), row loading spinner, error state, dan empty state.
 * Kolom: { key, label, sortable?, thClass?, tdClass? }
 */
export default {
  props: {
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    sortKey: { type: String, default: '' },
    sortDir: { type: String, default: 'asc' },
    emptyMessage: { type: String, default: 'Belum ada data.' },
  },

  emits: ['sort', 'retry'],
}
</script>

<template>
  <div class="table-responsive ds-table-wrap">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th
            v-for="column in columns"
            :key="column.key"
            class="ds-th text-nowrap"
            :class="[column.thClass]"
            :aria-sort="
              column.sortable
                ? sortKey === column.key
                  ? sortDir === 'asc'
                    ? 'ascending'
                    : 'descending'
                  : 'none'
                : undefined
            "
          >
            <button
              v-if="column.sortable"
              type="button"
              class="ds-sort-btn d-inline-flex align-items-center gap-1"
              @click="$emit('sort', column.key)"
            >
              {{ column.label }}
              <i
                class="bi"
                :class="
                  sortKey === column.key
                    ? sortDir === 'asc'
                      ? 'bi-caret-up-fill'
                      : 'bi-caret-down-fill'
                    : 'bi-arrow-down-up opacity-50'
                "
              ></i>
            </button>
            <template v-else>{{ column.label }}</template>
          </th>
          <th v-if="$slots.actions" class="ds-th text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="text-center py-5">
            <div class="spinner-border ds-loader" role="status" aria-hidden="true"></div>
            <div class="ds-muted-text mt-3">Memuat data...</div>
          </td>
        </tr>
        <tr v-else-if="error">
          <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="py-5">
            <div class="text-center">
              <i class="bi bi-cloud-slash h3 ds-danger-text d-block mb-2"></i>
              <p class="mb-3">{{ error }}</p>
              <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                @click="$emit('retry')"
              >
                <i class="bi bi-arrow-clockwise me-1"></i> Coba lagi
              </button>
            </div>
          </td>
        </tr>
        <tr v-else-if="!rows.length">
          <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="py-5">
            <div class="text-center ds-muted-text">
              <i class="bi bi-inbox h3 d-block mb-2"></i>
              {{ emptyMessage }}
            </div>
          </td>
        </tr>
        <template v-else>
          <tr v-for="(row, index) in rows" :key="row.id ?? index">
            <td v-for="column in columns" :key="column.key" class="ds-td" :class="[column.tdClass]">
              <slot :name="`cell-${column.key}`" :row="row" :index="index">
                {{ row[column.key] ?? '—' }}
              </slot>
            </td>
            <td v-if="$slots.actions" class="ds-td text-end text-nowrap">
              <slot name="actions" :row="row" :index="index" />
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.ds-table-wrap {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
}

.ds-table-wrap .table {
  --bs-table-color: var(--ds-text);
  --bs-table-bg: var(--ds-surface);
  --bs-table-hover-bg: rgba(var(--ds-primary-rgb), 0.06);
  --bs-table-hover-color: var(--ds-text);
  margin-bottom: 0;
}

.ds-th {
  color: var(--ds-muted);
  font-size: 0.74rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 700;
  border-bottom: 1px solid var(--ds-border) !important;
  padding: 0.6rem 0.75rem;
  background: transparent;
}

.ds-sort-btn {
  border: 0;
  background: transparent;
  padding: 0;
  color: inherit;
  font: inherit;
  letter-spacing: inherit;
  text-transform: inherit;
}

.ds-sort-btn:hover {
  color: var(--ds-primary);
}

.ds-td {
  border-bottom: 1px solid var(--ds-border);
  padding: 0.6rem 0.75rem;
}

.ds-loader {
  color: var(--ds-primary);
}

.ds-danger-text {
  color: var(--ds-danger);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
