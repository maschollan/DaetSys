import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import authService from '@/services/authService'
import { readToken, writeToken } from '@/services/http'

const USER_STORAGE_KEY = 'daetsys.user'

function readStoredUser() {
  try {
    const raw = window.localStorage.getItem(USER_STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function writeStoredUser(user) {
  try {
    if (user) {
      window.localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user))
    } else {
      window.localStorage.removeItem(USER_STORAGE_KEY)
    }
  } catch {
    // Penyimpanan tidak tersedia — user hanya hidup di memori halaman.
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref(readToken())
  // Cache user hanya dipercaya bila token masih sah (menjelang / sebelum
  // 6 jam); tanpa token, cache ikut dibersihkan.
  const user = ref(token.value ? readStoredUser() : null)

  if (!token.value) {
    writeStoredUser(null)
  }
  const isBootstrapping = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value && user.value))

  const userName = computed(() => user.value?.name ?? '')
  const userEmail = computed(() => user.value?.email ?? '')
  const role = computed(() => user.value?.role_name ?? user.value?.role?.name ?? null)

  const isAdmin = computed(() => role.value === 'admin')
  const isOfficer = computed(() => role.value === 'officer')
  const isAuditor = computed(() => role.value === 'auditor')

  /**
   * Apakah pengguna memiliki salah satu dari role yang diberikan.
   * Implementasi otorisasi tetap ada di backend; helper ini hanya untuk
   * menampilkan/menu UI yang relevan (PLAN.md §7).
   */
  function hasRole(...names) {
    return role.value ? names.includes(role.value) : false
  }

  function persist() {
    writeToken(token.value)
    writeStoredUser(user.value)
  }

  function clearSession() {
    token.value = null
    user.value = null
    writeToken(null)
    writeStoredUser(null)
  }

  /**
   * POST /api/login — simpan token dan data profil dari respons.
   */
  async function login({ email, password }) {
    const { data } = await authService.login({ email, password })

    token.value = data.data.token
    user.value = data.data.user
    persist()

    return user.value
  }

  /**
   * POST /api/logout — cabut token di backend lalu bersihkan sisi klien.
   * Kegagalan jaringan saat logout tetap menuntut pembersihan state lokal.
   */
  async function logout(callServer = true) {
    if (callServer && token.value) {
      try {
        await authService.logout()
      } catch {
        // Tetap bersihkan sisi klien walau permintaan gagal.
      }
    }

    clearSession()
  }

  /**
   * PUT /api/profile — perbarui profil sendiri (nama) lalu segarkan
   * snapshot user yang dicache agar seluruh UI langsung ikut berubah.
   */
  async function updateProfile(payload) {
    const { data } = await authService.updateProfile(payload)

    user.value = data.data
    persist()

    return user.value
  }

  /**
   * POST /api/change-password — ganti password sendiri (backend mencabut
   * seluruh token; pemanggil wajib mengarahkan pengguna ke halaman login).
   */
  async function updatePassword(payload) {
    return authService.changePassword(payload)
  }

  /**
   * GET /api/me — pulihkan profil saat token tersedia tetapi cache user
   * hilang (mis. halaman dimuat ulang di tengah sesi). Sesi dibereskan
   * ketika backend menyatakan token sudah tidak sah.
   */ async function bootstrap() {
    if (!token.value || isBootstrapping.value) {
      return
    }

    isBootstrapping.value = true

    try {
      const { data } = await authService.me()
      user.value = data.data
      persist()
    } catch {
      clearSession()
    } finally {
      isBootstrapping.value = false
    }
  }

  return {
    token,
    user,
    isBootstrapping,
    isAuthenticated,
    userName,
    userEmail,
    role,
    isAdmin,
    isOfficer,
    isAuditor,
    hasRole,
    login,
    logout,
    updateProfile,
    updatePassword,
    bootstrap,
    clearSession,
  }
})

export default useAuthStore
