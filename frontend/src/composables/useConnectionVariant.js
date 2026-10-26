import { ref } from 'vue'

/*
 * State varian animasi koneksi (ConnectionVisual — dipakai BootScreen &
 * ReconnectOverlay bersama). module-level ref supaya kedua layar selalu
 * memakai varian yang sama tanpa sinkronisasi tambahan.
 */

const STORAGE_KEY = 'daetsys.bootAnim'

/** Daftar varian yang tersedia; urutan = urutan siklus tombol switch. */
export const CONNECTION_VARIANTS = ['terminal', 'radar', 'scan', 'orbit', 'equalizer']

function safeGet() {
  try {
    return window.localStorage.getItem(STORAGE_KEY)
  } catch {
    return null // storage bisa terblokir (private mode/iframe)
  }
}

function initialVariant() {
  const stored = safeGet()
  const fromEnv = import.meta.env.VITE_BOOT_ANIMATION

  if (import.meta.env.DEV) {
    // Dev: pilihan pengguna via tombol switch menang; env hanya fallback.
    if (CONNECTION_VARIANTS.includes(stored)) {
      return stored
    }
  } else if (CONNECTION_VARIANTS.includes(fromEnv)) {
    // Production: pilihan utama dari .env (VITE_BOOT_ANIMATION).
    return fromEnv
  }

  if (CONNECTION_VARIANTS.includes(fromEnv)) {
    return fromEnv
  }
  if (CONNECTION_VARIANTS.includes(stored)) {
    return stored
  }
  return 'terminal'
}

/** Ref bersama — didefinisikan sekali per modul, di-share semua pemakai. */
const variant = ref(initialVariant())

/**
 * Ganti ke varian berikutnya (siklus 1→5) dan ingat pilihan itu untuk
 * kunjungan dev berikutnya. Tidak berpengaruh di production karena
 * tombol switch hanya dirender saat DEV.
 */
function cycleConnectionVariant() {
  const index = CONNECTION_VARIANTS.indexOf(variant.value)
  const next = CONNECTION_VARIANTS[(index + 1) % CONNECTION_VARIANTS.length]
  variant.value = next
  try {
    window.localStorage.setItem(STORAGE_KEY, next)
  } catch {
    // Storage terblokir: pilihan tetap berlaku sampai halaman ditutup.
  }
}

/**
 * Manager varian animasi koneksi. Default diambil dari, berurutan:
 * localStorage (dev switch) → VITE_BOOT_ANIMATION → 'terminal'.
 */
export function useConnectionVariant() {
  return variant
}

export { cycleConnectionVariant }
