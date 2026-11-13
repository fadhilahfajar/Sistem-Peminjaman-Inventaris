<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@/api'
import FormField from '@/components/FormField.vue'
import InputJumlah from '@/components/InputJumlah.vue'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import Memuat from '@/components/Memuat.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import StatusKosong from '@/components/StatusKosong.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const { pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()
const { tampil: toast } = useToast()

// Daftar barang rusak > 0 (FR-A-09, GET /api/admin/repairs, tanpa pagination).
const baris = ref([])
const sedangMuat = ref(false)

const kolom = [
  { kunci: 'nama', label: 'Nama Barang' },
  { kunci: 'kategori', label: 'Kategori' },
  { kunci: 'rusak', label: 'Unit Rusak' },
  { kunci: 'tersedia', label: 'Tersedia' },
  { kunci: 'total', label: 'Total' },
  { kunci: 'aksi', label: 'Aksi' },
]

async function muat() {
  sedangMuat.value = true
  bersihkan()
  try {
    const res = await adminApi.getRepairs()
    baris.value = res.data?.data || []
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

onMounted(muat)

function namaKategori(b) {
  return b.kategori?.nama ?? '—'
}

// --- Modal aksi perbaikan / afkir ---
const modalAksi = ref(false)
const jenisAksi = ref('perbaikan') // perbaikan | afkir
const targetAksi = ref(null)
const jumlah = ref(1)
const teks = ref('') // catatan (perbaikan) atau alasan (afkir)

function bukaPerbaikan(b) {
  bersihkan()
  jenisAksi.value = 'perbaikan'
  targetAksi.value = b
  jumlah.value = 1
  teks.value = ''
  modalAksi.value = true
}

function bukaAfkir(b) {
  bersihkan()
  jenisAksi.value = 'afkir'
  targetAksi.value = b
  jumlah.value = 1
  teks.value = ''
  modalAksi.value = true
}

function labelTeks() {
  return jenisAksi.value === 'perbaikan' ? 'Catatan (Opsional)' : 'Alasan (Wajib)'
}

async function jalankanAksi() {
  if (!targetAksi.value) return
  bersihkan()

  const jumlahIsi = Number(jumlah.value)
  if (!jumlahIsi || jumlahIsi < 1) {
    errors.value = { jumlah: ['Jumlah minimal 1.'] }
    return
  }

  const isi = teks.value.trim()
  if (jenisAksi.value === 'afkir' && !isi) {
    errors.value = { alasan: ['Alasan wajib diisi.'] }
    return
  }

  const itemId = targetAksi.value.id
  modalAksi.value = false
  sedangKirim.value = true

  try {
    if (jenisAksi.value === 'perbaikan') {
      await adminApi.repairComplete(itemId, { jumlah: jumlahIsi, catatan: isi || null })
      toast(`${jumlahIsi} unit selesai diperbaiki dan kembali tersedia.`)
    } else {
      await adminApi.writeOff(itemId, { jumlah: jumlahIsi, alasan: isi })
      toast(`${jumlahIsi} unit diafkir.`)
    }
    targetAksi.value = null
    await muat()
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Gagal menyimpan aksi.', 'galat')
  } finally {
    sedangKirim.value = false
  }
}
</script>

<template>
  <section class="space-y-6">
    <div>
      <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Stok Rusak</h2>
      <p class="text-sm text-slate-500 mt-0.5">
        Barang dengan unit rusak. Aksi: selesai diperbaiki (BR-09) atau afkir. Tandai rusak dilakukan dari daftar Barang.
      </p>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <Memuat v-if="sedangMuat" tipe="skeleton" :baris="4" />
    <StatusKosong
      v-else-if="baris.length === 0"
      judul="Tidak ada unit rusak"
      pesan="Semua barang dalam kondisi baik."
    >
      <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push('/admin/barang')">Lihat Daftar Barang</TombolBesar>
    </StatusKosong>

    <div v-else class="bg-white rounded-xl border border-slate-200 shadow-xs divide-y divide-slate-100">
      <div
        v-for="b in baris"
        :key="b.id"
        class="flex flex-wrap items-center gap-3 px-4 py-3"
      >
        <div class="flex-1 min-w-[180px]">
          <p class="font-semibold text-slate-900">{{ b.nama }}</p>
          <p class="text-xs text-slate-500">{{ namaKategori(b) }} &middot; rusak {{ b.rusak }} dari total {{ b.total }} (tersedia {{ b.tersedia }})</p>
        </div>

        <div class="flex items-center gap-2">
          <TombolBesar varian="sukses" ukuran="sedang" @click="bukaPerbaikan(b)">Selesai Diperbaiki</TombolBesar>
          <TombolBesar varian="bahaya" ukuran="sedang" @click="bukaAfkir(b)">Afkir</TombolBesar>
        </div>
      </div>
    </div>

    <!-- Modal aksi -->
    <div
      v-if="modalAksi"
      class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
    >
      <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">
          {{ jenisAksi === 'perbaikan' ? 'Selesai Diperbaiki' : 'Afkir' }} — {{ targetAksi?.nama }}
        </h3>

        <p class="text-xs text-slate-500">
          Unit rusak saat ini: {{ targetAksi?.rusak }}. {{ jenisAksi === 'perbaikan' ? 'Rusak berkurang, tersedia bertambah, total tetap.' : 'Rusak dan total berkurang sama banyak.' }}
        </p>

        <FormField id="s-jumlah" label="Jumlah Unit" wajib>
          <InputJumlah id="s-jumlah" v-model="jumlah" :min="1" :max="Number(targetAksi?.rusak) || 1" />
        </FormField>

        <FormField
          id="s-teks"
          :label="labelTeks()"
          :wajib="jenisAksi === 'afkir'"
          :galat="errors.alasan?.[0] || errors.catatan?.[0]"
        >
          <input
            id="s-teks"
            v-model="teks"
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
  </section>
</template>
