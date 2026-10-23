<script>
import api from '@/services/api'
import useAuthStore from '@/stores/auth'
import useUiStore from '@/stores/ui'
import useNotificationStore from '@/stores/notification'
import { formatDateTime } from '@/constants/status'

import AuditChangeList from '@/components/AuditChangeList.vue'
import PageHeader from '@/components/PageHeader.vue'
import ModalConfirm from '@/components/ModalConfirm.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const ORDER = ['pending', 'approved', 'handover', 'completed']

/** Label & ikon aksi jejak audit yang relevan untuk transfer. */
const ACTIVITY_META = {
  'transfer.requested': { label: 'Diajukan', icon: 'bi-plus-lg' },
  'transfer.approved': { label: 'Disetujui', icon: 'bi-check2-circle' },
  'transfer.rejected': { label: 'Ditolak', icon: 'bi-x-circle' },
  'transfer.handed_over': { label: 'Diserahkan', icon: 'bi-box-arrow-up-right' },
  'transfer.received': { label: 'Diterima', icon: 'bi-check2-all' },
  'transfer.cancelled': { label: 'Dibatalkan', icon: 'bi-slash-circle' },
  'transfer.conflict': { label: 'Konflik', icon: 'bi-exclamation-triangle' },
  'asset.updated': { label: 'Aset diperbarui', icon: 'bi-pencil' },
}

