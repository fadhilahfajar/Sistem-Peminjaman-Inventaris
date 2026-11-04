import { ref } from 'vue'

/**
 * Composable untuk menangani status galat formulir dan respons API.
 * Sesuai DESAIN 10.6:
 * - 422: errors per kolom + fokus ke kolom pertama
 * - 409, 429, 500: pesan umum
 */
export function useGalat() {
  const errors = ref({})
  const pesanGalat = ref('')
  const memuat = ref(false)

  function bersihkan() {
    errors.value = {}
    pesanGalat.value = ''
  }

  function tanganiGalat(err) {
    bersihkan()

    if (!err) return

    // Galat validasi 422
    if (err.response?.status === 422 && err.response?.data?.errors) {
      errors.value = err.response.data.errors
      pesanGalat.value = err.response.data.pesan || 'Mohon periksa kembali isian formulir.'
      return
    }

    // Pesan dari interceptor atau respons server
    pesanGalat.value = err.pesanUmum || err.response?.data?.pesan || err.message || 'Terjadi kesalahan.'
  }

  function getError(kolom) {
    const list = errors.value[kolom]
    return Array.isArray(list) ? list[0] : list || null
  }

  return {
    errors,
    pesanGalat,
    memuat,
    bersihkan,
    tanganiGalat,
    getError,
  }
}

export default useGalat
