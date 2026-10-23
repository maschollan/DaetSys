<script>
export default {
  props: {
    title: { type: String, required: true },
    description: { type: String, default: '' },
  },
}
</script>

<template>
  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div class="min-vw-0">
      <h1 class="h5 mb-1">{{ title }}</h1>
      <p v-if="description" class="ds-muted-text mb-0">{{ description }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<style scoped>
.ds-muted-text {
  color: var(--ds-muted);
}
</style>
