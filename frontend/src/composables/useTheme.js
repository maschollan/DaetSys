/**
 * Sinkronisasi tema light/dark melalui `data-bs-theme` pada <html>.
 * Implementasi Options API: modul murni tanpa reactivity Vue — komponen
 * (mis. MainLayout) menyimpan `theme` di data sendiri dan memanggil helper
 * dari modul ini.
 *
 * Attribut di-set lebih awal oleh script inline di index.html agar
 * tidak terjadi kedipan tema yang salah saat halaman dimuat.
 */
const THEME_KEY = 'daetsys.theme'
const DARK_MODE_QUERY = '(prefers-color-scheme: dark)'
const VALID_THEMES = ['light', 'dark']

function resolveInitialTheme() {
  try {
    const stored = window.localStorage.getItem(THEME_KEY)
    if (VALID_THEMES.includes(stored)) {
      return stored
    }
  } catch {
    // localStorage bisa tidak tersedia (mis. mode privat) — abaikan.
  }

  return window.matchMedia?.(DARK_MODE_QUERY).matches ? 'dark' : 'light'
}

let currentThemeValue = resolveInitialTheme()

/** Tempel tema saat ini ke <html> (dipanggil dari main.js sebelum mount). */
export function initTheme() {
  document.documentElement.setAttribute('data-bs-theme', currentThemeValue)
  return currentThemeValue
}

/** Nilai tema aktif untuk diinisialisasi ke data() komponen. */
export function currentTheme() {
  return currentThemeValue
}

export function setTheme(next) {
  if (!VALID_THEMES.includes(next)) {
    return currentThemeValue
  }

  currentThemeValue = next
  document.documentElement.setAttribute('data-bs-theme', next)

  try {
    window.localStorage.setItem(THEME_KEY, next)
  } catch {
    // Penyimpanan tidak tersedia — tema tetap berlaku untuk sesi ini.
  }

  return currentThemeValue
}

export function toggleTheme() {
  return setTheme(currentThemeValue === 'dark' ? 'light' : 'dark')
}
