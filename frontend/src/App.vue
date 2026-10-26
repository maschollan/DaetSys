<script>
import BootScreen from '@/components/BootScreen.vue'
import ReconnectOverlay from '@/components/ReconnectOverlay.vue'
import { pollUntilHealthy } from '@/services/healthService'

/*
 * Gate preload (PLAN: cold start Render): sebelum satu pun halaman
 * dirender, tunggu /api/health merespons 200 via polling tanpa batas
 * retry. Splash instan sudah tampil dari index.html; BootScreen mengambil
 * alih begitu Vue mount, lalu seluruh aplikasi baru dirender.
 */
export default {
  name: 'App',

  components: {
    BootScreen,
    ReconnectOverlay,
  },

  data() {
    return {
      serverReady: false,
    }
  },

  async mounted() {
    await pollUntilHealthy()
    this.serverReady = true
  },
}
</script>

<template>
  <BootScreen v-if="!serverReady" />
  <template v-else>
    <ReconnectOverlay />
    <RouterView />
  </template>
</template>

<style scoped></style>
