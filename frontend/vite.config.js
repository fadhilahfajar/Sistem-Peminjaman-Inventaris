import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// Port 5173 harus tetap: SANCTUM_STATEFUL_DOMAINS di backend memuat localhost:5173 dan 127.0.0.1:5173.
// Backend dijalankan dengan `php artisan serve` (127.0.0.1:8000).
const backend = 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: backend },
      '/sanctum': { target: backend },
    },
  },
})
