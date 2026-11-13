<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@/api'
import PesanGalat from '@/components/PesanGalat.vue'
import TabelData from '@/components/TabelData.vue'
import { useGalat } from '@/composables/useGalat'
import { useSesiStore } from '@/stores/sesi'
import { formatWaktuIndo } from '@/utils/tanggal'

const router = useRouter()
const sesi = useSesiStore()
const { pesanGalat, tanganiGalat, bersihkan } = useGalat()

// Tab antrean: tanpa status = menunggu (terlama di atas); status=diverifikasi = riwayat (terbaru di atas).
const tab = ref('menunggu')

const kolom = [
  { kunci: 'kode_klaim', label: 'Kode Klaim' },
  { kunci: 'peminjam', label: 'Peminjam' },
  { kunci: 'batas_kembali', label: 'Batas Kembali' },
  { kunci: 'hari_terlambat', label: 'Terlambat' },
  { kunci: 'jumlah_unit', label: 'Unit' },
  { kunci: 'diklaim_pada', label: 'Diklaim' },
]

const baris = ref([])
const meta = ref(null)
const sedangMuat = ref(false)

async function muat(halaman = 1) {
  sedangMuat.value = true
  bersihkan()
  try {
    const params = { page: halaman }
    if (tab.value === 'riwayat') {
      params.status = 'diverifikasi'
    }
    const res = await adminApi.getReturnClaims(params)
    baris.value = res.data?.data || []
    meta.value = res.data?.meta || null
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

onMounted(muat)

function pilihTab(nilai) {
  tab.value = nilai
  muat(1)
}

function bukaDetail(baris) {
  if (baris?.id) {
    router.push(`/admin/verifikasi/${baris.id}`)
  }
}

function namaPeminjam(b) {
  return b.peminjaman?.nama_peminjam ?? '—'
}

function kodePinjam(b) {
  return b.peminjaman?.kode_pinjam ?? '—'
}
</script>

<template>
  <section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Antrean Verifikasi</h2>
        <p class="text-sm text-slate-500 mt-0.5">
          Klaim pengembalian menunggu, terlama di atas. Antrean sekarang:
          <span class="font-bold text-slate-900 tabular-nums">{{ sesi.ringkasan?.antrean_verifikasi ?? '…' }}</span>
        </p>
      </div>
    </div>

    <!-- Tab Menunggu / Riwayat -->
    <div class="flex gap-2" role="tablist" aria-label="Tab antrean verifikasi">
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'menunggu'"
        :class="[
          'min-h-[44px] px-4 rounded-xl text-sm font-bold border transition',
          tab === 'menunggu'
            ? 'bg-[var(--color-aksen,#1d4ed8)] text-white border-transparent'
            : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50',
        ]"
        @click="pilihTab('menunggu')"
      >
        Menunggu
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'riwayat'"
        :class="[
          'min-h-[44px] px-4 rounded-xl text-sm font-bold border transition',
          tab === 'riwayat'
            ? 'bg-[var(--color-aksen,#1d4ed8)] text-white border-transparent'
            : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50',
        ]"
        @click="pilihTab('riwayat')"
      >
        Riwayat
      </button>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <TabelData
      :kolom="kolom"
      :baris="baris"
      :meta="meta"
      :memuat="sedangMuat"
      baris-diklik
      pesan-kosong="Tidak ada klaim di tab ini"
      @ubah-halaman="muat($event)"
      @klik-baris="bukaDetail"
    >
      <template #cell-peminjam="{ baris: b }">
        <p class="font-medium text-slate-900">{{ namaPeminjam(b) }}</p>
        <p class="text-xs text-slate-500 font-mono">{{ kodePinjam(b) }}</p>
      </template>
      <template #cell-batas_kembali="{ baris: b }">
        {{ b.peminjaman?.batas_kembali ? formatWaktuIndo(b.peminjaman.batas_kembali) : '—' }}
      </template>
      <template #cell-hari_terlambat="{ nilai }">
        <span :class="Number(nilai) > 0 ? 'font-bold text-red-700' : 'text-slate-500'" class="tabular-nums">
          {{ Number(nilai) > 0 ? `${nilai} hari` : '—' }}
        </span>
      </template>
      <template #cell-diklaim_pada="{ nilai }">{{ formatWaktuIndo(nilai) }}</template>
    </TabelData>
  </section>
</template>
