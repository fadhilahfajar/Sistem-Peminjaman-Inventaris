import { test, expect } from '@playwright/test'
import { bacaState } from './bantu'

test.skip(
  !process.env.UJI_ADMIN_USERNAME || !process.env.UJI_ADMIN_PASSWORD,
  'Butuh env UJI_ADMIN_USERNAME + UJI_ADMIN_PASSWORD (akun uji E2E).',
)

test('T-01: rute admin tanpa sesi dialihkan ke login; API admin membalas 401', async ({ page, request }) => {
  await page.goto('/admin/peminjaman')
  await expect(page.getByRole('heading', { name: 'Masuk Panel Admin' })).toBeVisible()
  await expect(page).toHaveURL(/\/admin\/login/)

  const res = await request.get('/api/admin/loans')
  expect(res.status()).toBe(401)
  const body = await res.json()
  expect(body?.kode).toBeTruthy()
})

test('T-41: laporan PDF admin (4 jenis) membalas PDF', async ({ page }) => {
  await loginAdmin(page)
  const jenis = ['rekap', 'belum-kembali', 'stok', 'pengembalian']
  for (const j of jenis) {
    // Sesi admin via cookie browser; fetch lewat page (browser) supaya cookie terkirim.
    const hasil = await page.evaluate(async (jenisPdf) => {
      const r = await fetch(`/api/admin/reports/${jenisPdf}.pdf`, {
        credentials: 'include',
        headers: { Accept: 'application/pdf' },
      })
      const buf = await r.arrayBuffer()
      const bytes = new Uint8Array(buf)
      let bin = ''
      const chunkSize = 0x8000
      for (let i = 0; i < bytes.length; i += chunkSize) {
        bin += String.fromCharCode(...bytes.subarray(i, i + chunkSize))
      }
      return { status: r.status, contentType: r.headers.get('content-type'), b64: btoa(bin) }
    }, j)
    expect(hasil.status).toBe(200)
    expect(hasil.contentType).toContain('application/pdf')
  }
})

test('T-30: form barang admin menolak tanpa kategori aktif', async ({ page }) => {
  await loginAdmin(page)
  await page.goto('/admin/barang/baru')
  await page.getByRole('button', { name: /Tambah Barang/i }).click()
  await expect(page.getByText('Kategori wajib dipilih.')).toBeVisible()
})

test('T-32: tipe peminjam kiosk hanya menerima karyawan atau magang', async ({ page }) => {
  await page.goto('/pinjam')
  await expect(page.getByText('Formulir Peminjaman Barang')).toBeVisible()
  const radioKaryawan = page.locator('input[type="radio"][value="karyawan"]')
  const radioMagang = page.locator('input[type="radio"][value="magang"]')
  await expect(radioKaryawan).toBeVisible()
  await expect(radioMagang).toBeVisible()
  await expect(page.locator('input[type="radio"][value="lainnya"]')).toHaveCount(0)
})

test('T-33: halaman stok rusak admin render daftar tanpa crash', async ({ page }) => {
  await loginAdmin(page)
  await page.goto('/admin/stok-rusak')
  await expect(page.getByRole('heading', { name: 'Stok Rusak' })).toBeVisible()
})

test('T-13 + UI-04: admin verifikasi klaim lalu kartu kiosk menampilkan catatan admin', async ({
  browser,
  page,
}) => {
  const state = bacaState()
  expect(state.kodeKlaim).toMatch(/^KMB-[A-Z0-9]{6}$/)

  await loginAdmin(page)
  await page.goto('/admin/verifikasi')
  await page.getByText(state.kodeKlaim).first().click()

  await page.getByRole('button', { name: /Setujui sesuai laporan/i }).click()
  await page.getByRole('button', { name: 'Simpan Verifikasi' }).click()
  await expect(page.getByText('Klaim diverifikasi. Stok sudah diperbarui.')).toBeVisible()

  const pageK = await browser.newPage()
  await pageK.goto('/cek')
  await pageK.fill('#kode_pinjam', state.kodePinjam)
  await pageK.getByRole('button', { name: 'Lihat Kartu Peminjaman' }).click()
  await expect(pageK.getByRole('heading', { name: 'Kartu Peminjaman' })).toBeVisible()
  await expect(pageK.getByText('Catatan Petugas IT')).toBeVisible()
  await pageK.close()
})

async function loginAdmin(page) {
  const username = process.env.UJI_ADMIN_USERNAME
  const password = process.env.UJI_ADMIN_PASSWORD
  if (!username || !password) throw new Error('Butuh env UJI_ADMIN_USERNAME + UJI_ADMIN_PASSWORD (akun uji E2E).')

  await page.goto('/admin/login')
  await expect(page.getByRole('heading', { name: 'Masuk Panel Admin' })).toBeVisible()
  await page.fill('#username', username)
  await page.fill('#password', password)
  await page.getByRole('button', { name: 'Masuk' }).click()
  await expect(page).toHaveURL(/\/admin(?!\/login)/)
}
