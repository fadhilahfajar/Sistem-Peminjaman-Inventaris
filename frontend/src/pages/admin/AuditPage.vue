<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/api'
import FilterBar from '@/components/FilterBar.vue'
import FormField from '@/components/FormField.vue'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import TabelData from '@/components/TabelData.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { formatWaktuIndo } from '@/utils/tanggal'

const { pesanGalat, tanganiGalat, bersihkan } = useGalat()

const kolom = [
  { kunci: 'dibuat_pada', label: 'Waktu' },
  { kunci: 'user', label: 'Pengguna' },
  { kunci: 'aksi', label: 'Aksi' },
  { kunci: 'entitas', label: 'Entitas' },
  { kunci: 'detail', label: 'Detail' },
]

const baris = ref([])
const meta = ref(null)
const sedangMuat = ref(false)

const f = ref({
  aksi: '',
  entitas: '',
  user_id: '',
})

const adminDaftar = ref([])

async function muat(halaman = 1) {
  sedangMuat.value = true
  bersihkan()
  try {
    const params = { page: halaman }
    if (f.value.aksi.trim()) params.aksi = f.value.aksi.trim()
    if (f.value.entitas.trim()) params.entitas = f.value.entitas.trim()
    if (f.value.user_id) params.user_id = f.value.user_id

    const res = await adminApi.getAuditLogs(params)
    baris.value = res.data?.data || []
    meta.value = res.data?.meta || null
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

async function muatAdmin() {
  try {
    const res = await adminApi.getUsers()
    adminDaftar.value = res.data?.data || []
  } catch {
    // Biarkan daftar admin kosong bila gagal
  }
}

onMounted(() => {
  muat()
  muatAdmin()
})

function terapkan() {
  muat(1)
}

function aturUlang() {
  f.value = { aksi: '', entitas: '', user_id: '' }
  muat(1)
}

// Modal Detail JSON
const detailTerpilih = ref(null)

function bukaDetail(baris) {
  detailTerpilih.value = baris
}
</script>

<template>
  <section class="space-y-6">
    <div class="pb-2 border-b border-slate-200">
      <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Jejak Audit</h2>
      <p class="text-sm text-slate-600 mt-0.5">
        Catatan aktivitas administratif sistem. Riwayat hanya baca.
      </p>
    </div>

    <FilterBar :memuat="sedangMuat" @terapkan="terapkan" @atur-ulang="aturUlang">
      <FormField id="fa-aksi" label="Aksi">
        <input
          id="fa-aksi"
          v-model="f.aksi"
          type="search"
          placeholder="Contoh: login, ubah_stok"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
      </FormField>

      <FormField id="fa-entitas" label="Entitas">
        <select
          id="fa-entitas"
          v-model="f.entitas"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua entitas</option>
          <option value="items">items</option>
          <option value="loans">loans</option>
          <option value="return_claims">return_claims</option>
          <option value="users">users</option>
          <option value="categories">categories</option>
          <option value="divisions">divisions</option>
          <option value="settings">settings</option>
        </select>
      </FormField>

      <FormField id="fa-user" label="Admin Pelaku">
        <select
          id="fa-user"
          v-model="f.user_id"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >
          <option value="">Semua admin / sistem</option>
          <option v-for="u in adminDaftar" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </FormField>
    </FilterBar>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <TabelData
      :kolom="kolom"
      :baris="baris"
      :meta="meta"
      :memuat="sedangMuat"
      pesan-kosong="Belum ada catatan audit"
      @ubah-halaman="muat($event)"
    >
      <template #cell-dibuat_pada="{ nilai }">
        <span class="text-xs text-slate-600 whitespace-nowrap">{{ formatWaktuIndo(nilai) }}</span>
      </template>

      <template #cell-user="{ baris: b }">
        <span class="font-medium text-slate-900">{{ b.user?.name || 'Sistem' }}</span>
      </template>

      <template #cell-aksi="{ nilai }">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-slate-100 text-slate-800 border border-slate-200">
          {{ nilai }}
        </span>
      </template>

      <template #cell-entitas="{ baris: b }">
        <span class="text-xs font-mono text-slate-600">
          {{ b.entitas }}{{ b.entitas_id ? ` #${b.entitas_id}` : '' }}
        </span>
      </template>

      <template #cell-detail="{ baris: b }">
        <TombolBesar
          v-if="b.detail && Object.keys(b.detail).length > 0"
          varian="sekunder"
          ukuran="sedang"
          @click="bukaDetail(b)"
        >
          Rincian
        </TombolBesar>
        <span v-else class="text-xs text-slate-400">-</span>
      </template>
    </TabelData>

    <!-- Rincian audit: baca-saja, modal bersama memberi Escape, klik luar, dan kunci gulir (R-26). -->
    <ModalKonfirmasi
      :terbuka="detailTerpilih !== null"
      :judul="`Rincian Audit #${detailTerpilih?.id ?? ''}`"
      teks-batal="Tutup"
      tanpa-konfirmasi
      @update:terbuka="!$event && (detailTerpilih = null)"
    >
      <p class="text-xs text-slate-600 font-mono -mt-2">
        {{ detailTerpilih?.aksi }} &middot; {{ detailTerpilih?.entitas }}
      </p>

      <pre class="bg-slate-900 text-slate-100 p-4 rounded-lg text-xs font-mono overflow-x-auto max-h-72">{{ JSON.stringify(detailTerpilih?.detail, null, 2) }}</pre>
    </ModalKonfirmasi>
  </section>
</template>
