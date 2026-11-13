<script setup>
import TombolBesar from './TombolBesar.vue'

defineProps({
  memuat: {
    type: Boolean,
    default: false,
  },
  teksTerapkan: {
    type: String,
    default: 'Terapkan',
  },
  // Teks kosong = tombol atur-ulang disembunyikan.
  teksAturUlang: {
    type: String,
    default: 'Atur Ulang',
  },
})

const emit = defineEmits(['terapkan', 'atur-ulang'])
</script>

<template>
  <form
    class="bg-white p-4 md:p-5 rounded-2xl border border-slate-200 shadow-xs"
    @submit.prevent="emit('terapkan')"
    @reset.prevent="emit('atur-ulang')"
  >
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-start">
      <slot />
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3 pt-3 border-t border-slate-100">
      <TombolBesar
        tipe="submit"
        varian="primer"
        ukuran="sedang"
        :memuat="memuat"
        :dinonaktifkan="memuat"
      >
        {{ teksTerapkan }}
      </TombolBesar>

      <TombolBesar
        v-if="teksAturUlang"
        tipe="reset"
        varian="sekunder"
        ukuran="sedang"
        :dinonaktifkan="memuat"
      >
        {{ teksAturUlang }}
      </TombolBesar>

      <slot name="tambahan" />
    </div>
  </form>
</template>
