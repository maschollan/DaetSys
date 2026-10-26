<script>
/*
 * Halaman loading preload: ditampilkan App.vue sampai /api/health
 * merespons 200 (server Render selesai cold start). Style memakai kelas
 * global .boot-* di styles/index.css agar visualnya identik dengan
 * splash statis yang di-inline di index.html.
 */
export default {
  name: 'BootScreen',
}
</script>

<template>
  <div class="boot-screen" role="status" aria-live="polite">
    <span class="boot-brand">&gt;_</span>
    <span class="boot-title">DAETSYS</span>
    <p class="boot-status">
      Connecting to server<span class="ds-dots"><span>.</span><span>.</span><span>.</span></span>
    </p>
  </div>
</template>
