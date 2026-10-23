<script>
import QRCode from 'qrcode'

import api from '@/services/api'
import useUiStore from '@/stores/ui'
import useAuthStore from '@/stores/auth'
import { formatDate, formatDateTime } from '@/constants/status'

import AuditChangeList from '@/components/AuditChangeList.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import DataTable from '@/components/DataTable.vue'

export default {
  name: 'AssetDetailView',

  components: { AuditChangeList, PageHeader, PaginationBar, StatusBadge, DataTable },

  data() {
    return {
      asset: null,
      loading: false,
      error: '',
      qrDataUrl: '',
      copied: false,
      history: [],
      historyMeta: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
      historyLoading: false,
      historyError: '',
      historyColumns: [
        { key: 'created_at', label: 'Waktu', sortable: false },
        { key: 'action', label: 'Tindakan' },
        { key: 'user', label: 'Pengguna' },
        { key: 'changes', label: 'Perubahan' },
      ],
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    ui: () => useUiStore(),
    auth: () => useAuthStore(),

    createdLabel() {
      return formatDateTime(this.asset?.created_at)
    },
  },

  mounted() {
    // Riwayat membutuhkan id numerik yang baru diketahui setelah aset diresolusi
    // dari qr_token; muat secara berurutan.
    void (async () => {
      await this.loadAsset()
      this.generateQr()
      this.loadHistory()
    })()
  },

  // Navigasi aset/A → aset/B memakai komponen yang sama; muat ulang saat token berubah.
  watch: {
    '$route.params.qrToken': {
      async handler(token) {
        if (token && token !== this.asset?.qr_token) {
          await this.loadAsset()
          this.generateQr()
          this.loadHistory()
        }
      },
    },
  },

  methods: {
    formatDate,
    formatDateTime,

    async loadAsset() {
      this.loading = true
      this.error = ''

      try {
        // URL detail memakai qr_token agar tidak membocorkan urutan id
        // dan standar untuk tautan hasil pemindaian QR.
        const { data } = await api.assets.byQrToken(this.$route.params.qrToken)
        this.asset = data.data
      } catch (exception) {
        if (exception.response?.status === 404) {
          this.error = 'Aset tidak ditemukan. Periksa kembali token QR.'
        } else {
          this.error = this.ui.apiError(exception, 'Gagal memuat detail aset.')
        }
      } finally {
        this.loading = false
      }
    },

    async generateQr() {
      if (!this.asset?.qr_token) return
      try {
        this.qrDataUrl = await QRCode.toDataURL(this.asset.qr_token, {
          margin: 1,
          width: 220,
          errorCorrectionLevel: 'M',
        })
      } catch {
        this.qrDataUrl = ''
      }
    },

    async downloadQr() {
      if (!this.asset?.qr_token) return

      try {
        const dataUrl = await QRCode.toDataURL(this.asset.qr_token, {
          margin: 2,
          width: 512,
          errorCorrectionLevel: 'M',
        })
        const link = document.createElement('a')
        link.href = dataUrl
        link.download = `qr-${this.asset.asset_code}.png`
        link.click()
      } catch {
        this.ui.error('QR tidak dapat dibuat.')
      }
    },

    async loadHistory(page = 1) {
      if (!this.asset?.id) return
      this.historyLoading = true
      this.historyError = ''

      try {
        const { data } = await api.assets.history(this.asset.id, {
          page,
          per_page: this.historyMeta.per_page,
        })
        this.history = data.data ?? []
        Object.assign(this.historyMeta, data.meta ?? {})
      } catch (exception) {
        this.historyError = this.ui.apiError(exception, 'Gagal memuat riwayat aset.')
      } finally {
        this.historyLoading = false
      }
    },

    async copyToken() {
      try {
        await navigator.clipboard.writeText(this.asset.qr_token)
        this.copied = true
        window.setTimeout(() => (this.copied = false), 1500)
      } catch {
        this.ui.error('Clipboard tidak tersedia pada browser ini.')
      }
    },
  },
}
</script>

<template>
  <div>
    <PageHeader title="Detail Aset" description="Identitas barang, status, QR code, dan riwayat.">
      <template #actions>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'assets' }">
          <i class="bi bi-arrow-left me-1"></i> Kembali
        </RouterLink>
        <RouterLink
          v-if="asset && auth.hasRole('admin', 'officer')"
          class="btn btn-outline-secondary btn-sm"
          :to="{ name: 'transfers' }"
        >
          <i class="bi bi-arrow-left-right me-1"></i> Buat Pengajuan Transfer
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border ds-loader" role="status" aria-hidden="true"></div>
    </div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-else-if="asset" class="row g-3">
      <div class="col-lg-8">
        <div class="ds-card p-4 h-100">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
              <div class="ds-code">{{ asset.asset_code }}</div>
              <h2 class="h5 mt-1 mb-0">{{ asset.name }}</h2>
            </div>
            <StatusBadge variant="asset" :value="asset.status" />
          </div>

          <dl class="row row-cols-1 row-cols-md-2 g-2 mb-0 ds-dl">
            <div class="col">
              <dt>Kategori</dt>
              <dd>{{ asset.category?.name ?? '—' }}</dd>
            </div>
            <div class="col">
              <dt>Lokasi</dt>
              <dd>
                {{
                  asset.location
                    ? `${asset.location.code} — ${asset.location.name}`
                    : 'Belum ditempatkan'
                }}
              </dd>
            </div>
            <div class="col">
              <dt>Nomor Seri</dt>
              <dd>{{ asset.serial_number ?? '—' }}</dd>
            </div>
            <div class="col">
              <dt>Tanggal Perolehan</dt>
              <dd>{{ formatDate(asset.acquisition_date) }}</dd>
            </div>
            <div class="col-12">
              <dt>Deskripsi</dt>
              <dd>{{ asset.description ?? '—' }}</dd>
            </div>
            <div class="col">
              <dt>Tercatat</dt>
              <dd>{{ createdLabel }}</dd>
            </div>
            <div class="col">
              <dt>Diperbarui</dt>
              <dd>{{ formatDateTime(asset.updated_at) }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="ds-card p-4 text-center h-100">
          <h3 class="h6 mb-3"><i class="bi bi-qr-code me-2"></i>QR Code Barang</h3>
          <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code aset" class="img-fluid ds-qr" />
          <div v-else class="ds-muted-text py-3">QR tidak dapat dibuat.</div>
          <div class="mt-2 d-flex flex-wrap align-items-center justify-content-center gap-2">
            <code class="ds-token">{{ asset.qr_token }}</code>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="copyToken">
              <i :class="copied ? 'bi bi-check2' : 'bi bi-clipboard'"></i>
              <span class="ms-1">{{ copied ? 'Tersalin' : 'Salin' }}</span>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="downloadQr">
              <i class="bi bi-download"></i><span class="ms-1">Unduh QR</span>
            </button>
          </div>
          <p class="ds-muted-text mt-3 mb-0">
            Pindai QR di halaman Lacak untuk memanggil API terlindungi; QR bukan alat persetujuan
            dan bukan pengganti autentikasi.
          </p>
        </div>
      </div>

      <div class="col-12">
        <h3 class="h6 mb-2">Riwayat Perpindahan &amp; Perubahan</h3>
        <DataTable
          :columns="historyColumns"
          :rows="history"
          :loading="historyLoading"
          :error="historyError"
          empty-message="Belum ada aktivitas terekam untuk aset ini."
          @retry="loadHistory()"
        >
          <template #cell-created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #cell-user="{ row }">{{ row.user?.name ?? 'Sistem' }}</template>
          <template #cell-changes="{ row }">
            <AuditChangeList :old-values="row.old_values" :new-values="row.new_values" />
          </template>
        </DataTable>

        <PaginationBar
          :meta="historyMeta"
          :disabled="historyLoading"
          @page-change="loadHistory"
          @per-page-change="
            (perPage) => {
              historyMeta.per_page = perPage
              loadHistory(1)
            }
          "
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.ds-card {
  background: var(--ds-surface);
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

.ds-dl dd {
  margin-bottom: 0.35rem;
}

.ds-qr {
  max-width: 220px;
}

.ds-token {
  font-size: 0.75rem;
  word-break: break-all;
}

.ds-summary {
  color: var(--ds-primary);
  cursor: pointer;
}

.ds-json {
  background: var(--ds-bg);
  border: 1px solid var(--ds-border);
  padding: 0.75rem;
  font-size: 0.75rem;
  max-height: 260px;
  overflow: auto;
}

.ds-loader {
  color: var(--ds-primary);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
