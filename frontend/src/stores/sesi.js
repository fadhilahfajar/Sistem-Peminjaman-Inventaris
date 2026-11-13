import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { adminApi, authApi } from '@/api'

/**
 * Sesi admin (Task 5.1): profil dari `/api/auth/me`, ringkasan dasbor
 * untuk lencana sidebar (FR-A-15), dan keluar. Murni memori — tidak ada
 * data peminjaman atau kode yang disimpan di penyimpanan browser.
 */
export const useSesiStore = defineStore('sesi', () => {
  const user = ref(null)
  // Sudah pernah mencoba memuat profil (agar guard tidak memanggil berulang).
  const termuat = ref(false)
  const ringkasan = ref(null)

  const masuk = computed(() => user.value !== null)

  /** Ambil profil. Bila sesi berakhir (401/419) galat dilempar ke pemanggil. */
  async function muatProfil() {
    try {
      const res = await authApi.me()
      user.value = res.data?.data || null
    } catch (err) {
      user.value = null
      throw err
    } finally {
      termuat.value = true
    }
  }

  /** Ambil angka dasbor dan lencana antrean dari `/api/admin/summary`. */
  async function muatRingkasan() {
    const res = await adminApi.getSummary()
    ringkasan.value = res.data?.data || null
  }

  /** Logout: panggil server lalu bersihkan memori sesi. */
  async function keluar() {
    try {
      await authApi.logout()
    } finally {
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
    muatProfil,
    muatRingkasan,
    keluar,
    bersihkan,
  }
})
