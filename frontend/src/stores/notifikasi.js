import { defineStore } from 'pinia'
import { ref } from 'vue'

/** Satu toast global kiosk. Dipasang sekali di LayoutKiosk. */
export const useNotifikasiStore = defineStore('notifikasi', () => {
  const terbuka = ref(false)
  const pesan = ref('')
  const tipe = ref('sukses')

  function tampilkan(pesanBaru, tipeBaru = 'sukses') {
    pesan.value = pesanBaru
    tipe.value = tipeBaru
    terbuka.value = true
  }

  function tutup() {
    terbuka.value = false
  }

  return { terbuka, pesan, tipe, tampilkan, tutup }
})
