import axios from 'axios'
import router from '@/router'

// Satu instance untuk seluruh aplikasi. Permintaan relatif ke origin yang sama
// (Vite meneruskan /api dan /sanctum ke backend), jadi cookie sesi Sanctum
// dan CSRF bekerja tanpa CORS.
const client = axios.create({
  baseURL: '/',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/**
 * Flag agar redirect 401/419 hanya terjadi sekali
 * meskipun beberapa permintaan gagal bersamaan.
 */
let sedangRedirect = false

client.interceptors.response.use(
  (response) => response,
  async (error) => {
    // Jaringan putus atau tidak ada respons dari server
    if (!error.response) {
      error.pesanUmum = 'Tidak dapat terhubung ke server. Periksa koneksi dan coba lagi.'
      return Promise.reject(error)
    }

    const { status, data } = error.response

    // 401 / 419 — sesi habis atau CSRF kedaluwarsa → arahkan ke login (DESAIN 10.6)
    if ((status === 401 || status === 419) && !sedangRedirect) {
      sedangRedirect = true

      // Tentukan halaman asal: admin → login admin, kiosk → cukup tolak
      const rute = router.currentRoute.value
      const diAdmin = rute.path.startsWith('/admin')

      if (diAdmin) {
        await router.push({
          name: 'admin.login',
          query: { sesi: 'berakhir' },
        })
      }
      // Di kiosk, 419 biasanya berarti CSRF kedaluwarsa.
      // Coba perbarui token CSRF secara otomatis, lalu ulangi permintaan.
      if (status === 419 && !diAdmin) {
        try {
          await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
          sedangRedirect = false
          return client.request(error.config)
        } catch {
          // Gagal memperbarui CSRF, biarkan galat diteruskan
        }
      }

      sedangRedirect = false
    }

    // 409 — Konflik: stok berubah, data sudah diproses (DESAIN 10.6)
    if (status === 409) {
      error.pesanUmum = data?.pesan || 'Data telah berubah. Halaman akan dimuat ulang.'
    }

    // 422 — Validasi: error.response.data.errors berisi detail per kolom
    // Ditangani oleh komponen form masing-masing, tidak perlu pesan umum.

    // 429 — Terlalu banyak percobaan
    if (status === 429) {
      const detik = error.response.headers['retry-after']
      const menit = detik ? Math.ceil(detik / 60) : 1
      error.pesanUmum = `Terlalu banyak percobaan, coba lagi dalam ${menit} menit.`
    }

    // 403 — Dilarang
    if (status === 403) {
      error.pesanUmum = data?.pesan || 'Anda tidak memiliki akses untuk tindakan ini.'
    }

    // 500 — Server error, tanpa informasi internal (DESAIN 10.6)
    if (status >= 500) {
      error.pesanUmum = 'Terjadi kesalahan pada server. Silakan coba lagi.'
    }

    return Promise.reject(error)
  },
)

/**
 * Panggil sekali saat aplikasi dimuat untuk mendapatkan cookie CSRF.
 * Axios akan otomatis mengirim X-XSRF-TOKEN pada permintaan berikutnya
 * karena withXSRFToken: true.
 */
export function initCsrf() {
  return client.get('/sanctum/csrf-cookie')
}

export default client
