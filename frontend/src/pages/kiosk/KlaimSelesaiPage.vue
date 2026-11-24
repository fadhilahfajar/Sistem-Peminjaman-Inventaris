<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKioskStore } from '@/stores/kiosk'
import StrukCetak from '@/components/StrukCetak.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { formatWaktuCetak, formatWaktuIndo } from '@/utils/tanggal'

const router = useRouter()
const kioskStore = useKioskStore()

const dataKlaim = computed(() => kioskStore.klaimSelesai)
const waktuCetakSekarang = ref(formatWaktuCetak())

// Akses langsung / reload / Back: bukti klaim hanya ada di memori (DESAIN 10.1).
if (!dataKlaim.value || !dataKlaim.value.kode_klaim) {
  router.replace({ path: '/cek', query: { perlu_kode: '1' } })
}

const LABEL_KONDISI = { baik: 'Baik', rusak: 'Rusak', hilang: 'Hilang' }

// Respons backend: lines[{ nama, kondisi_dilaporkan, qty }].
const ringkasanBarang = computed(() =>
  (dataKlaim.value?.lines || []).map((line) => ({
    ...line,
    nama: `${line.nama}, ${LABEL_KONDISI[line.kondisi_dilaporkan] || line.kondisi_dilaporkan}`,
  })),
)

const barisTambahan = computed(() => {
  const baris = [
    { label: 'Kode Peminjaman', nilai: dataKlaim.value?.kode_pinjam },
    { label: 'Diajukan', nilai: formatWaktuIndo(dataKlaim.value?.diklaim_pada) },
    {
      label: 'Status',
      nilai: dataKlaim.value?.status === 'diverifikasi' ? 'Diverifikasi' : 'Menunggu Verifikasi',
    },
  ]

  if (Number(dataKlaim.value?.hari_terlambat) > 0) {
    baris.push({ label: 'Hari Terlambat', nilai: `${dataKlaim.value.hari_terlambat} hari` })
  }

  return baris
})

function selesai() {
  kioskStore.resetSesi()
  router.replace('/')
}
</script>

<template>
  <div v-if="dataKlaim" class="space-y-6 max-w-2xl mx-auto py-4">
    <!-- Header Sukses (sembunyi saat cetak) -->
    <div class="print:hidden text-center space-y-2">
      <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center mx-auto mb-3">
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
      <p class="inline-flex items-center px-3 py-1 rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-sm font-bold">
        Menunggu Verifikasi
      </p>
    </div>

    <!-- Pengingat Menyerahkan Barang Fisik -->
    <div class="print:hidden p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm text-center">
      <strong>PENTING:</strong> Serahkan barang fisik ke petugas IT untuk diverifikasi.
      Status klaim masih <strong>Menunggu Verifikasi</strong> sampai petugas memeriksa barang.
    </div>

    <!-- Bukti Klaim (tata letak cetak) -->
    <StrukCetak
      judul="BUKTI KLAIM PENGEMBALIAN"
      :kode="dataKlaim.kode_klaim"
      label-kode="KODE KLAIM"
      :nama-peminjam="''"
      :tanggal-pinjam="''"
      :batas-kembali="''"
      :items="ringkasanBarang"
      :baris-tambahan="barisTambahan"
      :catatan="`Barang fisik harus diserahkan ke petugas IT untuk diverifikasi. Status saat ini: Menunggu Verifikasi.`"
      :waktu-cetak="waktuCetakSekarang"
    />

    <!-- Aksi: hanya Selesai, tanpa kembali ke form / kartu -->
    <div class="print:hidden pt-2 flex justify-center">
      <TombolBesar
        varian="primer"
        ukuran="besar"
        class="w-full sm:w-auto sm:min-w-[240px]"
        @click="selesai"
      >
        Selesai
      </TombolBesar>
    </div>
  </div>
</template>
