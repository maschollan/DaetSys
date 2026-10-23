<script>
import useAuthStore from '@/stores/auth'
import useUiStore from '@/stores/ui'

export default {
  name: 'ProfileView',

  data() {
    return {
      // ---------- Form identitas ----------
      nameForm: {
        name: '',
      },
      nameErrors: {},
      nameSubmitting: false,

      // ---------- Form password ----------
      passwordForm: {
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
      },
      passwordErrors: {},
      passwordSubmitting: false,
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
    ui: () => useUiStore(),

    roleBadgeClass() {
      switch (this.auth.role) {
        case 'admin':
          return 'text-bg-warning'
        case 'officer':
          return 'text-bg-success'
        default:
          return 'text-bg-secondary'
      }
    },
  },

  mounted() {
    this.nameForm.name = this.auth.userName
  },

  methods: {
    /* ---------------- Ubah nama ---------------- */

    validateName() {
      this.nameErrors = {}
      if (!this.nameForm.name.trim()) {
        this.nameErrors = { name: 'Nama wajib diisi.' }
      }
      return Object.keys(this.nameErrors).length === 0
    },

    async submitName() {
      if (!this.validateName()) return
      this.nameSubmitting = true

      try {
        await this.auth.updateProfile({ name: this.nameForm.name.trim() })
        this.ui.success('Profil berhasil diperbarui.')
      } catch (exception) {
        const response = exception.response
        if (response?.status === 422) {
          this.nameErrors = { ...(response.data?.errors ?? {}) }
          this.ui.error(response.data?.message ?? 'Data tidak valid.')
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal memperbarui profil.'))
        }
      } finally {
        this.nameSubmitting = false
      }
    },

    /* ---------------- Ubah password ---------------- */

    validatePassword() {
      const errors = {}
      const form = this.passwordForm

      if (!form.current_password) errors.current_password = 'Password saat ini wajib diisi.'
      if (!form.new_password) {
        errors.new_password = 'Password baru wajib diisi.'
      } else if (form.new_password.length < 8) {
        errors.new_password = 'Password minimal 8 karakter.'
      } else if (!/[a-zA-Z]/.test(form.new_password) || !/[0-9]/.test(form.new_password)) {
        errors.new_password = 'Password harus mengandung huruf dan angka.'
      } else if (form.new_password !== form.new_password_confirmation) {
        errors.new_password_confirmation = 'Konfirmasi password tidak cocok.'
      }

      this.passwordErrors = { ...errors }
      return Object.keys(errors).length === 0
    },

    async submitPassword() {
      if (!this.validatePassword()) return
      this.passwordSubmitting = true

      try {
        await this.auth.updatePassword(this.passwordForm)
        // Backend mencabut seluruh token: paksa sesi baharu.
        this.ui.success('Password diperbarui. Silakan masuk kembali.')
        await this.auth.logout(false)
        await this.$router.replace({ name: 'login' })
      } catch (exception) {
        const response = exception.response
        if (response?.status === 422) {
          this.passwordErrors = { ...(response.data?.errors ?? {}) }
          this.ui.error(response.data?.message ?? 'Data tidak valid.')
        } else {
          this.ui.error(this.ui.apiError(exception, 'Gagal mengubah password.'))
        }
      } finally {
        this.passwordSubmitting = false
      }
    },

    /* ---------------- Keluar ---------------- */

    async handleLogout() {
      await this.auth.logout()
      await this.$router.replace({ name: 'login' })
    },
  },
}
</script>

<template>
  <div>
    <div class="ds-card p-4 mb-3">
      <div class="d-flex align-items-center gap-3">
        <div class="ds-avatar-lg d-inline-flex align-items-center justify-content-center">
          <i class="bi bi-person-fill fs-4"></i>
        </div>
        <div class="min-vw-0">
          <div class="h5 mb-0 text-truncate">{{ auth.userName }}</div>
          <div class="ds-muted-text small text-truncate">{{ auth.userEmail }}</div>
          <span class="badge mt-1" :class="roleBadgeClass">{{ auth.role }}</span>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <!-- Ubah nama -->
      <div class="col-lg-6">
        <div class="ds-card p-4 h-100">
          <h3 class="h6 mb-3"><i class="bi bi-person-vcard me-1"></i> Ubah Nama</h3>
          <p class="ds-muted-text small">
            Nama tampil di sidebar dan seluruh halaman. Perubahan tercatat di jejak audit.
          </p>

          <form novalidate @submit.prevent="submitName">
            <div class="mb-3">
              <label class="form-label small" for="profile-name">Nama</label>
              <input
                id="profile-name"
                v-model="nameForm.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': nameErrors.name }"
              />
              <div v-if="nameErrors.name" class="invalid-feedback">{{ nameErrors.name }}</div>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="nameSubmitting">
              <span
                v-if="nameSubmitting"
                class="spinner-border spinner-border-sm me-2"
                role="status"
                aria-hidden="true"
              ></span>
              Simpan Profil
            </button>
          </form>
        </div>
      </div>

      <!-- Ubah password -->
      <div class="col-lg-6">
        <div class="ds-card p-4 h-100">
          <h3 class="h6 mb-3"><i class="bi bi-shield-lock me-1"></i> Ubah Password</h3>
          <p class="ds-muted-text small mb-3">
            Minimal 8 karakter, mengandung huruf dan angka. Setelah berhasil seluruh sesi dicabut
            dan Anda diminta masuk kembali.
          </p>

          <form novalidate @submit.prevent="submitPassword">
            <div class="mb-3">
              <label class="form-label small" for="profile-current-password"
                >Password saat ini</label
              >
              <input
                id="profile-current-password"
                v-model="passwordForm.current_password"
                type="password"
                class="form-control"
                :class="{ 'is-invalid': passwordErrors.current_password }"
                autocomplete="current-password"
              />
              <div v-if="passwordErrors.current_password" class="invalid-feedback">
                {{ passwordErrors.current_password }}
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small" for="profile-new-password">Password baru</label>
              <input
                id="profile-new-password"
                v-model="passwordForm.new_password"
                type="password"
                class="form-control"
                :class="{ 'is-invalid': passwordErrors.new_password }"
                autocomplete="new-password"
              />
              <div v-if="passwordErrors.new_password" class="invalid-feedback">
                {{ passwordErrors.new_password }}
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small" for="profile-confirm-password"
                >Konfirmasi password baru</label
              >
              <input
                id="profile-confirm-password"
                v-model="passwordForm.new_password_confirmation"
                type="password"
                class="form-control"
                :class="{ 'is-invalid': passwordErrors.new_password_confirmation }"
                autocomplete="new-password"
              />
              <div v-if="passwordErrors.new_password_confirmation" class="invalid-feedback">
                {{ passwordErrors.new_password_confirmation }}
              </div>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="passwordSubmitting">
              <span
                v-if="passwordSubmitting"
                class="spinner-border spinner-border-sm me-2"
                role="status"
                aria-hidden="true"
              ></span>
              Simpan Password
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Keluar -->
    <div class="ds-card p-4 mt-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <div class="fw-bold">Keluar dari akun</div>
        <div class="ds-muted-text small">Mengakhiri sesi saat ini di perangkat ini.</div>
      </div>
      <button type="button" class="btn btn-outline-danger" @click="handleLogout">
        <i class="bi bi-box-arrow-right me-1"></i> Keluar
      </button>
    </div>
  </div>
</template>

<style scoped>
.ds-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
}

.ds-avatar-lg {
  width: 56px;
  height: 56px;
  border: 1px solid var(--ds-border);
  color: var(--ds-muted);
  flex-shrink: 0;
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
