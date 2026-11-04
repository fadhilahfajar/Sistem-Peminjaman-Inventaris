<script setup>
defineProps({
  varian: {
    type: String,
    default: 'primer',
    validator: (v) => ['primer', 'sekunder', 'bahaya', 'sukses'].includes(v),
  },
  tipe: {
    type: String,
    default: 'button',
  },
  memuat: {
    type: Boolean,
    default: false,
  },
  dinonaktifkan: {
    type: Boolean,
    default: false,
  },
  ukuran: {
    type: String,
    default: 'besar',
    validator: (v) => ['besar', 'sedang'].includes(v),
  },
  lebarPenuh: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['click'])
</script>

<template>
  <button
    :type="tipe"
    :disabled="dinonaktifkan || memuat"
    :class="[
      'inline-flex items-center justify-center font-semibold rounded-xl transition-colors cursor-pointer select-none',
      'focus-visible:outline-3 focus-visible:outline-offset-2',
      ukuran === 'besar'
        ? 'min-h-[56px] px-6 py-3 text-lg md:text-xl gap-3'
        : 'min-h-[44px] px-4 py-2 text-base gap-2',
      lebarPenuh ? 'w-full' : '',
      varian === 'primer' && 'bg-[var(--color-aksen,#1d4ed8)] hover:bg-[var(--color-aksen-hover,#1e40af)] text-white focus-visible:outline-[var(--color-aksen,#1d4ed8)]',
      varian === 'sekunder' && 'bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 focus-visible:outline-slate-500',
      varian === 'bahaya' && 'bg-red-600 hover:bg-red-700 text-white focus-visible:outline-red-600',
      varian === 'sukses' && 'bg-emerald-600 hover:bg-emerald-700 text-white focus-visible:outline-emerald-600',
      (dinonaktifkan || memuat) && '!opacity-50 !cursor-not-allowed !pointer-events-none',
    ]"
    @click="$emit('click', $event)"
  >
    <svg
      v-if="memuat"
      class="animate-spin h-5 w-5 text-current"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
    </svg>
    <slot />
  </button>
</template>
