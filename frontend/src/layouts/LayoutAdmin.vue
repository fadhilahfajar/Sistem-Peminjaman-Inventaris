<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Navbar from '@/components/Navbar.vue'
import Sidebar from '@/components/Sidebar.vue'
import { useSesiStore } from '@/stores/sesi'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const sesi = useSesiStore()
const ui = useUiStore()
const toast = useToast()

// Profil dimuat oleh router guard; polling ringkasan untuk lencana (FR-A-15).
const JEDA_POLLING = 30_000
let timerRingkasan = null

async function ambilRingkasan() {
  if (!sesi.masuk) return
  try {
    await sesi.muatRingkasan()
  } catch {
    // 401/419 ditangani interceptor (arahkan ke login). Jaringan: tunggu polling berikut.
  }
}

onMounted(async () => {
  if (!sesi.termuat) {
    try {
      await sesi.muatProfil()
    } catch {
      // Guard akan mengarahkan ke login bila profil gagal dimuat.
    }
  }

  if (sesi.masuk) {
    ambilRingkasan()
    timerRingkasan = setInterval(ambilRingkasan, JEDA_POLLING)
  }
})

onUnmounted(() => {
  clearInterval(timerRingkasan)
  timerRingkasan = null
})

// Tutup menu geser setiap pindah halaman di layar kecil.
watch(() => route.path, () => {
  if (ui.sidebarTerbuka) {
    ui.sidebarTerbuka = false
  }
})

async function keluar() {
  await sesi.keluar()
  router.replace({ name: 'admin.login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-100 font-sans text-admin text-slate-900">
    <!-- Overlay menu geser di layar kecil -->
    <div
      v-if="ui.sidebarTerbuka"
      class="fixed inset-0 z-30 bg-slate-900/50 md:hidden"
      aria-hidden="true"
      @click="ui.sidebarTerbuka = false"
    ></div>

    <div class="md:flex min-h-screen">
      <!-- Sidebar kiri (layar lebar) / menu geser (layar sempit) -->
      <aside
        id="sidebar-admin"
        class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-800 p-4 overflow-y-auto transition-transform md:sticky md:top-0 md:h-screen md:translate-x-0"
        :class="ui.sidebarTerbuka ? 'translate-x-0' : '-translate-x-full'"
      >
        <p class="px-2 pb-4 font-extrabold text-white">Panel Admin</p>
        <Sidebar @navigasi="ui.sidebarTerbuka = false" />
      </aside>

      <div class="flex-1 min-w-0">
        <Navbar
          :sidebar-terbuka="ui.sidebarTerbuka"
          @toggle-sidebar="ui.toggleSidebar()"
          @keluar="keluar"
        />

        <main class="p-4 md:p-6">
          <RouterView />
        </main>
      </div>
    </div>

    <!-- Toast sukses/galat admin (DESAIN 10.6) -->
    <NotifikasiToast
      :terbuka="toast.terbuka"
      :pesan="toast.pesan"
      :tipe="toast.tipe"
      @update:terbuka="toast.terbuka = $event"
      @tutup="toast.tutup"
    />
  </div>
</template>
