<script setup>
import StatusKosong from './StatusKosong.vue'

const props = defineProps({
  // [{ kunci, label, kelas?, urut? }]
  kolom: {
    type: Array,
    required: true,
  },
  baris: {
    type: Array,
    default: () => [],
  },
  // { current_page, last_page, per_page, total } dari meta Laravel paginator.
  meta: {
    type: Object,
    default: null,
  },
  memuat: {
    type: Boolean,
    default: false,
  },
  // { kunci, arah } arah: 'naik' | 'turun'. Terkendali oleh halaman pemakai.
  urutan: {
    type: Object,
    default: null,
  },
  pesanKosong: {
    type: String,
    default: 'Belum ada data',
  },
  // Bila true, baris dapat diklik (emit klik-baris) untuk membuka detail.
  barisDiklik: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['ubah-urutan', 'ubah-halaman'])

function klikUrut(kolom) {
  if (kolom.urut) {
    emit('ubah-urutan', kolom.kunci)
  }
}

function kelasUrut(kolom) {
  if (!kolom.urut) return ''
  return props.urutan?.kunci === kolom.kunci
    ? 'text-slate-900 bg-slate-200'
    : 'hover:bg-slate-100'
}

function simbolUrut(kolom) {
  if (!kolom.urut || props.urutan?.kunci !== kolom.kunci) return ''
  return props.urutan.arah === 'naik' ? ' ↑' : ' ↓'
}

function teksAriaSort(kolom) {
  if (!kolom.urut || props.urutan?.kunci !== kolom.kunci) return undefined
  return props.urutan.arah === 'naik' ? 'ascending' : 'descending'
}

function goto(halaman) {
  if (!props.meta) return
  const target = Number(halaman)
  if (target >= 1 && target <= Number(props.meta.last_page) && target !== Number(props.meta.current_page)) {
    emit('ubah-halaman', target)
  }
}

function rentangTampil() {
  const m = props.meta
  if (!m || !m.total) return ''
  const dari = m.from ?? (m.current_page - 1) * m.per_page + 1
  const ke = m.to ?? Math.min(m.current_page * m.per_page, m.total)
  return `Menampilkan ${dari}–${ke} dari ${m.total} data`
}

// Baris selalu punya key stabil: prefer id, fallback indeks.
function kunciBaris(baris, i) {
  return baris?.id ?? i
}
</script>

<template>
  <div class="w-full">
    <!-- Keadaan memuat: skeleton -->
    <div v-if="memuat" class="space-y-2 py-2" role="status" aria-live="polite">
      <div
        v-for="i in 5"
        :key="i"
        class="h-11 bg-slate-200 rounded-lg animate-pulse w-full"
      />
      <span class="sr-only">Memuat data...</span>
    </div>

    <!-- Keadaan kosong -->
    <StatusKosong v-else-if="baris.length === 0" :judul="pesanKosong" />

    <template v-else>
      <!-- Mode kartu di layar sempit (UI-05, T-40) -->
      <div class="md:hidden space-y-3">
        <div
          v-for="(baris, i) in baris"
          :key="kunciBaris(baris, i)"
          class="bg-white rounded-xl border border-slate-200 shadow-xs p-4"
          :class="barisDiklik ? 'cursor-pointer hover:border-[var(--color-aksen,#1d4ed8)]' : ''"
          @click="$emit('klik-baris', baris)"
        >
          <dl class="space-y-2 text-sm">
            <div
              v-for="k in kolom"
              :key="k.kunci"
              class="flex flex-col gap-0.5"
            >
              <dt class="text-xs font-semibold text-slate-500 uppercase">{{ k.label }}</dt>
              <dd class="text-slate-900 font-medium">
                <slot
                  :name="`cell-${k.kunci}`"
                  :nilai="baris[k.kunci]"
                  :baris="baris"
                >{{ baris[k.kunci] ?? '—' }}</slot>
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Tabel di layar lebar -->
      <div class="hidden md:block bg-white rounded-xl border border-slate-200 shadow-xs overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th
                v-for="k in kolom"
                :key="k.kunci"
                scope="col"
                :aria-sort="teksAriaSort(k)"
                :class="[
                  'px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-600 whitespace-nowrap',
                  k.urut ? 'cursor-pointer select-none' : '',
                  kelasUrut(k),
                  k.kelasTh || '',
                ]"
                @click="klikUrut(k)"
              >
                {{ k.label }}{{ simbolUrut(k) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(baris, i) in baris"
              :key="kunciBaris(baris, i)"
              class="border-b border-slate-100 last:border-0 transition-colors"
              :class="barisDiklik ? 'hover:bg-slate-50 cursor-pointer' : ''"
              @click="$emit('klik-baris', baris)"
            >
              <td
                v-for="k in kolom"
                :key="k.kunci"
                :class="['px-4 py-3 align-top text-slate-900', k.kelas || '']"
              >
                <slot
                  :name="`cell-${k.kunci}`"
                  :nilai="baris[k.kunci]"
                  :baris="baris"
                >{{ baris[k.kunci] ?? '—' }}</slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <nav
        v-if="meta && Number(meta.last_page) > 1"
        class="mt-4 flex flex-wrap items-center justify-between gap-3"
        aria-label="Navigasi halaman"
      >
        <span class="text-xs md:text-sm text-slate-500">{{ rentangTampil() }}</span>
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="Number(meta.current_page) <= 1"
            class="min-h-[36px] px-3 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
            @click="goto(Number(meta.current_page) - 1)"
          >
            &larr; Sebelumnya
          </button>
          <span class="text-sm font-semibold text-slate-700 tabular-nums">
            {{ meta.current_page }} / {{ meta.last_page }}
          </span>
          <button
            type="button"
            :disabled="Number(meta.current_page) >= Number(meta.last_page)"
            class="min-h-[36px] px-3 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
            @click="goto(Number(meta.current_page) + 1)"
          >
            Selanjutnya &rarr;
          </button>
        </div>
      </nav>
    </template>
  </div>
</template>
