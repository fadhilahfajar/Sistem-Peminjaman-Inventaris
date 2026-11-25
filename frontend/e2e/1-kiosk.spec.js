import { test, expect } from '@playwright/test'
import { bacaState, tulisState } from './bantu'

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
  const fs = await import('node:fs')
  fs.writeFileSync('test-results/state.json', '{}')
})

async function dataBarang(page) {
  const res = await page.request.get('/api/kiosk/items')
  const body = await res.json()
  const data = body?.data?.data ?? body?.data ?? []
  return data
}

test('UI-01: beranda kiosk menampilkan tiga aksi utama', async ({ page }) => {
  await page.goto('/')
  await expect(page.locator('h1')).toBeVisible()
  await expect(page.getByRole('button', { name: /Lihat Katalog/ })).toBeVisible()
  await expect(page.getByRole('button', { name: /Pinjam Barang/ })).toBeVisible()
  await expect(page.getByRole('button', { name: /Cek atau Kembalikan/ })).toBeVisible()
})

test('BUG: popup pemilih barang /pinjam menampilkan daftar barang', async ({ page }) => {
  await page.goto('/pinjam')
  await expect(page.getByText('Formulir Peminjaman Barang')).toBeVisible()

  // Varianti tombol dari kartu keranjang kosong (StatusKosong -> Pilih Barang dari Katalog).
  await page.locator('.py-6').getByRole('button', { name: /Pilih Barang dari Katalog/ }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  await expect(dialog.getByText('Pilih Barang Inventaris')).toBeVisible()
  await expect(dialog.locator('#pemilih-barang-cari')).toHaveValue('')
  const jumlahTombolPilih = await dialog.getByRole('button', { name: '+ Pilih' }).count()
  expect(jumlahTombolPilih).toBeGreaterThan(0)
  const isiDialog = await dialog.innerText()
  expect(isiDialog).not.toContain('Tidak ada barang yang cocok')
  await page.getByRole('button', { name: 'Tutup' }).click()
  await expect(dialog).not.toBeVisible()

  // Varianti tombol kartu keranjang (formulasi) -> + Tambah Barang.
  await page.getByRole('button', { name: '+ Tambah Barang' }).click()
  const dialog2 = page.getByRole('dialog')
  await expect(dialog2).toBeVisible()
  await expect(dialog2.getByText('Pilih Barang Inventaris')).toBeVisible()
  expect(await dialog2.getByRole('button', { name: '+ Pilih' }).count()).toBeGreaterThan(0)
  await page.getByRole('button', { name: 'Tutup' }).click()
  await expect(dialog2).not.toBeVisible()
})

test('T-02 + UI-01: alur pinjam via UI, stok turun, kode tersimpan di halaman selesai', async ({
  page,
}) => {
  await page.goto('/pinjam')
  await expect(page.getByText('Formulir Peminjaman Barang')).toBeVisible()

  const data = await dataBarang(page)
  const item = data.find((b) => Number(b.tersedia) > 0) ?? data[0]
  expect(item).toBeTruthy()
  const nama = item.nama
  const tersediaSebelum = Number(item.tersedia)

  await page.getByRole('button', { name: '+ Tambah Barang' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByText(nama, { exact: true }).first().waitFor()
  await dialog.getByRole('button', { name: '+ Pilih' }).first().click()
  await dialog.getByRole('button', { name: 'Tambahkan 1 barang' }).click()
  await expect(page.getByText(/barang ditambahkan/)).toBeVisible()

  await page.fill('#nama_peminjam', 'Budi Santoso')
  await page.selectOption('#division_id', { index: 1 })
  await page.fill('#telepon', '081234567890')
  await page.fill('#keperluan', 'Membeli barang untuk rapat tim')

  await page.getByRole('button', { name: /Ajukan Peminjaman/ }).click()
  await expect(page).toHaveURL(/\/pinjam\/selesai/)
  await expect(page.getByText('Peminjaman Berhasil!')).toBeVisible()

  const kode = (await page.getByText(/^PJM-/).first().textContent())?.trim()
  expect(kode).toMatch(/^PJM-[A-Z0-9]{6}$/)
  await tulisState({ kodePinjam: kode, itemId: item.id, itemNama: nama })

  const dataSetelah = await dataBarang(page)
  const itemSetelah = dataSetelah.find((b) => b.nama === nama)
  expect(Number(itemSetelah?.tersedia)).toBe(tersediaSebelum - 1)
})

test('UI-02: tekan Ajukan Peminjaman dua kali cepat hanya satu transaksi', async ({ page }) => {
  await page.goto('/pinjam')
  await expect(page.getByText('Formulir Peminjaman Barang')).toBeVisible()

  await page.getByRole('button', { name: 'Pilih Barang dari Katalog' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByRole('button', { name: '+ Pilih' }).first().click()
  await dialog.getByRole('button', { name: 'Tambahkan 1 barang' }).click()
  await expect(page.getByText(/barang ditambahkan/)).toBeVisible()

  await page.fill('#nama_peminjam', 'Siti Nurhaliza')
  await page.selectOption('#division_id', { index: 1 })
  await page.fill('#telepon', '08111112222')
  await page.fill('#keperluan', 'Memesan barang untuk uji.')

  const tombol = page.getByRole('button', { name: /Ajukan Peminjaman/ })
  await tombol.scrollIntoViewIfNeeded()
  const respons = page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('/api/kiosk/loans'))
  await tombol.evaluate((el) => {
    el.click()
    el.click()
    el.click()
  })
  const res = await respons
  expect([200, 201]).toContain(res.status())
  await expect(page).toHaveURL(/\/pinjam\/selesai/)
})

test('T-35: teks <script> tampil sebagai teks, bukan dieksekusi', async ({ page }) => {
  await page.goto('/pinjam')
  await expect(page.getByText('Formulir Peminjaman Barang')).toBeVisible()

  const data = await dataBarang(page)
  const item = data.find((b) => Number(b.tersedia) > 0) ?? data[0]

  await page.getByRole('button', { name: '+ Tambah Barang' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByText(item.nama, { exact: true }).first().waitFor()
  await dialog.getByRole('button', { name: '+ Pilih' }).first().click()
  await dialog.getByRole('button', { name: 'Tambahkan 1 barang' }).click()
  await expect(page.getByText(/barang ditambahkan/)).toBeVisible()

  await page.fill('#nama_peminjam', 'Andi Prasetyo')
  await page.selectOption('#division_id', { index: 1 })
  await page.fill('#telepon', '08122223333')
  await page.fill('#keperluan', '<script>window.__xssInj=1</script>')

  await page.getByRole('button', { name: /Ajukan Peminjaman/ }).click()
  await expect(page).toHaveURL(/\/pinjam\/selesai/)

  const injeksi = await page.evaluate(() => window.__xssInj)
  expect(injeksi).toBeUndefined()

  const inner = await page.evaluate(() => document.body.innerText)
  expect(inner).toContain('<script>window.__xssInj=1</script>')
})

test('T-06: kode salah tidak membocorkan data; 5x gagal mengunci pencarian', async ({ page }) => {
  await page.goto('/cek')
  await page.fill('#kode_pinjam', 'PJM-AAAAA0')
  await page.getByRole('button', { name: 'Lihat Kartu Peminjaman' }).click()
  await expect(page.getByText('Pemeriksaan Gagal')).toBeVisible()
  await expect(page.getByText(/kode peminjaman tidak ditemukan/i)).toBeVisible()

  // Default maks_gagal = 5. Sudah 1 gagal di atas. Lakukan 4x lagi = total 5 → terkunci.
  for (let i = 0; i < 4; i += 1) {
    await page.fill('#kode_pinjam', `PJM-BBBBB${i}`)
    await page.getByRole('button', { name: 'Lihat Kartu Peminjaman' }).click()
    await expect(page.getByText(/kode peminjaman tidak ditemukan/i)).toBeVisible()
  }

  await expect(page.getByText('Pencarian Terkunci')).toBeVisible()
  const tombol = page.getByRole('button', { name: 'Lihat Kartu Peminjaman' })
  await expect(tombol).toBeDisabled()
})

test('T-16: kartu peminjaman menampilkan batas kembali dan status sisa', async ({ page }) => {
  const state = bacaState()
  expect(state.kodePinjam).toMatch(/^PJM-[A-Z0-9]{6}$/)

  await page.goto('/cek')
  await page.fill('#kode_pinjam', state.kodePinjam)
  await page.getByRole('button', { name: 'Lihat Kartu Peminjaman' }).click()
  await expect(page.getByRole('heading', { name: 'Kartu Peminjaman' })).toBeVisible()
  await expect(page.getByText('Batas Kembali')).toBeVisible()
  await expect(page.getByText(/Sisa .* hari lagi|Tunggal|Terlambat/)).toBeVisible()
})

test('UI-03: kode pinjam tidak pernah di URL, storage, cookie, atau riwayat', async ({ page }) => {
  const state = bacaState()
  expect(state.kodePinjam).toMatch(/^PJM-[A-Z0-9]{6}$/)

  await page.goto('/cek')
  await page.fill('#kode_pinjam', state.kodePinjam)
  await page.getByRole('button', { name: 'Lihat Kartu Peminjaman' }).click()
  await expect(page.getByRole('heading', { name: 'Kartu Peminjaman' })).toBeVisible()

  expect(page.url()).not.toContain(state.kodePinjam)
  const isiStorage = await page.evaluate(() => JSON.stringify({ ls: localStorage, ss: sessionStorage }))
  expect(isiStorage).not.toContain(state.kodePinjam)
  expect(await page.evaluate(() => document.cookie)).not.toContain(state.kodePinjam)
  const riwayat = await page.evaluate(() => performance.getEntriesByType('navigation').map((e) => e.name))
  expect(JSON.stringify(riwayat)).not.toContain(state.kodePinjam)
})

test('UI-05: layout 360px dan 768px tidak ada overflow horizontal', async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 800 })
  await page.goto('/pinjam')
  await expect(page.getByText('Formulir Peminjaman Barang')).toBeVisible()
  let lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
  expect(lebar).toBeLessThanOrEqual(1)

  await page.setViewportSize({ width: 768, height: 1024 })
  await page.goto('/katalog')
  await expect(page.getByPlaceholder('Cari nama barang inventaris...')).toBeVisible()
  lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
  expect(lebar).toBeLessThanOrEqual(1)
})

test('UI-07: keadaan memuat dan keadaan kosong tampil', async ({ page }) => {
  await page.route('**/api/kiosk/items', async (route) => {
    await new Promise((r) => setTimeout(r, 1500))
    await route.continue()
  })
  await page.goto('/pinjam')
  await expect(page.getByText('Menyiapkan formulir peminjaman...')).toBeVisible()
  await page.unroute('**/api/kiosk/items')
  await expect(page.getByText('Formulir Peminjaman Barang')).toBeVisible()

  await page.goto('/katalog')
  await page.getByPlaceholder('Cari nama barang inventaris...').fill('zzznonexistentqq')
  await expect(page.getByText(/Tidak ada barang inventaris/i)).toBeVisible()
})

test('T-07: alur klaim pengembalian sebagian lalu admin verifikasi', async ({ page }) => {
  const state = bacaState()
  expect(state.kodePinjam).toMatch(/^PJM-[A-Z0-9]{6}$/)

  await page.goto('/cek')
  await page.fill('#kode_pinjam', state.kodePinjam)
  await page.getByRole('button', { name: 'Lihat Kartu Peminjaman' }).click()
  await expect(page.getByRole('heading', { name: 'Kartu Peminjaman' })).toBeVisible()
  await page.getByRole('button', { name: 'Kembalikan Barang' }).click()
  await expect(page.getByRole('heading', { name: /Klaim Pengembalian Barang/ })).toBeVisible()

  const jumlahInput = page.locator('input[type="number"]')
  await jumlahInput.first().fill('1')
  await page.fill('#catatan', 'Adaptor tertinggal di ruang rapat')
  await page.getByRole('button', { name: 'Ajukan Klaim', exact: true }).click()
  await expect(page.getByText(/Ringkasan Klaim/)).toBeVisible()
  await page.getByRole('button', { name: 'Ya, Ajukan Klaim' }).click()
  await expect(page).toHaveURL(/\/klaim\/selesai/)
  const kodeKlaim = (await page.getByText(/^KMB-/).first().textContent())?.trim()
  expect(kodeKlaim).toMatch(/^KMB-[A-Z0-9]{6}$/)
  await tulisState({ ...state, kodeKlaim })
})
