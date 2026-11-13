<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@/api'
import BadgeStatus from '@/components/BadgeStatus.vue'
import FilterBar from '@/components/FilterBar.vue'
import FormField from '@/components/FormField.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import StatusKosong from '@/components/StatusKosong.vue'
import Memuat from '@/components/Memuat.vue'
import TabelData from '@/components/TabelData.vue'
import { useGalat } from '@/composables/useGalat'
import { useSesiStore } from '@/stores/sesi'
import { formatTanggalIndo } from '@/utils/tanggal'

const router = useRouter()
const sesi = useSesiStore()
const { pesanGalat, tanganiGalat, bersihkan } = useGalat()

const kolom = [
  { kunci: 'kode_pinjam', label: 'Kode' },
  { kunci: 'nama_peminjam', label: 'Peminjam' },
  { kunci: 'divisi', label: 'Divisi' },
  { kunci: 'tanggal_pinjam', label: 'Tgl Pinjam' },
  { kunci: 'batas_kembali', label: 'Batas Kembali' },
  { kunci: 'jumlah_unit', label: 'Unit' },
  { kunci: 'status', label: 'Status' },
]

const baris = ref([])
const meta = ref(null)
const sedangMuat = ref(false)

// Filter FR-A-05
const f = ref({
  status: '',
  barang: '',
  kategori: '',
  divisi: '',
  tipe_peminjam: '',
  dari: '',
  sampai: '',
  q: '',
  ada_catatan: '',
  ada_kerusakan: '',
  belum_kembali: '',
})

const kategoriDaftar = ref([])
const divisiDaftar = ref([])
const barangDaftar = ref([])

const STATUS = ['dipesan', 'dipinjam', 'terlambat', 'menunggu_verifikasi', 'selesai', 'dibatalkan']

