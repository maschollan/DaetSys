import 'bootstrap'
// Bootstrap JS dipanggil sekali di sini (bukan per-render Vue) agar
// komponen berbasis data-bs-* (dropdown, offcanvas, modal) tetap bekerja.
import '@/styles/index.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { initTheme } from './composables/useTheme'

// Sinkronkan tema sebelum aplikasi ditempel (index.html sudah menampilkan tema awal).
initTheme()

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
