import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { initCsrf } from './api/client'
import './assets/main.css'

// Minta cookie CSRF di awal agar seluruh POST (kiosk maupun admin)
// langsung memiliki token valid tanpa menunggu aksi pengguna.
initCsrf().catch(() => {
  // Abaikan kegagalan saat inisialisasi awal (mis. backend belum menyala)
})

createApp(App).use(createPinia()).use(router).mount('#app')
