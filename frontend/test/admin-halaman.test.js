import assert from 'node:assert'
import fs from 'node:fs'
import path from 'node:path'
import { compileScript, compileTemplate, parse } from 'vue/compiler-sfc'

/**
 * Pengujian statik halaman admin Task 5.5–5.9:
 * barang (FR-A-03, FR-A-09, FR-A-10), peminjaman + detail (FR-A-05..08, BR-13/16/25),
 * verifikasi (FR-A-04, BR-06), stok rusak (FR-A-09), kategori/divisi (BR-27/28/33).
 * ponytail: asersi statik; pasang @vue/test-utils bila butuh uji interaksi DOM.
 */

const SFC = [
  'src/pages/admin/BarangPage.vue',
  'src/pages/admin/BarangFormPage.vue',
  'src/pages/admin/PeminjamanPage.vue',
  'src/pages/admin/DetailPeminjamanPage.vue',
  'src/pages/admin/VerifikasiPage.vue',
  'src/pages/admin/VerifikasiDetailPage.vue',
  'src/pages/admin/StokRusakPage.vue',
  'src/pages/admin/KategoriPage.vue',
  'src/pages/admin/DivisiPage.vue',
  'src/pages/admin/LaporanPage.vue',
  'src/components/LiniMasa.vue',
]

for (const file of SFC) {
  const content = fs.readFileSync(path.resolve(file), 'utf-8')
  const { descriptor, errors } = parse(content)
  assert.strictEqual(errors.length, 0, `Parse error pada ${file}: ${errors[0]?.message}`)

  if (descriptor.scriptSetup) {
    compileScript(descriptor, { id: file })
  }
  if (descriptor.template) {
    const result = compileTemplate({
      id: file,
      filename: file,
      source: descriptor.template.content,
    })
    assert.strictEqual(result.errors.length, 0, `Template compile error pada ${file}: ${result.errors[0]?.message}`)
  }
}

const baca = (file) => fs.readFileSync(path.resolve(file), 'utf-8')

// Task 5.5 — Barang: daftar + filter, tambah/ubah/hapus, penyesuaian stok, tandai rusak
const barang = baca('src/pages/admin/BarangPage.vue')
assert.ok(barang.includes('adminApi.getItems'), 'BarangPage memuat daftar barang')
assert.ok(barang.includes('deleteItem'), 'BarangPage punya hapus soft-delete (FR-A-03)')
assert.ok(barang.includes('adjustStock'), 'BarangPage punya penyesuaian stok (FR-A-10, T-05)')
assert.ok(barang.includes('markDamaged'), 'BarangPage punya tandai rusak (FR-A-09)')
assert.ok(barang.includes('arah: arah.value'), 'Penyesuaian stok kirim arah tambah/kurang')
assert.ok(barang.includes('alasan'), 'Penyesuaian stok minta alasan wajib')
assert.ok(!barang.includes('rounded-2xl'), 'BarangPage tidak memakai radius 2xl (DESAIN 10.5)')
assert.ok(!barang.includes('rounded-full'), 'BarangPage tidak memakai badge pill (R-11)')

const barangForm = baca('src/pages/admin/BarangFormPage.vue')
assert.ok(barangForm.includes('createItem') && barangForm.includes('updateItem'), 'BarangFormPage tambah dan ubah barang')
assert.ok(barangForm.includes('jumlah_awal'), 'BarangFormPage kirim jumlah_awal saat tambah')
assert.ok(barangForm.includes('boleh_diperpanjang'), 'BarangFormPage kirim boleh_diperpanjang (FR-A-03)')
assert.ok(barangForm.includes('getItem'), 'BarangFormPage ubah memuat barang via getItem')
assert.ok(!barangForm.includes('rounded-2xl'), 'BarangFormPage tidak memakai radius 2xl (DESAIN 10.5)')
assert.ok(!barangForm.includes('text-slate-500'), 'BarangFormPage tidak memakai slate-500 untuk subjudul (DESAIN 10.5)')
assert.ok(!barangForm.includes('FR-A'), 'BarangFormPage tidak memakai referensi FR di UI')

// Task 5.6 — Peminjaman: filter lengkap FR-A-05, klik baris, chips ringkasan
const peminjaman = baca('src/pages/admin/PeminjamanPage.vue')
for (const filter of ['status', 'barang', 'kategori', 'divisi', 'tipe_peminjam', 'dari', 'sampai', 'q', 'ada_catatan', 'ada_kerusakan', 'belum_kembali']) {
  assert.ok(peminjaman.includes(`f.${filter}`) || peminjaman.includes(`'${filter}'`), `PeminjamanPage punya filter ${filter} (FR-A-05)`)
}
assert.ok(peminjaman.includes('klik-baris'), 'PeminjamanPage baris diklik untuk detail')
assert.ok(peminjaman.includes('sesi.ringkasan'), 'PeminjamanPage menampilkan chips ringkasan dari sesi')
assert.ok(!peminjaman.includes('rounded-xl'), 'PeminjamanPage tidak memakai radius 2xl/ex (DESAIN 10.5)')
assert.ok(!peminjaman.includes('text-slate-500'), 'PeminjamanPage tidak memakai slate-500 untuk subjudul (DESAIN 10.5)')

