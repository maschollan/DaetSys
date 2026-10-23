<script>
import {
  ASSET_STATUS_BADGES,
  ASSET_STATUS_LABELS,
  TRANSFER_STATUS_BADGES,
  TRANSFER_STATUS_LABELS,
} from '@/constants/status'

/**
 * Metra angka wilayah feature: `asset` | `transfer` | boolean.
 */
export default {
  props: {
    /** `asset` | `transfer` | `boolean` */
    variant: { type: String, default: 'asset' },
    value: { type: null, default: null },
  },

  computed: {
    text() {
      if (this.variant === 'asset') return ASSET_STATUS_LABELS[this.value] ?? this.value
      if (this.variant === 'transfer') return TRANSFER_STATUS_LABELS[this.value] ?? this.value
      return this.value ? 'Aktif' : 'Nonaktif'
    },

    badgeClass() {
      if (this.variant === 'asset') return ASSET_STATUS_BADGES[this.value] ?? 'ds-badge-secondary'
      if (this.variant === 'transfer')
        return TRANSFER_STATUS_BADGES[this.value] ?? 'ds-badge-secondary'
      return this.value ? 'ds-badge-success' : 'ds-badge-secondary'
    },
  },
}
</script>

<template>
  <span class="ds-badge" :class="badgeClass">{{ text }}</span>
</template>

<style scoped>
.ds-badge {
  display: inline-block;
  padding: 0.25rem 0.6rem;
  border: 1px solid currentColor;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  user-select: none;
}

.ds-badge-success {
  color: var(--ds-success);
  background: rgba(var(--bs-success-rgb), 0.08);
}

.ds-badge-info {
  color: var(--ds-primary);
  background: rgba(var(--ds-primary-rgb), 0.08);
}

.ds-badge-warning {
  color: var(--ds-warning);
  background: rgba(var(--bs-warning-rgb), 0.08);
}

.ds-badge-danger {
  color: var(--ds-danger);
  background: rgba(var(--bs-danger-rgb), 0.08);
}

.ds-badge-secondary {
  color: var(--ds-muted);
  background: rgba(128, 128, 128, 0.08);
}
</style>
