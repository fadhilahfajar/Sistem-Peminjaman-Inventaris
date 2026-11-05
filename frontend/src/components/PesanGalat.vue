<script setup>
defineProps({
  judul: {
    type: String,
    default: '',
  },
  pesan: {
    type: String,
    required: true,
  },
  bisaDicobaLagi: {
    type: Boolean,
    default: false,
  },
  teksCobaLagi: {
    type: String,
    default: 'Coba Lagi',
  },
})

defineEmits(['cobaLagi'])
</script>

<template>
  <div
    class="w-full p-4 rounded-xl border border-red-200 bg-red-50 text-red-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
    role="alert"
  >
    <div class="flex items-start gap-3">
      <svg
        class="w-5 h-5 text-red-600 shrink-0 mt-0.5"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div>
        <h4 v-if="judul" class="text-sm font-bold text-red-950 mb-0.5">
          {{ judul }}
        </h4>
        <p class="text-sm text-red-800 leading-snug">
          {{ pesan }}
        </p>
      </div>
    </div>

    <button
      v-if="bisaDicobaLagi"
      type="button"
      class="min-h-[44px] px-4 py-2 self-start sm:self-auto text-xs md:text-sm font-semibold bg-red-600 text-white rounded-lg hover:bg-red-700 cursor-pointer focus-visible:outline-2 focus-visible:outline-red-600 shrink-0"
      @click="$emit('cobaLagi')"
    >
      {{ teksCobaLagi }}
    </button>
  </div>
</template>
