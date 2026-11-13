<script setup>
import StatusKosong from './StatusKosong.vue'
import { formatWaktuIndo } from '@/utils/tanggal'

defineProps({
  // [{ jenis, waktu, ringkasan, catatan_peminjam?, catatan_admin? }]
  entri: {
    type: Array,
    default: () => [],
  },
})

const IKON = {
  dibuat: 'M12 4v16m8-8H4',
  perpanjangan: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
  klaim: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  verifikasi: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  koreksi: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
  pembatalan: 'M6 18L18 6M6 6l12 12',
}
</script>

<template>
  <ol v-if="entri.length > 0" class="relative space-y-5">
    <li
      v-for="(e, i) in entri"
      :key="i"
      class="relative pl-9"
    >
      <!-- Garis penghubung -->
      <span
        v-if="i < entri.length - 1"
        class="absolute left-[15px] top-8 h-[calc(100%-8px)] w-0.5 bg-slate-200"
        aria-hidden="true"
      ></span>

      <span class="absolute left-0 top-1 w-8 h-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center">
        <svg
          class="w-4 h-4"
          :class="e.jenis === 'pembatalan' ? 'text-red-600' : e.jenis === 'verifikasi' ? 'text-emerald-600' : 'text-slate-600'"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="IKON[e.jenis] || IKON.dibuat" />
        </svg>
      </span>

      <div class="space-y-1">
        <p class="text-sm font-semibold text-slate-900">{{ e.ringkasan }}</p>
        <p class="text-xs text-slate-500">{{ formatWaktuIndo(e.waktu) }}</p>
        <p v-if="e.catatan_peminjam" class="text-xs text-slate-600 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5">
          Catatan peminjam: {{ e.catatan_peminjam }}
        </p>
        <p v-if="e.catatan_admin" class="text-xs text-slate-600 bg-blue-50 border border-blue-200 rounded-lg px-2.5 py-1.5">
          Catatan admin: {{ e.catatan_admin }}
        </p>
      </div>
    </li>
  </ol>

  <StatusKosong v-else judul="Belum ada riwayat" />
</template>
