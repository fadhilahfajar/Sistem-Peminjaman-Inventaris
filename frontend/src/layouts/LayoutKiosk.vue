<script setup>
import { onErrorCaptured, ref } from 'vue'
import { useRouter } from 'vue-router'
import NotifikasiToast from '@/components/NotifikasiToast.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useIdleKiosk } from '@/composables/useIdleKiosk'
import { useKioskStore } from '@/stores/kiosk'
import { useNotifikasiStore } from '@/stores/notifikasi'

const router = useRouter()
const kioskStore = useKioskStore()
const notifikasi = useNotifikasiStore()
const { hitungMundur, aturUlang, batalkanMundur } = useIdleKiosk()

const galatTampilan = ref(false)

// Galat render apa pun ditampilkan sebagai pesan, bukan layar putih.
onErrorCaptured((err) => {
  console.error('[kiosk] galat tampilan:', err)
  kioskStore.resetSesi()
  notifikasi.tutup()
  galatTampilan.value = true
  return false
})

// Satu-satunya jalan umum ke beranda: header. Sesi dibersihkan seperti idle reset (FR-K-10).
function bersihkanSesi() {
  kioskStore.resetSesi()
  notifikasi.tutup()
}

function pulihkan() {
  bersihkanSesi()
  galatTampilan.value = false
  router.replace('/')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-kiosk text-slate-900 flex flex-col">
    <!-- Header Kiosk (Sembunyi saat cetak sesuai US-03, T-39, UI-06) -->
    <header class="print:hidden border-b border-slate-200 bg-white px-4 md:px-6 py-4 shadow-2xs">
      <div class="mx-auto max-w-5xl">
        <RouterLink
          to="/"
          aria-label="Kembali ke Beranda"
          class="flex items-center gap-2 hover:opacity-85 transition-opacity rounded-lg focus-visible:outline-2 focus-visible:outline-[var(--color-aksen,#1d4ed8)] py-1 px-1 -ml-1"
          @click="bersihkanSesi"
        >
          <span class="text-xl md:text-2xl font-black tracking-tight text-slate-900">
            Peminjaman Inventaris IT
          </span>
        </RouterLink>
      </div>
    </header>

    <!-- Konten Utama Kiosk -->
    <main class="mx-auto w-full max-w-5xl flex-1 p-4 md:p-6 print:p-0 print:max-w-none">
      <div v-if="galatTampilan" class="max-w-lg mx-auto py-12 text-center space-y-5">
        <p class="text-lg md:text-xl font-bold text-slate-900">
          Terjadi kesalahan. Kembali ke beranda.
        </p>
        <TombolBesar varian="primer" ukuran="besar" @click="pulihkan">
          Kembali ke Beranda
        </TombolBesar>
      </div>
      <RouterView v-else />
    </main>

    <!-- Satu toast global kiosk -->
    <NotifikasiToast
      v-model:terbuka="notifikasi.terbuka"
      :pesan="notifikasi.pesan"
      :tipe="notifikasi.tipe"
    />

    <!-- Overlay Hitung Mundur Auto-Reset (FR-K-10) -->
    <Teleport to="body">
      <div
        v-if="hitungMundur !== null"
        class="fixed inset-0 z-[60] bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-6"
        role="dialog"
        aria-modal="true"
        aria-label="Sesi akan diatur ulang"
      >
        <div class="bg-white rounded-2xl p-6 md:p-8 max-w-md w-full text-center space-y-4 shadow-2xl">
          <h3 class="text-xl md:text-2xl font-extrabold text-slate-900">
            Masih di sana?
          </h3>
          <p class="text-sm md:text-base text-slate-600">
            Sesi akan diatur ulang dan seluruh isian dihapus dalam
            <strong class="text-slate-900">{{ hitungMundur }}</strong> detik.
          </p>

          <div class="h-2.5 w-full bg-slate-100 rounded-full overflow-hidden">
            <div
              class="h-full bg-[var(--color-aksen,#1d4ed8)] transition-all duration-1000 ease-linear"
              :style="{ width: `${(hitungMundur / 10) * 100}%` }"
            ></div>
          </div>

          <TombolBesar varian="primer" ukuran="besar" lebar-penuh @click="batalkanMundur">
            Ya, Lanjutkan Sesi
          </TombolBesar>

          <TombolBesar varian="sekunder" ukuran="sedang" lebar-penuh @click="aturUlang">
            Atur Ulang Sekarang
          </TombolBesar>
        </div>
      </div>
    </Teleport>
  </div>
</template>
