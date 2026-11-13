<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import BadgeStatus from '@/components/BadgeStatus.vue'
import KartuAngka from '@/components/KartuAngka.vue'
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
    <div>
      <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Dasbor</h2>
      <p class="text-sm text-slate-500 mt-0.5">Diperbarui otomatis setiap 30 detik.</p>
    </div>

    <Memuat v-if="memuat" tipe="skeleton" :baris="2" />

    <template v-else>
      <!-- Kartu angka (FR-A-19) -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        <KartuAngka label="Dipinjam" :nilai="ringkasan.dipinjam" varian="biru" />
        <KartuAngka label="Terlambat" :nilai="ringkasan.terlambat" varian="merah" />
        <KartuAngka label="Menunggu Verifikasi" :nilai="ringkasan.menunggu_verifikasi" varian="kuning" />
        <KartuAngka label="Unit Stok Rusak" :nilai="ringkasan.stok_rusak" varian="ungu" />
      </div>

      <!-- Daftar peminjaman terlambat -->
      <section class="space-y-3">
        <div>
          <h3 class="text-base md:text-lg font-bold text-slate-900">Peminjaman Terlambat</h3>
          <p class="text-xs md:text-sm text-slate-500">Klik baris untuk membuka detail peminjaman.</p>
        </div>

        <TabelData
          :kolom="kolom"
          :baris="terlambat"
          baris-diklik
          pesan-kosong="Tidak ada peminjaman terlambat. Selamat!"
          @klik-baris="bukaDetail"
        >
          <template #cell-divisi="{ baris }">
            {{ baris.divisi?.nama ?? '—' }}
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
