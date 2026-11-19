import axios from 'axios'
import router from '@/router'
import { useSesiStore } from '@/stores/sesi'
import { useToast } from '@/composables/useToast'

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

// Satu janji pengalihan agar banyak permintaan gagal serentak tidak memicu redirect ganda.
let janjiArahUlang = null

function diRuteAdmin() {
  return router.currentRoute.value.path.startsWith('/admin')
}

function adalahPermintaanLogin(config) {
  const url = config?.url || ''
  return url.includes('/api/auth/login')
}

async function muatUlangCsrf() {
  // Pakai axios mentah agar tidak terpengaruh interceptor klien ini.
  await axios.get('/sanctum/csrf-cookie', {
    withCredentials: true,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  })
}

async function arahkanKeLoginSekali(lokasi) {
  if (janjiArahUlang) return janjiArahUlang

  janjiArahUlang = (async () => {
    try {
      const tujuanNama = lokasi?.name || 'admin.login'
      if (router.currentRoute.value.name === tujuanNama) {
        // Sudah di login: perbarui query bila perlu (mis. tambah sesi=berakhir).
        await router.replace(lokasi)
      } else {
        await router.push(lokasi)
      }
    } finally {
      // Lepas kunci setelah navigasi dijadwalkan/selesai agar burst berikutnya tidak ganda.
      janjiArahUlang = null
    }
  })()

  return janjiArahUlang
}

client.interceptors.response.use(
  (response) => response,
  async (error) => {
    // Jaringan putus atau tidak ada respons dari server
    if (!error.response) {
      error.pesanUmum = 'Tidak dapat terhubung ke server. Periksa koneksi dan coba lagi.'
      return Promise.reject(error)
    }

    const { status, data, config } = error.response
    const asli = error.config || {}

    // 419 CSRF: muat ulang cookie CSRF sekali lalu ulangi permintaan (kecuali login ditangani form).
    if (status === 419 && !asli._csrfUlang && !asli._lewatiCobaUlangCsrf) {
      asli._csrfUlang = true
      try {
        await muatUlangCsrf()
        return client.request(asli)
      } catch {
        // lanjut ke penanganan galat 419 di bawah
      }
    }

    // Permintaan login: biarkan galat ditangani formulir (inline), tanpa redirect/pesan sesi global.
    if (adalahPermintaanLogin(asli)) {
      if (status === 422) {
        // biarkan komponen form membaca errors per kolom
      } else if (status === 429) {
        const detik = error.response.headers['retry-after']
        const menit = detik ? Math.ceil(detik / 60) : 1
        error.pesanUmum = `Terlalu banyak percobaan, coba lagi dalam ${menit} menit.`
      } else if (status === 419) {
        // CSRF sudah dicoba ulang sekali dan tetap gagal
        error.pesanUmum = 'Permintaan tidak valid, muat ulang halaman.'
      } else if (status >= 500) {
        error.pesanUmum = 'Terjadi kesalahan pada server. Silakan coba lagi.'
      } else if (status === 401) {
        // Seharusnya tidak terjadi untuk login, tapi jangan arahkan sebagai "sesi berakhir".
        error.pesanUmum = data?.pesan || 'Gagal masuk.'
      }
      return Promise.reject(error)
    }

    // 401/419 pada permintaan selain login
    if ((status === 401 || status === 419) && !asli._lewatiArahUlangAuth) {
      // 419 yang tetap gagal setelah coba ulang CSRF: BUKAN "Sesi berakhir"
      if (status === 419) {
        error.pesanUmum = 'Permintaan tidak valid, muat ulang halaman.'
        if (diRuteAdmin()) {
          try {
            useToast().tampil(error.pesanUmum, 'galat')
          } catch {
            // abaikan jika toast belum siap
          }
        }
        return Promise.reject(error)
      }

      // 401: bedakan sesi habis (pernah masuk) vs belum pernah login
      if (status === 401 && diRuteAdmin()) {
        let pernah = false
        try {
          pernah = useSesiStore().pernahMasukSesiIni()
        } catch {
          pernah = false
        }

        const lokasi = pernah
          ? { name: 'admin.login', query: { sesi: 'berakhir' } }
          : { name: 'admin.login' }

        await arahkanKeLoginSekali(lokasi)
      }
      // Di kiosk, 401 tidak mengalihkan ke login admin.
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
 * Panggil untuk mendapatkan cookie CSRF.
 * Axios akan otomatis mengirim X-XSRF-TOKEN pada permintaan berikutnya
 * karena withXSRFToken: true.
 */
export function initCsrf() {
  return client.get('/sanctum/csrf-cookie', { _lewatiArahUlangAuth: true, _lewatiCobaUlangCsrf: true })
}

export default client
