<script setup>
import { computed } from 'vue'

const props = defineProps({
  lencana: {
    type: String,
    required: true,
  },
  diperpanjangKali: {
    type: Number,
    default: 0,
  },
  ukuran: {
    type: String,
    default: 'kecil',
    validator: (v) => ['kecil', 'sedang'].includes(v),
  },
})

const konfigurasi = computed(() => {
  const norm = (props.lencana || '').toLowerCase().trim()

  switch (norm) {
    case 'sebagian':
      return {
        teks: 'Sebagian',
        kelas: 'bg-amber-100 text-amber-800 border-amber-300',
      }
    case 'ada_klaim_menunggu':
    case 'klaim_menunggu':
      return {
        teks: 'Ada klaim menunggu',
        kelas: 'bg-yellow-100 text-yellow-800 border-yellow-300',
      }
    case 'diperpanjang': {
      const info = props.diperpanjangKali > 0 ? ` ${props.diperpanjangKali}×` : ''
      return {
        teks: `Diperpanjang${info}`,
        kelas: 'bg-blue-100 text-blue-800 border-blue-300',
      }
    }
    case 'ada_catatan':
    case 'catatan':
      return {
        teks: 'Ada catatan',
        kelas: 'bg-slate-100 text-slate-800 border-slate-300',
      }
    default:
      return {
        teks: props.lencana,
        kelas: 'bg-slate-100 text-slate-700 border-slate-200',
      }
  }
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center font-medium rounded-md border tracking-wide whitespace-nowrap',
      ukuran === 'kecil' && 'px-2 py-0.5 text-xs',
      ukuran === 'sedang' && 'px-2.5 py-1 text-xs md:text-sm',
      konfigurasi.kelas,
    ]"
  >
    {{ konfigurasi.teks }}
  </span>
</template>
