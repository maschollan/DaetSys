import axios from 'axios'

export const TOKEN_STORAGE_KEY = 'daetsys.token'
export const TOKEN_EXPIRY_STORAGE_KEY = 'daetsys.token_expires_at'

/** Masa berlaku sesi di sisi klien: 6 jam sejak token disimpan. */
export const TOKEN_TTL_MS = 6 * 60 * 60 * 1000

/*
 * Token disimpan di localStorage agar sesi bertahan lintas tab baru
 * (tab yang sama tetap login), tetapi dibatasi kedaluwarsa 6 jam untuk
 * menekan masa hidup token di disk. Pertimbangan XSS tetap
 * didokumentasikan pada PLAN.md §6 — halaman tidak memuat skrip pihak
 * ketiga, dan otorisasi penuh tetap di backend.
 */

function writeExpiresAt(expiresAt) {
  window.localStorage.setItem(TOKEN_EXPIRY_STORAGE_KEY, String(expiresAt))
}

export function readToken() {
  let token = null
  let expiresAt = null

  try {
    token = window.localStorage.getItem(TOKEN_STORAGE_KEY)
    expiresAt = Number(window.localStorage.getItem(TOKEN_EXPIRY_STORAGE_KEY))
  } catch {
    return null
  }

  if (!token) {
    return null
  }

  // Tanpa rekam waktu kedaluwarsa (mis. token lama) atau sudah lewat —
  // sesi dianggap tidak sah dan dibersihkan.
  if (!Number.isFinite(expiresAt) || Date.now() >= expiresAt) {
    writeToken(null)
    return null
  }

  return token
}

export function writeToken(token) {
  try {
    if (token) {
      window.localStorage.setItem(TOKEN_STORAGE_KEY, token)
      writeExpiresAt(Date.now() + TOKEN_TTL_MS)
    } else {
      window.localStorage.removeItem(TOKEN_STORAGE_KEY)
      window.localStorage.removeItem(TOKEN_EXPIRY_STORAGE_KEY)
    }
  } catch {
    // Penyimpanan tidak tersedia — token hanya hidup di memori halaman.
  }
}

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  timeout: 30000,
  headers: {
    Accept: 'application/json',
  },
})

http.interceptors.request.use((config) => {
  const token = readToken()

  if (token && !config.headers.Authorization) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const requestUrl = error.config?.url ?? ''

    // 401 pada endpoint selain /login berarti token tidak sah atau
    // sudah kedaluwarsa: bersihkan sisi klien dan kembalikan pengguna
    // ke halaman login.
    if (status === 401 && requestUrl !== '/login') {
      writeToken(null)
    }

    return Promise.reject(error)
  },
)

export default http
