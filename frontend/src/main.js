import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { initCsrf } from './api/client'
import { useSesiStore } from './stores/sesi'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Inisialisasi CSRF dan status masuk admin sekali di awal (simpan hasil) sebelum mount,
// agar guard tidak menampilkan rute admin lalu membuangnya.
initCsrf().catch(() => {
  // Abaikan kegagalan saat inisialisasi awal (mis. backend belum menyala)
})

const sesi = useSesiStore(pinia)
sesi.muatProfil({ diamkanGalat: true, lewatiArahUlangAuth: true }).finally(() => {
  app.mount('#app')
})
