import client from './client'

/**
 * Klien API untuk mode Kiosk (DESAIN 8.1).
 * Seluruh endpoint publik, sebagian dibatasi laju.
 * Kode pinjam tidak pernah dikirim via URL, hanya di request body.
 */
export const kioskApi = {
  /**
   * Ambil parameter publik kiosk (maks_durasi_awal_hari, kiosk_idle_detik, dll.).
   */
  getConfig() {
    return client.get('/api/kiosk/config')
  },

  /**
   * Ambil katalog barang (hanya nama dan jumlah tersedia).
   * @param {{ q?: string, kategori?: string|number }} params
   */
  getItems(params = {}) {
    return client.get('/api/kiosk/items', { params })
  },

  /**
   * Ambil daftar kategori aktif.
   */
  getCategories() {
    return client.get('/api/kiosk/categories')
  },

  /**
   * Ambil daftar divisi aktif.
   */
  getDivisions() {
    return client.get('/api/kiosk/divisions')
  },

  /**
   * Buat peminjaman atau pemesanan baru.
   * Mengembalikan data transaksi dan kode pinjam (DESAIN 8.1).
   * @param {{
   *   nama_peminjam: string,
   *   division_id: number,
   *   tipe_peminjam: 'karyawan' | 'magang',
   *   telepon?: string|null,
   *   tanggal_pinjam: string,
   *   batas_kembali: string,
   *   items: Array<{ item_id: number, qty: number }>,
   *   idempotency_key: string
   * }} payload
   */
  buatLoan(payload) {
    return client.post('/api/kiosk/loans', payload)
  },

  /**
   * Cari kartu peminjaman berdasarkan kode (POST agar kode tidak masuk log URL).
   * @param {{ kode: string }} payload
   */
  lookupLoan(payload) {
    return client.post('/api/kiosk/loans/lookup', payload)
  },

  /**
   * Ajukan klaim pengembalian barang.
   * @param {{
   *   kode: string,
   *   lines: Array<{ item_id: number, qty: number, kondisi: string }>,
   *   catatan?: string,
   *   idempotency_key: string
   * }} payload
   */
  buatReturnClaim(payload) {
    return client.post('/api/kiosk/return-claims', payload)
  },

  /**
   * Ajukan perpanjangan batas pengembalian.
   * @param {{
   *   kode: string,
   *   tambahan_hari: number,
   *   alasan?: string
   * }} payload
   */
  perpanjangLoan(payload) {
    return client.post('/api/kiosk/extensions', payload)
  },

  /**
   * Batalkan pemesanan barang.
   * @param {{ kode: string }} payload
   */
  batalLoan(payload) {
    return client.post('/api/kiosk/loans/cancel', payload)
  },
}

export default kioskApi
