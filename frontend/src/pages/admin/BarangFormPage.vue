<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '@/api'
import FormField from '@/components/FormField.vue'
import InputJumlah from '@/components/InputJumlah.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const { errors, pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()
const { tampil: toast } = useToast()

// Bila ada param id, halaman berubah menjadi mode ubah.
const idUbah = computed(() => (route.params.id ? Number(route.params.id) : null))

const nama = ref('')
const deskripsi = ref('')
const kategoriId = ref('')
const bolehDiperpanjang = ref(true)
const jumlahAwal = ref(0)

const kategoriDaftar = ref([])
const sedangMuat = ref(false)

async function muatKategori() {
  try {
    const res = await adminApi.getCategories()
    kategoriDaftar.value = (res.data?.data || []).filter((k) => k.aktif)
  } catch (err) {
    tanganiGalat(err)
  }
}

async function muatBarang() {
  sedangMuat.value = true
  try {
    const res = await adminApi.getItem(idUbah.value)
    const barang = res.data?.data

    if (!barang) {
      toast('Barang tidak ditemukan. Mungkin sudah dihapus.', 'galat')
      router.replace('/admin/barang')
      return
    }

    nama.value = barang.nama
    deskripsi.value = barang.deskripsi || ''
    kategoriId.value = String(barang.kategori?.id ?? '')
    bolehDiperpanjang.value = Boolean(barang.boleh_diperpanjang)
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

onMounted(async () => {
  await muatKategori()
  if (idUbah.value) {
    await muatBarang()
  }
})

async function simpan() {
  bersihkan()
  const teks = nama.value.trim()
  const galatKlien = {}

  if (teks.length < 2 || teks.length > 150) {
    galatKlien.nama = ['Nama minimal 2 dan maksimal 150 karakter.']
  }
  if (!kategoriId.value) {
    galatKlien.kategori_id = ['Kategori wajib dipilih.']
  }
  if (idUbah.value === null && Number(jumlahAwal.value) < 0) {
    galatKlien.jumlah_awal = ['Jumlah awal tidak boleh negatif.']
  }

  if (Object.keys(galatKlien).length > 0) {
    errors.value = galatKlien
    pesanGalat.value = 'Mohon periksa kembali isian formulir.'
    return
  }

  sedangKirim.value = true
  try {
    if (idUbah.value === null) {
      await adminApi.createItem({
        nama: teks,
        deskripsi: deskripsi.value.trim() || null,
        kategori_id: Number(kategoriId.value),
        boleh_diperpanjang: bolehDiperpanjang.value,
        jumlah_awal: Number(jumlahAwal.value) || 0,
      })
      toast('Barang ditambahkan.')
    } else {
      await adminApi.updateItem(idUbah.value, {
        nama: teks,
        deskripsi: deskripsi.value.trim() || null,
        kategori_id: Number(kategoriId.value),
        boleh_diperpanjang: bolehDiperpanjang.value,
      })
      toast('Barang diubah.')
    }
    router.push('/admin/barang')
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangKirim.value = false
  }
}
</script>

<template>
  <section class="space-y-6 max-w-2xl">
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">
          {{ idUbah ? 'Ubah Barang' : 'Tambah Barang' }}
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
          {{ idUbah ? 'Stok diubah lewat penyesuaian, bukan form ini (FR-A-03).' : 'Stok awal masuk lewat mutasi TAMBAH_STOK.' }}
        </p>
      </div>
      <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push('/admin/barang')">&larr; Kembali</TombolBesar>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <form class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5" @submit.prevent="simpan">
      <FormField id="b-nama" label="Nama Barang" wajib :galat="errors.nama?.[0]">
        <input
          id="b-nama"
          v-model="nama"
          type="text"
          maxlength="150"
          placeholder="Contoh: Proyektor Epson EB-X51"
          :disabled="sedangKirim || sedangMuat"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
      </FormField>

      <FormField id="b-deskripsi" label="Deskripsi" bantuan="Opsional.">
        <textarea
          id="b-deskripsi"
          v-model="deskripsi"
          rows="3"
          maxlength="500"
          :disabled="sedangKirim || sedangMuat"
          class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        ></textarea>
      </FormField>

      <FormField id="b-kategori" label="Kategori" wajib bantuan="Hanya kategori aktif yang tampil (BR-28)." :galat="errors.kategori_id?.[0]">
        <select
          id="b-kategori"
          v-model="kategoriId"
          :disabled="sedangKirim || sedangMuat"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Pilih kategori</option>
          <option v-for="k in kategoriDaftar" :key="k.id" :value="String(k.id)">{{ k.nama }}</option>
        </select>
      </FormField>

      <FormField v-if="idUbah === null" id="b-jumlah" label="Jumlah Awal" bantuan="0 sampai 100.000, bawaan 0." :galat="errors.jumlah_awal?.[0]">
        <InputJumlah id="b-jumlah" v-model="jumlahAwal" :min="0" :max="100000" :dinonaktifkan="sedangKirim || sedangMuat" />
      </FormField>

      <label class="flex items-center gap-3 cursor-pointer select-none">
        <input
          v-model="bolehDiperpanjang"
          type="checkbox"
          :disabled="sedangKirim || sedangMuat"
          class="w-5 h-5 rounded border-slate-300 text-[var(--color-aksen,#1d4ed8)] focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
        <span class="text-sm font-medium text-slate-900">Boleh diperpanjang oleh peminjam</span>
      </label>

      <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <TombolBesar varian="sekunder" ukuran="sedang" :dinonaktifkan="sedangKirim" @click="router.push('/admin/barang')">Batal</TombolBesar>
        <TombolBesar tipe="submit" varian="primer" ukuran="sedang" :memuat="sedangKirim" :dinonaktifkan="sedangKirim || sedangMuat">
          {{ idUbah ? 'Simpan Perubahan' : 'Tambah Barang' }}
        </TombolBesar>
      </div>
    </form>
  </section>
</template>
