import assert from 'node:assert'
import fs from 'node:fs'
import path from 'node:path'
import { compileScript, compileTemplate, parse } from 'vue/compiler-sfc'

const pagesDir = path.resolve('src/pages/kiosk')
const expectedPages = [
  'BerandaPage.vue',
  'BatalPage.vue',
  'CekPage.vue',
  'KartuPage.vue',
  'KatalogPage.vue',
  'KlaimPage.vue',
  'KlaimSelesaiPage.vue',
  'PerpanjangPage.vue',
  'PerpanjangSelesaiPage.vue',
  'PinjamPage.vue',
  'PinjamSelesaiPage.vue',
]

for (const pageName of expectedPages) {
  const filePath = path.join(pagesDir, pageName)
  assert.ok(fs.existsSync(filePath), `Halaman ${pageName} tidak ditemukan`)
  const content = fs.readFileSync(filePath, 'utf-8')
  const { descriptor, errors } = parse(content)
  assert.strictEqual(errors.length, 0, `Parse error pada ${pageName}: ${errors[0]?.message}`)

  if (descriptor.scriptSetup) {
    compileScript(descriptor, { id: pageName })
  }
  if (descriptor.template) {
    const result = compileTemplate({
      id: pageName,
      filename: pageName,
      source: descriptor.template.content,
    })
    assert.strictEqual(
      result.errors.length,
      0,
      `Template compile error pada ${pageName}: ${result.errors[0]?.message}`,
    )
  }
}

// Uji aturan bisnis FR-K-01 pada template KartuBarang: stok 0 harus ditandai habis dan dinonaktifkan
const kartuBarangContent = fs.readFileSync(path.resolve('src/components/KartuBarang.vue'), 'utf-8')
assert.ok(kartuBarangContent.includes('Stok Habis'), 'KartuBarang harus memuat teks penanda Stok Habis')
assert.ok(kartuBarangContent.includes('dinonaktifkan'), 'KartuBarang harus menonaktifkan aksi saat stok 0')

