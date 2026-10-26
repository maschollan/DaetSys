<script>
import useConnectionStore from '@/stores/connection'
import useUiStore from '@/stores/ui'

/*
 * Overlay mid-session saat server (Render free tier) tidur di tengah
 * pemakaian: axios menandai offline, loop recovery di store connection
 * menutupnya kembali begitu /health merespons 200.
 */
export default {
  name: 'ReconnectOverlay',

  data() {
    return {
      connection: useConnectionStore(),
      ui: useUiStore(),
    }
  },

  watch: {
    'connection.offline'(isOffline, wasOffline) {
      if (wasOffline === true && isOffline === false) {
        this.ui.notify('Koneksi ke server kembali tersambung.', 'info')
      }
    },
  },
}
</script>

<template>
  <Transition name="ds-fade">
    <div v-if="connection.offline" class="ds-reconnect-overlay" role="status" aria-live="polite">
      <span class="ds-reconnect-brand">&gt;_</span>
      <p class="ds-reconnect-text">
        Reconnecting to server<span class="ds-dots"
          ><span>.</span><span>.</span><span>.</span></span
        >
      </p>
    </div>
  </Transition>
</template>

<style scoped>
.ds-reconnect-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  background: var(--ds-bg);
  /* Glass ringan di browser yang mendukung color-mix; fallback di atas
     adalah surface solid, jadi overlay tetap terbaca penuh tanpa itu. */
  background: color-mix(in srgb, var(--ds-bg) 88%, transparent);
  -webkit-backdrop-filter: blur(6px);
  backdrop-filter: blur(6px);
}

.ds-reconnect-brand {
  color: var(--ds-primary);
  font-weight: 700;
  font-size: 1.25rem;
  letter-spacing: 0.05em;
}

.ds-reconnect-text {
  margin: 0;
  color: var(--ds-muted);
  font-size: 0.9rem;
  letter-spacing: 0.04em;
}

.ds-fade-enter-active,
.ds-fade-leave-active {
  transition: opacity 0.3s ease;
}

.ds-fade-enter-from,
.ds-fade-leave-to {
  opacity: 0;
}
</style>
