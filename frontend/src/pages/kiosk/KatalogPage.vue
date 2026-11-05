<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { kioskApi } from '@/api'
import KartuBarang from '@/components/KartuBarang.vue'
import Memuat from '@/components/Memuat.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import StatusKosong from '@/components/StatusKosong.vue'
import TombolBesar from '@/components/TombolBesar.vue'

const router = useRouter()

const items = ref([])
const kategoriList = ref([])
const kategoriTerpilih = ref(null)
const kataKunci = ref('')
const sedangMemuat = ref(false)
const pesanError = ref('')

let timerDebounce = null

const petaKategori = computed(() => {
  const map = {}
  for (const kat of kategoriList.value) {
    map[kat.id] = kat.nama
  }
  return map
})

function namaKategori(id) {
  return petaKategori.value[id] || ''
}

async function muatKategori() {
  try {
    const res = await kioskApi.getCategories()
    kategoriList.value = res.data?.data || []
  } catch {
    // Kategori opsional, jangan gagalkan seluruh layar jika gagal muat kategori
    kategoriList.value = []
  }
}

async function muatBarang() {
  sedangMemuat.value = true
  pesanError.value = ''

  try {
    const params = {}
    if (kataKunci.value.trim()) {
      params.q = kataKunci.value.trim()
    }
    if (kategoriTerpilih.value !== null) {
      params.kategori = kategoriTerpilih.value
    }

    const res = await kioskApi.getItems(params)
    items.value = res.data?.data || []
  } catch (err) {
    pesanError.value =
      err.pesanUmum ||
      err.response?.data?.pesan ||
      'Gagal memuat katalog barang. Periksa koneksi jaringan dan coba lagi.'
  } finally {
    sedangMemuat.value = false
  }
}

function onInputPencarian(e) {
  clearTimeout(timerDebounce)
  timerDebounce = setTimeout(() => {
    muatBarang()
  }, 250)
}

function bersihkanPencarian() {
  kataKunci.value = ''
  muatBarang()
}

function pilihKategori(id) {
  if (kategoriTerpilih.value === id) return
  kategoriTerpilih.value = id
  muatBarang()
}

function resetFilter() {
  kataKunci.value = ''
  kategoriTerpilih.value = null
  muatBarang()
}

function onPilihBarang(barang) {
  router.push({
    path: '/pinjam',
    query: { item_id: barang.id },
  })
}

onMounted(async () => {
  await muatKategori()
  await muatBarang()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Navigasi Atas & Judul Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
          Katalog Barang
        </h2>
        <p class="text-sm md:text-base text-slate-600 mt-0.5">
          Daftar perangkat inventaris IT dan ketersediaan stok aktif.
        </p>
      </div>

      <TombolBesar
        varian="sekunder"
        ukuran="sedang"
        class="self-start sm:self-auto"
        @click="router.push('/')"
      >
        &larr; Kembali ke Beranda
      </TombolBesar>
    </div>

    <!-- Filter & Pencarian -->
    <div class="space-y-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
      <!-- Kolom Pencarian -->
      <div class="relative w-full">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <input
          v-model="kataKunci"
          type="text"
          placeholder="Cari nama barang inventaris..."
          maxlength="100"
          class="w-full min-h-[48px] pl-10 pr-10 bg-slate-50 border border-slate-300 rounded-xl text-base text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors"
          @input="onInputPencarian"
        >

        <button
          v-if="kataKunci"
          type="button"
          class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 min-w-[44px] justify-center cursor-pointer"
          aria-label="Bersihkan pencarian"
          @click="bersihkanPencarian"
        >
          <span class="text-xl font-bold">&times;</span>
        </button>
      </div>

      <!-- Pilihan Kategori -->
      <div
        v-if="kategoriList.length > 0"
        class="flex items-center gap-2 overflow-x-auto pb-1 text-sm scrollbar-none"
      >
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider shrink-0 mr-1">
          Kategori:
        </span>

        <!-- Tombol Semua Kategori -->
        <button
          type="button"
          :class="[
            'min-h-[44px] px-4 py-2 rounded-xl font-medium transition-colors cursor-pointer shrink-0 text-sm select-none',
            'focus-visible:outline-2 focus-visible:outline-[var(--color-aksen,#1d4ed8)]',
            kategoriTerpilih === null
              ? 'bg-[var(--color-aksen,#1d4ed8)] text-white shadow-xs font-semibold'
              : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
          ]"
          @click="pilihKategori(null)"
        >
          Semua Kategori
        </button>

        <!-- Daftar Tombol Kategori Dinamis -->
        <button
          v-for="kat in kategoriList"
          :key="kat.id"
          type="button"
          :class="[
            'min-h-[44px] px-4 py-2 rounded-xl font-medium transition-colors cursor-pointer shrink-0 text-sm select-none',
            'focus-visible:outline-2 focus-visible:outline-[var(--color-aksen,#1d4ed8)]',
            kategoriTerpilih === kat.id
              ? 'bg-[var(--color-aksen,#1d4ed8)] text-white shadow-xs font-semibold'
              : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
          ]"
          @click="pilihKategori(kat.id)"
        >
          {{ kat.nama }}
        </button>
      </div>
    </div>

    <!-- Status Memuat -->
    <div v-if="sedangMemuat" class="py-12">
      <Memuat tipe="skeleton" :baris="6" teks="Memuat daftar barang inventaris..." />
    </div>

    <!-- Status Galat API -->
    <div v-else-if="pesanError" class="py-4">
      <PesanGalat
        judul="Gagal Memuat Katalog"
        :pesan="pesanError"
        :bisa-dicoba-lagi="true"
        teks-coba-lagi="Muat Ulang"
        @coba-lagi="muatBarang"
      />
    </div>

    <!-- Status Kosong -->
    <div v-else-if="items.length === 0" class="py-8">
      <StatusKosong
        judul="Barang Tidak Ditemukan"
        pesan="Tidak ada barang inventaris yang sesuai dengan kata kunci pencarian atau kategori yang dipilih."
      >
        <TombolBesar
          v-if="kataKunci || kategoriTerpilih !== null"
          varian="sekunder"
          ukuran="sedang"
          @click="resetFilter"
        >
          Reset Filter & Pencarian
        </TombolBesar>
      </StatusKosong>
    </div>

    <!-- Grid Kartu Barang -->
    <div v-else class="space-y-4">
      <div class="flex items-center justify-between text-xs text-slate-500 px-1">
        <span>Menampilkan <strong>{{ items.length }}</strong> barang</span>
        <span>Stok 0 ditandai habis dan tidak dapat dipilih</span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <KartuBarang
          v-for="item in items"
          :key="item.id"
          :barang="item"
          :kategori-nama="namaKategori(item.kategori_id)"
          @pilih="onPilihBarang"
        />
      </div>
    </div>
  </div>
</template>
