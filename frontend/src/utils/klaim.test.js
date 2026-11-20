import assert from 'node:assert/strict'
import { bentukPayloadKlaim, pesanGalatKlaim } from './klaim.js'

// Badan request sesuai SimpanKlaimRequest.php (bukan bentuk internal Service).
const payload = bentukPayloadKlaim({
  kode: 'PJM-A7B8C9',
  barang: [
    { item_id: 1, sisa: 3 },
    { item_id: 2, sisa: 2 },
  ],
  isian: {
    1: { baik: 2, rusak: 0, hilang: 0 },
    2: { baik: 1, rusak: 0, hilang: 1 },
  },
  catatan: '  Kabel HDMI berfungsi baik.  ',
  idempotencyKey: 'uuid-contoh',
})

assert.deepEqual(Object.keys(payload).sort(), ['catatan', 'idempotency_key', 'kode', 'lines'])
assert.equal(payload.kode, 'PJM-A7B8C9')
assert.equal(payload.idempotency_key, 'uuid-contoh')
assert.equal(payload.catatan, 'Kabel HDMI berfungsi baik.')
assert.deepEqual(payload.lines, [
  { item_id: 1, kondisi: 'baik', qty: 2 },
  { item_id: 2, kondisi: 'baik', qty: 1 },
  { item_id: 2, kondisi: 'hilang', qty: 1 },
])

// Nol tidak dikirim; catatan kosong menjadi null.
assert.deepEqual(
  bentukPayloadKlaim({ kode: 'PJM-X', barang: [{ item_id: 1 }], isian: { 1: { baik: 0 } }, catatan: '  ' }).lines,
  [],
)
assert.equal(bentukPayloadKlaim({ kode: 'PJM-X', catatan: '' }).catatan, null)

// Pemetaan galat submit klaim.
const konflik = pesanGalatKlaim({
  response: {
    status: 409,
    data: { kode: 'klaim_melebihi_sisa', pesan: 'Jumlah klaim Kabel HDMI melebihi sisa (1 unit).' },
  },
})
assert.equal(konflik.kode, 'klaim_melebihi_sisa')
assert.equal(konflik.pesan, 'Jumlah klaim Kabel HDMI melebihi sisa (1 unit).')
assert.equal(konflik.muatUlangKartu, true)

const tidakDitemukan = pesanGalatKlaim({
  response: { status: 404, data: { kode: 'kode_tidak_ditemukan', pesan: 'Kode peminjaman tidak ditemukan.' } },
})
assert.equal(tidakDitemukan.pesan, 'Kode peminjaman tidak ditemukan.')
assert.equal(tidakDitemukan.muatUlangKartu, false)

const validasi = pesanGalatKlaim({
  response: { status: 422, data: { errors: { 'lines.0.qty': ['Jumlah melebihi sisa.'] } } },
})
assert.equal(validasi.pesan, 'Jumlah melebihi sisa.')

const terkunci = pesanGalatKlaim({
  response: {
    status: 429,
    headers: { 'retry-after': '300' },
    data: { kode: 'lookup_terkunci', pesan: 'Terlalu banyak percobaan, coba lagi dalam 5 menit.' },
  },
})
assert.equal(terkunci.kode, 'lookup_terkunci')
assert.match(terkunci.pesan, /300 detik/)
assert.equal(terkunci.retryAfter, 300)

const jaringan = pesanGalatKlaim({ pesanUmum: 'Tidak dapat terhubung ke server.' })
assert.equal(jaringan.pesan, 'Tidak dapat terhubung ke server.')
assert.equal(pesanGalatKlaim({}).pesan, 'Gagal mengajukan klaim. Silakan coba lagi.')

console.log('klaim: semua uji lulus')
