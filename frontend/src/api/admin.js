import client from './client'

/**
 * Klien API untuk Autentikasi dan Panel Admin (DESAIN 8.2).
 * Seluruh endpoint admin memerlukan sesi login aktif (Sanctum SPA cookie).
 */
export const authApi = {
  /**
   * Login admin.
   * @param {{ email: string, kata_sandi: string }} kredensial
   */
  login(kredensial) {
    return client.post('/api/auth/login', kredensial)
  },

  /**
   * Logout admin.
   */
  logout() {
    return client.post('/api/auth/logout')
  },

  /**
   * Info pengguna yang sedang login.
   */
  me() {
    return client.get('/api/auth/me')
  },

  /**
   * Ganti kata sandi sendiri.
   * @param {{
   *   kata_sandi_lama: string,
   *   kata_sandi_baru: string,
   *   kata_sandi_baru_confirmation: string
   * }} payload
   */
  changePassword(payload) {
    return client.post('/api/auth/change-password', payload)
  },
}

export const adminApi = {
  // --- Pengguna / Akun Admin ---
  getUsers() {
    return client.get('/api/admin/users')
  },
  createUser(payload) {
    return client.post('/api/admin/users', payload)
  },
  updateUser(id, payload) {
    return client.patch(`/api/admin/users/${id}`, payload)
  },

  // --- Barang / Items ---
  getItems(params = {}) {
    return client.get('/api/admin/items', { params })
  },
  createItem(payload) {
    return client.post('/api/admin/items', payload)
  },
  updateItem(id, payload) {
    return client.put(`/api/admin/items/${id}`, payload)
  },
  deleteItem(id) {
    return client.delete(`/api/admin/items/${id}`)
  },
  adjustStock(itemId, payload) {
    return client.post(`/api/admin/items/${itemId}/stock-adjustments`, payload)
  },
  markDamaged(itemId, payload) {
    return client.post(`/api/admin/items/${itemId}/mark-damaged`, payload)
  },
  getRepairs() {
    return client.get('/api/admin/repairs')
  },
  repairComplete(itemId, payload) {
    return client.post(`/api/admin/items/${itemId}/repair-complete`, payload)
  },
  writeOff(itemId, payload) {
    return client.post(`/api/admin/items/${itemId}/write-off`, payload)
  },

  // --- Dasbor ---
  getSummary() {
    return client.get('/api/admin/summary')
  },

  // --- Peminjaman ---
  getLoans(params = {}) {
    return client.get('/api/admin/loans', { params })
  },
  getLoan(id) {
    return client.get(`/api/admin/loans/${id}`)
  },
  koreksiLoan(id, payload) {
    return client.patch(`/api/admin/loans/${id}`, payload)
  },
  perpanjangLoan(id, payload) {
    return client.post(`/api/admin/loans/${id}/extensions`, payload)
  },
  batalLoan(id, payload) {
    return client.post(`/api/admin/loans/${id}/cancel`, payload)
  },

  // --- Divisi & Kategori ---
  getDivisions() {
    return client.get('/api/admin/divisions')
  },
  createDivision(payload) {
    return client.post('/api/admin/divisions', payload)
  },
  updateDivision(id, payload) {
    return client.patch(`/api/admin/divisions/${id}`, payload)
  },
  getCategories() {
    return client.get('/api/admin/categories')
  },
  createCategory(payload) {
    return client.post('/api/admin/categories', payload)
  },
  updateCategory(id, payload) {
    return client.patch(`/api/admin/categories/${id}`, payload)
  },

  // --- Klaim Pengembalian & Verifikasi ---
  getReturnClaims(params = {}) {
    return client.get('/api/admin/return-claims', { params })
  },
  getReturnClaim(id) {
    return client.get(`/api/admin/return-claims/${id}`)
  },
  verifyReturnClaim(id, payload) {
    return client.post(`/api/admin/return-claims/${id}/verify`, payload)
  },

  // --- Laporan ---
  getReportPdf(jenis, params = {}) {
    return client.get(`/api/admin/reports/${jenis}.pdf`, {
      params,
      responseType: 'blob',
    })
  },

  // --- Pengaturan ---
  getSettings() {
    return client.get('/api/admin/settings')
  },
  updateSettings(payload) {
    return client.put('/api/admin/settings', payload)
  },

  // --- Jejak Audit ---
  getAuditLogs(params = {}) {
    return client.get('/api/admin/audit-logs', { params })
  },
}

export default {
  auth: authApi,
  admin: adminApi,
}
