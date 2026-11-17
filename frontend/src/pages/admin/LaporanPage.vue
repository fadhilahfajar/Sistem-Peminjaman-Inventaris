<script setup>
import { computed, onMounted, ref } from 'vue'
import { adminApi } from '@/api'
import FilterBar from '@/components/FilterBar.vue'
import FormField from '@/components/FormField.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import { useGalat } from '@/composables/useGalat'

const JENIS_LAPORAN = [
  { nilai: 'rekap', label: 'Rekap peminjaman' },
  { nilai: 'belum-kembali', label: 'Belum dikembalikan' },
  { nilai: 'stok', label: 'Stok barang' },
  { nilai: 'pengembalian', label: 'Riwayat pengembalian' },
]

const FILTER_PER_JENIS = {
  rekap: ['barang', 'kategori', 'divisi', 'tipe_peminjam', 'status', 'dari', 'sampai', 'q', 'ada_catatan', 'ada_kerusakan', 'belum_kembali'],
  'belum-kembali': ['barang', 'kategori', 'divisi', 'tipe_peminjam'],
  stok: ['kategori'],
  pengembalian: ['barang', 'dari', 'sampai', 'status'],
}

const STATUS_PEMINJAMAN = ['dipesan', 'dipinjam', 'terlambat', 'menunggu_verifikasi', 'selesai', 'dibatalkan']
const STATUS_KLAIM = ['menunggu', 'diverifikasi']

const { pesanGalat, tanganiGalat, bersihkan } = useGalat()
const jenis = ref('rekap')
const filter = ref(filterAwal())
const kategoriDaftar = ref([])
const divisiDaftar = ref([])
const barangDaftar = ref([])
const sedangMuat = ref(false)
const sedangUnduh = ref(false)

const statusDaftar = computed(() => jenis.value === 'pengembalian' ? STATUS_KLAIM : STATUS_PEMINJAMAN)

function filterAwal() {
  return {
    barang: '',
    kategori: '',
    divisi: '',
    tipe_peminjam: '',
    status: '',
    dari: '',
    sampai: '',
    q: '',
    ada_catatan: '',
    ada_kerusakan: '',
    belum_kembali: '',
  }
}

function tampilkanFilter(kunci) {
  return FILTER_PER_JENIS[jenis.value].includes(kunci)
}

