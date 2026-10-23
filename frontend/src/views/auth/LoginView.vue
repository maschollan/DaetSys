<script>
import useAuthStore from '@/stores/auth'

export default {
  name: 'LoginView',

  data() {
    return {
      form: {
        email: '',
        password: '',
      },
      formErrors: {},
      showPassword: false,
      submitting: false,
      formMessage: '',
      isDemo: import.meta.env.DEV,
      demoAccounts: [
        { role: 'Admin', email: 'admin@daetsys.local' },
        { role: 'Petugas', email: 'officer@daetsys.local' },
        { role: 'Auditor', email: 'auditor@daetsys.local' },
      ],
    }
  },

  computed: {
    /** Pinia store diakses lewat computed agar reaktif. */
    auth: () => useAuthStore(),
  },

  mounted() {
    document.title = 'Masuk — DaetSys'
  },

  methods: {
    isEmail(value) {
      return /.+@.+\..+/.test(value)
    },

    resetErrors() {
      this.formMessage = ''
      this.formErrors = {}
    },

    validate() {
      const errors = {}

      if (!this.form.email.trim()) {
        errors.email = 'Email wajib diisi.'
      } else if (!this.isEmail(this.form.email.trim())) {
        errors.email = 'Format email tidak valid.'
      }

      if (!this.form.password) {
        errors.password = 'Password wajib diisi.'
      }

      this.formErrors = { ...errors }
      return Object.keys(errors).length === 0
    },

    async fillDemo(account) {
      this.form.email = account.email
      this.form.password = 'password'
    },

    async submit() {
      this.resetErrors()

      if (!this.validate()) {
        return
      }

      this.submitting = true

      try {
        await this.auth.login({
          email: this.form.email.trim(),
          password: this.form.password,
        })
        this.form.password = ''

        // Kembali ke halaman tujuan asal jika ada query redirect.
        const redirect = this.$route.query.redirect
        await this.$router.replace(typeof redirect === 'string' ? redirect : { name: 'dashboard' })
      } catch (error) {
        const response = error.response

        if (response?.status === 422 && response.data?.errors) {
          this.formErrors = { ...(response.data.errors ?? {}) }
          this.formMessage = 'Periksa kembali isian formulir.'
        } else if (response?.status === 401 || response?.status === 403) {
          this.formMessage = response.data?.message || 'Email atau password salah.'
        } else {
          this.formMessage = 'Tidak dapat terhubung ke server. Coba lagi nanti.'
        }
      } finally {
        this.submitting = false
      }
    },
  },
}
</script>

<template>
  <div class="login-page d-flex align-items-center justify-content-center min-vh-100 px-3">
    <main class="w-100" style="max-width: 420px">
      <div class="text-center mb-4">
        <div class="ds-brand-row text-primary fw-bold">
          <span class="ds-brand-mark">&gt;_</span> DAETSYS
        </div>
        <p class="ds-muted-text mt-2 mb-0">Digital Asset &amp; Evidence Tracking System</p>
        <div class="ds-terminal-rule mx-auto"></div>
      </div>

      <section class="ds-login-card">
        <h1 class="h5 mb-1">Masuk</h1>
        <p class="ds-muted-text mb-4">Gunakan akun yang diberikan administrator.</p>

        <div
          v-if="formMessage"
          class="alert alert-danger d-flex align-items-start gap-2 py-2"
          role="alert"
        >
          <i class="bi bi-exclamation-triangle-fill mt-1"></i>
          <span>{{ formMessage }}</span>
        </div>

        <form novalidate @submit.prevent="submit">
          <div class="mb-3">
            <label class="form-label" for="login-email">Email</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input
                id="login-email"
                v-model="form.email"
                type="email"
                class="form-control"
                :class="{ 'is-invalid': formErrors.email }"
                autocomplete="email"
                placeholder="nama@instansi.go.id"
                required
              />
            </div>
            <div v-if="formErrors.email" class="invalid-feedback d-block">
              {{ formErrors.email }}
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label" for="login-password">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-key"></i></span>
              <input
                id="login-password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="form-control"
                :class="{ 'is-invalid': formErrors.password }"
                autocomplete="current-password"
                placeholder="••••••••"
                required
              />
              <button
                type="button"
                class="btn btn-outline-secondary"
                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                @click="showPassword = !showPassword"
              >
                <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
              </button>
            </div>
            <div v-if="formErrors.password" class="invalid-feedback d-block">
              {{ formErrors.password }}
            </div>
          </div>

          <button type="submit" class="btn btn-fill-primary w-100" :disabled="submitting">
            <span
              v-if="submitting"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"
            ></span>
            {{ submitting ? 'Memverifikasi...' : 'Masuk' }}
          </button>
        </form>

        <!-- Kredensial demo hanya tampil pada build development (PLAN.md §12.A). -->
        <div v-if="isDemo" class="mt-4 border-top pt-3">
          <small class="d-flex align-items-center gap-1 ds-muted-text mb-2">
            <i class="bi bi-terminal"></i> Akun demo (dev): password
            <code>password</code>
          </small>
          <div class="d-flex flex-wrap gap-2">
            <button
              v-for="account in demoAccounts"
              :key="account.email"
              type="button"
              class="btn btn-outline-secondary btn-sm"
              :title="account.email"
              @click="fillDemo(account)"
            >
              {{ account.role }}
            </button>
          </div>
        </div>
      </section>

      <p class="text-center ds-muted-text mt-4 mb-0">© 2026 DaetSys — Instansi Pemerintah</p>
    </main>
  </div>
</template>

<style scoped>
.login-page {
  background:
    linear-gradient(rgba(var(--ds-primary-rgb), 0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(var(--ds-primary-rgb), 0.06) 1px, transparent 1px), var(--ds-bg);
  background-size:
    36px 36px,
    36px 36px,
    auto;
}

.ds-brand-row {
  font-size: 1.5rem;
  letter-spacing: 0.2em;
}

.ds-terminal-rule {
  width: 72px;
  height: 3px;
  background: var(--ds-primary);
  margin-top: 0.6rem;
}

.ds-login-card {
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
  padding: 2rem;
}

.ds-muted-text {
  color: var(--ds-muted);
}
</style>
