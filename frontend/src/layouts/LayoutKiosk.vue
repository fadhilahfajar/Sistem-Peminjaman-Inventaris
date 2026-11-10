<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()
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
  </div>
</template>
