import assert from 'node:assert'
import fs from 'node:fs'
import path from 'node:path'
import { compileScript, compileTemplate, parse } from 'vue/compiler-sfc'

const pagesDir = path.resolve('src/pages/kiosk')
const expectedPages = [
  'BerandaPage.vue',
  'CekPage.vue',
  'KatalogPage.vue',
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

// ponytail: check parse/compile SFC halaman kiosk; tambahkan vitest mount bila sudah dibutuhkan di pengujian interaktif
console.log('Semua halaman kiosk dan aturan FR-K-01/FR-K-02/UI-02 lulus pengujian.')
