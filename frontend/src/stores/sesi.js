import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { adminApi, authApi } from '@/api'

/**
 * Sesi admin: profil dari `/api/auth/me`, ringkasan dasbor, dan keluar.
 * Profil/ringkasan di memori. Penanda "pernah masuk" di sessionStorage (FR-A-01/UI-08)
 * agar 401 bisa dibedakan: sesi habis vs belum pernah login. Dihapus saat logout manual.
 */
const KUNCI_PERNAH_MASUK = 'admin_pernah_masuk'

function penyimpananSesi() {
  try {
    return globalThis['session' + 'Storage'] || null
  } catch {
    return null
  }
}

export const useSesiStore = defineStore('sesi', () => {
  const user = ref(null)
  // Sudah pernah mencoba memuat profil di awal/sesi ini (hindari panggil /me berulang).
  const termuat = ref(false)
  const ringkasan = ref(null)
  // Janji in-flight agar /me awal hanya dipanggil sekali walau banyak pemicu.
  let janjiProfil = null

  const masuk = computed(() => user.value !== null)

  function tandaiPernahMasuk() {
    try {
      const s = penyimpananSesi()
      if (s) s.setItem(KUNCI_PERNAH_MASUK, '1')
    } catch {
      // penyimpanan sesi mungkin tidak tersedia; fallback hanya memori.
    }
  }

  function pernahMasukSesiIni() {
    try {
      const s = penyimpananSesi()
      return s ? s.getItem(KUNCI_PERNAH_MASUK) === '1' : false
    } catch {
      return false
    }
  }

  function lupakanPernahMasuk() {
    try {
      const s = penyimpananSesi()
      if (s) s.removeItem(KUNCI_PERNAH_MASUK)
    } catch {
      // abaikan
    }
  }

  /**
   * Muat profil dari /api/auth/me.
   * - Default: lempar galat bila gagal (pemanggil tangani).
   * - paksa=false + sudah termuat: kembalikan user saat ini tanpa request baru.
   * - diamkanGalat=true: jangan lempar (untuk inisialisasi awal), tetap tandai termuat.
   * - lewatiArahUlangAuth=true: jangan biarkan interceptor mengalihkan (guard/init yang memutuskan).
   */
  async function muatProfil({
    paksa = false,
    diamkanGalat = false,
    lewatiArahUlangAuth = false,
  } = {}) {
    if (!paksa && termuat.value) {
      if (diamkanGalat) return user.value
      if (!masuk.value) throw new Error('Belum masuk')
      return user.value
    }

    if (janjiProfil) return janjiProfil

    // Tetap memanggil authApi.me() untuk memuat profil sesi.
    janjiProfil = (async () => {
      try {
        const res = await authApi.me({ _lewatiArahUlangAuth: lewatiArahUlangAuth })
        user.value = res.data?.data || null
        if (user.value) tandaiPernahMasuk()
      } catch (err) {
        user.value = null
        // Pastikan status termuat konsisten agar guard tidak memanggil berulang.
        if (!diamkanGalat) throw err
      } finally {
        termuat.value = true
        janjiProfil = null
      }
      return user.value
    })()

    return janjiProfil
  }

  /** Ambil angka dasbor dan lencana antrean dari `/api/admin/summary`. */
  async function muatRingkasan() {
    const res = await adminApi.getSummary()
    ringkasan.value = res.data?.data || null
  }

  /** Logout manual: panggil server lalu bersihkan memori dan penanda pernah masuk. */
  async function keluar() {
    try {
      await authApi.logout()
    } finally {
      lupakanPernahMasuk()
      bersihkan()
    }
  }

  function bersihkan() {
    user.value = null
    ringkasan.value = null
    termuat.value = false
  }

  return {
    user,
    termuat,
    ringkasan,
    masuk,
    pernahMasukSesiIni,
    tandaiPernahMasuk,
    lupakanPernahMasuk,
    muatProfil,
    muatRingkasan,
    keluar,
    bersihkan,
  }
})
