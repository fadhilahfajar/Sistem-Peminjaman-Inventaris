<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BadgeLencana from '@/components/BadgeLencana.vue'
import BadgeStatus from '@/components/BadgeStatus.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useKioskStore } from '@/stores/kiosk'
import { formatTanggalIndo, formatWaktuIndo } from '@/utils/tanggal'

const router = useRouter()
const kioskStore = useKioskStore()

const kartu = computed(() => kioskStore.kartuPinjam)

onMounted(() => {
  // Sesuai DESAIN 10.1: bila halaman kartu dimuat ulang dan tidak ada data di memori,
  // kembalikan peminjam ke layar Cek.
  if (!kartu.value || !kartu.value.kode_pinjam) {
    router.replace('/cek')
  }
})

function kembaliKeCek() {
  kioskStore.hapusKode()
  router.push('/cek')
}

function keHalamanKlaim() {
  router.push('/klaim')
}

function keHalamanPerpanjang() {
  router.push('/perpanjang')
}

function keHalamanBatal() {
  router.push('/batal')
}
</script>

<template>
  <div v-if="kartu" class="space-y-6 max-w-4xl mx-auto py-2">
    <!-- Navigasi Atas -->
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
          Kartu Peminjaman
        </h2>
        <p class="text-xs md:text-sm text-slate-600 mt-0.5">
          Informasi rincian transaksi dan status pengembalian barang.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <TombolBesar varian="sekunder" ukuran="sedang" @click="kembaliKeCek">
          &larr; Cek Kode Lain
        </TombolBesar>
      </div>
    </div>

    <!-- Kartu Utama Ringkasan Transaksi -->
    <section class="bg-white rounded-2xl border border-slate-200 p-5 md:p-6 shadow-xs space-y-5">
      <!-- Baris Atas: Kode Pinjam Besar & Badge Status -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
        <div>
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest block">
            {{ kartu.pemesanan ? 'KODE PEMESANAN' : 'KODE PEMINJAMAN' }}
          </span>
          <span class="text-2xl md:text-3xl font-mono font-extrabold tracking-widest text-slate-900">
            {{ kartu.kode_pinjam }}
          </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <!-- Status Utama -->
          <BadgeStatus
            :status="kartu.status"
            :hari-terlambat="kartu.hitung_mundur?.hari_terlambat || 0"
            ukuran="besar"
          />

          <!-- Lencana Tambahan (Sebagian, Ada klaim menunggu, Diperpanjang, Ada catatan) -->
          <BadgeLencana
            v-for="l in kartu.lencana"
            :key="l"
            :lencana="l"
            :diperpanjang-kali="kartu.perpanjangan?.terpakai || 0"
            ukuran="sedang"
          />
        </div>
      </div>

      <!-- Banner Status Khusus & Hitung Mundur (FR-K-04) -->
      <!-- Kasus 1: Pemesanan -->
      <div
        v-if="kartu.pemesanan"
        class="p-4 rounded-xl bg-purple-50 border border-purple-200 text-purple-900 flex items-start gap-3"
      >
        <svg class="w-6 h-6 text-purple-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <div>
          <h4 class="font-bold text-base text-purple-950">
            Dipesan, mulai {{ formatTanggalIndo(kartu.tanggal_pinjam) }}
          </h4>
          <p class="text-xs md:text-sm text-purple-800 mt-0.5">
            Stok barang inventaris telah ditahan untuk Anda. Ambil barang pada tanggal mulai pemesanan atau batalkan sebelum tanggal tersebut bila batal digunakan.
          </p>
        </div>
      </div>

      <!-- Kasus 2: Terlambat -->
      <div
        v-else-if="kartu.hitung_mundur?.hari_terlambat > 0"
        class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-900 flex items-start gap-3"
      >
        <svg class="w-6 h-6 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <h4 class="font-bold text-base text-red-950">
            Terlambat {{ kartu.hitung_mundur.hari_terlambat }} hari
          </h4>
          <p class="text-xs md:text-sm text-red-800 mt-0.5">
            Batas pengembalian telah lewat sejak {{ formatTanggalIndo(kartu.batas_kembali) }}. Harap segera ajukan pengembalian dan serahkan unit barang ke petugas IT.
          </p>
        </div>
      </div>

      <!-- Kasus 3: Berjalan normal (sisa hari) -->
      <div
        v-else-if="kartu.status === 'dipinjam' && kartu.hitung_mundur?.sisa_hari !== null"
        class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-900 flex items-start gap-3"
      >
        <svg class="w-6 h-6 text-[var(--color-aksen,#1d4ed8)] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <h4 class="font-bold text-base text-blue-950">
            Sisa {{ kartu.hitung_mundur.sisa_hari }} hari lagi
          </h4>
          <p class="text-xs md:text-sm text-blue-800 mt-0.5">
            Batas pengembalian: <strong>{{ formatTanggalIndo(kartu.batas_kembali) }}</strong>. Anda dapat mengajukan perpanjangan batas waktu jika kuota masih tersedia.
          </p>
        </div>
      </div>

      <!-- Detail Grid Peminjam & Tanggal -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs md:text-sm pt-2">
        <div class="p-3 bg-slate-50 rounded-xl space-y-0.5">
          <span class="text-slate-500 font-semibold block text-[11px] uppercase">Peminjam</span>
          <span class="font-bold text-slate-900 block truncate">{{ kartu.nama_peminjam }}</span>
          <span class="text-slate-500 text-xs capitalize block">{{ kartu.tipe_peminjam }}</span>
        </div>

        <div class="p-3 bg-slate-50 rounded-xl space-y-0.5">
          <span class="text-slate-500 font-semibold block text-[11px] uppercase">Divisi</span>
          <span class="font-bold text-slate-900 block truncate">{{ kartu.division?.nama || '-' }}</span>
        </div>

        <div class="p-3 bg-slate-50 rounded-xl space-y-0.5">
          <span class="text-slate-500 font-semibold block text-[11px] uppercase">Tanggal Pinjam</span>
          <span class="font-bold text-slate-900 block">{{ formatTanggalIndo(kartu.tanggal_pinjam) }}</span>
        </div>

        <div class="p-3 bg-slate-50 rounded-xl space-y-0.5">
          <span class="text-slate-500 font-semibold block text-[11px] uppercase">Batas Kembali</span>
          <span class="font-bold text-slate-900 block">{{ formatTanggalIndo(kartu.batas_kembali) }}</span>
          <span
            v-if="kartu.batas_kembali_awal && kartu.batas_kembali_awal !== kartu.batas_kembali"
            class="text-[10px] text-slate-400 block"
          >
            Awal: {{ formatTanggalIndo(kartu.batas_kembali_awal) }}
          </span>
        </div>
      </div>

      <!-- Info Kuota Perpanjangan (FR-K-04) -->
      <div
        v-if="!kartu.pemesanan && kartu.status !== 'dibatalkan'"
        class="text-xs text-slate-600 bg-slate-50 px-4 py-2.5 rounded-xl flex flex-wrap items-center justify-between gap-2 border border-slate-100"
      >
        <span>
          Status Perpanjangan: <strong>{{ kartu.perpanjangan?.terpakai || 0 }}</strong> dari <strong>{{ kartu.perpanjangan?.maks || 0 }}</strong> terpakai
        </span>
        <span class="font-semibold text-slate-800">
          Sisa kuota: {{ kartu.perpanjangan?.sisa_kuota ?? 0 }} kali
        </span>
      </div>
    </section>

    <!-- Daftar Barang & Status Unit (FR-K-04: Per barang dipinjam, menunggu, terverifikasi, sisa) -->
    <section class="bg-white rounded-2xl border border-slate-200 p-5 md:p-6 shadow-xs space-y-4">
      <div class="border-b border-slate-100 pb-3">
        <h3 class="text-lg md:text-xl font-bold text-slate-900">
          Daftar Barang Inventaris
        </h3>
        <p class="text-xs md:text-sm text-slate-500">
          Rincian status unit per barang: dipinjam, sedang menunggu verifikasi petugas, terverifikasi, dan sisa.
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs md:text-sm border-collapse min-w-[550px]">
          <thead>
            <tr class="border-b border-slate-200 text-slate-500 uppercase text-[11px] tracking-wider bg-slate-50">
              <th class="py-3 px-3">Nama Barang</th>
              <th class="py-3 px-3 text-center">Dipinjam</th>
              <th class="py-3 px-3 text-center">Menunggu</th>
              <th class="py-3 px-3 text-center">Terverifikasi</th>
              <th class="py-3 px-3 text-center">Sisa</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="item in kartu.items"
              :key="item.item_id"
              class="hover:bg-slate-50 transition-colors"
            >
              <td class="py-3.5 px-3">
                <span class="font-bold text-slate-900 block text-sm md:text-base">{{ item.nama }}</span>
                <span
                  v-if="!item.boleh_diperpanjang"
                  class="text-[11px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md inline-block mt-0.5 border border-amber-200"
                >
                  Tidak boleh diperpanjang
                </span>
              </td>
              <td class="py-3.5 px-3 text-center font-semibold text-slate-700">
                {{ item.qty_dipinjam }} unit
              </td>
              <td class="py-3.5 px-3 text-center">
                <span
                  :class="[
                    'font-semibold px-2 py-0.5 rounded-full text-xs',
                    item.menunggu > 0 ? 'bg-amber-100 text-amber-800' : 'text-slate-400',
                  ]"
                >
                  {{ item.menunggu }} unit
                </span>
              </td>
              <td class="py-3.5 px-3 text-center">
                <span
                  :class="[
                    'font-semibold px-2 py-0.5 rounded-full text-xs',
                    item.terverifikasi > 0 ? 'bg-emerald-100 text-emerald-800' : 'text-slate-400',
                  ]"
                >
                  {{ item.terverifikasi }} unit
                </span>
              </td>
              <td class="py-3.5 px-3 text-center">
                <span
                  :class="[
                    'font-extrabold px-2.5 py-1 rounded-full text-xs',
                    item.sisa > 0 ? 'bg-blue-100 text-blue-900 border border-blue-200' : 'bg-slate-100 text-slate-400',
                  ]"
                >
                  {{ item.sisa }} unit
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Riwayat Klaim Pengembalian (FR-K-04 & FR-K-09: Hasil klaim & catatan admin terlihat peminjam) -->
    <section class="bg-white rounded-2xl border border-slate-200 p-5 md:p-6 shadow-xs space-y-4">
      <div class="border-b border-slate-100 pb-3">
        <h3 class="text-lg md:text-xl font-bold text-slate-900">
          Riwayat Pengembalian (Klaim)
        </h3>
        <p class="text-xs md:text-sm text-slate-500">
          Riwayat permohonan pengembalian barang dan hasil pemeriksaan fisik oleh petugas IT.
        </p>
      </div>

      <!-- Jika Ada Klaim -->
      <div v-if="kartu.klaim && kartu.klaim.length > 0" class="space-y-4">
        <div
          v-for="c in kartu.klaim"
          :key="c.kode_klaim"
          class="border border-slate-200 rounded-xl p-4 md:p-5 space-y-3 bg-slate-50/50"
        >
          <!-- Header Baris Klaim -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2.5 border-b border-slate-200">
            <div class="flex items-center gap-2">
              <span class="font-mono font-bold text-slate-900 text-base">
                {{ c.kode_klaim }}
              </span>
              <span
                :class="[
                  'text-xs font-bold px-2.5 py-0.5 rounded-full border',
                  c.status === 'diverifikasi'
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    : 'bg-amber-50 text-amber-700 border-amber-200',
                ]"
              >
                {{ c.status === 'diverifikasi' ? 'Diverifikasi' : 'Menunggu Verifikasi' }}
              </span>
            </div>

            <span class="text-xs text-slate-500">
              Diajukan: {{ formatWaktuIndo(c.diklaim_pada) }}
            </span>
          </div>

          <!-- Catatan Peminjam saat mengajukan -->
          <div v-if="c.catatan_peminjam" class="text-xs text-slate-600 bg-white p-2.5 rounded-lg border border-slate-100">
            <strong class="font-semibold text-slate-700">Catatan Peminjam:</strong>
            <p class="mt-0.5 italic text-slate-800">{{ c.catatan_peminjam }}</p>
          </div>

          <!-- Hasil Verifikasi Petugas IT (FR-K-09) -->
          <div v-if="c.status === 'diverifikasi'" class="space-y-3 pt-1">
            <!-- Rincian Hasil Barang -->
            <div v-if="c.hasil && c.hasil.length > 0" class="space-y-1.5">
              <span class="text-xs font-bold text-slate-700 uppercase tracking-wide block">
                Hasil Pemeriksaan Fisik:
              </span>
              <div class="space-y-1.5">
                <div
                  v-for="(h, idx) in c.hasil"
                  :key="idx"
                  class="bg-white p-3 rounded-lg border border-slate-200 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2"
                >
                  <div>
                    <span class="font-bold text-slate-900 block">{{ h.nama }}</span>
                    <span class="text-slate-500 text-[11px]">
                      Kondisi dilaporkan: <strong class="capitalize">{{ h.kondisi_dilaporkan }}</strong> ({{ h.qty }} unit)
                    </span>
                  </div>

                  <!-- Hasil 4 Kategori (baik, rusak, hilang, ditolak) -->
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span v-if="h.qty_baik > 0" class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-semibold text-[11px]">
                      Baik: {{ h.qty_baik }}
                    </span>
                    <span v-if="h.qty_rusak > 0" class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 font-semibold text-[11px]">
                      Rusak: {{ h.qty_rusak }}
                    </span>
                    <span v-if="h.qty_hilang > 0" class="px-2 py-0.5 rounded bg-red-50 text-red-800 border border-red-200 font-semibold text-[11px]">
                      Hilang: {{ h.qty_hilang }}
                    </span>
                    <span v-if="h.qty_ditolak > 0" class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-300 font-semibold text-[11px]">
                      Ditolak: {{ h.qty_ditolak }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Catatan Admin Terlihat Peminjam (FR-K-09) -->
            <div
              v-if="c.catatan_admin"
              class="p-3 rounded-lg bg-blue-50/70 border border-blue-200 text-xs text-blue-900 space-y-1"
            >
              <div class="flex items-center gap-1.5 font-bold text-blue-950">
                <svg class="w-4 h-4 text-[var(--color-aksen,#1d4ed8)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                </svg>
                <span>Catatan Petugas IT:</span>
              </div>
              <p class="whitespace-pre-line text-blue-900 pl-5">
                {{ c.catatan_admin }}
              </p>
            </div>
          </div>

          <!-- Petunjuk Jika Masih Menunggu Verifikasi -->
          <div v-else class="text-xs text-amber-800 bg-amber-50 p-2.5 rounded-lg border border-amber-200 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>
              Segera serahkan unit fisik barang ke petugas IT di ruangan untuk diperiksa dan diverifikasi.
            </span>
          </div>
        </div>
      </div>

      <!-- Jika Belum Ada Klaim -->
      <div v-else class="text-center py-6 text-slate-500 text-xs md:text-sm">
        Belum ada riwayat pengajuan pengembalian (klaim) untuk peminjaman ini.
      </div>
    </section>

    <!-- Tombol Aksi Bawah (DESAIN 10.2: Kembalikan, Perpanjang, Batalkan hanya bila berlaku) -->
    <section class="bg-white rounded-2xl border border-slate-200 p-5 md:p-6 shadow-xs space-y-4">
      <h3 class="text-base font-bold text-slate-900">
        Tindakan Peminjaman
      </h3>

      <!-- Opsi 1: Pemesanan (Hanya Tombol Batalkan) -->
      <div v-if="kartu.pemesanan" class="space-y-2">
        <TombolBesar
          varian="bahaya"
          ukuran="besar"
          class="w-full sm:w-auto sm:min-w-[220px]"
          :dinonaktifkan="!kartu.aksi?.batal?.boleh"
          @click="keHalamanBatal"
        >
          Batalkan Pemesanan
        </TombolBesar>

        <p v-if="!kartu.aksi?.batal?.boleh && kartu.aksi?.batal?.alasan" class="text-xs text-red-600 font-semibold">
          {{ kartu.aksi.batal.alasan }}
        </p>
      </div>

      <!-- Opsi 2: Bukan Pemesanan (Tombol Kembalikan & Perpanjang) -->
      <div v-else class="flex flex-col sm:flex-row items-stretch sm:items-start gap-4">
        <!-- Aksi Kembalikan / Klaim -->
        <div class="space-y-1.5 flex-1">
          <TombolBesar
            varian="primer"
            ukuran="besar"
            lebar-penuh
            :dinonaktifkan="!kartu.aksi?.klaim?.boleh"
            @click="keHalamanKlaim"
          >
            Kembalikan Barang
          </TombolBesar>
          <p v-if="!kartu.aksi?.klaim?.boleh && kartu.aksi?.klaim?.alasan" class="text-xs text-slate-500 text-center">
            {{ kartu.aksi.klaim.alasan }}
          </p>
        </div>

        <!-- Aksi Perpanjangan -->
        <div class="space-y-1.5 flex-1">
          <TombolBesar
            varian="sekunder"
            ukuran="besar"
            lebar-penuh
            :dinonaktifkan="!kartu.aksi?.perpanjang?.boleh"
            @click="keHalamanPerpanjang"
          >
            Perpanjang Batas Waktu
          </TombolBesar>
          <p v-if="!kartu.aksi?.perpanjang?.boleh && kartu.aksi?.perpanjang?.alasan" class="text-xs text-slate-500 text-center">
            {{ kartu.aksi.perpanjang.alasan }}
          </p>
        </div>
      </div>
    </section>
  </div>
</template>
