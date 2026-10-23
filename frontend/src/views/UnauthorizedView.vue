<script>
export default {}
</script>

<template>
  <div class="container py-5">
    <div class="ds-card p-4 mx-auto text-center" style="max-width: 480px">
      <div class="ds-code mb-2">401</div>
      <h1 class="h5">Sesi tidak berlaku</h1>
      <p class="ds-muted-text mb-3">
        Anda belum terverifikasi, token kedaluwarsa, akun dinonaktifkan, atau URL tidak dikenali.
        Masuk kembali untuk melanjutkan.
      </p>
      <RouterLink :to="{ name: 'login' }" class="btn btn-fill-primary">Buka Login</RouterLink>
    </div>
  </div>
</template>

<style scoped>
.ds-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
}

.ds-code {
  font-size: 2.2rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  color: var(--ds-primary);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
