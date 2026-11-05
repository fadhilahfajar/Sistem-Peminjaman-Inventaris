<script setup>
import { onMounted, ref } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'PJM-XXXXXX',
  },
  maksPanjang: {
    type: Number,
    default: 12,
  },
  dinonaktifkan: {
    type: Boolean,
    default: false,
  },
  id: {
    type: String,
    default: '',
  },
  fokusOtomatis: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])
const inputRef = ref(null)

onMounted(() => {
  if (props.fokusOtomatis && inputRef.value) {
    inputRef.value.focus()
  }
})

function onInput(e) {
  const kapital = (e.target.value || '').toUpperCase()
  emit('update:modelValue', kapital)
}
</script>

<template>
  <input
    :id="id || undefined"
    ref="inputRef"
    type="text"
    :value="modelValue"
    :maxlength="maksPanjang"
    :placeholder="placeholder"
    :disabled="dinonaktifkan"
    class="w-full min-h-[52px] px-4 text-center font-mono text-xl md:text-2xl font-bold tracking-widest text-slate-900 bg-white border-2 border-slate-300 rounded-xl uppercase transition-colors focus:border-[var(--color-aksen,#1d4ed8)] focus:outline-none focus:ring-3 focus:ring-[var(--color-aksen-lembut,#dbeafe)] disabled:bg-slate-100 disabled:opacity-60 disabled:cursor-not-allowed"
    autocomplete="off"
    autocapitalize="characters"
    spellcheck="false"
    @input="onInput"
  >
</template>
