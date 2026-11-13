<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useSesiStore } from '@/stores/sesi'

const route = useRoute()
const sesi = useSesiStore()

const GRUP = [
  {
    judul: '',
    item: [{ label: 'Dasbor', path: '/admin', tepat: true }],
  },
  {
    judul: 'Peminjaman',
    item: [
      { label: 'Daftar', path: '/admin/peminjaman' },
      { label: 'Antrean Verifikasi', path: '/admin/verifikasi', lencana: 'verifikasi' },
    ],
  },
  {
    judul: 'Inventaris',
    item: [
      { label: 'Barang', path: '/admin/barang' },
      { label: 'Stok Rusak', path: '/admin/stok-rusak', lencana: 'perbaikan' },
    ],
  },
  {
    judul: 'Master Data',
    item: [
      { label: 'Kategori', path: '/admin/kategori' },
      { label: 'Divisi', path: '/admin/divisi' },
    ],
  },
  {
    judul: '',
    item: [{ label: 'Laporan', path: '/admin/laporan' }],
  },
  {
    judul: 'Sistem',
    item: [
      { label: 'Pengaturan', path: '/admin/pengaturan' },
      { label: 'Akun Admin', path: '/admin/akun' },
      { label: 'Jejak Audit', path: '/admin/audit' },
    ],
  },
]

function aktif(path, tepat = false) {
  if (tepat) return route.path === path
  return route.path === path || route.path.startsWith(`${path}/`)
}

const jumlahLencana = computed(() => {
  const r = sesi.ringkasan
  return {
    verifikasi: r?.antrean_verifikasi ?? 0,
    perbaikan: r?.antrean_perbaikan ?? 0,
  }
})
</script>

<template>
  <nav aria-label="Navigasi admin" class="space-y-5 text-sm">
    <div
      v-for="(grup, gi) in GRUP"
      :key="gi"
      class="space-y-1"
    >
      <p
        v-if="grup.judul"
        class="px-2 pb-1 text-[11px] font-bold uppercase tracking-widest text-slate-400"
      >
        {{ grup.judul }}
      </p>

      <RouterLink
        v-for="item in grup.item"
        :key="item.path"
        :to="item.path"
        :class="[
          'flex items-center justify-between gap-2 rounded-lg px-3 py-2 min-h-[40px] font-medium transition-colors',
          aktif(item.path, item.tepat) ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white',
        ]"
        @click="$emit('navigasi')"
      >
        <span>{{ item.label }}</span>

        <!-- Lencana antrean (FR-A-15, FR-A-19) -->
        <span
          v-if="item.lencana && jumlahLencana[item.lencana] > 0"
          class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full text-xs font-bold bg-amber-400 text-slate-900 tabular-nums"
          :aria-label="`${jumlahLencana[item.lencana]} item menunggu`"
        >
          {{ jumlahLencana[item.lencana] > 99 ? '99+' : jumlahLencana[item.lencana] }}
        </span>
      </RouterLink>
    </div>
  </nav>
</template>
