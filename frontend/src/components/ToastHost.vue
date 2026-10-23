<script>
import useUiStore from '@/stores/ui'

export default {
  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    ui: () => useUiStore(),

    ICONS() {
      return {
        success: 'bi-check-circle-fill',
        danger: 'bi-x-octagon-fill',
        warning: 'bi-exclamation-triangle-fill',
        info: 'bi-info-circle-fill',
      }
    },

    CLASSES() {
      return {
        success: 'text-bg-success',
        danger: 'text-bg-danger',
        warning: 'text-bg-warning',
        info: 'text-bg-info',
      }
    },
  },
}
</script>

<template>
  <div class="toast-stack position-fixed bottom-0 end-0 p-3" style="z-index: 1090">
    <TransitionGroup name="toast-fade">
      <div
        v-for="toast in ui.toasts"
        :key="toast.id"
        class="toast show"
        :class="CLASSES[toast.variant] ?? CLASSES.info"
        role="alert"
      >
        <div class="d-flex align-items-start gap-2 py-2 px-3">
          <i :class="`bi ${ICONS[toast.variant] ?? ICONS.info} mt-1`"></i>
          <div class="toast-body bg-transparent p-0 me-2">{{ toast.message }}</div>
          <button
            type="button"
            class="btn-close btn-close-white ms-auto"
            aria-label="Tutup"
            @click="ui.dismiss(toast.id)"
          ></button>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-stack {
  max-width: 360px;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
  transition:
    transform 0.2s ease,
    opacity 0.2s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
  transform: translateY(8px);
  opacity: 0;
}
</style>
