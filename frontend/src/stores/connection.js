import { ref } from 'vue'
import { defineStore } from 'pinia'

import { pollUntilHealthy } from '@/services/healthService'

export const useConnectionStore = defineStore('connection', () => {
  /** true = server sedang tidak terjangkau; overlay Reconnecting aktif. */
  const offline = ref(false)

  /** Nomor percobaan probe saat ini (kanal ke animasi ConnectionVisual). */
  const attempts = ref(0)

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
   * dikirim ulang otomatis demi keamanan idempotency. Tiap percobaan
   * diteruskan ke `attempts` agar animasi menampilkan retry nyata.
   */
  function startRecovery() {
    if (recovering) {
      return
    }

    recovering = true
    attempts.value = 0
    pollUntilHealthy({
      onAttempt(count) {
        attempts.value = count
      },
    }).then(() => {
      recovering = false
      offline.value = false
    })
  }

  return { offline, attempts, goOffline }
})

export default useConnectionStore
