import axios from 'axios'

import { API_BASE_URL } from '@/constants/api'

/** Jeda antar percobaan health probe (ms). */
const RETRY_INTERVAL_MS = 3000

/** Timeout satu probe — longgar untuk jaringan lambat tanpa memanjangkan siklus polling. */
const PROBE_TIMEOUT_MS = 10000

const delay = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms))

/**
 * Satu probe GET {API_BASE_URL}/health. `validateStatus` dinonaktifkan
 * agar respons non-200 (502/503 Render, dsb.) tidak melempar exception
 * dan cukup diperiksa statusnya. Endpoint public, tanpa token.
 */
export async function probeHealth() {
  try {
    const response = await axios.get(`${API_BASE_URL}/health`, {
      timeout: PROBE_TIMEOUT_MS,
      validateStatus: () => true,
      headers: { Accept: 'application/json' },
    })
    return response.status === 200
  } catch {
    // Jaringan murni gagal (DNS/refused/timeout axios) dianggap belum siap.
    return false
  }
}

/**
 * Polling health hingga server merespons 200. Retry bersifat otomatis
 * tanpa batas percobaan: free tier Render bisa mengalami cold start jauh
 * lebih lama dari satu dua kali coba, jadi pemanggil (gate preload di
 * App.vue atau store connection) cukup menunggu Promise ini selesai.
 */
export async function pollUntilHealthy({ intervalMs = RETRY_INTERVAL_MS } = {}) {
  for (;;) {
    if (await probeHealth()) {
      return true
    }
    await delay(intervalMs)
  }
}

export default { probeHealth, pollUntilHealthy }
