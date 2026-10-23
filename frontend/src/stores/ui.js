import { ref } from 'vue'
import { defineStore } from 'pinia'

let toastId = 0

export const useUiStore = defineStore('ui', () => {
  const toasts = ref([])

  /**
   * Tampilkan toast notifikasi. `variant`: success | danger | warning | info.
   * Pesan kesalahan dari backend dipetakan agar pengguna selalu diberi umpan balik.
   */
  function notify(message, variant = 'success', duration = 5000) {
    const id = ++toastId
    toasts.value.push({ id, message, variant })
    window.setTimeout(() => dismiss(id), duration)
    return id
  }

  function success(message) {
    return notify(message, 'success')
  }

  function error(message) {
    return notify(message, 'danger', 7000)
  }

  function dismiss(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  /**
   * Ekstrak pesan kesalahan yang konsisten dari respons axios/backend.
   */
  function apiError(thrown, fallback = 'Terjadi kesalahan. Coba lagi.') {
    const response = thrown?.response

    if (response?.status === 422 && response.data?.errors) {
      // Gabungkan seluruh pesan per field menjadi satu teks terbaca,
      // bukan hanya pesan pada field pertama.
      return Object.entries(response.data.errors)
        .map(([field, messages]) => {
          const label = field.replace(/_/g, ' ')
          const text = Array.isArray(messages) ? messages.join(' ') : String(messages)
          return `${label}: ${text}`
        })
        .join(' · ')
    }

    return response?.data?.message ?? fallback
  }

  return { toasts, notify, success, error, dismiss, apiError }
})

export default useUiStore
