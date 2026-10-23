<script>
import { describeChanges, formatChangeValue } from '@/utils/auditChanges'

/**
 * Daftar perubahan jejak audit dalam format teks ringkas:
 * `Field: nilai_lama → nilai_baru` (bukan JSON mentah).
 */
export default {
  props: {
    oldValues: { type: Object, default: null },
    newValues: { type: Object, default: null },
  },

  computed: {
    changes() {
      return describeChanges(this.oldValues, this.newValues)
    },
  },

  methods: {
    formatChangeValue,

    hasOld(change) {
      return change.old !== null && change.old !== undefined && change.old !== ''
    },

    hasNew(change) {
      return change.new !== null && change.new !== undefined && change.new !== ''
    },
  },
}
</script>

<template>
  <span v-if="!changes.length" class="ds-muted-text small">—</span>
  <ul v-else class="mb-0 ps-3 ds-changes small">
    <li v-for="change in changes" :key="change.key" class="py-1">
      <span class="ds-field">{{ change.label }}:</span>
      <template v-if="hasOld(change)">
        <s class="ds-old">{{ formatChangeValue(change.key, change.old) }}</s>
      </template>
      <template v-if="hasNew(change)">
        <span v-if="hasOld(change)" class="mx-1" aria-hidden="true">
          <i class="bi bi-arrow-right"></i>
        </span>
        <strong class="ds-new">{{ formatChangeValue(change.key, change.new) }}</strong>
      </template>
    </li>
  </ul>
</template>

<style scoped>
.ds-changes li {
  border-bottom: 1px dashed var(--ds-border);
}

.ds-changes li:last-child {
  border-bottom: 0;
}

.ds-field {
  color: var(--ds-muted);
}

.ds-old {
  color: var(--ds-muted);
}

.ds-new {
  color: var(--ds-text);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