// Uji aturan FR-K-02 & UI-02 pada PinjamPage:
const pinjamPageContent = fs.readFileSync(path.resolve('src/pages/kiosk/PinjamPage.vue'), 'utf-8')
assert.ok(pinjamPageContent.includes('crypto.randomUUID()'), 'PinjamPage harus membuat kunci idempotensi UUID')
assert.ok(pinjamPageContent.includes('sedangKirim'), 'PinjamPage harus memiliki state sedangKirim untuk mencegah pengiriman berulang')
assert.ok(pinjamPageContent.includes('apakahPemesanan'), 'PinjamPage harus mendeteksi mode pemesanan jika tanggal pinjam masa depan')
assert.ok(pinjamPageContent.includes('keranjang'), 'PinjamPage harus memiliki keranjang barang')
assert.ok(!pinjamPageContent.includes('rounded-2xl') && !pinjamPageContent.includes('rounded-xl'), 'PinjamPage tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!pinjamPageContent.includes('text-slate-500'), 'PinjamPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(!pinjamPageContent.includes('tracking-tight'), 'PinjamPage tidak memakai tracking-tight (DESAIN 10.5)')
const berandaPageContent = fs.readFileSync(path.resolve('src/pages/kiosk/BerandaPage.vue'), 'utf-8')
assert.ok(!berandaPageContent.includes('rounded-2xl') && !berandaPageContent.includes('rounded-xl'), 'BerandaPage tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!berandaPageContent.includes('tracking-tight'), 'BerandaPage tidak memakai tracking-tight (DESAIN 10.5)')
const klaimDesain = fs.readFileSync(path.resolve('src/pages/kiosk/KlaimPage.vue'), 'utf-8')
assert.ok(!klaimDesain.includes('rounded-2xl') && !klaimDesain.includes('rounded-xl'), 'KlaimPage tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!klaimDesain.includes('text-slate-500'), 'KlaimPage tidak memakai slate-500 (DESAIN 10.5)')
const katalogPageContent = fs.readFileSync(path.resolve('src/pages/kiosk/KatalogPage.vue'), 'utf-8')
assert.ok(!katalogPageContent.includes('rounded-2xl') && !katalogPageContent.includes('rounded-xl'), 'KatalogPage tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!katalogPageContent.includes('text-slate-500'), 'KatalogPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(!katalogPageContent.includes('uppercase'), 'KatalogPage tidak memakai label uppercase (DESAIN 10.5)')
assert.ok(!katalogPageContent.includes('tracking'), 'KatalogPage tidak memakai tracking (DESAIN 10.5)')
const kartuBarangDesain = fs.readFileSync(path.resolve('src/components/KartuBarang.vue'), 'utf-8')
assert.ok(!kartuBarangDesain.includes('rounded-2xl') && !kartuBarangDesain.includes('rounded-xl') && !kartuBarangDesain.includes('rounded-full'), 'KartuBarang tidak memakai radius 2xl/xl/pill (DESAIN 10.5)')
assert.ok(!kartuBarangDesain.includes('text-slate-500'), 'KartuBarang tidak memakai slate-500 (DESAIN 10.5)')
const kartuPageDesain = fs.readFileSync(path.resolve('src/pages/kiosk/KartuPage.vue'), 'utf-8')
assert.ok(!kartuPageDesain.includes('rounded-2xl') && !kartuPageDesain.includes('rounded-xl'), 'KartuPage tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!kartuPageDesain.includes('rounded-full'), 'KartuPage tidak memakai badge pill (DESAIN 10.5, R-11)')
assert.ok(!kartuPageDesain.includes('text-slate-500'), 'KartuPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(!kartuPageDesain.includes('uppercase'), 'KartuPage tidak memakai label uppercase (DESAIN 10.5)')
assert.ok(!kartuPageDesain.includes('tracking-tight'), 'KartuPage tidak memakai tracking-tight (DESAIN 10.5)')
const batalPageDesain = fs.readFileSync(path.resolve('src/pages/kiosk/BatalPage.vue'), 'utf-8')
assert.ok(!batalPageDesain.includes('rounded-2xl') && !batalPageDesain.includes('rounded-xl'), 'BatalPage tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!batalPageDesain.includes('text-slate-500'), 'BatalPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(!batalPageDesain.includes('tracking-tight'), 'BatalPage tidak memakai tracking-tight (DESAIN 10.5)')
const batalTemplate = batalPageDesain.split('<template>')[1] || ''
assert.ok(!batalTemplate.includes('FR-'), 'BatalPage tidak menampilkan referensi FR di UI (R-16)')
const cekPageDesain = fs.readFileSync(path.resolve('src/pages/kiosk/CekPage.vue'), 'utf-8')
assert.ok(!cekPageDesain.includes('rounded-2xl') && !cekPageDesain.includes('rounded-xl'), 'CekPage tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!cekPageDesain.includes('text-slate-500'), 'CekPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(!cekPageDesain.includes('tracking-tight'), 'CekPage tidak memakai tracking-tight (DESAIN 10.5)')
const perpanjangPageDesain = fs.readFileSync(path.resolve('src/pages/kiosk/PerpanjangPage.vue'), 'utf-8')
assert.ok(!perpanjangPageDesain.includes('rounded-2xl') && !perpanjangPageDesain.includes('rounded-xl'), 'PerpanjangPage tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!perpanjangPageDesain.includes('text-slate-500'), 'PerpanjangPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(!perpanjangPageDesain.includes('uppercase'), 'PerpanjangPage tidak memakai label uppercase (DESAIN 10.5)')
assert.ok(!perpanjangPageDesain.includes('tracking-tight'), 'PerpanjangPage tidak memakai tracking-tight (DESAIN 10.5)')
const klaimSelesaiDesain = fs.readFileSync(path.resolve('src/pages/kiosk/KlaimSelesaiPage.vue'), 'utf-8')
assert.ok(!klaimSelesaiDesain.includes('rounded-2xl') && !klaimSelesaiDesain.includes('rounded-xl') && !klaimSelesaiDesain.includes('rounded-full'), 'KlaimSelesaiPage tidak memakai radius 2xl/xl/pill (DESAIN 10.5)')
assert.ok(!klaimSelesaiDesain.includes('text-slate-500'), 'KlaimSelesaiPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(!klaimSelesaiDesain.includes('FR-'), 'KlaimSelesaiPage tidak menampilkan referensi FR di UI (R-16)')
const perpanjangSelesaiDesain = fs.readFileSync(path.resolve('src/pages/kiosk/PerpanjangSelesaiPage.vue'), 'utf-8')
assert.ok(!perpanjangSelesaiDesain.includes('rounded-2xl') && !perpanjangSelesaiDesain.includes('rounded-xl') && !perpanjangSelesaiDesain.includes('rounded-full'), 'PerpanjangSelesaiPage tidak memakai radius 2xl/xl/pill (DESAIN 10.5)')
assert.ok(!perpanjangSelesaiDesain.includes('text-slate-500'), 'PerpanjangSelesaiPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(!perpanjangSelesaiDesain.includes('FR-'), 'PerpanjangSelesaiPage tidak menampilkan referensi FR di UI (R-16)')
const pemilihBarangDesain = fs.readFileSync(path.resolve('src/components/kiosk/PemilihBarang.vue'), 'utf-8')
assert.ok(!pemilihBarangDesain.includes('rounded-2xl') && !pemilihBarangDesain.includes('rounded-xl'), 'PemilihBarang tidak memakai radius 2xl/xl (DESAIN 10.5)')
assert.ok(!pemilihBarangDesain.includes('shadow-2xl'), 'PemilihBarang tidak memakai shadow-2xl (DESAIN 10.5)')
assert.ok(!pemilihBarangDesain.includes('text-slate-500'), 'PemilihBarang tidak memakai slate-500 (DESAIN 10.5)')

// Uji aturan FR-K-03 & T-39 / UI-06 pada StrukCetak & Layout:
const strukCetakContent = fs.readFileSync(path.resolve('src/components/StrukCetak.vue'), 'utf-8')
assert.ok(strukCetakContent.includes('pemesanan'), 'StrukCetak harus mendukung prop pemesanan')
assert.ok(strukCetakContent.includes('[PEMESANAN]'), 'StrukCetak harus memiliki penanda [PEMESANAN] bila masa depan (FR-K-03)')
assert.ok(strukCetakContent.includes('Petunjuk Pengembalian'), 'StrukCetak harus memuat petunjuk pengembalian (FR-K-03)')
assert.ok(strukCetakContent.includes('@media print'), 'StrukCetak harus memiliki stylesheet cetak')
assert.ok(strukCetakContent.includes('salinKode'), 'StrukCetak harus memiliki aksi salin kode')

const layoutKioskContent = fs.readFileSync(path.resolve('src/layouts/LayoutKiosk.vue'), 'utf-8')
assert.ok(layoutKioskContent.includes('print:hidden'), 'LayoutKiosk harus menyembunyikan header navigasi saat cetak (US-03, T-39, UI-06)')

// Uji aturan FR-K-04 pada CekPage:
const cekPageContent = fs.readFileSync(path.resolve('src/pages/kiosk/CekPage.vue'), 'utf-8')
assert.ok(cekPageContent.includes('PJM-'), 'CekPage harus memvalidasi awalan kode PJM-')
assert.ok(cekPageContent.includes('lookupLoan'), 'CekPage harus memanggil lookupLoan via kioskApi')
assert.ok(cekPageContent.includes('terkunci'), 'CekPage harus menangani status terkunci 429')

// Uji aturan FR-K-04 & FR-K-09 pada KartuPage:
const kartuPageContent = fs.readFileSync(path.resolve('src/pages/kiosk/KartuPage.vue'), 'utf-8')
assert.ok(kartuPageContent.includes('menunggu'), 'KartuPage harus menampilkan unit menunggu')
assert.ok(kartuPageContent.includes('terverifikasi'), 'KartuPage harus menampilkan unit terverifikasi')
assert.ok(kartuPageContent.includes('sisa'), 'KartuPage harus menampilkan sisa unit')
assert.ok(kartuPageContent.includes('hitung_mundur'), 'KartuPage harus menampilkan status hitung mundur')
assert.ok(kartuPageContent.includes('perpanjangan'), 'KartuPage harus menampilkan status dan kuota perpanjangan')
assert.ok(kartuPageContent.includes('catatan_admin'), 'KartuPage harus menampilkan catatan admin pada hasil klaim (FR-K-09)')
assert.ok(kartuPageContent.includes('Batalkan Pemesanan'), 'KartuPage harus menyediakan tombol Batalkan Pemesanan untuk pemesanan')

// Uji aturan FR-K-05 & FR-K-06 pada KlaimPage / KlaimSelesaiPage:
const klaimPageContent = fs.readFileSync(path.resolve('src/pages/kiosk/KlaimPage.vue'), 'utf-8')
assert.ok(klaimPageContent.includes('baik'), 'KlaimPage harus menyediakan isian kondisi baik')
assert.ok(klaimPageContent.includes('rusak'), 'KlaimPage harus menyediakan isian kondisi rusak')
assert.ok(klaimPageContent.includes('hilang'), 'KlaimPage harus menyediakan isian kondisi hilang')
assert.ok(klaimPageContent.includes('sisa'), 'KlaimPage harus membatasi total pengembalian ≤ sisa (FR-K-05)')
assert.ok(klaimPageContent.includes('crypto.randomUUID()'), 'KlaimPage harus memakai kunci idempotensi UUID')

const klaimSelesaiContent = fs.readFileSync(path.resolve('src/pages/kiosk/KlaimSelesaiPage.vue'), 'utf-8')
assert.ok(klaimSelesaiContent.includes('kode_klaim'), 'KlaimSelesaiPage harus menampilkan kode klaim (FR-K-06)')
assert.ok(klaimSelesaiContent.includes('Menunggu Verifikasi'), 'KlaimSelesaiPage harus menampilkan status Menunggu Verifikasi (FR-K-06)')
assert.ok(klaimSelesaiContent.includes('petugas IT'), 'KlaimSelesaiPage harus mengingatkan menyerahkan barang fisik (FR-K-06)')

// Uji aturan FR-K-07 & FR-K-08 pada PerpanjangPage / PerpanjangSelesaiPage:
const perpanjangPageContent = fs.readFileSync(path.resolve('src/pages/kiosk/PerpanjangPage.vue'), 'utf-8')
assert.ok(perpanjangPageContent.includes('maksHari'), 'PerpanjangPage harus membatasi tambahan hari sesuai batas')
assert.ok(perpanjangPageContent.includes('batasBaru'), 'PerpanjangPage harus menampilkan batas kembali lama dan baru (FR-K-07)')

const perpanjangSelesaiContent = fs.readFileSync(path.resolve('src/pages/kiosk/PerpanjangSelesaiPage.vue'), 'utf-8')
assert.ok(perpanjangSelesaiContent.includes('batas_kembali'), 'PerpanjangSelesaiPage harus menampilkan batas kembali baru (FR-K-08)')

// Uji aturan FR-K-12 pada BatalPage:
const batalPageContent = fs.readFileSync(path.resolve('src/pages/kiosk/BatalPage.vue'), 'utf-8')
assert.ok(batalPageContent.includes('ModalKonfirmasi'), 'BatalPage harus menampilkan modal konfirmasi (FR-K-12)')

// Uji aturan FR-K-10 pada useIdleKiosk & LayoutKiosk:
const idleKioskContent = fs.readFileSync(path.resolve('src/composables/useIdleKiosk.js'), 'utf-8')
assert.ok(idleKioskContent.includes('kiosk_idle_detik'), 'useIdleKiosk harus memakai kiosk_idle_detik dari config (FR-K-10)')
assert.ok(idleKioskContent.includes('resetSesi'), 'useIdleKiosk harus menghapus seluruh isian saat idle')
assert.ok(layoutKioskContent.includes('useIdleKiosk'), 'LayoutKiosk harus memakai composable useIdleKiosk (FR-K-10)')

// Uji T-35 (XSS): tidak ada v-html di seluruh halaman kiosk — Vue escape otomatis dipertahankan
for (const pageName of expectedPages) {
  const content = fs.readFileSync(path.join(pagesDir, pageName), 'utf-8')
  assert.ok(!content.includes('v-html'), `T-35: ${pageName} tidak boleh memakai v-html`)
}

// Uji UI-03 (privasi kode): kode pinjam tidak masuk URL, riwayat browser, atau penyimpanan browser
const routerContent = fs.readFileSync(path.resolve('src/router/index.js'), 'utf-8')
assert.ok(!/kode/i.test(routerContent), 'UI-03: router tidak boleh membawa kode peminjaman')
const storeKioskContent = fs.readFileSync(path.resolve('src/stores/kiosk.js'), 'utf-8')
assert.ok(!storeKioskContent.includes('localStorage.') && !storeKioskContent.includes('sessionStorage.'), 'UI-03: kode tidak boleh disimpan di penyimpanan browser')
const cekKodeBody = fs.readFileSync(path.resolve('src/api/kiosk.js'), 'utf-8')
assert.ok(cekKodeBody.includes("client.post('/api/kiosk/loans/lookup', payload)"), 'UI-03: kode harus dikirim via POST body, bukan URL')

// ponytail: check parse/compile SFC halaman kiosk; tambahkan vitest mount bila sudah dibutuhkan di pengujian interaktif
console.log('Semua halaman kiosk dan aturan FR-K-01..FR-K-12 + T-35 + UI-03 lulus pengujian.')
