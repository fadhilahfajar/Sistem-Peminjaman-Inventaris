import assert from 'node:assert/strict'
import {
  gabungkanKeKeranjang,
  hitungTersembunyi,
  keteranganPilihan,
  ringkasanNama,
  statusPilihan,
  toggleDipilih,
} from './pemilihBarang.js'

const barang = [
  { id: 1, nama: 'Laptop', tersedia: 3 },
  { id: 2, nama: 'Mouse', tersedia: 5 },
  { id: 3, nama: 'Keyboard', tersedia: 0 },
]

// Toggle tidak mengubah array asal.
const awal = []
const satu = toggleDipilih(awal, 1)
assert.deepEqual(awal, [])
assert.deepEqual(satu, [1])
assert.deepEqual(toggleDipilih(satu, 2), [1, 2])
assert.deepEqual(toggleDipilih([1, 2], 1), [2])

// Status: yang sudah di form menang atas stok 0.
const diKeranjang = new Set([1])
assert.equal(statusPilihan(barang[0], diKeranjang), 'form')
assert.equal(statusPilihan(barang[1], diKeranjang), 'tersedia')
assert.equal(statusPilihan(barang[2], diKeranjang), 'habis')

// Ringkasan nama: maksimal tiga nama, sisanya jadi hitungan.
assert.equal(ringkasanNama([]), '')
assert.equal(ringkasanNama(['A']), 'A')
assert.equal(ringkasanNama(['A', 'B']), 'A dan B')
assert.equal(ringkasanNama(['A', 'B', 'C']), 'A, B, dan C')
assert.equal(ringkasanNama(['A', 'B', 'C', 'D']), 'A, B, C, dan 1 lainnya')
assert.equal(ringkasanNama(['A', 'B', 'C', 'D', 'E']), 'A, B, C, dan 2 lainnya')

// Keterangan aria-live mengikuti jumlah dan pencarian.
assert.equal(
  keteranganPilihan([]),
  'Ketuk barang untuk memilih. Anda bisa memilih lebih dari satu barang sekaligus.',
)
assert.equal(
  keteranganPilihan(barang.slice(0, 2)),
  '2 barang dipilih: Laptop dan Mouse. Jumlah tiap barang diatur di formulir setelah ini.',
)
assert.equal(hitungTersembunyi([barang[0]], [barang[1]]), 1)
assert.match(keteranganPilihan([barang[0]], 1), /Termasuk 1 barang yang tidak tampil karena pencarian/)

// Gabung: qty awal 1, tanpa duplikat, isian lama tidak berubah.
const hasil = gabungkanKeKeranjang([{ item_id: 1, nama: 'Laptop', tersedia: 3, qty: 4 }], [barang[0], barang[1]])
assert.deepEqual(hasil, [
  { item_id: 1, nama: 'Laptop', tersedia: 3, qty: 4 },
  { item_id: 2, nama: 'Mouse', tersedia: 5, qty: 1 },
])
assert.deepEqual(gabungkanKeKeranjang([], []), [])

console.log('pemilihBarang: semua uji lulus')
