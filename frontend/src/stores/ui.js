import { defineStore } from 'pinia'
import { ref } from 'vue'

// Status tampilan saja (bukan data bisnis, bukan kode peminjaman).
export const useUiStore = defineStore('ui', () => {
  const sidebarTerbuka = ref(false)

  function toggleSidebar() {
    sidebarTerbuka.value = !sidebarTerbuka.value
  }

  return { sidebarTerbuka, toggleSidebar }
})