async function muatPilihanFilter() {
  sedangMuat.value = true
  bersihkan()
  try {
    const [kategori, divisi, barang] = await Promise.all([
      adminApi.getCategories(),
      adminApi.getDivisions(),
      adminApi.getItems({ page_size: 100 }),
    ])
    kategoriDaftar.value = kategori.data?.data || []
    divisiDaftar.value = (divisi.data?.data || []).filter((item) => item.aktif)
    barangDaftar.value = barang.data?.data || []
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

function parameterLaporan() {
  const params = {}
  for (const kunci of FILTER_PER_JENIS[jenis.value]) {
    const nilai = filter.value[kunci]
    if (nilai !== '' && nilai !== null) params[kunci] = nilai
  }
  return params
}

function namaBerkas(response) {
  const disposition = response.headers?.['content-disposition'] || ''
  const nama = disposition.match(/filename="?([^";]+)"?/i)?.[1]
  return nama?.replace(/[^a-zA-Z0-9._-]/g, '') || `laporan-${jenis.value}.pdf`
}

async function tanganiGalatUnduh(err) {
  const data = err.response?.data
  if (typeof Blob !== 'undefined' && data instanceof Blob) {
    try {
      const payload = JSON.parse(await data.text())
      tanganiGalat({ ...err, response: { ...err.response, data: payload } })
    } catch {
      pesanGalat.value = err.pesanUmum || 'Laporan gagal diunduh. Silakan coba lagi.'
    }
    return
  }
  tanganiGalat(err)
}

async function unduhLaporan() {
  bersihkan()
  if (tampilkanFilter('dari') && filter.value.dari && filter.value.sampai && filter.value.dari > filter.value.sampai) {
    pesanGalat.value = 'Tanggal awal tidak boleh melebihi tanggal akhir.'
    return
  }

  sedangUnduh.value = true
  try {
    const response = await adminApi.getReportPdf(jenis.value, parameterLaporan())
    if (!(response.data instanceof Blob)) {
      pesanGalat.value = 'Server tidak mengembalikan berkas PDF yang valid.'
      return
    }

    const url = URL.createObjectURL(response.data)
    const tautan = document.createElement('a')
    tautan.href = url
    tautan.download = namaBerkas(response)
    tautan.hidden = true
    document.body.append(tautan)
    tautan.click()
    tautan.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (err) {
    await tanganiGalatUnduh(err)
  } finally {
    sedangUnduh.value = false
  }
}

function aturUlang() {
  jenis.value = 'rekap'
  filter.value = filterAwal()
  bersihkan()
}

onMounted(muatPilihanFilter)
</script>

<template>
  <section class="space-y-6">
    <div class="pb-2 border-b border-slate-200">
      <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Laporan</h2>
      <p class="text-sm text-slate-500 mt-0.5">
        Pilih jenis laporan dan filter yang diperlukan, lalu unduh sebagai PDF.
      </p>
    </div>

    <p v-if="sedangMuat" class="text-sm text-slate-500" role="status">Memuat pilihan filter…</p>

    <FilterBar
      teks-terapkan="Unduh PDF"
      :memuat="sedangUnduh"
      @terapkan="unduhLaporan"
      @atur-ulang="aturUlang"
    >
      <FormField id="lap-jenis" label="Jenis Laporan">
        <select
          id="lap-jenis"
          v-model="jenis"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option v-for="opsi in JENIS_LAPORAN" :key="opsi.nilai" :value="opsi.nilai">{{ opsi.label }}</option>
        </select>
      </FormField>

      <FormField v-if="tampilkanFilter('barang')" id="lap-barang" label="Barang">
        <select
          id="lap-barang"
          v-model="filter.barang"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua barang</option>
          <option v-for="barang in barangDaftar" :key="barang.id" :value="barang.id">{{ barang.nama }}</option>
        </select>
      </FormField>

      <FormField v-if="tampilkanFilter('kategori')" id="lap-kategori" label="Kategori">
        <select
          id="lap-kategori"
          v-model="filter.kategori"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua kategori</option>
          <option v-for="kategori in kategoriDaftar" :key="kategori.id" :value="kategori.id">{{ kategori.nama }}</option>
        </select>
      </FormField>

      <FormField v-if="tampilkanFilter('divisi')" id="lap-divisi" label="Divisi">
        <select
          id="lap-divisi"
          v-model="filter.divisi"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua divisi</option>
          <option v-for="divisi in divisiDaftar" :key="divisi.id" :value="divisi.id">{{ divisi.nama }}</option>
        </select>
      </FormField>

      <FormField v-if="tampilkanFilter('tipe_peminjam')" id="lap-tipe" label="Tipe Peminjam">
        <select
          id="lap-tipe"
          v-model="filter.tipe_peminjam"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua tipe</option>
          <option value="karyawan">Karyawan</option>
          <option value="magang">Magang</option>
        </select>
      </FormField>

      <FormField v-if="tampilkanFilter('status')" id="lap-status" label="Status">
        <select
          id="lap-status"
          v-model="filter.status"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua status</option>
          <option v-for="status in statusDaftar" :key="status" :value="status">{{ status.replaceAll('_', ' ') }}</option>
        </select>
      </FormField>

      <FormField v-if="tampilkanFilter('dari')" id="lap-dari" label="Dari">
        <input
          id="lap-dari"
          v-model="filter.dari"
          type="date"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
      </FormField>

      <FormField v-if="tampilkanFilter('sampai')" id="lap-sampai" label="Sampai">
        <input
          id="lap-sampai"
          v-model="filter.sampai"
          type="date"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
      </FormField>

      <FormField v-if="tampilkanFilter('q')" id="lap-q" label="Cari (nama, kode)">
        <input
          id="lap-q"
          v-model="filter.q"
          type="search"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
      </FormField>

      <div v-if="jenis === 'rekap'" class="space-y-3 pt-2 text-sm">
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="filter.belum_kembali" type="checkbox" value="1" class="w-4 h-4 rounded border-slate-300">
          <span class="font-medium text-slate-700">Belum dikembalikan</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="filter.ada_catatan" type="checkbox" value="1" class="w-4 h-4 rounded border-slate-300">
          <span class="font-medium text-slate-700">Ada catatan</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="filter.ada_kerusakan" type="checkbox" value="1" class="w-4 h-4 rounded border-slate-300">
          <span class="font-medium text-slate-700">Ada kerusakan/kehilangan</span>
        </label>
      </div>
    </FilterBar>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />
  </section>
</template>
