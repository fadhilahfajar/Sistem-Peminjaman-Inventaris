import assert from 'node:assert'
import { formatTanggalIndo, formatWaktuCetak, formatWaktuIndo, formatYmd, hariIniYmd, tambahHariYmd } from '../src/utils/tanggal.js'

// Format YMD
const d = new Date(2026, 10, 9) // 9 Nov 2026
assert.strictEqual(formatYmd(d), '2026-11-09')

// Hari ini YMD regex
assert.match(hariIniYmd(), /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/)

// Tambah hari YMD
assert.strictEqual(tambahHariYmd('2026-11-09', 7), '2026-11-16')
assert.strictEqual(tambahHariYmd('2026-11-28', 5), '2026-12-03')
assert.strictEqual(tambahHariYmd('2026-12-31', 1), '2027-01-01')

// Format Indo
const indo = formatTanggalIndo('2026-11-09')
assert.ok(indo.includes('2026'), 'Harus memuat tahun 2026')

// Format Waktu Cetak
const waktu = formatWaktuCetak(d)
assert.ok(waktu.includes('WIB'), 'Harus memuat zona WIB')

// Format Waktu Indo
const waktuIndo = formatWaktuIndo('2026-11-09T10:30:00Z')
assert.ok(waktuIndo.includes('WIB'), 'Harus memuat zona WIB')

console.log('Semua pengujian utilitas tanggal lulus.')
