<script setup>
import { useRoute } from 'vue-router'
import { useIdleKiosk } from '@/composables/useIdleKiosk'
import TombolBesar from '@/components/TombolBesar.vue'

const route = useRoute()
const { hitungMundur, aturUlang, batalkanMundur } = useIdleKiosk()
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-kiosk text-slate-900 flex flex-col">
    <!-- Header Kiosk (Sembunyi saat cetak sesuai US-03, T-39, UI-06) -->
    <header class="print:hidden border-b border-slate-200 bg-white px-4 md:px-6 py-4 shadow-2xs">
      <div class="mx-auto flex max-w-5xl items-center justify-between">
        <RouterLink
          to="/"
          class="flex items-center gap-2 hover:opacity-85 transition-opacity rounded-lg focus-visible:outline-2 focus-visible:outline-[var(--color-aksen,#1d4ed8)] py-1 px-1 -ml-1"
        >
          <span class="text-xl md:text-2xl font-black tracking-tight text-slate-900">
            Peminjaman Inventaris IT
          </span>
        </RouterLink>

        <!-- Tombol Cepat Kembali ke Beranda jika di luar Beranda -->
        <RouterLink
          v-if="route.path !== '/'"
          to="/"
          class="inline-flex items-center gap-1.5 min-h-[44px] px-3.5 py-2 text-sm font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors select-none focus-visible:outline-2 focus-visible:outline-slate-700"
        >
          <span>&larr;</span>
          <span>Beranda</span>
        </RouterLink>
      </div>
    </header>

    <!-- Konten Utama Kiosk -->
    <main class="mx-auto w-full max-w-5xl flex-1 p-4 md:p-6 print:p-0 print:max-w-none">
      <RouterView />
    </main>

    <!-- Overlay Hitung Mundur Auto-Reset (FR-K-10) -->
    <Teleport to="body">
      <div
        v-if="hitungMundur !== null"
        class="fixed inset-0 z-[60] bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-6"
        role="dialog"
        aria-modal="true"
        aria-label="Sesi akan diatur ulang"
      >
        <div class="bg-white rounded-2xl p-6 md:p-8 max-w-md w-full text-center space-y-4 shadow-2xl">
          <h3 class="text-xl md:text-2xl font-extrabold text-slate-900">
            Masih di sana?
          </h3>
          <p class="text-sm md:text-base text-slate-600">
            Sesi akan diatur ulang dan seluruh isian dihapus dalam
            <strong class="text-slate-900">{{ hitungMundur }}</strong> detik.
          </p>

          <div class="h-2.5 w-full bg-slate-100 rounded-full overflow-hidden">
            <div
              class="h-full bg-[var(--color-aksen,#1d4ed8)] transition-all duration-1000 ease-linear"
              :style="{ width: `${(hitungMundur / 10) * 100}%` }"
            ></div>
          </div>

          <TombolBesar varian="primer" ukuran="besar" lebar-penuh @click="batalkanMundur">
            Ya, Lanjutkan Sesi
          </TombolBesar>

          <TombolBesar varian="sekunder" ukuran="sedang" lebar-penuh @click="aturUlang">
            Atur Ulang Sekarang
          </TombolBesar>
        </div>
      </div>
    </Teleport>
  </div>
</template>
