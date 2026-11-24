<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const menuKiosk = [
  // R-14: Tiga aksi kiosk utama dapat berat sama; kartu seragam memang menyampaikan paritas.
  {
    judul: 'Lihat Katalog',
    deskripsi: 'Cari barang inventaris dan cek ketersediaan stok terkini.',
    rute: '/katalog',
    varian: 'bg-blue-600 hover:bg-blue-700 text-white border-blue-600 focus-visible:outline-blue-600',
    ikon: 'katalog',
    badge: 'Katalog',
  },
  {
    judul: 'Pinjam Barang',
    deskripsi: 'Ajukan peminjaman barang hari ini atau reservasi tanggal mendatang.',
    rute: '/pinjam',
    varian: 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 focus-visible:outline-emerald-600',
    ikon: 'pinjam',
    badge: 'Peminjaman',
  },
  {
    judul: 'Cek atau Kembalikan',
    deskripsi: 'Cek status, batas waktu, ajukan klaim pengembalian, atau perpanjangan.',
    rute: '/cek',
    varian: 'bg-indigo-600 hover:bg-indigo-700 text-white border-indigo-600 focus-visible:outline-indigo-600',
    ikon: 'cek',
    badge: 'Status & Pengembalian',
  },
]

function navigasi(rute) {
  router.push(rute)
}
</script>

<template>
  <div class="py-4 md:py-8 space-y-8">
    <!-- Header Hero Kiosk -->
    <header class="text-center py-4">
      <h1 class="text-4xl md:text-6xl font-black text-slate-900">
        IT <span class="text-blue-600">Inventory</span>
      </h1>
      <p class="mt-4 text-lg text-slate-600 font-medium">
        Sistem Peminjaman Mandiri Tim IT
      </p>
    </header>

    <!-- Tiga Tombol Utama Layar Sentuh -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 max-w-6xl mx-auto">
      <button
        v-for="menu in menuKiosk"
        :key="menu.rute"
        type="button"
        :class="[
          'group relative flex flex-col justify-between p-6 rounded-lg border-2 text-left cursor-pointer transition-all duration-150',
          'min-h-[220px] shadow-sm hover:shadow-md hover:-translate-y-0.5 active:translate-y-0',
          'focus-visible:outline-3 focus-visible:outline-offset-2',
          menu.varian,
        ]"
        @click="navigasi(menu.rute)"
      >
        <div class="space-y-4">
          <!-- Ikon SVG & Badge -->
          <div class="flex items-center justify-between">
            <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center shrink-0">
              <!-- Ikon Katalog -->
              <svg
                v-if="menu.ikon === 'katalog'"
                class="w-7 h-7 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
              </svg>

              <!-- Ikon Pinjam -->
              <svg
                v-else-if="menu.ikon === 'pinjam'"
                class="w-7 h-7 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>

              <!-- Ikon Cek -->
              <svg
                v-else
                class="w-7 h-7 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            </div>

            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white/20">
              {{ menu.badge }}
            </span>
          </div>

          <!-- Judul & Deskripsi -->
          <div>
            <h3 class="text-xl md:text-2xl font-bold leading-snug">
              {{ menu.judul }}
            </h3>
            <p class="mt-2 text-sm md:text-base text-white/90 leading-relaxed">
              {{ menu.deskripsi }}
            </p>
          </div>
        </div>

        <div class="mt-6 flex items-center gap-2 text-sm font-bold text-white/95">
          <span>Buka Layanan</span>
          <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7-7 7M3 12h18" />
          </svg>
        </div>
      </button>
    </div>

    <!-- Catatan Informasi Operasional -->
    <div class="max-w-xl mx-auto p-4 rounded-lg bg-slate-100 text-slate-600 text-xs text-center border border-slate-200">
      Kiosk mandiri beroperasi untuk tim internal dan magang. Pastikan mencatat kode peminjaman setelah menyelesaikan permohonan.
    </div>
  </div>
</template>
