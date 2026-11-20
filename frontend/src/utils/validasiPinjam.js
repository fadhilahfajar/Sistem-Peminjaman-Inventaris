/**
 * Validasi kemudahan sisi kiosk (FR-K-02, T-43, T-44).
 * Server tetap memutuskan sah tidaknya permintaan.
 */

import { hariIniYmd, tambahHariYmd } from './tanggal.js'

/** Trim, buang spasi/tanda hubung, lalu buang satu '+' di awal. */
export function normalisasiTelepon(nilai) {
  if (typeof nilai !== 'string') return ''

  return nilai.trim().replace(/[\s-]/gu, '').replace(/^\+/, '')
}

function panjangKarakter(teks) {
  return [...teks].length
}

/**
 * @param {object} form
 * @param {{ hariIni?: string, maks_booking_hari?: number, maks_durasi_awal_hari?: number }} config
 * @returns {Record<string, string[]>} kesalahan per kolom
 */
export function validasiFormPinjam(form, config = {}) {
  const kesalahan = {}
  const tambah = (kunci, pesan) => {
    if (!kesalahan[kunci]) kesalahan[kunci] = []
    kesalahan[kunci].push(pesan)
  }

  const hariIni = config.hariIni || hariIniYmd()
  const maksBooking = Number(config.maks_booking_hari) || 7
  const maksDurasi = Number(config.maks_durasi_awal_hari) || 7

  const nama = String(form.nama_peminjam ?? '').trim()
  if (!nama) tambah('nama_peminjam', 'Nama peminjam wajib diisi.')
  else if (panjangKarakter(nama) < 2) tambah('nama_peminjam', 'Nama peminjam minimal 2 karakter.')
  else if (panjangKarakter(nama) > 100) tambah('nama_peminjam', 'Nama peminjam maksimal 100 karakter.')

  if (!form.division_id) tambah('division_id', 'Divisi wajib dipilih.')

  if (!['karyawan', 'magang'].includes(form.tipe_peminjam)) {
    tambah('tipe_peminjam', 'Tipe peminjam wajib dipilih (karyawan atau magang).')
  }

  const telepon = normalisasiTelepon(form.telepon)
  if (!telepon) tambah('telepon', 'Nomor WhatsApp wajib diisi.')
  else if (!/^[0-9]{8,15}$/.test(telepon)) {
    tambah('telepon', 'Nomor WhatsApp hanya berisi angka, 8 sampai 15 digit.')
  }

  const keperluan = String(form.keperluan ?? '').trim()
  if (!keperluan) tambah('keperluan', 'Keperluan wajib diisi.')
  else if (panjangKarakter(keperluan) < 3) tambah('keperluan', 'Keperluan minimal 3 karakter.')
  else if (panjangKarakter(keperluan) > 255) tambah('keperluan', 'Keperluan maksimal 255 karakter.')

  const tanggalPinjam = String(form.tanggal_pinjam ?? '').trim()
  if (!tanggalPinjam) tambah('tanggal_pinjam', 'Tanggal pinjam wajib diisi.')
  else if (tanggalPinjam < hariIni) tambah('tanggal_pinjam', 'Tanggal pinjam tidak boleh di masa lalu.')
  else if (tanggalPinjam > tambahHariYmd(hariIni, maksBooking)) {
    tambah('tanggal_pinjam', `Tanggal pinjam paling jauh ${maksBooking} hari dari hari ini.`)
  }

  const batasKembali = String(form.batas_kembali ?? '').trim()
  if (!batasKembali) tambah('batas_kembali', 'Batas kembali wajib diisi.')
  else if (tanggalPinjam && batasKembali < tanggalPinjam) {
    tambah('batas_kembali', 'Batas kembali tidak boleh sebelum tanggal pinjam.')
  } else if (tanggalPinjam && batasKembali > tambahHariYmd(tanggalPinjam, maksDurasi)) {
    tambah('batas_kembali', `Batas kembali maksimal ${maksDurasi} hari dari tanggal pinjam.`)
  }

  const items = Array.isArray(form.items) ? form.items : []
  if (items.length === 0) tambah('items', 'Pilih minimal satu barang untuk dipinjam.')

  items.forEach((item, idx) => {
    const qty = Number(item.qty)
    const tersedia = Number(item.tersedia)
    const kunci = `items.${idx}.qty`

    if (!Number.isInteger(qty) || qty < 1) {
      tambah(kunci, 'Jumlah barang harus minimal 1 unit.')
    } else if (Number.isFinite(tersedia) && qty > tersedia) {
      tambah(kunci, `Jumlah melebihi stok yang tersedia (${tersedia} unit).`)
    }
  })

  return kesalahan
}
