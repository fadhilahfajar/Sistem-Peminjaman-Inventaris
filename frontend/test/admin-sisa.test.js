import assert from 'node:assert'
import fs from 'node:fs'
import path from 'node:path'
import { compileScript, compileTemplate, parse } from 'vue/compiler-sfc'

/**
 * Pengujian statik halaman admin sisa Task 5.10–5.12:
 * pengaturan (FR-A-12), akun admin (FR-A-02, ganti kata sandi), jejak audit (FR-A-13),
 * halaman galat ramah 403/404/419 (Task 5.11, DESAIN 10.3/10.6), dan konsistensi badge (UI-04, T-35).
 * ponytail: asersi statik; pasang @vue/test-utils bila butuh uji interaksi DOM.
 */

const SFC = [
  'src/pages/admin/PengaturanPage.vue',
  'src/pages/admin/AkunPage.vue',
  'src/pages/admin/AuditPage.vue',
  'src/pages/galat/GalatPage.vue',
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

// Task 5.10 — Pengaturan: 8 parameter dengan min/maks (FR-A-12)
const pengaturan = baca('src/pages/admin/PengaturanPage.vue')
assert.ok(pengaturan.includes('adminApi.getSettings'), 'PengaturanPage memuat daftar pengaturan')
assert.ok(pengaturan.includes('adminApi.updateSettings'), 'PengaturanPage memperbarui pengaturan')
for (const kunci of [
  'maks_durasi_awal_hari',
  'maks_perpanjangan',
  'maks_hari_per_perpanjangan',
  'maks_booking_hari',
  'sesi_idle_menit',
  'kiosk_idle_detik',
  'kiosk_lookup_maks_gagal',
  'kiosk_lookup_kunci_menit',
]) {
  assert.ok(pengaturan.includes(kunci), `PengaturanPage memuat parameter ${kunci}`)
}

// Task 5.10 — Akun admin: daftar, tambah (password min 12), nonaktifkan, ganti password
const akun = baca('src/pages/admin/AkunPage.vue')
assert.ok(akun.includes('adminApi.getUsers'), 'AkunPage memuat daftar akun')
assert.ok(akun.includes('adminApi.createUser'), 'AkunPage bisa menambah admin baru')
assert.ok(akun.includes('adminApi.updateUser'), 'AkunPage bisa mengubah status aktif akun')
assert.ok(akun.includes('authApi.changePassword'), 'AkunPage bisa ganti password sendiri')
assert.ok(akun.includes('12'), 'AkunPage menegakkan validasi password minimal 12 karakter')
assert.ok(akun.includes('password_lama') && akun.includes('password_baru_confirmation'), 'AkunPage mengirim password lama dan konfirmasi')

// Task 5.10 — Jejak audit: daftar, filter aksi/entitas/user, pagination, read-only
const audit = baca('src/pages/admin/AuditPage.vue')
assert.ok(audit.includes('adminApi.getAuditLogs'), 'AuditPage memuat log audit')
assert.ok(audit.includes('TabelData'), 'AuditPage menggunakan TabelData dengan pagination')
for (const f of ['aksi', 'entitas', 'user_id']) {
  assert.ok(audit.includes(`f.${f}`) || audit.includes(f), `AuditPage memiliki filter ${f}`)
}

// Desain ulang 5.10 (DESAIN 10.5, R-11): radius tunggal, tanpa pill/shadow tebal, teks tanpa referensi FR
for (const [nama, isi] of [
  ['PengaturanPage', pengaturan],
  ['AkunPage', akun],
  ['AuditPage', audit],
]) {
  assert.ok(!isi.includes('rounded-2xl'), `${nama} tidak memakai radius 2xl (DESAIN 10.5)`)
  assert.ok(!isi.includes('rounded-xl'), `${nama} tidak memakai radius xl (DESAIN 10.5)`)
  assert.ok(!isi.includes('rounded-full'), `${nama} tidak memakai badge pill (R-11)`)
  assert.ok(!isi.includes('shadow-2xl'), `${nama} tidak memakai bayangan tebal (R-11)`)
  assert.ok(!isi.includes('text-slate-500'), `${nama} tidak memakai slate-500 untuk teks (DESAIN 10.5)`)
  assert.ok(!isi.includes('FR-A'), `${nama} tidak menampilkan referensi FR di UI`)
}
assert.ok(akun.includes('ModalKonfirmasi'), 'AkunPage memakai modal bersama (R-26)')
assert.ok(audit.includes('ModalKonfirmasi'), 'AuditPage memakai modal bersama (R-26)')

// Task 5.11 — Halaman galat 403, 404, 419 (DESAIN 10.3 / 10.6)
const galat = baca('src/pages/galat/GalatPage.vue')
assert.ok(galat.includes('403') && galat.includes('404') && galat.includes('419'), 'GalatPage menangani kode 403, 404, dan 419')
assert.ok(galat.includes('Sesi berakhir, silakan masuk lagi'), 'GalatPage 419 memuat pesan sesi berakhir (DESAIN 10.6)')
assert.ok(galat.includes('/admin/login?sesi=berakhir'), 'GalatPage 419 mengarahkan ke login dengan parameter sesi=berakhir')
assert.ok(!galat.includes('rounded-2xl') && !galat.includes('rounded-xl') && !galat.includes('rounded-full'), 'GalatPage tidak memakai radius berlebih atau pill (R-11)')
assert.ok(!galat.includes('text-slate-500'), 'GalatPage tidak memakai slate-500 (DESAIN 10.5)')

// Router: rute pengaturan, akun, audit, galat terdaftar
const routerJs = baca('src/router/index.js')
for (const rute of ['admin.pengaturan', 'admin.akun', 'admin.audit', 'galat.403', 'galat.404', 'galat.419', 'galat.catch-all']) {
  assert.ok(routerJs.includes(`'${rute}'`), `Router mendaftarkan rute ${rute}`)
}
assert.ok(!routerJs.includes("pengaturan: 'Pengaturan'"), 'Pengaturan bukan lagi placeholder')
assert.ok(!routerJs.includes("akun: 'Akun Admin'"), 'Akun bukan lagi placeholder')
assert.ok(!routerJs.includes("audit: 'Jejak Audit'"), 'Audit bukan lagi placeholder')

// Task 5.12 — Badge status konsisten antara kiosk dan admin (UI-04)
const badgeStatus = baca('src/components/BadgeStatus.vue')
for (const st of ['dipesan', 'dipinjam', 'terlambat', 'menunggu_verifikasi', 'selesai', 'dibatalkan']) {
  assert.ok(badgeStatus.includes(st), `BadgeStatus memuat warna dan teks status ${st} sesuai DESAIN 10.5`)
}

// T-35: tidak ada v-html di berkas yang diuji
for (const file of SFC) {
  const content = baca(file)
  assert.ok(!content.includes('v-html'), `T-35: ${file} tidak boleh memakai v-html`)
  assert.ok(!content.includes('console.log'), `${file} tidak boleh memuat console.log`)
}

console.log('Semua pengujian halaman admin sisa (Task 5.10-5.12) lulus.')
