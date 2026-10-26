<script>
import {
  CONNECTION_VARIANTS,
  cycleConnectionVariant,
  useConnectionVariant,
} from '@/composables/useConnectionVariant'
import { RETRY_INTERVAL_MS } from '@/services/healthService'

/*
 * Visual animasi koneksi — dipakai bersama oleh BootScreen (preload)
 * dan ReconnectOverlay (mid-session) agar bahasa visualnya konsisten.
 * Lima varian tersedia; saat dev, tombol kecil di pojok kanan-bawah
 * mensiklusnya dan menyimpan pilihan; production memakai pilihan utama
 * dari VITE_BOOT_ANIMATION (lihat composables/useConnectionVariant.js).
 */
export default {
  name: 'ConnectionVisual',

  props: {
    /** Teks status di atas animasi, mis. "Connecting to server". */
    label: { type: String, default: 'Connecting to server' },
    /** Nomor percobaan nyata dari health polling; 0 = belum ada data. */
    attempts: { type: Number, default: 0 },
    showSwitcher: { type: Boolean, default: true },
  },

  data() {
    return {
      /** Ref bersama lintas BootScreen & ReconnectOverlay. */
      variant: useConnectionVariant(),
      dev: import.meta.env.DEV,
      second: 0,
    }
  },

  computed: {
    /** Posisi varian saat ini untuk label tombol switch. */
    switchTitle() {
      return `Ganti animasi (${CONNECTION_VARIANTS.indexOf(this.variant) + 1}/${CONNECTION_VARIANTS.length})`
    },
    /** Hitung mundur ke retry berikutnya; tidak pernah negatif. */
    timer() {
      return Math.max(0, RETRY_INTERVAL_MS / 1000 - this.second)
    },
    /** Detik berjalan sejak percobaan terakhir; dibatasi ke interval. */
    elapsed() {
      return Math.min(this.second, RETRY_INTERVAL_MS / 1000)
    },
  },

  watch: {
    attempts() {
      this.second = 0
    },
  },

  mounted() {
    this.tick = setInterval(() => {
      this.second += 1
    }, 1000)
  },

  beforeUnmount() {
    // BootScreen unmount saat server siap; hentikan timer agar tidak
    // menyisakan interval yatim yang terus men-tick.
    clearInterval(this.tick)
  },

  methods: {
    cycle: cycleConnectionVariant,
  },
}
</script>

<template>
  <div class="cv">
    <p class="cv-label">
      {{ label }}<span class="ds-dots"><span>.</span><span>.</span><span>.</span></span>
    </p>

    <!-- terminal: log gaya CLI, menampilkan percobaan nyata (branding >_ ) -->
    <div v-if="variant === 'terminal'" class="cv-terminal">
      <p class="cv-term-line">> GET /api/health <span class="cv-term-err">FAIL</span></p>
      <p :key="attempts" class="cv-term-line cv-term-retry">
        > percobaan #{{ attempts || 1 }}
        <template v-if="second > 1">· retry in {{ timer }}s</template
        ><span class="cv-caret">▊</span>
      </p>
    </div>

    <!-- radar: lingkaran ping memancar (efek scanning sinyal) -->
    <div v-else-if="variant === 'radar'" class="cv-radar" aria-hidden="true">
      <span class="cv-ping"></span>
      <span class="cv-ping p2"></span>
      <span class="cv-ping p3"></span>
      <span class="cv-core"></span>
    </div>

    <!-- scan: bar pemindai dengan garis shimmer -->
    <div v-else-if="variant === 'scan'" class="cv-scan" aria-hidden="true">
      <span class="cv-scan-track"><span class="cv-scan-line"></span></span>
    </div>

    <!-- orbit: titik-titik mengorbit pusat -->
    <div v-else-if="variant === 'orbit'" class="cv-orbit" aria-hidden="true">
      <span class="cv-orbit-ring"></span>
      <span class="cv-orbit-core"></span>
      <span class="cv-orbit-arm a1"><span class="cv-orbit-dot"></span></span>
      <span class="cv-orbit-arm a2"><span class="cv-orbit-dot"></span></span>
      <span class="cv-orbit-arm a3"><span class="cv-orbit-dot"></span></span>
    </div>

    <!-- equalizer: bar gelombang naik-turun -->
    <div v-else class="cv-eq" aria-hidden="true">
      <span v-for="n in 5" :key="n" class="cv-eq-bar"></span>
    </div>

    <p v-if="attempts > 0 && variant !== 'terminal'" class="cv-meta">
      percobaan #{{ attempts }} <template v-if="second > 1">· retry in {{ timer }}s</template>
    </p>

    <button
      v-if="dev && showSwitcher"
      class="cv-switch"
      type="button"
      :title="switchTitle"
      @click="cycle"
    >
      fx: {{ variant }} ⌄
    </button>
  </div>
</template>

<style scoped>
.cv {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.9rem;
  min-height: 110px;
}

.cv-label {
  margin: 0;
  color: var(--ds-muted);
  font-size: 0.85rem;
  letter-spacing: 0.04em;
}

