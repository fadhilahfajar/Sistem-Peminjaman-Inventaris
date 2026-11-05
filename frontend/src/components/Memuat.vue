<script setup>
defineProps({
  teks: {
    type: String,
    default: 'Memuat data...',
  },
  tipe: {
    type: String,
    default: 'spinner',
    validator: (v) => ['spinner', 'skeleton'].includes(v),
  },
  baris: {
    type: Number,
    default: 3,
  },
})
</script>

<template>
  <div
    class="w-full flex flex-col items-center justify-center p-6 text-slate-500"
    role="status"
    aria-live="polite"
  >
    <!-- Spinner mode -->
    <template v-if="tipe === 'spinner'">
      <div class="flex items-center gap-3">
        <svg
          class="animate-spin h-6 w-6 text-[var(--color-aksen,#1d4ed8)]"
          fill="none"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
        </svg>
        <span class="text-sm font-medium text-slate-600">{{ teks }}</span>
      </div>
    </template>

    <!-- Skeleton mode for tables / cards -->
    <template v-else>
      <div class="w-full space-y-3">
        <div
          v-for="i in baris"
          :key="i"
          class="h-10 bg-slate-200 rounded-lg animate-pulse w-full"
        />
        <span class="sr-only">{{ teks }}</span>
      </div>
    </template>
  </div>
</template>
