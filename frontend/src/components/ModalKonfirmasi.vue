<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import TombolBesar from './TombolBesar.vue'

const props = defineProps({
  terbuka: {
    type: Boolean,
    default: false,
  },
  judul: {
    type: String,
    required: true,
  },
  pesan: {
    type: String,
    default: '',
  },
  teksBatal: {
    type: String,
    default: 'Batal',
  },
  teksKonfirmasi: {
    type: String,
    default: 'Ya, Lanjutkan',
  },
  varianKonfirmasi: {
    type: String,
    default: 'primer',
    validator: (v) => ['primer', 'bahaya', 'sukses'].includes(v),
  },
  memuat: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:terbuka', 'konfirmasi', 'batal'])

let counter = 0
const modalId = ref(`modal-${Date.now()}-${++counter}`)

function tutup() {
  if (props.memuat) return
  emit('update:terbuka', false)
  emit('batal')
}

function konfirmasi() {
  emit('konfirmasi')
}

function handleKeydown(e) {
  if (e.key === 'Escape' && props.terbuka) {
    tutup()
  }
}

watch(
  () => props.terbuka,
  (val) => {
    if (val) {
      document.body.classList.add('overflow-hidden')
    } else {
      document.body.classList.remove('overflow-hidden')
    }
  },
)

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="terbuka"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="`${modalId}-title`"
      @click.self="tutup"
    >
      <div
        class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 p-6 flex flex-col gap-4 animate-in fade-in zoom-in-95 duration-150"
      >
        <h2
          :id="`${modalId}-title`"
          class="text-xl font-bold text-slate-900"
        >
          {{ judul }}
        </h2>

        <p v-if="pesan" class="text-sm md:text-base text-slate-600">
          {{ pesan }}
        </p>

        <slot />

        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <TombolBesar
            varian="sekunder"
            ukuran="sedang"
            lebar-penuh
            class="sm:w-auto"
            :dinonaktifkan="memuat"
            @click="tutup"
          >
            {{ teksBatal }}
          </TombolBesar>

          <TombolBesar
            :varian="varianKonfirmasi"
            ukuran="sedang"
            lebar-penuh
            class="sm:w-auto"
            :memuat="memuat"
            @click="konfirmasi"
          >
            {{ teksKonfirmasi }}
          </TombolBesar>
        </div>
      </div>
    </div>
  </Teleport>
</template>