// Detail peminjaman: lini masa + koreksi BR-16 + perpanjang BR-13 + batal BR-25
const detail = baca('src/pages/admin/DetailPeminjamanPage.vue')
assert.ok(detail.includes('LiniMasa'), 'DetailPeminjamanPage menampilkan lini masa (FR-A-06)')
assert.ok(detail.includes('koreksiLoan') && detail.includes('alasan'), 'DetailPeminjamanPage punya koreksi dengan alasan wajib (FR-A-07, BR-16)')
assert.ok(detail.includes('perpanjangLoan') && detail.includes('tambahan_hari'), 'DetailPeminjamanPage punya perpanjangan admin (FR-A-08, BR-13)')
assert.ok(detail.includes('batalLoan'), 'DetailPeminjamanPage punya pembatalan admin (BR-25)')
assert.ok(detail.includes('lini_masa'), 'DetailPeminjamanPage memakai data lini_masa dari API')
assert.ok(!detail.includes('rounded-2xl') && !detail.includes('rounded-xl'), 'DetailPeminjamanPage tidak memakai radius berlebih (DESAIN 10.5)')
assert.ok(!detail.includes('text-slate-500'), 'DetailPeminjamanPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(detail.includes('ModalKonfirmasi'), 'DetailPeminjamanPage memakai modal bersama (R-26)')

// Task 5.7 — Verifikasi: tab antrean/riwayat, pembagian per baris, aksi cepat, BR-06
const verifikasi = baca('src/pages/admin/VerifikasiPage.vue')
assert.ok(verifikasi.includes("status = 'diverifikasi'"), 'VerifikasiPage tab riwayat memakai status=diverifikasi')
assert.ok(verifikasi.includes('antrean_verifikasi'), 'VerifikasiPage menampilkan jumlah antrean')
assert.ok(verifikasi.includes('klik-baris'), 'VerifikasiPage baris diklik untuk detail')

const verifikasiDetail = baca('src/pages/admin/VerifikasiDetailPage.vue')
for (const kunci of ['qty_baik', 'qty_rusak', 'qty_hilang', 'qty_ditolak', 'line_id']) {
  assert.ok(verifikasiDetail.includes(kunci), `VerifikasiDetailPage membagi baris dengan ${kunci} (FR-A-04)`)
}
assert.ok(verifikasiDetail.includes('Setujui sesuai laporan'), 'VerifikasiDetailPage punya aksi cepat setujui sesuai laporan (FR-A-04)')
assert.ok(verifikasiDetail.includes('semuaPas'), 'VerifikasiDetailPage simpan hanya bila semua baris dibagi pas')
assert.ok(verifikasiDetail.includes('perluCatatan') && verifikasiDetail.includes('catatan_admin'), 'VerifikasiDetailPage catatan admin wajib sesuai BR-06')
assert.ok(verifikasiDetail.includes('verifyReturnClaim'), 'VerifikasiDetailPage kirim verifikasi ke API')
assert.ok(verifikasiDetail.includes('kondisi_dilaporkan'), 'VerifikasiDetailPage membandingkan hasil dengan kondisi dilaporkan')
assert.ok(!verifikasi.includes('rounded-2xl'), 'VerifikasiPage/Detail tidak memakai radius 2xl (DESAIN 10.5)')
assert.ok(!verifikasi.includes('rounded-xl'), 'VerifikasiPage/Detail tidak memakai radius xl (DESAIN 10.5)')
assert.ok(!verifikasiDetail.includes('FR-A'), 'VerifikasiDetailPage tidak memakai referensi FR di UI')

// Task 5.8 — Stok rusak: selesai diperbaiki + afkir
const stokRusak = baca('src/pages/admin/StokRusakPage.vue')
assert.ok(stokRusak.includes('getRepairs'), 'StokRusakPage memuat daftar barang rusak')
assert.ok(stokRusak.includes('repairComplete'), 'StokRusakPage punya aksi selesai diperbaiki (BR-09)')
assert.ok(stokRusak.includes('writeOff'), 'StokRusakPage punya aksi afkir')
assert.ok(stokRusak.includes('alasan'), 'Afkir minta alasan wajib')
assert.ok(!stokRusak.includes('rounded-2xl'), 'StokRusakPage tidak memakai radius 2xl (DESAIN 10.5)')
assert.ok(!stokRusak.includes('rounded-xl'), 'StokRusakPage tidak memakai radius xl (DESAIN 10.5)')
assert.ok(!stokRusak.includes('text-slate-500'), 'StokRusakPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(stokRusak.includes('ModalKonfirmasi'), 'StokRusakPage memakai modal bersama agar Escape dan klik luar bekerja (R-26)')
assert.ok(stokRusak.includes('errors.jumlah'), 'Galat jumlah ditampilkan di form (R-27)')

// Task 5.9 — Kategori dan divisi: tambah/ubah nama/toggle aktif, BR-27/28/33
const kategori = baca('src/pages/admin/KategoriPage.vue')
assert.ok(kategori.includes('createCategory') && kategori.includes('updateCategory'), 'KategoriPage tambah dan ubah kategori (BR-28)')
assert.ok(kategori.includes('aktif: !kategori.aktif'), 'KategoriPage toggle aktif')
assert.ok(kategori.includes('Konfirmasi') || kategori.includes('konfirmasi'), 'Nonaktif kategori lewat konfirmasi')
assert.ok(!kategori.includes('rounded-2xl') && !kategori.includes('rounded-xl') && !kategori.includes('rounded-full'), 'KategoriPage tidak memakai radius berlebih atau badge pill (R-11)')
assert.ok(!kategori.includes('text-slate-500'), 'KategoriPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(kategori.includes('ModalKonfirmasi'), 'KategoriPage memakai modal bersama (R-26)')

const divisi = baca('src/pages/admin/DivisiPage.vue')
assert.ok(divisi.includes('createDivision') && divisi.includes('updateDivision'), 'DivisiPage tambah dan ubah divisi (BR-27)')
assert.ok(divisi.includes('aktif: !divisi.aktif'), 'DivisiPage toggle aktif (BR-27)')
assert.ok(!divisi.includes('rounded-2xl') && !divisi.includes('rounded-xl') && !divisi.includes('rounded-full'), 'DivisiPage tidak memakai radius berlebih atau badge pill (R-11)')
assert.ok(!divisi.includes('text-slate-500'), 'DivisiPage tidak memakai slate-500 (DESAIN 10.5)')
assert.ok(divisi.includes('ModalKonfirmasi'), 'DivisiPage memakai modal bersama (R-26)')

// Router: 6 layar baru + rute nested, placeholder tersisa untuk 5.10–5.12
const routerJs = baca('src/router/index.js')
for (const rute of [
  'admin.peminjaman-detail',
  'admin.verifikasi-detail',
  'admin.barang-baru',
  'admin.barang-ubah',
  'admin.stok-rusak',
  'admin.kategori',
  'admin.divisi',
]) {
  assert.ok(routerJs.includes(`'${rute}'`), `Router punya rute ${rute}`)
}
assert.ok(routerJs.includes("'admin.laporan'") && routerJs.includes('@/pages/admin/LaporanPage.vue'), 'Router memakai halaman laporan untuk Task 6.3')
assert.ok(!routerJs.includes("peminjaman: 'Daftar Peminjaman'"), 'Peminjaman sudah halaman sungguhan, bukan placeholder')

// Task 6.3 — pilih laporan, kirim filter sesuai jenis, unduh PDF
const laporan = baca('src/pages/admin/LaporanPage.vue')
for (const jenis of ['rekap', 'belum-kembali', 'stok', 'pengembalian']) {
  assert.ok(laporan.includes(`nilai: '${jenis}'`), `LaporanPage menyediakan jenis ${jenis}`)
}
for (const filter of ['barang', 'kategori', 'divisi', 'tipe_peminjam', 'status', 'dari', 'sampai', 'q', 'ada_catatan', 'ada_kerusakan', 'belum_kembali']) {
  assert.ok(laporan.includes(`'${filter}'`), `LaporanPage mendukung filter ${filter}`)
}
assert.ok(laporan.includes('adminApi.getReportPdf'), 'LaporanPage meminta berkas PDF dari API')
assert.ok(laporan.includes('URL.createObjectURL') && laporan.includes('tautan.download'), 'LaporanPage mengunduh PDF dengan nama berkas')
assert.ok(laporan.includes("tampilkanFilter('dari') && filter.value.dari"), 'Validasi tanggal hanya berlaku saat laporan memiliki filter tanggal')
assert.ok(laporan.includes('Tanggal awal tidak boleh melebihi tanggal akhir.'), 'LaporanPage memvalidasi rentang tanggal')

// T-35: tidak ada v-html, dan tidak ada kode uji di berkas halaman
for (const file of SFC) {
  const content = baca(file)
  assert.ok(!content.includes('v-html'), `T-35: ${file} tidak boleh memakai v-html`)
  assert.ok(!content.includes('console.log'), `${file} tidak boleh memuat console.log`)
}

console.log('Semua pengujian halaman admin (Task 5.5-5.9) lulus.')
