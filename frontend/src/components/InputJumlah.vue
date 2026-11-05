<script setup>
const props = defineProps({
  modelValue: {
    type: Number,
    default: 1,
  },
  min: {
    type: Number,
    default: 1,
  },
  max: {
    type: Number,
    default: 999,
  },
  dinonaktifkan: {
    type: Boolean,
    default: false,
  },
  id: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

function ubah(nilai) {
  const target = Math.min(props.max, Math.max(props.min, Number(nilai) || props.min))
  emit('update:modelValue', target)
}

function kurangi() {
  if (props.dinonaktifkan || props.modelValue <= props.min) return
  ubah(props.modelValue - 1)
}

function tambahi() {
  if (props.dinonaktifkan || props.modelValue >= props.max) return
  ubah(props.modelValue + 1)
}

function onInput(e) {
  const parsed = parseInt(e.target.value, 10)
  if (!Number.isNaN(parsed)) {
    ubah(parsed)
  }
}
</script>

<template>
  <div class="inline-flex items-center border border-slate-300 rounded-xl overflow-hidden bg-white shadow-xs">
    <button
      type="button"
      :disabled="dinonaktifkan || modelValue <= min"
      class="min-w-[44px] min-h-[44px] flex items-center justify-center text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors text-xl font-bold select-none focus-visible:outline-3 focus-visible:outline-offset-1 focus-visible:outline-[var(--color-aksen,#1d4ed8)]"
      aria-label="Kurangi jumlah"
      @click="kurangi"
    >
      &minus;
    </button>

    <input
      :id="id || undefined"
      type="number"
      :value="modelValue"
      :min="min"
      :max="max"
      :disabled="dinonaktifkan"
      class="w-16 min-h-[44px] text-center font-bold text-slate-900 border-x border-slate-200 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
      @input="onInput"
      @blur="ubah($event.target.value)"
    >

    <button
      type="button"
      :disabled="dinonaktifkan || modelValue >= max"
      class="min-w-[44px] min-h-[44px] flex items-center justify-center text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors text-xl font-bold select-none focus-visible:outline-3 focus-visible:outline-offset-1 focus-visible:outline-[var(--color-aksen,#1d4ed8)]"
      aria-label="Tambah jumlah"
      @click="tambahi"
    >
      +
    </button>
  </div>
</template>
