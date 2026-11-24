<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import BadgeStatus from '@/components/BadgeStatus.vue'
import Memuat from '@/components/Memuat.vue'
import TabelData from '@/components/TabelData.vue'
import { useSesiStore } from '@/stores/sesi'
import { formatTanggalIndo } from '@/utils/tanggal'

const router = useRouter()
const sesi = useSesiStore()

// Angka dan daftar datang dari respons /api/admin/summary yang sama
// sehingga selalu konsisten (FR-A-19). Polling 30 detik di LayoutAdmin.
const ringkasan = computed(() => sesi.ringkasan)
const memuat = computed(() => ringkasan.value === null)
const terlambat = computed(() => ringkasan.value?.peminjaman_terlambat || [])

const kolom = [
  { kunci: 'kode_pinjam', label: 'Kode' },
  { kunci: 'nama_peminjam', label: 'Peminjam' },
  { kunci: 'divisi', label: 'Divisi' },
  { kunci: 'batas_kembali', label: 'Batas Kembali' },
  { kunci: 'status', label: 'Status' },
]

function bukaDetail(baris) {
  if (baris?.id) {
    router.push(`/admin/peminjaman/${baris.id}`)
  }
}
</script>

<template>
  <section class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Dasbor</h2>
        <p class="text-sm text-slate-600 mt-0.5">Diperbarui otomatis setiap 30 detik.</p>
      </div>
      <RouterLink
        to="/admin/verifikasi"
        class="inline-flex min-h-[44px] items-center rounded-lg bg-[var(--color-aksen)] px-4 text-sm font-semibold text-white hover:bg-[var(--color-aksen-hover)]"
      >
        Buka antrean verifikasi
      </RouterLink>
    </div>

    <Memuat v-if="memuat" tipe="skeleton" :baris="2" />

    <template v-else>
      <!-- R-14: empat angka satu baris, bukan empat kartu setara. Fokus halaman = antrean. FR-A-19. -->
      <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 lg:grid-cols-4">
        <div class="bg-white px-4 py-3">
          <dt class="text-xs font-semibold text-slate-600">Dipinjam</dt>
          <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ ringkasan.dipinjam }}</dd>
        </div>
        <div class="bg-white px-4 py-3">
          <dt class="text-xs font-semibold text-red-800">Terlambat</dt>
          <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ ringkasan.terlambat }}</dd>
        </div>
        <div class="bg-white px-4 py-3">
          <dt class="text-xs font-semibold text-amber-900">Menunggu Verifikasi</dt>
          <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ ringkasan.menunggu_verifikasi }}</dd>
        </div>
        <div class="bg-white px-4 py-3">
          <dt class="text-xs font-semibold text-slate-600">Unit Stok Rusak</dt>
          <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ ringkasan.stok_rusak }}</dd>
        </div>
      </dl>

      <!-- Daftar peminjaman terlambat -->
      <section class="space-y-3">
        <div>
          <h3 class="text-base md:text-lg font-bold text-slate-900">Peminjaman Terlambat</h3>
          <p class="text-xs md:text-sm text-slate-600">Klik baris untuk membuka detail peminjaman.</p>
        </div>

        <TabelData
          :kolom="kolom"
          :baris="terlambat"
          baris-diklik
          pesan-kosong="Tidak ada peminjaman terlambat."
          @klik-baris="bukaDetail"
        >
          <template #cell-divisi="{ baris }">
            {{ baris.divisi?.nama ?? '' }}
          </template>
          <template #cell-batas_kembali="{ nilai }">
            {{ formatTanggalIndo(nilai) }}
          </template>
          <template #cell-status="{ baris }">
            <!-- Badge dipakai ulang agar identik dengan layar lain (UI-04) -->
            <BadgeStatus :status="baris.status" :hari-terlambat="Number(baris.hari_terlambat) || 0" ukuran="kecil" />
          </template>
        </TabelData>
      </section>
    </template>
  </section>
</template>
