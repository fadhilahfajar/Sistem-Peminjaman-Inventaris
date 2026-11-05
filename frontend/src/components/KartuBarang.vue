<script setup>
import { computed } from 'vue'
import TombolBesar from './TombolBesar.vue'

const props = defineProps({
  barang: {
    type: Object,
    required: true,
  },
  kategoriNama: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['pilih'])

const tersedia = computed(() => Number(props.barang?.tersedia ?? 0))
const habis = computed(() => tersedia.value <= 0)

function onPilih() {
  if (habis.value) return
  emit('pilih', props.barang)
}
</script>

<template>
  <div
    :class="[
      'flex flex-col justify-between rounded-2xl border p-5 transition-all shadow-xs',
      habis
        ? 'bg-slate-50 border-slate-200 opacity-75'
        : 'bg-white border-slate-200 hover:border-blue-400 hover:shadow-md',
    ]"
  >
    <div class="space-y-2">
      <!-- Tag Kategori & Status Ketersediaan -->
      <div class="flex items-center justify-between gap-2">
        <span
          v-if="kategoriNama"
          class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 truncate max-w-[150px]"
        >
          {{ kategoriNama }}
        </span>
        <span v-else class="text-xs text-slate-400">Inventaris IT</span>

        <!-- Badge Ketersediaan -->
        <span
          v-if="!habis"
          class="inline-flex items-center text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200"
        >
          Tersedia {{ tersedia }} unit
        </span>
        <span
          v-else
          class="inline-flex items-center text-xs font-bold px-2.5 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200"
        >
          Stok Habis
        </span>
      </div>

      <!-- Nama Barang -->
      <h3
        :class="[
          'text-lg font-bold leading-snug line-clamp-2',
          habis ? 'text-slate-500' : 'text-slate-900',
        ]"
      >
        {{ barang.nama }}
      </h3>
    </div>

    <!-- Tombol Tindakan -->
    <div class="mt-5 pt-3 border-t border-slate-100">
      <TombolBesar
        v-if="!habis"
        varian="primer"
        ukuran="sedang"
        lebar-penuh
        @click="onPilih"
      >
        Pilih Barang
      </TombolBesar>

      <TombolBesar
        v-else
        varian="sekunder"
        ukuran="sedang"
        lebar-penuh
        dinonaktifkan
        aria-disabled="true"
      >
        Tidak Tersedia
      </TombolBesar>
    </div>
  </div>
</template>