export default {
  name: 'TransferDetailView',

  components: { AuditChangeList, PageHeader, ModalConfirm, StatusBadge },

  data() {
    return {
      transfer: null,
      loading: false,
      error: '',
      actionBusy: false,
      active: null,
      cancelReason: '',
      notes: '',
      notesTitle: '',
      cancelBusy: false,
      notesBusy: false,
      timeline: [],

      /** Jejak audit spesifik transfer ini (admin & auditor). */
      activities: [],
      activityLoading: false,
      activityError: '',
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
    ui: () => useUiStore(),
    notifications: () => useNotificationStore(),
  },

  mounted() {
    this.load()
  },

  // Navigasi transfer/UNIQ_A → transfer/UNIQ_B memakai komponen yang sama
  // sehingga mounted tidak dijalankan ulang; muat ulang data saat token berubah.
  watch: {
    '$route.params.uniqId'(nextToken, prevToken) {
      if (nextToken && nextToken !== prevToken) {
        void this.load()
      }
    },
  },

  methods: {
    formatDateTime,

    async load() {
      this.loading = true
      this.error = ''

      try {
        // URL detail memakai uniqid; binding backend mengenali uniqid maupun
        // id numerik lama (fallback).
        const { data } = await api.transfers.get(this.$route.params.uniqId)
        this.transfer = data.data
        this.buildTimeline()
        this.loadActivities()
      } catch (exception) {
        this.error = this.ui.apiError(exception, 'Gagal memuat detail transfer.')
      } finally {
        this.loading = false
      }
    },

    /**
     * Muat jejak audit contoh transfer ini. Endpoint activity-logs hanya
     * untuk admin & auditor (backend Policy) — untuk officer card menampilkan
     * catatan singkat alih-alih error 403.
     */
    async loadActivities() {
      if (!this.auth.hasRole('admin', 'auditor')) {
        this.activities = []
        this.activityError = ''
        return
      }

      this.activityLoading = true
      this.activityError = ''

      try {
        const { data } = await api.activityLogs.list({
          transfer_id: this.transfer.id,
          per_page: 20,
        })
        this.activities = data.data ?? []
      } catch (exception) {
        this.activityError = this.ui.apiError(exception, 'Gagal memuat riwayat aktivitas.')
      } finally {
        this.activityLoading = false
      }
    },

    activityMeta(action) {
      return (
        ACTIVITY_META[action] ?? {
          label: action,
          icon: 'bi-clock-history',
        }
      )
    },

    activityHasChanges(activity) {
      return Boolean(
        (activity.old_values && Object.keys(activity.old_values).length) ||
        (activity.new_values && Object.keys(activity.new_values).length),
      )
    },

    buildTimeline() {
      const status = this.transfer?.status ?? 'pending'
      const isAborted = status === 'rejected' || status === 'cancelled'
      const step = isAborted ? 0 : ORDER.indexOf(status)

      this.timeline = ORDER.map((value, index) => {
        const state = isAborted
          ? index === 0
            ? 'aborted'
            : 'future'
          : index < step
            ? 'done'
            : index === step
              ? 'current'
              : 'future'
        return { value, state }
      })
    },

    /* -------- Gating tombol sesuai policy backend (PLAN.md §7) -------- */

    canApprove() {
      const model = this.transfer
      return (
        Boolean(model) &&
        model.status === 'pending' &&
        this.auth.hasRole('admin') &&
        model.requested_by &&
        model.requested_by.id !== this.auth.user.id
      )
    },

    canReject() {
      return this.canApprove()
    },

    canHandover() {
      const model = this.transfer
      return Boolean(model) && model.status === 'approved' && this.auth.hasRole('admin', 'officer')
    },

    canReceive() {
      const model = this.transfer
      if (!model || model.status !== 'handover' || !this.auth.hasRole('admin', 'officer'))
        return false
      // Penerima tidak boleh sama dengan pemberi serah terima (separation of duties).
      return model.handover && model.handover.handed_over_by?.id !== this.auth.user.id
    },

    canCancel() {
      const model = this.transfer
      if (!model || !['pending', 'approved'].includes(model.status)) return false
      if (this.auth.hasRole('admin')) return true
      return this.auth.hasRole('officer') && model.requested_by?.id === this.auth.user.id
    },

    /* -------- Aksi -------- */

    async runAction(kind) {
      this.actionBusy = true

      try {
        if (kind === 'approve')
          await api.transfers.approve(this.transfer.uniqid, this.notes || null)
        if (kind === 'reject') await api.transfers.reject(this.transfer.uniqid, this.notes)
        if (kind === 'handover')
          await api.transfers.handover(this.transfer.uniqid, this.notes || null)
        if (kind === 'receive')
          await api.transfers.receive(this.transfer.uniqid, this.notes || null)
        if (kind === 'cancel')
          await api.transfers.cancel(this.transfer.uniqid, this.cancelReason || null)

        this.notes = ''
        this.cancelReason = ''
        this.ui.success('Aksi transfer berhasil.')
        await this.load()
        this.notifications.fetch({ perPage: 1 })
      } catch (exception) {
        const response = exception.response

        if (response?.status === 422) {
          this.ui.error(response.data?.message ?? 'Data tidak valid.')
        } else {
          // 409 konflik status, 403 akses — tampilkan pesan backend apa adanya.
          this.ui.error(this.ui.apiError(exception, 'Aksi gagal.'))
        }
      } finally {
        this.actionBusy = false
      }
    },

    openNotes(kind) {
      this.active = kind
      this.notesTitle =
        kind === 'reject'
          ? 'Tolak transfer'
          : kind === 'handover'
            ? 'Rekam serah terima'
            : 'Persetujuan transfer'
      this.notes = ''
      this.$refs.notesModal?.show()
    },

    async confirmNotes() {
      this.notesBusy = true

      try {
        await this.runAction(this.active)
        this.$refs.notesModal?.hide()
      } finally {
        this.notesBusy = false
      }
    },

    async confirmCancel() {
      this.cancelBusy = true

      try {
        await this.runAction('cancel')
        this.$refs.cancelModal?.hide()
      } finally {
        this.cancelBusy = false
      }
    },
  },
}
</script>

<template>
  <div>
    <PageHeader
      v-if="transfer"
      :title="`Transfer #${transfer.id}`"
      :description="`${transfer.asset?.asset_code ?? '—'} — ${transfer.asset?.name ?? ''}`"
    >
      <template #actions>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'transfers' }">
          <i class="bi bi-arrow-left me-1"></i> Kembali
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border ds-loader" role="status" aria-hidden="true"></div>
    </div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else-if="transfer">
      <div class="ds-card p-4 mb-3">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
          <div>
            <RouterLink
              :to="{ name: 'assets.detail', params: { qrToken: transfer.asset.qr_token } }"
              class="ds-code"
            >
              {{ transfer.asset.asset_code }}
            </RouterLink>
            <div class="h6 mb-0">{{ transfer.asset.name }}</div>
          </div>
          <StatusBadge variant="transfer" :value="transfer.status" />
        </div>

        <!-- Timeline proses transfer -->
        <ol class="ds-timeline mt-4 mb-0 list-unstyled">
          <li v-for="step in timeline" :key="step.value" :class="`ds-step ds-step-${step.state}`">
            <span class="ds-dot"><i class="bi bi-check-lg"></i></span>
            <span class="ds-step-label">{{
              step.value === 'pending'
                ? 'Menunggu'
                : step.value === 'approved'
                  ? 'Disetujui'
                  : step.value === 'handover'
                    ? 'Serah Terima'
                    : 'Diterima / Selesai'
            }}</span>
          </li>
        </ol>

        <dl class="row row-cols-1 row-cols-md-3 g-2 mt-3 mb-0 ds-dl">
          <div class="col">
            <dt>Dari</dt>
            <dd>
              {{
                transfer.from_location
                  ? `${transfer.from_location.code} — ${transfer.from_location.name}`
                  : '—'
              }}
            </dd>
          </div>
          <div class="col">
            <dt>Ke</dt>
            <dd>
              {{
                transfer.to_location
                  ? `${transfer.to_location.code} — ${transfer.to_location.name}`
                  : '—'
              }}
            </dd>
          </div>
          <div class="col">
            <dt>Pengaju</dt>
            <dd>{{ transfer.requested_by?.name ?? '—' }}</dd>
          </div>
          <div class="col">
            <dt>Diajukan</dt>
            <dd>{{ formatDateTime(transfer.requested_at) }}</dd>
          </div>
          <div class="col">
            <dt>Selesai</dt>
            <dd>{{ formatDateTime(transfer.completed_at) }}</dd>
          </div>
          <div class="col">
            <dt>Alasan</dt>
            <dd>{{ transfer.reason ?? '—' }}</dd>
          </div>
        </dl>
      </div>

      <!-- Aksi workflow -->
      <div
        v-if="canApprove() || canReject() || canHandover() || canReceive() || canCancel()"
        class="ds-card p-3 mb-3 d-flex flex-wrap gap-2"
      >
        <button
          v-if="canHandover()"
          type="button"
          class="btn btn-outline-secondary"
          :disabled="actionBusy"
          @click="openNotes('handover')"
        >
          <i class="bi bi-box-arrow-up-right me-1"></i> Rekam Serah Terima
        </button>
        <button
          v-if="canReceive()"
          type="button"
          class="btn btn-fill-primary"
          :disabled="actionBusy"
          @click="openNotes('receive')"
        >
          <i class="bi bi-check2-circle me-1"></i> Kirim Penerimaan
        </button>
        <button
          v-if="canApprove()"
          type="button"
          class="btn btn-primary"
          :disabled="actionBusy"
          @click="openNotes('approve')"
        >
          <i class="bi bi-check2 me-1"></i> Setujui
        </button>
        <button
          v-if="canReject()"
          type="button"
          class="btn btn-outline-danger"
          :disabled="actionBusy"
          @click="openNotes('reject')"
        >
          <i class="bi bi-x-lg me-1"></i> Tolak
        </button>
        <button
          v-if="canCancel()"
          type="button"
          class="btn btn-outline-danger"
          :disabled="actionBusy"
          @click="$refs.cancelModal?.show()"
        >
          <i class="bi bi-slash-circle me-1"></i> Batalkan
        </button>
      </div>

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="ds-card p-3 h-100">
            <h3 class="h6 mb-3">History Approval</h3>
            <dl class="row g-2 ds-dl mb-2">
              <div class="col-12">
                <dt>Handover</dt>
                <dd>
                  <template v-if="transfer.handover">
                    {{ transfer.handover.handed_over_by?.name ?? '—' }} menyerahkan pada
                    {{ formatDateTime(transfer.handover.handed_over_at) }};
                    {{ transfer.handover.received_by?.name ?? 'menunggu penerimaan' }}
                    <span v-if="transfer.handover.received_at">
                      {{ formatDateTime(transfer.handover.received_at) }}</span
                    >
                    <div v-if="transfer.handover.notes" class="small ds-muted-text">
                      Catatan: {{ transfer.handover.notes }}
                    </div>
                  </template>
                  <span v-else>Belum ada handover.</span>
                </dd>
              </div>
              <div class="col-12">
                <dt>Riwayat keputusan</dt>
                <dd>
                  <div v-if="!transfer.approvals?.length" class="ds-muted-text">
                    Belum ada keputusan.
                  </div>
                  <ul v-else class="list-unstyled mb-0 small">
                    <li v-for="approval in transfer.approvals" :key="approval.id" class="mb-1">
                      <span :class="approval.decision === 'approved' ? 'ds-good' : 'ds-bad'">
                        {{ approval.decision === 'approved' ? 'Disetujui' : 'Ditolak' }}
                      </span>
                      oleh {{ approval.approver?.name ?? '—' }} —
                      {{ formatDateTime(approval.decided_at) }}
                      <div v-if="approval.notes" class="ds-muted-text">“{{ approval.notes }}”</div>
                    </li>
                  </ul>
                </dd>
              </div>
            </dl>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="ds-card p-3 h-100">
            <h3 class="h6 mb-3"><i class="bi bi-clock-history me-1"></i> Riwayat Aktivitas</h3>

            <template v-if="auth.hasRole('admin', 'auditor')">
              <div v-if="activityLoading" class="text-center py-3">
                <div class="spinner-border spinner-border-sm ds-loader" role="status">
                  <span class="visually-hidden">Memuat...</span>
                </div>
              </div>

              <div v-else-if="activityError" class="alert alert-danger py-2 small mb-0">
                {{ activityError }}
              </div>

              <p v-else-if="!activities.length" class="ds-muted-text small mb-0">
                Belum ada aktivitas yang tercatat untuk transfer ini.
              </p>

              <ul v-else class="list-unstyled mb-0 ds-activity small">
                <li v-for="activity in activities" :key="activity.id" class="py-2">
                  <div class="d-flex align-items-center gap-2">
                    <i :class="`bi ${activityMeta(activity.action).icon}`" aria-hidden="true"></i>
                    <strong>{{ activityMeta(activity.action).label }}</strong>
                    <span class="ms-auto ds-muted-text text-nowrap">{{
                      formatDateTime(activity.created_at)
                    }}</span>
                  </div>
                  <div class="ds-muted-text mt-1">
                    oleh {{ activity.user?.name ?? 'sistem' }}
                    <span v-if="activity.ip_address" class="ms-1"
                      >· IP {{ activity.ip_address }}</span
                    >
                  </div>
                  <div v-if="activityHasChanges(activity)" class="mt-1">
                    <AuditChangeList
                      :old-values="activity.old_values"
                      :new-values="activity.new_values"
                    />
                  </div>
                </li>
              </ul>
            </template>

            <p v-else class="ds-muted-text small mb-0">
              Riwayat aktivitas tersedia untuk admin dan auditor.
            </p>
          </div>
        </div>
      </div>
    </template>

    <!-- Modal catatan (approve / reject / handover / receive) -->
    <ModalConfirm
      v-if="transfer"
      ref="notesModal"
      :title="notesTitle"
      confirm-text="Kirim"
      :busy="notesBusy"
      @confirm="confirmNotes"
      @closed="active = null"
    >
      <div class="mb-2">
        <label class="form-label small" for="transfer-notes">
          Catatan {{ active === 'reject' ? '(wajib)' : '(opsional)' }}
        </label>
        <textarea id="transfer-notes" v-model="notes" class="form-control" rows="3"></textarea>
      </div>
    </ModalConfirm>

    <!-- Modal pembatalan -->
    <ModalConfirm
      v-if="transfer"
      ref="cancelModal"
      title="Batalkan transfer?"
      confirm-text="Batalkan Transfer"
      tone="danger"
      :busy="cancelBusy"
      @confirm="confirmCancel"
      @closed="cancelReason = ''"
    >
      <div class="mb-2">
        <label class="form-label small" for="transfer-cancel-reason"
          >Alasan pembatalan (opsional)</label
        >
        <textarea
          id="transfer-cancel-reason"
          v-model="cancelReason"
          class="form-control"
          rows="2"
        ></textarea>
      </div>
    </ModalConfirm>
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
  text-decoration: none;
}

