import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Store sesi kiosk untuk kode peminjaman aktif dan data transaksi terakhir.
 * Sesuai DESAIN 10.1: data hanya disimpan di memori aplikasi (tanpa persist,
 * tanpa localStorage/sessionStorage), tidak masuk URL, dan dihapus saat kembali ke beranda atau idle.
 */
export const useKioskStore = defineStore('kiosk', () => {
  const kode = ref('')
  const pinjamSelesai = ref(null)

  function setKode(nilai) {
    kode.value = nilai
  }

  function setPinjamSelesai(data) {
    pinjamSelesai.value = data
    if (data?.kode_pinjam) {
      kode.value = data.kode_pinjam
    }
  }

  function hapusKode() {
    kode.value = ''
  }

  function resetSesi() {
    kode.value = ''
    pinjamSelesai.value = null
  }

  return {
    kode,
    pinjamSelesai,
    setKode,
    setPinjamSelesai,
    hapusKode,
    resetSesi,
  }
})