async function muat(halaman = 1) {
  sedangMuat.value = true
  bersihkan()
  try {
    const params = { page: halaman }
    for (const [kunci, nilai] of Object.entries(f.value)) {
      if (nilai !== '' && nilai !== null) {
        params[kunci] = nilai
      }
    }
    const res = await adminApi.getLoans(params)
    baris.value = res.data?.data || []
    meta.value = res.data?.meta || null
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

async function muatMaster() {
  try {
    const [resKat, resDiv, resBar] = await Promise.allSettled([
      adminApi.getCategories(),
      adminApi.getDivisions(),
      adminApi.getItems({ page_size: 100 }),
    ])
    kategoriDaftar.value = resKat.value?.data?.data || []
    divisiDaftar.value = (resDiv.value?.data?.data || []).filter((d) => d.aktif)
    barangDaftar.value = resBar.value?.data?.data || []
  } catch {
    // Filter master kosong bila gagal
  }
}

onMounted(() => {
  muat()
  muatMaster()
})

function terapkan() {
  muat(1)
}

function aturUlang() {
  f.value = { status: '', barang: '', kategori: '', divisi: '', tipe_peminjam: '', dari: '', sampai: '', q: '', ada_catatan: '', ada_kerusakan: '', belum_kembali: '' }
  muat(1)
}

function bukaDetail(baris) {
  if (baris?.id) {
    router.push(`/admin/peminjaman/${baris.id}`)
  }
}

function namaDivisi(b) {
  return b.divisi?.nama ?? '—'
}
</script>

<template>
  <section class="space-y-6">
    <div>
      <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Daftar Peminjaman</h2>
      <p class="text-sm text-slate-500 mt-0.5">Filter lengkap, urut terbaru. Klik baris untuk detail (FR-A-05).</p>
    </div>

    <!-- Ringkasan singkat dari angka dasbor (satu sumber data) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
      <div class="px-3 py-2 rounded-xl bg-blue-50 border border-blue-200 text-sm">
        <span class="font-bold text-blue-950 tabular-nums">{{ sesi.ringkasan?.dipinjam ?? '…' }}</span>
        <span class="text-blue-800"> Dipinjam</span>
      </div>
      <div class="px-3 py-2 rounded-xl bg-red-50 border border-red-200 text-sm">
        <span class="font-bold text-red-950 tabular-nums">{{ sesi.ringkasan?.terlambat ?? '…' }}</span>
        <span class="text-red-800"> Terlambat</span>
      </div>
      <div class="px-3 py-2 rounded-xl bg-yellow-50 border border-yellow-200 text-sm">
        <span class="font-bold text-yellow-950 tabular-nums">{{ sesi.ringkasan?.menunggu_verifikasi ?? '…' }}</span>
        <span class="text-yellow-800"> Menunggu Verifikasi</span>
      </div>
      <div class="px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-200 text-sm">
        <span class="font-bold text-emerald-950 tabular-nums">{{ sesi.ringkasan?.selesai ?? '…' }}</span>
        <span class="text-emerald-800"> Selesai</span>
      </div>
    </div>

    <FilterBar :memuat="sedangMuat" @terapkan="terapkan" @atur-ulang="aturUlang">
      <FormField id="pl-q" label="Cari (nama, kode, telepon)">
        <input
          id="pl-q"
          v-model="f.q"
          type="search"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
      </FormField>

      <FormField id="pl-status" label="Status">
        <select
          id="pl-status"
          v-model="f.status"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua status</option>
          <option v-for="s in STATUS" :key="s" :value="s">{{ s.replace('_', ' ').replace('dipesan', 'Dipesan').replace(/^./, (c) => c.toUpperCase()) }}</option>
        </select>
      </FormField>

      <FormField id="pl-barang" label="Barang">
        <select
          id="pl-barang"
          v-model="f.barang"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua barang</option>
          <option v-for="b in barangDaftar" :key="b.id" :value="b.id">{{ b.nama }}</option>
        </select>
      </FormField>

      <FormField id="pl-kategori" label="Kategori">
        <select
          id="pl-kategori"
          v-model="f.kategori"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua kategori</option>
          <option v-for="k in kategoriDaftar" :key="k.id" :value="k.id">{{ k.nama }}</option>
        </select>
      </FormField>

      <FormField id="pl-divisi" label="Divisi">
        <select
          id="pl-divisi"
          v-model="f.divisi"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua divisi</option>
          <option v-for="d in divisiDaftar" :key="d.id" :value="d.id">{{ d.nama }}</option>
        </select>
      </FormField>

      <FormField id="pl-tipe" label="Tipe Peminjam">
        <select
          id="pl-tipe"
          v-model="f.tipe_peminjam"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua tipe</option>
          <option value="karyawan">Karyawan</option>
          <option value="magang">Magang</option>
        </select>
      </FormField>

      <FormField id="pl-dari" label="Pinjam Dari">
        <input
          id="pl-dari"
          v-model="f.dari"
          type="date"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
      </FormField>

      <FormField id="pl-sampai" label="Sampai">
        <input
          id="pl-sampai"
          v-model="f.sampai"
          type="date"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
      </FormField>

      <div class="space-y-2 text-sm pt-2">
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="f.belum_kembali" type="checkbox" value="1" class="w-4 h-4 rounded border-slate-300">
          <span class="font-medium text-slate-700">Belum dikembalikan</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="f.ada_catatan" type="checkbox" value="1" class="w-4 h-4 rounded border-slate-300">
          <span class="font-medium text-slate-700">Ada catatan</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="f.ada_kerusakan" type="checkbox" value="1" class="w-4 h-4 rounded border-slate-300">
          <span class="font-medium text-slate-700">Ada kerusakan/kehilangan</span>
        </label>
      </div>
    </FilterBar>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <TabelData
      :kolom="kolom"
      :baris="baris"
      :meta="meta"
      :memuat="sedangMuat"
      baris-diklik
      pesan-kosong="Belum ada peminjaman"
      @ubah-halaman="muat($event)"
      @klik-baris="bukaDetail"
    >
      <template #cell-divisi="{ baris: b }">{{ namaDivisi(b) }}</template>
      <template #cell-tanggal_pinjam="{ nilai }">{{ formatTanggalIndo(nilai) }}</template>
      <template #cell-batas_kembali="{ nilai }">{{ formatTanggalIndo(nilai) }}</template>
      <template #cell-status="{ baris: b }">
        <BadgeStatus :status="b.status" :hari-terlambat="Number(b.hari_terlambat) || 0" ukuran="kecil" />
      </template>
    </TabelData>
  </section>
</template>
