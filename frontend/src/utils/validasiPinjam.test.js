import assert from 'node:assert/strict'
import { normalisasiTelepon, validasiFormPinjam } from './validasiPinjam.js'

const config = {
  hariIni: '2026-11-20',
  maks_booking_hari: 7,
  maks_durasi_awal_hari: 7,
}

function formValid() {
  return {
    nama_peminjam: 'Budi Santoso',
    division_id: 1,
    tipe_peminjam: 'karyawan',
    telepon: '+62 812-3456-7890',
    keperluan: 'Membawa laptop ke rapat',
    tanggal_pinjam: '2026-11-20',
    batas_kembali: '2026-11-23',
    items: [{ item_id: 1, nama: 'Kabel HDMI', tersedia: 10, qty: 2 }],
  }
}

// T-43: normalisasi telepon.
assert.equal(normalisasiTelepon('+62 812-3456-7890'), '6281234567890')
assert.equal(normalisasiTelepon('0812 3456 7890'), '081234567890')
assert.equal(normalisasiTelepon('1234567'), '1234567')
assert.equal(normalisasiTelepon('0812abcd7890'), '0812abcd7890')
assert.equal(normalisasiTelepon('1234567890123456'), '1234567890123456')

// T-43: nilai hasil normalisasi yang tidak valid tetap ditolak validator.
for (const telepon of ['1234567', '0812abcd7890', '1234567890123456']) {
  const galat = validasiFormPinjam({ ...formValid(), telepon }, config)
  assert.ok(galat.telepon, `Telepon ${telepon} harus menghasilkan kesalahan`)
}

// Setiap kolom kosong menghasilkan kesalahan di kolomnya sendiri.
const kolomKosong = [
  ['nama_peminjam', ''],
  ['division_id', ''],
  ['tipe_peminjam', ''],
  ['telepon', ''],
  ['keperluan', ''],
  ['tanggal_pinjam', ''],
  ['batas_kembali', ''],
  ['items', []],
]

for (const [kunci, nilai] of kolomKosong) {
  const galat = validasiFormPinjam({ ...formValid(), [kunci]: nilai }, config)
  assert.ok(galat[kunci], `Kolom ${kunci} kosong harus menghasilkan kesalahan`)
}

// Nama, tipe, dan divisi terisi tetapi WhatsApp kosong tetap salah.
const tanpaWhatsapp = validasiFormPinjam({ ...formValid(), telepon: '' }, config)
assert.ok(tanpaWhatsapp.telepon)
assert.equal(tanpaWhatsapp.nama_peminjam, undefined)
assert.equal(tanpaWhatsapp.division_id, undefined)
assert.equal(tanpaWhatsapp.tipe_peminjam, undefined)

// T-44: keperluan dihitung per karakter dan minimal 3 setelah trim.
assert.ok(validasiFormPinjam({ ...formValid(), keperluan: 'ab' }, config).keperluan)
assert.ok(validasiFormPinjam({ ...formValid(), keperluan: '   ' }, config).keperluan)
assert.ok(validasiFormPinjam({ ...formValid(), keperluan: 'a'.repeat(256) }, config).keperluan)
assert.equal(validasiFormPinjam({ ...formValid(), keperluan: 'a'.repeat(255) }, config).keperluan, undefined)
assert.equal(validasiFormPinjam({ ...formValid(), keperluan: 'é'.repeat(255) }, config).keperluan, undefined)
assert.ok(validasiFormPinjam({ ...formValid(), keperluan: 'é'.repeat(256) }, config).keperluan)

// Semua kolom valid menghasilkan nol kesalahan.
assert.deepEqual(validasiFormPinjam(formValid(), config), {})

console.log('validasiPinjam: semua uji lulus')
