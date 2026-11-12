<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKioskStore } from '@/stores/kiosk'
import StrukCetak from '@/components/StrukCetak.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { formatWaktuCetak, formatWaktuIndo } from '@/utils/tanggal'

const router = useRouter()
const kioskStore = useKioskStore()

const dataKlaim = computed(() => kioskStore.klaimSelesai)
const waktuCetakSekarang = ref('')

onMounted(() => {
  // Bila tidak ada data klaim di memori (mis. reload), kembali ke layar Cek (DESAIN 10.1)
  if (!dataKlaim.value || !dataKlaim.value.kode_klaim) {
    router.replace('/cek')
    return
  }

  waktuCetakSekarang.value = formatWaktuCetak()
})

function selesai() {
  kioskStore.hapusKode()
  router.push('/')
}
</script>

<template>
  <div v-if="dataKlaim" class="space-y-6 max-w-2xl mx-auto py-4">
    <!-- Header Sukses (sembunyi saat cetak) -->
    <div class="print:hidden text-center space-y-2">
      <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
      </div>

      <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900">
        Klaim Pengembalian Diajukan!
      </h2>
      <p class="text-slate-600 text-base">
        Ajukan pada {{ formatWaktuIndo(dataKlaim.diklaim_pada) }}.
      </p>
    </div>

    <!-- Pengingat Menyerahkan Barang Fisik (FR-K-06) -->
    <div class="print:hidden p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm text-center">
      <strong>PENTING:</strong> Segera serahkan barang fisik yang dilaporkan ke petugas IT.
      Status klaim masih <strong>Menunggu Verifikasi</strong> sampai petugas memeriksa barang.
    </div>

    <!-- Bukti Klaim (FR-K-06, tata letak cetak) -->
    <StrukCetak
      judul="BUKTI KLAIM PENGEMBALIAN"
      :kode="dataKlaim.kode_klaim"
      label-kode="KODE KLAIM"
      :nama-peminjam="''"
      :tanggal-pinjam="''"
      :batas-kembali="''"
      :items="dataKlaim.lines"
      :baris-tambahan="[
        { label: 'Kode Peminjaman', nilai: dataKlaim.kode_pinjam },
        { label: 'Diajukan', nilai: formatWaktuIndo(dataKlaim.diklaim_pada) },
        { label: 'Status', nilai: dataKlaim.status === 'diverifikasi' ? 'Diverifikasi' : 'Menunggu Verifikasi' },
      ]"
      :catatan="`Barang fisik harus diserahkan ke petugas IT untuk diverifikasi. Status saat ini: Menunggu Verifikasi.`"
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
