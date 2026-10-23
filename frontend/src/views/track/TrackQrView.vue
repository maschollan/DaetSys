<script>
import jsQR from 'jsqr'

import api from '@/services/api'
import useUiStore from '@/stores/ui'
import useAuthStore from '@/stores/auth'
import { formatDate } from '@/constants/status'

import StatusBadge from '@/components/StatusBadge.vue'
import PageHeader from '@/components/PageHeader.vue'

export default {
  name: 'TrackQrView',

  components: { StatusBadge, PageHeader },

  data() {
    return {
      tokenInput: '',
      asset: null,
      loading: false,
      notFound: false,
      scanning: false,
      cameraError: '',
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
    ui: () => useUiStore(),
  },

  beforeUnmount() {
    this.stopCamera()
  },

  methods: {
    formatDate,

    async resolve(tokenRaw) {
      const token = this.normalizeToken(tokenRaw)

      if (!token) {
        this.ui.error('Token QR kosong. Tempel token atau hasilkan QR dari detail aset.')
        return
      }

      this.loading = true
      this.notFound = false
      this.asset = null

      try {
        const { data } = await api.assets.byQrToken(token)
        this.asset = data.data
      } catch (exception) {
        if (exception.response?.status === 404) {
          this.notFound = true
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal melacak barang.'))
        }
      } finally {
        this.loading = false
      }
    },

    /**
     * Token QR memuat string panjang tidak mudah ditebak (qr_token). Selain
     * token penuh, izinkan pengguna menempel URL berbentuk
     * .../track?token=<qr_token>.
     */
    normalizeToken(raw) {
      const value = String(raw ?? '').trim()
      if (!value) return ''

      const match = value.match(/token=([\w-]+)/)
      return match ? match[1] : value
    },

    /* ---------- Pindai gambar ---------- */

    async scanImageFile(event) {
      const file = event.target.files?.[0]
      if (!file) return

      const input = event.target

      try {
        const bitmap = await createImageBitmap(file)
        // Batasi dimensi agar QR besar tetap terbaca dan hemat memori.
        const scale = Math.min(1, 1200 / Math.max(bitmap.width, bitmap.height))
        const width = Math.round(bitmap.width * scale)
        const height = Math.round(bitmap.height * scale)

        const canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        canvas.getContext('2d').drawImage(bitmap, 0, 0, width, height)

        const imageData = canvas.getContext('2d').getImageData(0, 0, width, height)
        // jsQR membutuhkan width & height secara eksplisit.
        const code = jsQR(imageData.data, width, height)

        if (code?.data) {
          this.tokenInput = code.data
          await this.resolve(code.data)
        } else {
          this.ui.error('QR code tidak terbaca dari gambar. Coba perbesar atau perjelas gambar.')
        }
      } catch {
        this.ui.error('Gambar tidak dapat dibaca.')
      } finally {
        // Reset agar memilih file yang sama tetap memicu pemindaian ulang.
        input.value = ''
      }
    },

    /* ---------- Pindai kamera ---------- */

    async startCamera() {
      this.cameraError = ''

      if (!navigator.mediaDevices?.getUserMedia) {
        this.cameraError = 'Kamera tidak didukung browser ini (butuh HTTPS atau localhost).'
        return
      }

      try {
        this.stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment' },
        })

        // Render <video> lebih dulu (v-if="scanning"), lalu tunggu Vue memasangnya.
        this.scanning = true
        await this.$nextTick()
        if (!this.$refs.videoRef) {
          throw new Error('Video preview belum siap.')
        }

        this.$refs.videoRef.srcObject = this.stream
        // autoplay muted playsinline adalah syarat kamera tampil di browser
        // modern (Chrome/Safari memblokir play() ber-kanvas suara).
        await this.$refs.videoRef.play()

        this.canvas = document.createElement('canvas')
        this.scanLoop = window.setInterval(() => this.scanFrame(), 350)
      } catch {
        this.stopCamera()
        this.cameraError = 'Tidak dapat menampilkan kamera. Periksa izin browser.'
      }
    },

    scanFrame() {
      if (!this.$refs.videoRef || !this.canvas) return

      const video = this.$refs.videoRef
      const width = video.videoWidth
      const height = video.videoHeight
      if (!width || !height) return

      this.canvas.width = width
      this.canvas.height = height

      const context = this.canvas.getContext('2d')
      context.drawImage(video, 0, 0, width, height)

      // jsQR membutuhkan width & height; tanpa keduanya QR tidak pernah terbaca.
      const code = jsQR(context.getImageData(0, 0, width, height).data, width, height)

      if (code?.data) {
        this.tokenInput = code.data
        this.stopCamera()
        this.resolve(code.data)
      }
    },

    stopCamera() {
      window.clearInterval(this.scanLoop)
      this.scanLoop = null
      this.stream?.getTracks().forEach((track) => track.stop())
      this.stream = null
      this.scanning = false
    },
  },
}
</script>

