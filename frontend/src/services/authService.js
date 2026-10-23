import http from './http'

/**
 * Wrapper endpoint autentikasi backend (PLAN.md §8).
 * Semua respons memakai format `{ message?, data? }` dari backend.
 */
export const authService = {
  login({ email, password }) {
    return http.post('/login', { email, password })
  },

  logout() {
    return http.post('/logout')
  },

  me() {
    return http.get('/me')
  },

  updateProfile(payload) {
    return http.put('/profile', payload)
  },

  changePassword({ current_password, new_password, new_password_confirmation }) {
    return http.post('/change-password', {
      current_password,
      new_password,
      new_password_confirmation,
    })
  },
}

export default authService
