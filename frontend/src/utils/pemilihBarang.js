/** Logika murni popup pemilih barang kiosk. Tanpa Vue, tanpa DOM. */

/** Status tombol per baris: sudah di form, habis, atau bisa dipilih. */
export function statusPilihan(barang, idsDiKeranjang) {
  if (idsDiKeranjang.has(barang.id)) return 'form'
  if (Number(barang.tersedia) <= 0) return 'habis'
  return 'tersedia'
}

/** Kumpulan id baru; tidak mengubah array asal. */
export function toggleDipilih(dipilih, id) {
  return dipilih.includes(id) ? dipilih.filter((x) => x !== id) : [...dipilih, id]
}

/** Banyak barang terpilih yang tersembunyi oleh pencarian. */
export function hitungTersembunyi(barangDipilih, barangTerlihat) {
  const terlihat = new Set(barangTerlihat.map((b) => b.id))
  return barangDipilih.filter((b) => !terlihat.has(b.id)).length
}

/** "A", "A dan B", "A, B, dan C", lalu "A, B, C, dan 2 lainnya". */
export function ringkasanNama(nama, maks = 3) {
  if (nama.length === 0) return ''
  if (nama.length === 1) return nama[0]
  if (nama.length === 2) return `${nama[0]} dan ${nama[1]}`
  if (nama.length <= maks) {
    return `${nama.slice(0, -1).join(', ')}, dan ${nama[nama.length - 1]}`
  }
  return `${nama.slice(0, maks).join(', ')}, dan ${nama.length - maks} lainnya`
}

/** Teks aria-live di bawah daftar. */
export function keteranganPilihan(barangDipilih, jumlahTersembunyi = 0) {
  const n = barangDipilih.length
  if (n === 0) {
    return 'Ketuk barang untuk memilih. Anda bisa memilih lebih dari satu barang sekaligus.'
  }

  const inti = `${n} barang dipilih: ${ringkasanNama(barangDipilih.map((b) => b.nama))}. Jumlah tiap barang diatur di formulir setelah ini.`
  return jumlahTersembunyi > 0
    ? `${inti} Termasuk ${jumlahTersembunyi} barang yang tidak tampil karena pencarian.`
    : inti
}

/** Gabung tanpa duplikat; entri lama dipertahankan (termasuk qty-nya). */
export function gabungkanKeKeranjang(keranjang, barangDipilih) {
  const hasil = keranjang.map((k) => ({ ...k }))
  const ada = new Set(hasil.map((k) => k.item_id))

  for (const barang of barangDipilih) {
    if (ada.has(barang.id)) continue
    hasil.push({
      item_id: barang.id,
      nama: barang.nama,
      tersedia: barang.tersedia,
      qty: 1,
    })
    ada.add(barang.id)
  }
  return hasil
}