<template>
  <div>
    <PageHeader
      title="Lacak QR"
      description="Masukkan atau pindai QR code untuk memverifikasi identitas dan lokasi barang."
    />

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="ds-card p-4">
          <label class="form-label" for="track-token">Token QR</label>
          <div class="input-group mb-3">
            <span class="input-group-text"><i class="bi bi-qr-code"></i></span>
            <input
              id="track-token"
              v-model="tokenInput"
              type="text"
              class="form-control"
              placeholder="Tempel token atau URL QR..."
              @keyup.enter="resolve(tokenInput)"
            />
            <button
              type="button"
              class="btn btn-fill-primary"
              :disabled="loading"
              @click="resolve(tokenInput)"
            >
              <i class="bi bi-search me-1"></i> Lacak
            </button>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <button
              type="button"
              class="btn btn-outline-secondary btn-sm"
              @click="startCamera"
              v-if="!scanning"
            >
              <i class="bi bi-camera me-1"></i> Pindai Kamera
            </button>
            <button
              type="button"
              class="btn btn-outline-secondary btn-sm"
              v-else
              @click="stopCamera"
            >
              <i class="bi bi-stop-circle me-1"></i> Hentikan Kamera
            </button>
            <button
              type="button"
              class="btn btn-outline-secondary btn-sm"
              @click="$refs.fileInput?.click()"
            >
              <i class="bi bi-image me-1"></i> Unggah Gambar QR
            </button>
            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              class="d-none"
              @change="scanImageFile"
            />
          </div>

          <div v-if="cameraError" class="alert alert-warning mt-3 py-2">{{ cameraError }}</div>

          <div v-if="scanning" class="mt-3">
            <video ref="videoRef" class="ds-video d-block" autoplay playsinline muted></video>
            <p class="ds-muted-text small mb-0 mt-1">Arahkan kamera pada QR code.</p>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div v-if="loading" class="text-center py-5 ds-card p-4">
          <div class="spinner-border ds-loader" role="status" aria-hidden="true"></div>
          <p class="ds-muted-text mt-2 mb-0">Memverifikasi barang...</p>
        </div>

        <div v-else-if="notFound" class="alert alert-warning d-flex gap-2 py-3">
          <i class="bi bi-exclamation-triangle"></i>
          <div>
            QR tidak cocok dengan barang manapun. Pastikan token yang ditempel masih berlaku.
          </div>
        </div>

        <div v-else-if="asset" class="ds-card p-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <div class="ds-code">{{ asset.asset_code }}</div>
              <div class="h5 mb-0">{{ asset.name }}</div>
            </div>
            <StatusBadge variant="asset" :value="asset.status" />
          </div>

          <dl class="row g-2 mb-3 ds-dl">
            <div class="col-6">
              <dt>Kategori</dt>
              <dd>{{ asset.category?.name ?? '—' }}</dd>
            </div>
            <div class="col-6">
              <dt>Lokasi</dt>
              <dd>
                {{
                  asset.location
                    ? `${asset.location.code} — ${asset.location.name}`
                    : 'Belum ditempatkan'
                }}
              </dd>
            </div>
            <div class="col-6">
              <dt>Nomor Seri</dt>
              <dd>{{ asset.serial_number ?? '—' }}</dd>
            </div>
            <div class="col-6">
              <dt>Perolehan</dt>
              <dd>{{ formatDate(asset.acquisition_date) }}</dd>
            </div>
          </dl>

          <RouterLink
            :to="{ name: 'assets.detail', params: { qrToken: asset.qr_token } }"
            class="btn btn-outline-secondary btn-sm"
          >
            <i class="bi bi-box-arrow-up-right me-1"></i> Detail &amp; Riwayat Lengkap
          </RouterLink>
        </div>

        <div v-else class="ds-card p-4 text-center ds-muted-text">
          <i class="bi bi-qr-code h1 d-block mb-2"></i>
          Hasil lacak akan tampil di sini.
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ds-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
}

.ds-video {
  width: 100%;
  max-height: 320px;
  background: var(--ds-bg);
  border: 1px solid var(--ds-border);
}

.ds-code {
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--ds-primary);
}

.ds-dl dt {
  color: var(--ds-muted);
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.ds-loader {
  color: var(--ds-primary);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
