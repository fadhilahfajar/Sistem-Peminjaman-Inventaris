<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/api'
import FormField from '@/components/FormField.vue'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import StatusKosong from '@/components/StatusKosong.vue'
import Memuat from '@/components/Memuat.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useToast } from '@/composables/useToast'

const { errors, pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()
const { tampil: toast } = useToast()

const daftar = ref([])
const sedangMuat = ref(false)

// Form tambah/ubah nama
const modeForm = ref('tutup') // tutup | tambah | ubah
const sedangUbah = ref(null)
const nama = ref('')

// Toggle aktif (BR-28, BR-33)
const targetToggle = ref(null)
const modalKonfirmasi = ref(false)
const modalNonaktif = ref(false)

async function muat() {
  sedangMuat.value = true
  try {
    const res = await adminApi.getCategories()
    daftar.value = res.data?.data || []
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

onMounted(muat)

function bukaTambah() {
  bersihkan()
  sedangUbah.value = null
  nama.value = ''
  modeForm.value = 'tambah'
}

function bukaUbah(kategori) {
  bersihkan()
  sedangUbah.value = kategori
  nama.value = kategori.nama
  modeForm.value = 'ubah'
}

async function simpan() {
  bersihkan()
  const teks = nama.value.trim()
  if (teks.length < 2 || teks.length > 100) {
    errors.value = { nama: ['Nama minimal 2 dan maksimal 100 karakter.'] }
    return
  }

  sedangKirim.value = true
  try {
    if (modeForm.value === 'tambah') {
      await adminApi.createCategory({ nama: teks })
      toast(`Kategori "${teks}" ditambahkan.`)
    } else {
      await adminApi.updateCategory(sedangUbah.value.id, { nama: teks })
      toast('Nama kategori diubah.')
    }
    modeForm.value = 'tutup'
    await muat()
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangKirim.value = false
  }
}

function mintaToggle(kategori) {
  bersihkan()
  targetToggle.value = kategori
  // Nonaktif diberi konfirmasi; mengaktifkan langsung.
  if (kategori.aktif) {
    modalKonfirmasi.value = true
  } else {
    jalankanToggle()
  }
}

async function jalankanToggle() {
  modalKonfirmasi.value = false
  const kategori = targetToggle.value
  if (!kategori) return

  modalNonaktif.value = true
  try {
    await adminApi.updateCategory(kategori.id, { aktif: !kategori.aktif })
    toast(kategori.aktif ? `Kategori "${kategori.nama}" dinonaktifkan.` : `Kategori "${kategori.nama}" diaktifkan.`)
    await muat()
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Gagal mengubah status kategori.', 'galat')
  } finally {
    modalNonaktif.value = false
    targetToggle.value = null
  }
}
</script>

<template>
  <section class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Kategori Barang</h2>
        <p class="text-sm text-slate-500 mt-0.5">Tambah, ubah nama, dan nonaktifkan (BR-28, BR-33).</p>
      </div>
      <TombolBesar varian="primer" ukuran="sedang" @click="bukaTambah">+ Tambah</TombolBesar>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <Memuat v-if="sedangMuat" tipe="skeleton" :baris="3" />
    <StatusKosong v-else-if="daftar.length === 0" judul="Belum ada kategori" />

    <div v-else class="bg-white rounded-xl border border-slate-200 shadow-xs divide-y divide-slate-100">
      <div
        v-for="k in daftar"
        :key="k.id"
        class="flex flex-wrap items-center gap-3 px-4 py-3"
      >
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-slate-900 truncate">{{ k.nama }}</p>
        </div>

        <span
          :class="[
            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border whitespace-nowrap',
            k.aktif ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-300',
          ]"
        >
          {{ k.aktif ? 'Aktif' : 'Nonaktif' }}
        </span>

        <div class="flex items-center gap-2">
          <TombolBesar varian="sekunder" ukuran="sedang" @click="bukaUbah(k)">Ubah</TombolBesar>
          <TombolBesar
            varian="sekunder"
            ukuran="sedang"
            :dinonaktifkan="modalNonaktif"
            @click="mintaToggle(k)"
          >
            {{ k.aktif ? 'Nonaktifkan' : 'Aktifkan' }}
          </TombolBesar>
        </div>
      </div>
    </div>

    <!-- Form tambah/ubah nama -->
    <div
      v-if="modeForm !== 'tutup'"
      class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
    >
      <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">
          {{ modeForm === 'tambah' ? 'Tambah Kategori' : 'Ubah Kategori' }}
        </h3>

        <FormField id="nama" label="Nama Kategori" wajib :galat="errors.nama?.[0]">
          <input
            id="nama"
            v-model="nama"
            type="text"
            maxlength="100"
            class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent"
          >
        </FormField>

        <div class="flex justify-end gap-2">
          <TombolBesar varian="sekunder" ukuran="sedang" @click="modeForm = 'tutup'">Batal</TombolBesar>
          <TombolBesar varian="primer" ukuran="sedang" :memuat="sedangKirim" @click="simpan">
            {{ modeForm === 'tambah' ? 'Tambah' : 'Simpan' }}
          </TombolBesar>
        </div>
      </div>
    </div>

    <!-- Konfirmasi nonaktif -->
    <ModalKonfirmasi
      :terbuka="modalKonfirmasi"
      judul="Nonaktifkan Kategori"
      :pesan="`Nonaktifkan kategori &quot;${targetToggle?.nama}&quot;? Kategori yang masih dipakai barang aktif akan ditolak server.`"
      teks-batal="Jangan"
      teks-konfirmasi="Ya, Nonaktifkan"
      :memuat="modalNonaktif"
      @update:terbuka="modalKonfirmasi = $event"
      @konfirmasi="jalankanToggle"
    />
  </section>
</template>
