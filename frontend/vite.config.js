import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // Khusus development: request relatif "/api" di-proxy ke Laravel lokal
    // (php artisan serve di port 8000) supaya origin localhost tidak
    // diblokir CORS (backend hanya menerima origin FRONTEND_URL yang
    // terdaftar). Pairing dengan .env dev: VITE_API_BASE_URL=/api.
    // API produksi tetap dipakai lewat .env.production saat build.
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
