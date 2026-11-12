<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKioskStore } from '@/stores/kiosk'
import StrukCetak from '@/components/StrukCetak.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { formatWaktuCetak } from '@/utils/tanggal'

const router = useRouter()
const kioskStore = useKioskStore()

const dataPerpanjang = computed(() => kioskStore.perpanjangSelesai)
const waktuCetakSekarang = ref('')

onMounted(() => {
  if (!dataPerpanjang.value || !dataPerpanjang.value.kode_pinjam) {
    router.replace('/cek')
    return
  }

  waktuCetakSekarang.value = formatWaktuCetak()
})

function selesai() {
  kioskStore.resetSesi()
  router.push('/')
}
</script>

<template>
  <div v-if="dataPerpanjang" class="space-y-6 max-w-2xl mx-auto py-4">
    <!-- Header Sukses (sembunyi saat cetak) -->
    <div class="print:hidden text-center space-y-2">
      <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
      </div>

      <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900">
        Perpanjangan Berhasil!
      </h2>
      <p class="text-slate-600 text-base">
        Batas kembali bergeser {{ dataPerpanjang.tambahan_hari }} hari lebih lama.
      </p>
    </div>

    <!-- Bukti Perpanjangan (FR-K-08, dapat dicetak) -->
    <StrukCetak
      judul="BUKTI PERPANJANGAN BATAS KEMBALI"
      :kode="dataPerpanjang.kode_pinjam"
      :batas-kembali="dataPerpanjang.batas_kembali"
      :items="[]"
      :baris-tambahan="[
        { label: 'Batas Sebelumnya', nilai: dataPerpanjang.batas_kembali_sebelumnya },
        { label: 'Tambahan Hari', nilai: `${dataPerpanjang.tambahan_hari} hari` },
        { label: 'Kuota Terpakai', nilai: `${dataPerpanjang.perpanjangan?.terpakai || 0} dari ${dataPerpanjang.perpanjangan?.maks || 0}` },
      ]"
      :waktu-cetak="waktuCetakSekarang"
    />

    <!-- Aksi -->
    <div class="print:hidden pt-2 flex justify-center">
      <TombolBesar
        varian="primer"
        ukuran="besar"
        class="w-full sm:w-auto sm:min-w-[240px]"
        @click="selesai"
      >
        Selesai & Kembali ke Beranda
      </TombolBesar>
    </div>
  </div>
</template>
