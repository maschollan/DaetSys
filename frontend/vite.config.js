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
    // Khusus development: proxy /api ke API produksi supaya origin
    // localhost tidak diblokir CORS (backend hanya menerima origin
    // FRONTEND_URL yang terdaftar, yaitu domain production).
    proxy: {
      '/api': {
        target: 'https://daetsysapi.onrender.com',
        changeOrigin: true,
      },
    },
  },
})
