/**
 * Logika murni klaim pengembalian kiosk (FR-K-05, BR-04, BR-20).
 * Kontrak request mengikuti SimpanKlaimRequest.php yang berjalan:
 * { kode, lines[{ item_id, kondisi, qty }], catatan, idempotency_key }.
 * Bentuk internal Service ({ baris, catatan_peminjam }) bukan badan request.
 */

const KONDISI = ['baik', 'rusak', 'hilang']

export function bentukPayloadKlaim({ kode, barang = [], isian = {}, catatan = '', idempotencyKey }) {
  const lines = []

  for (const item of barang) {
    const isi = isian[item.item_id] || {}
    for (const kondisi of KONDISI) {
      const qty = Number(isi[kondisi]) || 0
      if (qty > 0) {
        lines.push({ item_id: item.item_id, kondisi, qty })
      }
    }
  }

  return {
    kode,
    lines,
    catatan: String(catatan ?? '').trim() || null,
    idempotency_key: idempotencyKey,
  }
}

/** Pemetaan respons galat ke pesan tampilan. Tanpa informasi internal. */
export function pesanGalatKlaim(err) {
  const status = err?.response?.status
  const data = err?.response?.data || {}
  const header = err?.response?.headers || {}
  const retryAfter = Number(header['retry-after'] ?? header['Retry-After']) || 0

  if (status === 409) {
    return {
      pesan: data.pesan || 'Jumlah klaim melebihi sisa unit. Mohon periksa kembali jumlah yang diajukan.',
      kode: data.kode || 'klaim_melebihi_sisa',
      muatUlangKartu: true,
      retryAfter: 0,
    }
  }

  if (status === 429) {
    return {
      pesan: retryAfter > 0
        ? `Terlalu banyak percobaan. Coba lagi dalam ${retryAfter} detik.`
        : (data.pesan || 'Terlalu banyak percobaan. Silakan coba lagi beberapa saat.'),
      kode: data.kode || 'lookup_terkunci',
      muatUlangKartu: false,
      retryAfter,
    }
  }

  if (status === 404) {
    return {
      pesan: data.pesan || 'Kode peminjaman tidak ditemukan.',
      kode: data.kode || 'kode_tidak_ditemukan',
      muatUlangKartu: false,
      retryAfter: 0,
    }
  }

  if (status === 422) {
    const pertama = Object.values(data.errors || {}).flat().find(Boolean)
    return {
      pesan: pertama || data.pesan || 'Mohon periksa kembali isian klaim.',
      kode: data.kode || 'validasi_gagal',
      muatUlangKartu: false,
      retryAfter: 0,
    }
  }

  return {
    pesan: err?.pesanUmum || data.pesan || 'Gagal mengajukan klaim. Silakan coba lagi.',
    kode: data.kode || '',
    muatUlangKartu: false,
    retryAfter: 0,
  }
}
