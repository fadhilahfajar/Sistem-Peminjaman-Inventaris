<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/api'
import FormField from '@/components/FormField.vue'
import InputJumlah from '@/components/InputJumlah.vue'
import Memuat from '@/components/Memuat.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useToast } from '@/composables/useToast'

const { errors, pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()
const { tampil: toast } = useToast()

const sedangMuat = ref(false)
const daftar = ref([])
// { [kunci]: nilai }
const nilaiForm = ref({})

const INFO_KUNCI = {
  maks_durasi_awal_hari: {
    label: 'Durasi Pinjam Awal',
    satuan: 'hari',
    deskripsi: 'Batas durasi peminjaman saat pertama kali dibuat.',
  },
  maks_perpanjangan: {
    label: 'Batas Perpanjangan',
    satuan: 'kali',
    deskripsi: 'Frekuensi maksimal peminjaman dapat diperpanjang oleh peminjam.',
  },
  maks_hari_per_perpanjangan: {
    label: 'Hari per Perpanjangan',
    satuan: 'hari',
    deskripsi: 'Tambahan hari maksimal untuk setiap kali perpanjangan.',
  },
  maks_booking_hari: {
    label: 'Pemesanan di Muka',
    satuan: 'hari',
    deskripsi: 'Maksimal jarak hari ke depan untuk tanggal mulai pinjam.',
  },
  sesi_idle_menit: {
    label: 'Idle Sesi Admin',
    satuan: 'menit',
    deskripsi: 'Sesi login admin berakhir otomatis bila tidak aktif.',
  },
  kiosk_idle_detik: {
    label: 'Idle Layar Kiosk',
    satuan: 'detik',
    deskripsi: 'Layar kiosk mereset otomatis ke beranda setelah waktu ini.',
  },
  kiosk_lookup_maks_gagal: {
    label: 'Batas Gagal Kode Kiosk',
    satuan: 'kali',
    deskripsi: 'Maksimal salah kode di kiosk sebelum akses diblokir sementara.',
  },
  kiosk_lookup_kunci_menit: {
    label: 'Durasi Kunci Kiosk',
    satuan: 'menit',
    deskripsi: 'Lama waktu pemblokiran kiosk setelah melewati batas gagal.',
  },
}

async function muat() {
  sedangMuat.value = true
  bersihkan()
  try {
    const res = await adminApi.getSettings()
    daftar.value = res.data?.data || []
    const baru = {}
    for (const item of daftar.value) {
      baru[item.kunci] = Number(item.nilai)
    }
    nilaiForm.value = baru
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

onMounted(muat)

async function simpan() {
  bersihkan()
  const galatKlien = {}

  for (const item of daftar.value) {
    const val = Number(nilaiForm.value[item.kunci])
    if (Number.isNaN(val) || val < item.min || val > item.maks) {
      galatKlien[item.kunci] = [`Nilai harus di antara ${item.min} dan ${item.maks}.`]
    }
  }

  if (Object.keys(galatKlien).length > 0) {
    errors.value = galatKlien
    pesanGalat.value = 'Terdapat nilai pengaturan di luar rentang valid.'
    return
  }

  sedangKirim.value = true
  try {
    await adminApi.updateSettings(nilaiForm.value)
    toast('Pengaturan berhasil disimpan dan langsung berlaku.')
    await muat()
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangKirim.value = false
  }
}
</script>

<template>
  <section class="space-y-6 max-w-4xl">
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Pengaturan Sistem</h2>
        <p class="text-sm text-slate-500 mt-0.5">
          Delapan parameter operasional kiosk dan panel admin (FR-A-12). Nilai baru langsung berlaku.
        </p>
      </div>
      <TombolBesar
        varian="primer"
        ukuran="sedang"
        :memuat="sedangKirim"
        :dinonaktifkan="sedangMuat || sedangKirim"
        @click="simpan"
      >
        Simpan Pengaturan
      </TombolBesar>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <Memuat v-if="sedangMuat" tipe="skeleton" :baris="4" />

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="item in daftar"
        :key="item.kunci"
        class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between gap-3"
      >
        <div>
          <div class="flex items-start justify-between gap-2">
            <h3 class="font-bold text-slate-900 text-sm md:text-base">
              {{ INFO_KUNCI[item.kunci]?.label || item.kunci }}
            </h3>
            <span class="text-xs font-semibold px-2 py-0.5 bg-slate-100 text-slate-600 rounded">
              {{ item.min }} – {{ item.maks }} {{ INFO_KUNCI[item.kunci]?.satuan || '' }}
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            {{ INFO_KUNCI[item.kunci]?.deskripsi || item.kunci }}
          </p>
        </div>

        <FormField
          :id="`s-${item.kunci}`"
          :label="`Nilai (${INFO_KUNCI[item.kunci]?.satuan || 'satuan'})`"
          :galat="errors[item.kunci]?.[0]"
        >
          <InputJumlah
            :id="`s-${item.kunci}`"
            v-model="nilaiForm[item.kunci]"
            :min="item.min"
            :max="item.maks"
            :dinonaktifkan="sedangKirim"
          />
        </FormField>
      </div>
    </div>
  </section>
</template>
