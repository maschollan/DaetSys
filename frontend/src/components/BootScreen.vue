<script>
import ConnectionVisual from '@/components/connection/ConnectionVisual.vue'

/*
 * Halaman loading preload: ditampilkan App.vue sampai /api/health
 * merespons 200 (server Render selesai cold start). Animasinya dari
 * ConnectionVisual (varian pilihan dev/production); container .boot-screen
 * memakai kelas global di styles/index.css agar visualnya identik dengan
 * splash statis yang di-inline di index.html.
 */
export default {
  name: 'BootScreen',

  components: {
    ConnectionVisual,
  },

  props: {
    /** Nomor percobaan health probe yang sedang berjalan. */
    attempts: { type: Number, default: 0 },
  },
}
</script>

<template>
  <div class="boot-screen" role="status" aria-live="polite">
    <span class="boot-brand">>_</span>
    <span class="boot-title">DAETSYS</span>
    <ConnectionVisual label="Connecting to server" :attempts="attempts" />
  </div>
</template>
