import { expect, test } from '@playwright/test'

test('beranda kiosk tampil dan bisa ke katalog', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { name: 'Selamat Datang di Inventaris IT' })).toBeVisible()
  await page.getByRole('button', { name: /Lihat Katalog/ }).click()
  await expect(page).toHaveURL(/\/katalog$/)
})

test('halaman login admin menolak tanpa sesi', async ({ page }) => {
  await page.goto('/admin')

  await expect(page).toHaveURL(/\/admin\/login/)
  await expect(page.getByRole('heading', { name: 'Masuk Panel Admin' })).toBeVisible()
  await expect(page.locator('#username')).toBeVisible()
  await expect(page.locator('#password')).toBeVisible()
})
