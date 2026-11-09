/**
 * Utilitas tanggal lokal untuk format YYYY-MM-DD kalender.
 * Operasi kalender murni berbasis angka tanpa pergeseran zona waktu.
 */

export function formatYmd(date = new Date()) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export function hariIniYmd() {
  return formatYmd(new Date())
}

export function tambahHariYmd(tanggalStr, jumlahHari) {
  if (!tanggalStr) return ''
  const [y, m, d] = tanggalStr.split('-').map(Number)
  const date = new Date(y, m - 1, d)
  date.setDate(date.getDate() + jumlahHari)
  return formatYmd(date)
}

export function formatTanggalIndo(tanggalStr) {
  if (!tanggalStr) return ''
  const [y, m, d] = tanggalStr.split('-').map(Number)
  const date = new Date(y, m - 1, d)
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
  }).format(date)
}
