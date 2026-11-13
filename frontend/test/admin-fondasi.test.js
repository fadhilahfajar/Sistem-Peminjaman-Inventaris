import assert from 'node:assert'
import fs from 'node:fs'
import path from 'node:path'
import { compileScript, compileTemplate, parse } from 'vue/compiler-sfc'

/**
 * Pengujian statik fondasi panel admin (Task 5.1–5.4):
 * login + guard sesi (UI-08), layout admin, TabelData/FilterBar, dasbor polling (FR-A-15, FR-A-19).
 * ponytail: asersi statik; pasang @vue/test-utils bila butuh uji interaksi DOM.
 */

const SFC = [
  'src/pages/admin/LoginPage.vue',
  'src/pages/admin/DasborPage.vue',
  'src/pages/admin/PlaceholderAdmin.vue',
  'src/layouts/LayoutAdmin.vue',
  'src/components/Sidebar.vue',
  'src/components/Navbar.vue',
  'src/components/TabelData.vue',
  'src/components/FilterBar.vue',
  'src/components/KartuAngka.vue',
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

// Task 5.1 — Login admin: kirim email + password sesuai DESAIN 8.2
const loginPage = baca('src/pages/admin/LoginPage.vue')
assert.ok(loginPage.includes('authApi.login'), 'LoginPage harus memanggil authApi.login')
assert.ok(loginPage.includes('password: password.value'), 'LoginPage harus mengirim field password (DESAIN 8.2)')
assert.ok(loginPage.includes("sesi === 'berakhir'"), 'LoginPage harus menampilkan pesan sesi berakhir dari query (UI-08)')
assert.ok(loginPage.includes('redirect'), 'LoginPage harus kembali ke halaman tujuan setelah login')

// Task 5.1 — Kontrak auth di admin.js sesuai DESAIN 8.2
const adminJs = baca('src/api/admin.js')
assert.ok(adminJs.includes('@param {{ email: string, password: string }} kredensial'), 'authApi.login harus memakai field password')
assert.ok(!adminJs.includes('kata_sandi'), 'Kontrak auth tidak memakai kata_sandi (DESAIN 8.2)')
assert.ok(adminJs.includes('password_lama') && adminJs.includes('password_baru_confirmation'), 'authApi.changePassword harus memakai password_lama/password_baru_confirmation')

// Task 5.1 — Router guard dari /api/auth/me
const routerJs = baca('src/router/index.js')
assert.ok(routerJs.includes('beforeEach'), 'Router harus memiliki guard beforeEach')
assert.ok(routerJs.includes('useSesiStore'), 'Guard harus memakai store sesi')
assert.ok(routerJs.includes('muatProfil'), 'Guard harus memuat profil dari /api/auth/me')
assert.ok(routerJs.includes('butuhSesi'), 'Rute admin harus ditandai butuhSesi')

// Task 5.1 — Store sesi: memori saja, tanpa penyimpanan browser (UI-03)
const sesiStore = baca('src/stores/sesi.js')
assert.ok(sesiStore.includes('authApi.me()'), 'Store sesi memuat profil dari authApi.me')
assert.ok(!sesiStore.includes('localStorage.') && !sesiStore.includes('sessionStorage.'), 'Store sesi tidak boleh menyimpan ke penyimpanan browser')

// Task 5.2 — Layout admin: sidebar geser, navbar dengan keluar, lencana polling 30 detik
const layoutAdmin = baca('src/layouts/LayoutAdmin.vue')
assert.ok(layoutAdmin.includes('Sidebar') && layoutAdmin.includes('Navbar'), 'LayoutAdmin memakai komponen Sidebar dan Navbar')
assert.ok(layoutAdmin.includes('md:hidden'), 'Sidebar harus bergeser di layar kecil (UI-05)')
assert.ok(layoutAdmin.includes('keluar'), 'LayoutAdmin harus menyediakan aksi keluar')
assert.ok(layoutAdmin.includes('muatRingkasan'), 'LayoutAdmin harus memuat ringkasan untuk lencana (FR-A-15)')
assert.ok(layoutAdmin.includes('30_000'), 'Lencana diperbarui setiap 30 detik (FR-A-15)')

const sidebar = baca('src/components/Sidebar.vue')
for (const grup of ['Dasbor', 'Peminjaman', 'Verifikasi', 'Barang', 'Stok Rusak', 'Kategori', 'Divisi', 'Laporan', 'Pengaturan', 'Akun', 'Jejak Audit']) {
  assert.ok(sidebar.includes(grup), `Sidebar harus memiliki menu ${grup}`)
}
assert.ok(sidebar.includes('antrean_verifikasi'), 'Sidebar menampilkan lencana antrean verifikasi (FR-A-15)')

const navbar = baca('src/components/Navbar.vue')
assert.ok(navbar.includes('sesi.user.name'), 'Navbar menampilkan nama admin (DESAIN 10.3)')

// Task 5.3 — TabelData: urutan, pagination, mode kartu layar sempit
const tabelData = baca('src/components/TabelData.vue')
assert.ok(tabelData.includes('ubah-urutan'), 'TabelData harus emit ubah-urutan')
assert.ok(tabelData.includes('ubah-halaman'), 'TabelData harus emit ubah-halaman')
assert.ok(tabelData.includes('md:hidden'), 'TabelData harus punya mode kartu di layar sempit (UI-05)')
assert.ok(tabelData.includes('aria-sort'), 'TabelData menandai arah urutan dengan aria-sort')
assert.ok(tabelData.includes('animate-pulse'), 'TabelData menampilkan skeleton saat memuat (DESAIN 10.6)')

const filterBar = baca('src/components/FilterBar.vue')
assert.ok(filterBar.includes("emit('terapkan')"), 'FilterBar harus emit terapkan')
assert.ok(filterBar.includes("emit('atur-ulang')"), 'FilterBar harus emit atur-ulang')

// Task 5.4 — Dasbor: 4 kartu angka, daftar terlambat, polling 30 detik
const dasbor = baca('src/pages/admin/DasborPage.vue')
for (const kartu of ['Dipinjam', 'Terlambat', 'Menunggu Verifikasi', 'Stok Rusak']) {
  assert.ok(dasbor.includes(kartu), `Dasbor harus memiliki kartu ${kartu} (FR-A-19)`)
}
assert.ok(dasbor.includes('peminjaman_terlambat'), 'Dasbor menampilkan daftar peminjaman terlambat (FR-A-19)')
assert.ok(dasbor.includes('BadgeStatus'), 'Dasbor memakai BadgeStatus agar identik dengan layar lain (UI-04)')
assert.ok(dasbor.includes('30 detik'), 'Dasbor menyebut pembaruan 30 detik (FR-A-15)')

// T-35: tidak ada v-html di seluruh berkas yang diuji
for (const file of SFC) {
  const content = baca(file)
  assert.ok(!content.includes('v-html'), `T-35: ${file} tidak boleh memakai v-html`)
}

console.log('Semua pengujian fondasi panel admin (Task 5.1-5.4) lulus.')
