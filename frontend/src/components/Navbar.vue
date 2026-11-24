<script setup>
import { useSesiStore } from '@/stores/sesi'
import TombolBesar from './TombolBesar.vue'

defineProps({
  sidebarTerbuka: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['toggle-sidebar'])

const sesi = useSesiStore()
</script>

<template>
  <header class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-2.5">
    <!-- Menu geser di layar kecil (DESAIN 10.3) -->
    <button
      type="button"
      class="min-h-sentuh inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 md:hidden"
      :aria-expanded="sidebarTerbuka"
      aria-controls="sidebar-admin"
      @click="emit('toggle-sidebar')"
    >
      <!-- R-04: tiga garis = menu, stroke 2.5 agar terbaca di 44px. -->
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2.5" d="M4 7h16M4 12h16M4 17h16" />
      </svg>
      Menu
    </button>

    <span class="font-bold text-slate-900 hidden sm:inline">Inventaris IT</span>

    <div class="flex-1"></div>

    <!-- Navbar atas: nama admin, ganti password, Keluar (DESAIN 10.3) -->
    <span v-if="sesi.user" class="text-sm text-slate-600 truncate max-w-[160px]">
      {{ sesi.user.name }}
    </span>

    <TombolBesar
      varian="sekunder"
      ukuran="sedang"
      @click="$router.push('/admin/akun')"
    >
      Ganti Password
    </TombolBesar>

    <TombolBesar
      varian="sekunder"
      ukuran="sedang"
      @click="$emit('keluar')"
    >
      Keluar
    </TombolBesar>
  </header>
</template>
