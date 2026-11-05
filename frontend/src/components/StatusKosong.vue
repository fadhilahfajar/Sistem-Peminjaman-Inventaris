<script setup>
defineProps({
  judul: {
    type: String,
    default: 'Belum ada data',
  },
  pesan: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center p-8 md:p-12 text-center bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl w-full">
    <div class="w-12 h-12 mb-3 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
      </svg>
    </div>

    <h3 class="text-base md:text-lg font-bold text-slate-800">
      {{ judul }}
    </h3>

    <p v-if="pesan" class="mt-1 text-sm text-slate-500 max-w-sm">
      {{ pesan }}
    </p>

    <div v-if="$slots.default" class="mt-4 flex items-center gap-3">
      <slot />
    </div>
  </div>
</template>
