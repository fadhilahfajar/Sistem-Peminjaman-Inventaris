<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { kioskApi } from '@/api'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useKioskStore } from '@/stores/kiosk'
import { formatTanggalIndo, formatWaktuIndo } from '@/utils/tanggal'

const router = useRouter()
const kioskStore = useKioskStore()
const { pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()

const kartu = computed(() => kioskStore.kartuPinjam)

const bolehBatal = computed(() => kartu.value?.aksi?.batal?.boleh === true)
const alasanTidakBoleh = computed(() => kartu.value?.aksi?.batal?.alasan || '')

const modalTerbuka = ref(false)
const suksesData = ref(null)

onMounted(() => {
  if (!kartu.value || !kartu.value.kode_pinjam) {
    router.replace('/cek')
  }
})

async function batalkan() {
  if (sedangKirim.value) return

  modalTerbuka.value = false
  sedangKirim.value = true
  bersihkan()

  try {
    const res = await kioskApi.batalLoan({ kode: kartu.value.kode_pinjam })
    suksesData.value = res.data?.data
    // Stok telah dikembalikan server. Sesi kode selesai, bersihkan memori.
    kioskStore.resetSesi()
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangKirim.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto py-2">
    <!-- Header -->
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
          Batalkan Pemesanan
        </h2>
        <p class="text-xs md:text-sm text-slate-600 mt-0.5">
          Stok barang akan langsung dikembalikan setelah pembatalan berhasil (FR-K-12).
        </p>
      </div>

      <TombolBesar
        v-if="!suksesData"
        varian="sekunder"
        ukuran="sedang"
        @click="router.push('/cek/kartu')"
      >
        &larr; Kartu
      </TombolBesar>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <!-- Layar Sukses -->
    <div v-if="suksesData" class="space-y-6">
      <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm text-center">
        <strong>Pemesanan {{ suksesData.kode_pinjam }} berhasil dibatalkan</strong>
        pada {{ formatWaktuIndo(suksesData.dibatalkan_pada) }}. Stok barang telah kembali tersedia.
      </div>

      <div class="flex justify-center">
        <TombolBesar varian="primer" ukuran="besar" @click="router.push('/')">
          Kembali ke Beranda
        </TombolBesar>
      </div>
    </div>

    <!-- Layar Konfirmasi -->
    <div v-else-if="kartu" class="space-y-6">
      <!-- Aksi tidak berlaku -->
      <div
        v-if="!bolehBatal"
        class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm"
      >
        <strong>Pembatalan tidak dapat dilakukan:</strong> {{ alasanTidakBoleh }}
      </div>

      <!-- Ringkasan Pemesanan -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <div class="pb-3 border-b border-slate-100">
          <h3 class="text-lg md:text-xl font-bold text-slate-900">Ringkasan Pemesanan</h3>
        </div>

        <div class="text-sm space-y-2">
          <div class="flex justify-between">
            <span class="text-slate-500">Kode Pemesanan:</span>
            <span class="font-mono font-bold text-slate-900">{{ kartu.kode_pinjam }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Peminjam:</span>
            <span class="font-bold text-slate-900">{{ kartu.nama_peminjam }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Tanggal Mulai:</span>
            <span class="font-bold text-slate-900">{{ formatTanggalIndo(kartu.tanggal_pinjam) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Total Barang:</span>
            <span class="font-bold text-slate-900">
              {{ (kartu.items || []).length }} jenis &middot;
              {{ (kartu.items || []).reduce((t, i) => t + (i.qty_dipinjam || 0), 0) }} unit
            </span>
          </div>
        </div>

        <!-- Daftar barang -->
        <div class="pt-2 border-t border-slate-100 space-y-1 text-xs">
          <div
            v-for="item in kartu.items"
            :key="item.item_id"
            class="flex justify-between text-slate-600"
          >
            <span>{{ item.nama }}</span>
            <span class="font-semibold">{{ item.qty_dipinjam }} unit</span>
          </div>
        </div>
      </section>

      <!-- Aksi -->
      <div class="flex justify-center">
        <TombolBesar
          varian="bahaya"
          ukuran="besar"
          class="w-full sm:w-auto sm:min-w-[260px]"
          :dinonaktifkan="!bolehBatal"
          @click="modalTerbuka = true"
        >
          Batalkan Pemesanan Ini
        </TombolBesar>
      </div>
    </div>

    <!-- Modal Konfirmasi (FR-K-12) -->
    <ModalKonfirmasi
      :terbuka="modalTerbuka"
      judul="Konfirmasi Pembatalan"
      :pesan="`Batalkan pemesanan ${kartu?.kode_pinjam}? Stok barang akan langsung dikembalikan ke katalog dan pemesanan tidak dapat dipulihkan.`"
      teks-batal="Jangan Batalkan"
      teks-konfirmasi="Ya, Batalkan Pemesanan"
      varian-konfirmasi="bahaya"
      :memuat="sedangKirim"
      @update:terbuka="modalTerbuka = $event"
      @konfirmasi="batalkan"
    />
  </div>
</template>
