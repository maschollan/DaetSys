import { ref } from 'vue'
import { defineStore } from 'pinia'

import { pollUntilHealthy } from '@/services/healthService'

export const useConnectionStore = defineStore('connection', () => {
  /** true = server sedang tidak terjangkau; overlay Reconnecting aktif. */
  const offline = ref(false)

  let recovering = false

  /**
   * Dipicu interceptor axios (services/http.js) ketika request gagal
   * karena fault jaringan/server. Beberapa request yang gagal bersamaan
   * hanya memicu satu loop recovery (idempotent).
   */
  function goOffline() {
    offline.value = true
    startRecovery()
  }

  /**
   * Loop recovery: polling /health sampai 200, lalu tutup overlay dan
   * izinkan pengguna mengulang aksi yang gagal. Tidak ada mutasi yang
   * dikirim ulang otomatis demi keamanan idempotency.
   */
  function startRecovery() {
    if (recovering) {
      return
    }

    recovering = true
    pollUntilHealthy().then(() => {
      recovering = false
      offline.value = false
    })
  }

  return { offline, goOffline }
})

export default useConnectionStore
