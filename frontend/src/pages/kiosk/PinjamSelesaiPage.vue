<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKioskStore } from '@/stores/kiosk'
import StrukCetak from '@/components/StrukCetak.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { formatTanggalIndo, formatWaktuCetak } from '@/utils/tanggal'

const router = useRouter()
const kioskStore = useKioskStore()

const dataPinjam = computed(() => kioskStore.pinjamSelesai)
const waktuCetakSekarang = ref('')

onMounted(() => {
  // Bila tidak ada data peminjaman di memori (mis. reload), kembali ke beranda (DESAIN 10.1)
  if (!dataPinjam.value || !dataPinjam.value.kode_pinjam) {
    router.replace('/')
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
  <div v-if="dataPinjam" class="space-y-8 max-w-2xl mx-auto py-4">
    <!-- Header Sukses (Sembunyi saat cetak fisik) -->
    <div class="print:hidden text-center space-y-2">
      <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
      </div>

      <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900">
        {{ dataPinjam.pemesanan ? 'Pemesanan Berhasil Dibuat!' : 'Peminjaman Berhasil!' }}
      </h2>

      <p class="text-slate-600 text-base max-w-md mx-auto">
        <template v-if="dataPinjam.pemesanan">
          Pemesanan untuk tanggal <strong>{{ formatTanggalIndo(dataPinjam.tanggal_pinjam) }}</strong> telah dicatat dan stok barang telah ditahan.
        </template>
        <template v-else>
          Permohonan peminjaman inventaris Anda telah dicatat dalam sistem.
        </template>
      </p>
    </div>

    <!-- Peringatan Simpan Kode (Sembunyi saat cetak fisik) -->
    <div class="print:hidden p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm text-center">
      <strong>PENTING:</strong> Catat atau foto <strong>Kode Peminjaman</strong> di bawah ini. Kode ini diperlukan saat pengambilan, pengecekan, perpanjangan, dan pengembalian barang.
    </div>

    <!-- Struk Cetak Komponen (FR-K-03, T-39, UI-06) -->
    <StrukCetak
      :judul="dataPinjam.pemesanan ? 'BUKTI PEMESANAN INVENTARIS' : 'BUKTI PEMINJAMAN INVENTARIS'"
      :kode="dataPinjam.kode_pinjam"
      :nama-peminjam="dataPinjam.nama_peminjam"
      :divisi="dataPinjam.division?.nama"
      :tipe-peminjam="dataPinjam.tipe_peminjam"
      :tanggal-pinjam="dataPinjam.tanggal_pinjam"
      :batas-kembali="dataPinjam.batas_kembali"
      :items="dataPinjam.items"
      :pemesanan="Boolean(dataPinjam.pemesanan)"
      :waktu-cetak="waktuCetakSekarang"
    />

    <!-- Tombol Selesai (Sembunyi saat cetak fisik) -->
    <div class="print:hidden pt-4 flex justify-center">
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
