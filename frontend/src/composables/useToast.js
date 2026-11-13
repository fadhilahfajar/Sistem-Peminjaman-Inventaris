import { reactive } from 'vue'

/**
 * Toast global ringan untuk panel admin (DESAIN 10.6: toast hijau ±4 detik).
 * Satu instance dipakai bersama lewat NotifikasiToast di LayoutAdmin.
 */
const toast = reactive({ terbuka: false, pesan: '', tipe: 'sukses' })

export function useToast() {
  function tampil(pesan, tipe = 'sukses', durasi = 4000) {
    toast.pesan = pesan
    toast.tipe = tipe
    toast.terbuka = true
    // NotifikasiToast menutup sendiri berdasarkan durasi.
  }

  function tutup() {
    toast.terbuka = false
  }

  return { toast, tampil, tutup }
}
