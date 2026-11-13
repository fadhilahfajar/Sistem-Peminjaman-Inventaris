<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@/api'
import FilterBar from '@/components/FilterBar.vue'
import FormField from '@/components/FormField.vue'
import InputJumlah from '@/components/InputJumlah.vue'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import TabelData from '@/components/TabelData.vue'
import { useGalat } from '@/composables/useGalat'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const { pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()
const { tampil: toast } = useToast()

// --- Daftar barang ---
const kolom = [
  { kunci: 'nama', label: 'Nama' },
  { kunci: 'kategori', label: 'Kategori' },
  { kunci: 'total', label: 'Total' },
  { kunci: 'tersedia', label: 'Tersedia' },
  { kunci: 'rusak', label: 'Rusak' },
  { kunci: 'sedang_keluar', label: 'Dipinjam' },
  { kunci: 'kondisi', label: 'Kondisi' },
  { kunci: 'aksi', label: 'Aksi' },
]

const baris = ref([])
const meta = ref(null)
const sedangMuat = ref(false)

const q = ref('')
const kategori = ref('')
const kategoriDaftar = ref([])

async function muat(halaman = 1) {
  sedangMuat.value = true
  bersihkan()
  try {
    const res = await adminApi.getItems({
      q: q.value.trim() || undefined,
      kategori: kategori.value || undefined,
      page: halaman,
    })
    baris.value = res.data?.data || []
    meta.value = res.data?.meta || null
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

async function muatKategori() {
  try {
    const res = await adminApi.getCategories()
    kategoriDaftar.value = (res.data?.data || []).filter((k) => k.aktif)
  } catch {
    // Filter kategori kosong saja bila gagal
  }
}

onMounted(() => {
  muat()
  muatKategori()
})

function terapkan() {
  muat(1)
}

function aturUlang() {
  q.value = ''
  kategori.value = ''
  muat(1)
}

function namaKategori(baris) {
  return baris.kategori?.nama ?? '—'
}

function labelKondisi(kondisi) {
  return { baik: 'Baik', sebagian_rusak: 'Sebagian Rusak', semua_rusak: 'Semua Rusak' }[kondisi] || kondisi
}

// --- Hapus (soft-delete, BR-14/BR-33) ---
const targetHapus = ref(null)
const modalHapus = ref(false)

function mintaHapus(baris) {
  bersihkan()
  targetHapus.value = baris
  modalHapus.value = true
}

async function jalankanHapus() {
  modalHapus.value = false
  if (!targetHapus.value) return

  sedangKirim.value = true
  try {
    await adminApi.deleteItem(targetHapus.value.id)
    toast(`Barang "${targetHapus.value.nama}" dihapus.`)
    targetHapus.value = null
    await muat(meta.value?.current_page ?? 1)
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Gagal menghapus barang.', 'galat')
  } finally {
    sedangKirim.value = false
  }
}

// --- Penyesuaian stok (FR-A-10, T-05) dan tandai rusak (FR-A-09) ---
const modalAksi = ref(false)
const jenisAksi = ref('stok') // stok | rusak
const targetAksi = ref(null)
const arah = ref('tambah')
const jumlah = ref(1)
const alasan = ref('')

function bukaStok(baris) {
  bersihkan()
  jenisAksi.value = 'stok'
  targetAksi.value = baris
  arah.value = 'tambah'
  jumlah.value = 1
  alasan.value = ''
  modalAksi.value = true
}

function bukaRusak(baris) {
  bersihkan()
  jenisAksi.value = 'rusak'
  targetAksi.value = baris
  jumlah.value = 1
  alasan.value = ''
  modalAksi.value = true
}

function maksAksi() {
  if (!targetAksi.value) return 999
  return jenisAksi.value === 'rusak' ? targetAksi.value.tersedia : 999
}

function judulAksi() {
  return jenisAksi.value === 'rusak' ? 'Tandai Rusak' : 'Penyesuaian Stok'
}

async function jalankanAksi() {
  if (!targetAksi.value) return
  bersihkan()

  const jumlahIsi = Number(jumlah.value)
  if (!jumlahIsi || jumlahIsi < 1) {
    return
  }

  if (jenisAksi.value === 'rusak' && !alasan.value.trim()) {
    return
  }

  const itemId = targetAksi.value.id
  modalAksi.value = false
  sedangKirim.value = true

  try {
    if (jenisAksi.value === 'rusak') {
      await adminApi.markDamaged(itemId, { jumlah: jumlahIsi, alasan: alasan.value.trim() })
      toast(`${jumlahIsi} unit ditandai rusak.`)
    } else {
      await adminApi.adjustStock(itemId, { arah: arah.value, jumlah: jumlahIsi, alasan: alasan.value.trim() })
      toast(`Stok ${arah.value} ${jumlahIsi} berhasil.`)
    }
    targetAksi.value = null
    await muat(meta.value?.current_page ?? 1)
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Gagal menyimpan penyesuaian stok.', 'galat')
  } finally {
    sedangKirim.value = false
  }
}

// Ulangi muat bila kembali dari halaman form
watch(() => router.currentRoute.value.path, () => {
  if (router.currentRoute.value.path === '/admin/barang') {
    muat(meta.value?.current_page ?? 1)
  }
})
</script>

<template>
  <section class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Barang</h2>
        <p class="text-sm text-slate-500 mt-0.5">Kelola inventaris, penyesuaian stok lewat mutasi berkas besar (FR-A-03).</p>
      </div>
      <TombolBesar varian="primer" ukuran="sedang" @click="router.push('/admin/barang/baru')">+ Tambah Barang</TombolBesar>
    </div>

    <FilterBar :memuat="sedangMuat" @terapkan="terapkan" @atur-ulang="aturUlang">
      <FormField id="f-q" label="Cari Nama">
        <input
          id="f-q"
          v-model="q"
          type="search"
          placeholder="Contoh: Proyektor"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent"
        >
      </FormField>

      <FormField id="f-kategori" label="Kategori">
        <select
          id="f-kategori"
          v-model="kategori"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua kategori</option>
          <option v-for="k in kategoriDaftar" :key="k.id" :value="k.id">{{ k.nama }}</option>
        </select>
      </FormField>
    </FilterBar>

    <TabelData
      :kolom="kolom"
      :baris="baris"
      :meta="meta"
      :memuat="sedangMuat"
      pesan-kosong="Belum ada barang"
      @ubah-halaman="muat($event)"
    >
      <template #cell-kategori="{ baris: b }">{{ namaKategori(b) }}</template>
      <template #cell-kondisi="{ baris: b }">
        <span
          :class="[
            'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold border whitespace-nowrap',
            b.kondisi === 'baik' ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
              : b.kondisi === 'semua_rusak' ? 'bg-red-50 text-red-800 border-red-200'
              : 'bg-yellow-50 text-yellow-800 border-yellow-200',
          ]"
        >
          {{ labelKondisi(b.kondisi) }}
        </span>
      </template>
      <template #cell-aksi="{ baris: b }">
        <div class="flex flex-wrap items-center gap-1.5">
          <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push(`/admin/barang/${b.id}/ubah`)">Ubah</TombolBesar>
          <TombolBesar varian="sekunder" ukuran="sedang" @click="bukaStok(b)">Stok</TombolBesar>
          <TombolBesar
            v-if="b.tersedia > 0"
            varian="sekunder"
            ukuran="sedang"
            @click="bukaRusak(b)"
          >Tandai Rusak</TombolBesar>
          <TombolBesar varian="bahaya" ukuran="sedang" @click="mintaHapus(b)">Hapus</TombolBesar>
        </div>
      </template>
    </TabelData>

    <!-- Modal penyesuaian stok / tandai rusak -->
    <div
      v-if="modalAksi"
      class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
    >
      <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">{{ judulAksi() }} — {{ targetAksi?.nama }}</h3>

        <div v-if="jenisAksi === 'stok'" class="space-y-4">
          <FormField id="a-arah" label="Arah" wajib>
            <select
              id="a-arah"
              v-model="arah"
              class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
            >
              <option value="tambah">Tambah</option>
              <option value="kurang">Kurang</option>
            </select>
          </FormField>

          <FormField id="a-jumlah" label="Jumlah" wajib>
            <InputJumlah id="a-jumlah" v-model="jumlah" :min="1" :max="999" />
          </FormField>
        </div>

        <FormField v-else id="a-jumlah" label="Jumlah Unit Rusak" wajib bantuan="Maksimal tersedia saat ini.">
          <InputJumlah id="a-jumlah" v-model="jumlah" :min="1" :max="maksAksi()" />
        </FormField>

        <FormField
          id="a-alasan"
          label="Alasan"
          wajib
          bantuan="Wajib. Tercatat di mutasi stok dan jejak audit."
        >
          <input
            id="a-alasan"
            v-model="alasan"
            type="text"
            maxlength="500"
            class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
          >
        </FormField>

        <div class="flex justify-end gap-2">
          <TombolBesar varian="sekunder" ukuran="sedang" @click="modalAksi = false">Batal</TombolBesar>
          <TombolBesar varian="primer" ukuran="sedang" :memuat="sedangKirim" @click="jalankanAksi">Simpan</TombolBesar>
        </div>
      </div>
    </div>

    <!-- Konfirmasi hapus -->
    <ModalKonfirmasi
      :terbuka="modalHapus"
      judul="Hapus Barang"
      :pesan="`Hapus barang &quot;${targetHapus?.nama}&quot;? Barang tidak akan tampil di katalog dan tabel (soft-delete).`"
      teks-batal="Jangan Hapus"
      teks-konfirmasi="Ya, Hapus"
      varian-konfirmasi="bahaya"
      :memuat="sedangKirim"
      @update:terbuka="modalHapus = $event"
      @konfirmasi="jalankanHapus"
    />
  </section>
</template>
