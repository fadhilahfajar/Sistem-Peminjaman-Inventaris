<script setup>
import { onUnmounted, watch } from 'vue'

const props = defineProps({
  terbuka: {
    type: Boolean,
    default: false,
  },
  pesan: {
    type: String,
    required: true,
  },
  tipe: {
    type: String,
    default: 'sukses',
    validator: (v) => ['sukses', 'galat', 'info', 'peringatan'].includes(v),
  },
  durasi: {
    type: Number,
    default: 4000,
  },
})

const emit = defineEmits(['update:terbuka', 'tutup'])

let timer = null

function tutup() {
  clearTimeout(timer)
  emit('update:terbuka', false)
  emit('tutup')
}

watch(
  () => props.terbuka,
  (terbuka) => {
    clearTimeout(timer)
    if (terbuka && props.durasi > 0) {
      timer = setTimeout(() => {
        tutup()
      }, props.durasi)
    }
  },
  { immediate: true },
)

onUnmounted(() => {
  clearTimeout(timer)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="terbuka"
      class="fixed bottom-6 right-6 z-50 max-w-sm w-full p-4 rounded-xl shadow-xl border flex items-center justify-between gap-3 animate-in fade-in slide-in-from-bottom-4 duration-200"
      :class="[
        tipe === 'sukses' && 'bg-emerald-50 text-emerald-900 border-emerald-300',
        tipe === 'galat' && 'bg-red-50 text-red-900 border-red-300',
        tipe === 'peringatan' && 'bg-amber-50 text-amber-900 border-amber-300',
        tipe === 'info' && 'bg-blue-50 text-blue-900 border-blue-300',
      ]"
      role="status"
      aria-live="polite"
    >
      <div class="flex items-center gap-3">
        <!-- Ikon Sukses -->
        <svg
          v-if="tipe === 'sukses'"
          class="w-5 h-5 text-emerald-600 shrink-0"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>

        <!-- Ikon Galat -->
        <svg
          v-else-if="tipe === 'galat'"
          class="w-5 h-5 text-red-600 shrink-0"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>

        <!-- Ikon Peringatan/Info -->
        <svg
          v-else
          class="w-5 h-5 text-slate-600 shrink-0"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>

        <p class="text-sm font-medium leading-snug">
          {{ pesan }}
        </p>
      </div>

      <button
        type="button"
        class="min-h-[44px] min-w-[44px] -my-2 -mr-2 flex items-center justify-center text-slate-400 hover:text-slate-700 cursor-pointer rounded-lg focus-visible:outline-2 focus-visible:outline-slate-500"
        aria-label="Tutup notifikasi"
        @click="tutup"
      >
        <span aria-hidden="true" class="text-xl leading-none">&times;</span>
      </button>
    </div>
  </Teleport>
</template>
