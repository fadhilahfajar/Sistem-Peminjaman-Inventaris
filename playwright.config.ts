import { defineConfig } from '@playwright/test'

/**
 * E2E untuk frontend kiosk + panel admin.
 * Butuh server dev berjalan di baseURL (frontend `npm run dev`, port 5173)
 * dan backend `php artisan serve` di port 8000 (diproxy Vite).
 * ponytail: webServer tidak dijalankan otomatis; tambah blok webServer bila CI perlu boot sendiri.
 */
export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  fullyParallel: false,
  reporter: 'list',
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:5173',
    trace: 'on-first-retry',
    locale: 'id-ID',
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
})
