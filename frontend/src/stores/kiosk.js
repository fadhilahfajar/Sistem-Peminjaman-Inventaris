import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Store sesi kiosk untuk kode peminjaman aktif, data transaksi terakhir,
 * dan kartu peminjaman aktif.
 * Sesuai DESAIN 10.1: data hanya disimpan di memori aplikasi (tanpa persist,
 * tanpa localStorage/sessionStorage), tidak masuk URL, dan dihapus saat kembali ke beranda atau idle.
 */
export const useKioskStore = defineStore('kiosk', () => {
  const kode = ref('')
  const pinjamSelesai = ref(null)
  const kartuPinjam = ref(null)

  function setKode(nilai) {
    kode.value = nilai
  }

  function setPinjamSelesai(data) {
    pinjamSelesai.value = data
    if (data?.kode_pinjam) {
      kode.value = data.kode_pinjam
    }
  }

  function setKartuPinjam(data) {
    kartuPinjam.value = data
    if (data?.kode_pinjam) {
      kode.value = data.kode_pinjam
    }
  }

  function hapusKode() {
    kode.value = ''
    kartuPinjam.value = null
  }

  function resetSesi() {
    kode.value = ''
    pinjamSelesai.value = null
    kartuPinjam.value = null
  }

  return {
    kode,
    pinjamSelesai,
    kartuPinjam,
    setKode,
    setPinjamSelesai,
    setKartuPinjam,
    hapusKode,
    resetSesi,
  }
})