.ds-code:hover {
  text-decoration: underline;
}

/* Timeline horizontal mulai kiri */
.ds-timeline {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
}

.ds-step {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding-right: 1.5rem;
  position: relative;
}

.ds-step:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 12px;
  left: 28px;
  right: 0;
  height: 1px;
  background: var(--ds-border);
}

.ds-dot {
  width: 24px;
  height: 24px;
  border: 2px solid var(--ds-border);
  border-radius: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  background: var(--ds-surface);
  color: transparent;
  position: relative;
  z-index: 1;
}

.ds-step-done .ds-dot,
.ds-step-current .ds-dot {
  border-color: var(--ds-primary);
  color: #fff;
  background: var(--ds-primary);
}

.ds-step-aborted .ds-dot {
  border-color: var(--ds-danger);
  background: rgba(var(--bs-danger-rgb), 0.12);
}

.ds-step-label {
  font-size: 0.78rem;
  font-weight: 700;
}

.ds-step-done .ds-step-label,
.ds-step-current .ds-step-label {
  color: var(--ds-primary);
}

.ds-step-aborted .ds-step-label {
  color: var(--ds-danger);
}

.ds-dl dt {
  color: var(--ds-muted);
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Daftar jejak audit per transfer: pemisah garis putus-putus tipis. */
.ds-activity li {
  border-bottom: 1px dashed var(--ds-border);
}

.ds-activity li:first-child {
  padding-top: 0;
}

.ds-activity li:last-child {
  border-bottom: 0;
}

.ds-good {
  color: var(--ds-success);
  font-weight: 700;
}

.ds-bad {
  color: var(--ds-danger);
  font-weight: 700;
}

.ds-loader {
  color: var(--ds-primary);
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
