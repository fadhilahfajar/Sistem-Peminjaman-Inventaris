<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import TombolBesar from '@/components/TombolBesar.vue'
import {
  hitungTersembunyi,
  keteranganPilihan,
  statusPilihan,
  toggleDipilih,
} from '@/utils/pemilihBarang'

const props = defineProps({
  terbuka: { type: Boolean, default: false },
  barang: { type: Array, default: () => [] },
  idsDiKeranjang: { type: Array, default: () => [] },
  generasiSesi: { type: Number, default: 0 },
})
const emit = defineEmits(['update:terbuka', 'konfirmasi'])

const dialogId = 'pemilih-barang'
const inputCari = ref(null)
const cari = ref('')
const dipilih = ref([])

const idsSet = computed(() => new Set(props.idsDiKeranjang))
const daftarTampil = computed(() => {
  const q = cari.value.trim().toLowerCase()
  return props.barang.filter((b) => !q || b.nama.toLowerCase().includes(q))
})
const barangDipilih = computed(() =>
  props.barang.filter((b) => dipilih.value.includes(b.id)),
)
const keterangan = computed(() =>
  keteranganPilihan(
    barangDipilih.value,
    hitungTersembunyi(barangDipilih.value, daftarTampil.value),
  ),
)
const teksUtama = computed(() => {
  const n = dipilih.value.length
  return n === 0 ? 'Pilih barang dulu' : `Tambahkan ${n} barang`
})

function status(b) {
  return statusPilihan(b, idsSet.value)
}
function apakahDipilih(b) {
  return dipilih.value.includes(b.id)
}
function pilih(b) {
  if (status(b) !== 'tersedia') return
  dipilih.value = toggleDipilih(dipilih.value, b.id)
}
function tutup() {
  dipilih.value = []
  cari.value = ''
  emit('update:terbuka', false)
}
function konfirmasi() {
  if (barangDipilih.value.length === 0) return
  emit('konfirmasi', barangDipilih.value)
  tutup()
}
function handleKeydown(e) {
  if (e.key === 'Escape' && props.terbuka) tutup()
}

watch(
  () => props.terbuka,
  (buka) => {
    if (buka) {
      dipilih.value = []
      cari.value = ''
      document.body.classList.add('overflow-hidden')
      nextTick(() => inputCari.value?.focus())
    } else {
      document.body.classList.remove('overflow-hidden')
    }
  },
)

// Idle reset menaikkan generasi sesi: popup ikut tertutup, pilihan dibuang.
watch(
  () => props.generasiSesi,
  () => {
    if (props.terbuka) tutup()
  },
)

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="terbuka"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="`${dialogId}-judul`"
      :aria-describedby="`${dialogId}-keterangan`"
    >
      <div class="w-full max-w-2xl max-h-[92vh] bg-white rounded-lg shadow-lg border border-slate-200 flex flex-col overflow-hidden">
        <div class="px-5 pt-5 pb-4 border-b border-slate-100">
          <h2 :id="`${dialogId}-judul`" class="text-xl md:text-2xl font-bold text-slate-900">
            Pilih Barang Inventaris
          </h2>
          <p class="text-sm text-slate-600 mt-1">
            Pilih beberapa barang sekaligus. Jumlah tiap barang diatur di formulir.
          </p>
        </div>

        <div class="px-5 pt-4">
          <label :for="`${dialogId}-cari`" class="sr-only">Cari nama barang</label>
          <input
            :id="`${dialogId}-cari`"
            ref="inputCari"
            v-model="cari"
            type="text"
            placeholder="Ketik nama barang..."
            class="w-full min-h-[44px] px-3.5 bg-slate-50 border border-slate-300 rounded-lg text-sm md:text-base text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent"
          >
        </div>

        <div class="px-5 py-3 flex-1 min-h-0 overflow-y-auto max-h-[min(52vh,420px)] space-y-2">
          <div
            v-for="b in daftarTampil"
            :key="b.id"
            :class="[
              'rounded-lg border border-slate-200 border-l-4 p-3 flex items-center justify-between gap-3 min-h-[64px]',
              apakahDipilih(b)
                ? 'border-l-[var(--color-aksen,#1d4ed8)] bg-blue-50'
                : 'border-l-transparent bg-white',
              status(b) !== 'tersedia' && 'bg-slate-50',
            ]"
          >
            <div class="min-w-0">
              <p class="font-bold text-slate-900 text-sm md:text-base truncate">
                {{ b.nama }}
              </p>
              <p
                class="text-xs font-semibold"
                :class="status(b) === 'habis' ? 'text-red-600' : 'text-emerald-600'"
              >
                {{ status(b) === 'habis' ? 'Stok habis' : `Tersedia ${b.tersedia} unit` }}
              </p>
            </div>

            <button
              v-if="status(b) === 'tersedia'"
              type="button"
              :aria-pressed="apakahDipilih(b) ? 'true' : 'false'"
              :class="[
                'shrink-0 inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 rounded-lg border-2 text-sm font-bold cursor-pointer select-none transition-colors focus-visible:outline-3 focus-visible:outline-offset-2',
                apakahDipilih(b)
                  ? 'border-[var(--color-aksen,#1d4ed8)] bg-[var(--color-aksen,#1d4ed8)] text-white focus-visible:outline-[var(--color-aksen,#1d4ed8)]'
                  : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100 focus-visible:outline-slate-500',
              ]"
              @click="pilih(b)"
            >
              <svg
                v-if="apakahDipilih(b)"
                class="w-4 h-4 shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
              </svg>
              <span>{{ apakahDipilih(b) ? 'Dipilih' : '+ Pilih' }}</span>
            </button>

            <span
              v-else
              class="shrink-0 inline-flex items-center gap-1.5 min-h-[44px] px-3 text-sm font-bold"
              :class="status(b) === 'form' ? 'text-slate-600' : 'text-slate-400'"
            >
              <svg
                v-if="status(b) === 'form'"
                class="w-4 h-4 shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
              </svg>
              <span>{{ status(b) === 'form' ? 'Sudah di formulir' : 'Habis' }}</span>
            </span>
          </div>

          <p
            v-if="daftarTampil.length === 0"
            class="text-sm text-slate-600 text-center py-6"
          >
            Tidak ada barang yang cocok dengan pencarian.
          </p>
        </div>

        <div class="px-5 py-3">
          <p
            :id="`${dialogId}-keterangan`"
            aria-live="polite"
            class="text-sm md:text-base text-slate-600 min-h-[40px]"
          >
            {{ keterangan }}
          </p>
        </div>

        <div class="sticky bottom-0 px-5 py-4 border-t border-slate-200 bg-white flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
          <TombolBesar
            varian="sekunder"
            ukuran="sedang"
            lebar-penuh
            class="sm:w-auto"
            @click="tutup"
          >
            Tutup
          </TombolBesar>
          <TombolBesar
            varian="primer"
            ukuran="sedang"
            lebar-penuh
            class="sm:w-auto"
            :dinonaktifkan="dipilih.length === 0"
            @click="konfirmasi"
          >
            {{ teksUtama }}
          </TombolBesar>
        </div>
      </div>
    </div>
  </Teleport>
</template>
