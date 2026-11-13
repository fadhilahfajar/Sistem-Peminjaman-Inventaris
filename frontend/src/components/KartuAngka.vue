<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
  nilai: {
    type: Number,
    default: 0,
  },
  // Varian warna mengikuti tabel status DESAIN 10.5.
  varian: {
    type: String,
    default: 'netral',
    validator: (v) => ['netral', 'biru', 'merah', 'kuning', 'hijau', 'ungu'].includes(v),
  },
})

const kelasVarian = {
  netral: 'bg-white border-slate-200 text-slate-900',
  biru: 'bg-blue-50 border-blue-200 text-blue-950',
  merah: 'bg-red-50 border-red-200 text-red-950',
  kuning: 'bg-yellow-50 border-yellow-200 text-yellow-950',
  hijau: 'bg-emerald-50 border-emerald-200 text-emerald-950',
  ungu: 'bg-purple-50 border-purple-200 text-purple-950',
}
</script>

<template>
  <div :class="['p-4 md:p-5 rounded-2xl border shadow-xs', kelasVarian[varian] || kelasVarian.netral]">
    <p class="text-xs md:text-sm font-semibold opacity-75">{{ label }}</p>
    <p class="mt-1 text-2xl md:text-3xl font-extrabold tabular-nums leading-none">
      {{ nilai }}
    </p>
  </div>
</template>
