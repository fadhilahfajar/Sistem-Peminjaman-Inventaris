<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true,
  },
  hariTerlambat: {
    type: Number,
    default: 0,
  },
  ukuran: {
    type: String,
    default: 'sedang',
    validator: (v) => ['kecil', 'sedang', 'besar'].includes(v),
  },
})

const konfigurasi = computed(() => {
  const norm = (props.status || '').toLowerCase().trim()

  switch (norm) {
    case 'dipesan':
      return {
        teks: 'Dipesan',
        kelas: 'bg-[var(--color-status-dipesan,#f3e8ff)] text-[var(--color-status-dipesan-teks,#6b21a8)] border-purple-200',
      }
    case 'dipinjam':
      return {
        teks: 'Dipinjam',
        kelas: 'bg-[var(--color-status-dipinjam,#dbeafe)] text-[var(--color-status-dipinjam-teks,#1e40af)] border-blue-200',
      }
    case 'terlambat': {
      const infoHari = props.hariTerlambat > 0 ? ` (${props.hariTerlambat} hari)` : ''
      return {
        teks: `Terlambat${infoHari}`,
        kelas: 'bg-[var(--color-status-terlambat,#fee2e2)] text-[var(--color-status-terlambat-teks,#991b1b)] border-red-200',
      }
    }
    case 'menunggu_verifikasi':
    case 'menunggu verifikasi':
      return {
        teks: 'Menunggu Verifikasi',
        kelas: 'bg-[var(--color-status-menunggu,#fef9c3)] text-[var(--color-status-menunggu-teks,#854d0e)] border-amber-200',
      }
    case 'selesai':
      return {
        teks: 'Selesai',
        kelas: 'bg-[var(--color-status-selesai,#dcfce7)] text-[var(--color-status-selesai-teks,#166534)] border-emerald-200',
      }
    case 'dibatalkan':
    case 'batal':
      return {
        teks: 'Dibatalkan',
        kelas: 'bg-[var(--color-status-batal,#e2e8f0)] text-[var(--color-status-batal-teks,#1e293b)] border-slate-300',
      }
    default:
      return {
        teks: props.status,
        kelas: 'bg-slate-100 text-slate-700 border-slate-200',
      }
  }
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center font-bold rounded-full border tracking-wide whitespace-nowrap',
      ukuran === 'kecil' && 'px-2 py-0.5 text-xs',
      ukuran === 'sedang' && 'px-3 py-1 text-xs md:text-sm',
      ukuran === 'besar' && 'px-4 py-1.5 text-sm md:text-base',
      konfigurasi.kelas,
    ]"
  >
    {{ konfigurasi.teks }}
  </span>
</template>
