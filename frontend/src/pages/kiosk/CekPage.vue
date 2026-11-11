<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { kioskApi } from '@/api'
import FormField from '@/components/FormField.vue'
import InputKode from '@/components/InputKode.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useKioskStore } from '@/stores/kiosk'

const router = useRouter()
const kioskStore = useKioskStore()

const kodeInput = ref('')
const sedangMemeriksa = ref(false)
const pesanGalat = ref('')
const terkunci = ref(false)

onMounted(() => {
  // Jika sudah ada kode di store sebelumnya, gunakan sebagai isian default
  if (kioskStore.kode) {
    kodeInput.value = kioskStore.kode
  }
})

async function periksaKode() {
  pesanGalat.value = ''
  const kodeBersih = kodeInput.value.trim().toUpperCase()

  if (!kodeBersih) {
    pesanGalat.value = 'Silakan masukkan kode peminjaman Anda.'
    return
  }

  // Format kode harus PJM-XXXXXX (BR-17)
  if (!kodeBersih.startsWith('PJM-') || kodeBersih.length !== 10) {
    pesanGalat.value = 'Format kode peminjaman tidak valid. Gunakan format PJM-XXXXXX.'
    return
  }

  sedangMemeriksa.value = true

  try {
    const res = await kioskApi.lookupLoan({ kode: kodeBersih })
    const dataKartu = res.data?.data

    if (dataKartu) {
      kioskStore.setKartuPinjam(dataKartu)
      await router.push('/cek/kartu')
    }
  } catch (err) {
    const status = err.response?.status
    const data = err.response?.data

    if (status === 429) {
      terkunci.value = true
      pesanGalat.value = err.pesanUmum || 'Terlalu banyak percobaan gagal. Akses pencarian kode terkunci sementara demi keamanan sistem. Silakan tunggu beberapa saat.'
    } else if (status === 404) {
      // Kode salah tidak membocorkan data (FR-K-04)
      pesanGalat.value = data?.pesan || 'Kode peminjaman tidak ditemukan. Mohon periksa kembali kode pada struk atau foto bukti peminjaman Anda.'
    } else {
      pesanGalat.value = err.pesanUmum || data?.pesan || 'Gagal memeriksa kode peminjaman. Silakan coba lagi.'
    }
  } finally {
    sedangMemeriksa.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto py-4">
    <!-- Header Halaman -->
    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
          Cek Peminjaman & Pengembalian
        </h2>
        <p class="text-sm md:text-base text-slate-600 mt-0.5">
          Periksa status peminjaman, ajukan pengembalian, perpanjang, atau batalkan pemesanan.
        </p>
      </div>

      <TombolBesar
        varian="sekunder"
        ukuran="sedang"
        class="shrink-0"
        :dinonaktifkan="sedangMemeriksa"
        @click="router.push('/')"
      >
        &larr; Beranda
      </TombolBesar>
    </div>

    <!-- Formulir Pencarian Kode -->
    <div class="bg-white p-6 md:p-8 rounded-2xl border border-slate-200 shadow-xs space-y-6">
      <div class="text-center space-y-2 max-w-md mx-auto">
        <div class="w-14 h-14 bg-blue-50 text-[var(--color-aksen,#1d4ed8)] rounded-2xl flex items-center justify-center mx-auto mb-2">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-slate-900">
          Masukkan Kode Peminjaman
        </h3>
        <p class="text-xs md:text-sm text-slate-500">
          Kode tercantum pada struk bukti peminjaman dengan format <strong>PJM-XXXXXX</strong>.
        </p>
      </div>

      <!-- Pesan Galat / Peringatan Terkunci -->
      <PesanGalat
        v-if="pesanGalat"
        :judul="terkunci ? 'Pencarian Terkunci' : 'Pemeriksaan Gagal'"
        :pesan="pesanGalat"
      />

      <form class="space-y-5" @submit.prevent="periksaKode">
        <FormField
          id="kode_pinjam"
          label="Kode Peminjaman"
          wajib
          bantuan="Contoh: PJM-A7B8C9 (huruf besar otomatis)"
        >
          <InputKode
            id="kode_pinjam"
            v-model="kodeInput"
            placeholder="PJM-XXXXXX"
            :dinonaktifkan="sedangMemeriksa || terkunci"
            fokus-otomatis
          />
        </FormField>

        <div class="pt-2">
          <TombolBesar
            tipe="submit"
            varian="primer"
            ukuran="besar"
            lebar-penuh
            :memuat="sedangMemeriksa"
            :dinonaktifkan="sedangMemeriksa || !kodeInput.trim() || terkunci"
          >
            Lihat Kartu Peminjaman
          </TombolBesar>
        </div>
      </form>
    </div>

    <!-- Bantuan / Petunjuk SOP -->
    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs md:text-sm text-slate-600 space-y-1.5">
      <strong class="font-bold text-slate-800 block">Lupa atau kehilangan kode peminjaman?</strong>
      <p>
        Silakan hubungi petugas IT yang bertugas di ruangan untuk bantuan pencarian kartu peminjaman berdasarkan identitas Anda.
      </p>
    </div>
  </div>
</template>