.cv-meta {
  margin: 0;
  color: var(--ds-muted);
  font-size: 0.75rem;
  letter-spacing: 0.05em;
  opacity: 0.85;
}

/* ---------- varian: terminal ---------- */

.cv-terminal {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 280px;
  text-align: left;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 0.78rem;
}

.cv-term-line {
  margin: 0;
  color: var(--ds-muted);
  white-space: nowrap;
  overflow: hidden;
}

.cv-term-err {
  color: var(--ds-danger);
  font-weight: 700;
}

/* Baris retry diketik ulang tiap attempt (:key memicu remount). */
.cv-term-retry {
  color: var(--ds-text);
  max-width: 0;
  animation: cv-typing 0.8s steps(24) forwards;
}

.cv-caret {
  color: var(--ds-primary);
  animation: cv-caret 1s steps(2, start) infinite;
}

@keyframes cv-typing {
  from {
    max-width: 0;
  }
  to {
    max-width: 320px; /* lebih besar dari teks terpanjang; sisa tersembunyi */
  }
}

@keyframes cv-caret {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0;
  }
}

/* ---------- varian: radar ---------- */

.cv-radar {
  position: relative;
  width: 110px;
  height: 110px;
}

.cv-ping {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid var(--ds-primary);
  opacity: 0;
  transform: scale(0.25);
  animation: cv-radar 2.4s linear infinite;
}

.cv-ping.p2 {
  animation-delay: 0.8s;
}

.cv-ping.p3 {
  animation-delay: 1.6s;
}

.cv-core {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 10px;
  height: 10px;
  margin: -5px 0 0 -5px;
  border-radius: 50%;
  background: var(--ds-primary);
  animation: cv-core-pulse 2.4s ease-in-out infinite;
}

@keyframes cv-radar {
  0% {
    transform: scale(0.25);
    opacity: 0.9;
  }
  100% {
    transform: scale(1.2);
    opacity: 0;
  }
}

@keyframes cv-core-pulse {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.6);
  }
}

/* ---------- varian: scan ---------- */

.cv-scan {
  display: flex;
  align-items: center;
  justify-content: center;
}

.cv-scan-track {
  position: relative;
  display: block;
  width: 240px;
  height: 6px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--ds-muted) 25%, transparent);
  overflow: hidden;
}

.cv-scan-line {
  position: absolute;
  top: 0;
  left: 0;
  width: 64px;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent, var(--ds-primary));
  animation: cv-scan 1.4s ease-in-out infinite alternate;
}

@keyframes cv-scan {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(176px);
  }
}

/* ---------- varian: orbit ---------- */

.cv-orbit {
  position: relative;
  width: 110px;
  height: 110px;
  display: grid;
  place-items: center;
}

.cv-orbit-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 1px dashed color-mix(in srgb, var(--ds-muted) 45%, transparent);
}

.cv-orbit-core {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 8px;
  height: 8px;
  margin: -4px 0 0 -4px;
  border-radius: 50%;
  background: var(--ds-primary);
}

.cv-orbit-arm {
  position: absolute;
  inset: 0;
  animation: cv-orbit 2.2s linear infinite;
}

.cv-orbit-arm.a2 {
  animation-delay: -0.73s;
}

.cv-orbit-arm.a3 {
  animation-delay: -1.47s;
}

.cv-orbit-dot {
  position: absolute;
  top: -4px;
  left: 50%;
  width: 7px;
  height: 7px;
  margin-left: -3.5px;
  border-radius: 50%;
  background: var(--ds-primary);
}

@keyframes cv-orbit {
  to {
    transform: rotate(360deg);
  }
}

/* ---------- varian: equalizer ---------- */

.cv-eq {
  display: flex;
  align-items: flex-end;
  gap: 5px;
  height: 34px;
}

.cv-eq-bar {
  width: 5px;
  height: 100%;
  border-radius: 2px;
  background: var(--ds-primary);
  transform: scaleY(0.3);
  transform-origin: center;
  animation: cv-eq 1.1s ease-in-out infinite alternate;
}

.cv-eq-bar:nth-child(2) {
  animation-delay: -0.55s;
}
.cv-eq-bar:nth-child(3) {
  animation-delay: -0.2s;
}
.cv-eq-bar:nth-child(4) {
  animation-delay: -0.85s;
}
.cv-eq-bar:nth-child(5) {
  animation-delay: -0.35s;
}

@keyframes cv-eq {
  from {
    transform: scaleY(0.25);
  }
  to {
    transform: scaleY(1);
  }
}

/* ---------- tombol switch (dev saja) ---------- */

.cv-switch {
  position: fixed;
  right: 0.75rem;
  bottom: 0.75rem;
  z-index: 2500;
  padding: 0.3rem 0.65rem;
  font-size: 0.7rem;
  letter-spacing: 0.05em;
  color: var(--ds-muted);
  background: var(--ds-bg);
  border: 1px solid color-mix(in srgb, var(--ds-muted) 40%, transparent);
  border-radius: 999px;
  opacity: 0.55;
  cursor: pointer;
  transition:
    opacity 0.15s ease,
    color 0.15s ease;
}

.cv-switch:hover {
  opacity: 1;
  color: var(--ds-primary);
}
</style>
