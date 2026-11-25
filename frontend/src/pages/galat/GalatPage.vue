<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TombolBesar from '@/components/TombolBesar.vue'

const props = defineProps({
  kode: {
    type: [String, Number],
    default: null,
  },
})

const route = useRoute()
const router = useRouter()

const kodeAktif = computed(() => {
  if (props.kode) return String(props.kode)
  if (route.meta?.statusGalat) return String(route.meta.statusGalat)
  if (route.path === '/403') return '403'
  if (route.path === '/419') return '419'
  return '404'
})

const DATA_GALAT = {
  403: {
    judul: 'Akses Ditolak',
    pesan: 'Anda tidak memiliki izin untuk mengakses halaman ini.',
    aksiLabel: 'Kembali ke Beranda',
    aksiTujuan: '/',
  },
  404: {
    judul: 'Halaman Tidak Ditemukan',
    pesan: 'Halaman yang Anda tuju tidak ditemukan atau tautan salah.',
    aksiLabel: 'Kembali ke Beranda',
    aksiTujuan: '/',
  },
  419: {
    judul: 'Sesi Berakhir',
    pesan: 'Sesi berakhir, silakan masuk lagi.',
    aksiLabel: 'Masuk Lagi',
    aksiTujuan: '/admin/login?sesi=berakhir',
  },
}

const info = computed(() => DATA_GALAT[kodeAktif.value] || DATA_GALAT['404'])

function kembali() {
  router.push(info.value.aksiTujuan)
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 p-4 font-sans text-slate-900">
    <div class="max-w-md w-full bg-white p-8 rounded-lg border border-slate-200 shadow-sm text-center space-y-5">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-lg bg-slate-100 text-slate-700 font-extrabold text-2xl font-mono">
        {{ kodeAktif }}
      </div>

      <div class="space-y-1">
        <h1 class="text-xl md:text-2xl font-extrabold text-slate-900">
          {{ info.judul }}
        </h1>
        <p class="text-sm text-slate-600">
          {{ info.pesan }}
        </p>
      </div>

      <div class="pt-2">
        <TombolBesar
          varian="primer"
          ukuran="sedang"
          class="w-full justify-center"
          @click="kembali"
        >
          {{ info.aksiLabel }}
        </TombolBesar>
      </div>
    </div>
  </div>
</template>
