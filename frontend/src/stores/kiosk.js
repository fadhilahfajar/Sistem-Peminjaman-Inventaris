import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Store sesi kiosk untuk kode peminjaman aktif.
 * Sesuai DESAIN 10.1: kode pinjam hanya disimpan di memori aplikasi (tanpa plugin persist,
 * tanpa localStorage/sessionStorage), tidak masuk URL, dan dihapus saat kembali ke beranda atau idle.
 */
export const useKioskStore = defineStore('kiosk', () => {
  const kode = ref('')

  function setKode(nilai) {
    kode.value = nilai
  }

  function hapusKode() {
    kode.value = ''
  }

  return {
    kode,
    setKode,
    hapusKode,
  }
})
