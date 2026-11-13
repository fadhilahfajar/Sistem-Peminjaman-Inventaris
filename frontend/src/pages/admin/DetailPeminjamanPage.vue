<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '@/api'
import BadgeLencana from '@/components/BadgeLencana.vue'
import BadgeStatus from '@/components/BadgeStatus.vue'
import FormField from '@/components/FormField.vue'
import InputJumlah from '@/components/InputJumlah.vue'
import LiniMasa from '@/components/LiniMasa.vue'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import Memuat from '@/components/Memuat.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import StatusKosong from '@/components/StatusKosong.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useToast } from '@/composables/useToast'
import { formatTanggalIndo, formatWaktuIndo } from '@/utils/tanggal'

const route = useRoute()
const router = useRouter()
const { errors, pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()
const { tampil: toast } = useToast()

const id = computed(() => Number(route.params.id))
const data = ref(null)
const sedangMuat = ref(false)

async function muat() {
  sedangMuat.value = true
  bersihkan()
  try {
    const res = await adminApi.getLoan(id.value)
    data.value = res.data?.data || null
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

onMounted(async () => {
  await muat()
  if (!data.value) {
    router.replace('/admin/peminjaman')
  }
})

const sudahPunyaKlaim = computed(() => (data.value?.klaim || []).length > 0)

// --- Koreksi (BR-16) ---
const modalKoreksi = ref(false)
const formKoreksi = ref({ nama_peminjam: '', telepon: '', tanggal_pinjam: '', batas_kembali: '', alasan: '' })
const qtyKoreksi = ref({})

function bukaKoreksi() {
  bersihkan()
  formKoreksi.value = {
    nama_peminjam: data.value.nama_peminjam,
    telepon: data.value.telepon || '',
    tanggal_pinjam: data.value.tanggal_pinjam,
    batas_kembali: data.value.batas_kembali,
    alasan: '',
  }
  qtyKoreksi.value = Object.fromEntries(
    (data.value.items || []).map((i) => [i.item_id, i.qty_dipinjam]),
  )
  modalKoreksi.value = true
}

function galatKoreksi() {
  bersihkan()
  const galat = {}
  if (!formKoreksi.value.nama_peminjam.trim()) {
    galat.nama_peminjam = ['Nama peminjam wajib diisi.']
  }
  if (!formKoreksi.value.alasan.trim()) {
    galat.alasan = ['Alasan koreksi wajib diisi (BR-16).']
  }
  for (const i of data.value.items || []) {
    const qty = Number(qtyKoreksi.value[i.item_id])
    if (!Number.isInteger(qty) || qty < 1) {
      galat.items = ['Jumlah pinjam minimal 1.']
    }
  }
  return galat
}

async function simpanKoreksi() {
  const galat = galatKoreksi()
  if (Object.keys(galat).length > 0) {
    errors.value = galat
    return
  }

  const payload = {
    nama_peminjam: formKoreksi.value.nama_peminjam.trim(),
    telepon: formKoreksi.value.telepon.trim() || null,
    tanggal_pinjam: formKoreksi.value.tanggal_pinjam,
    batas_kembali: formKoreksi.value.batas_kembali,
    items: (data.value.items || []).map((i) => ({ item_id: i.item_id, qty: Number(qtyKoreksi.value[i.item_id]) })),
    alasan: formKoreksi.value.alasan.trim(),
  }

  modalKoreksi.value = false
  sedangKirim.value = true
  try {
    await adminApi.koreksiLoan(id.value, payload)
    toast('Koreksi disimpan.')
    await muat()
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Koreksi ditolak server.', 'galat')
  } finally {
    sedangKirim.value = false
  }
}

// --- Perpanjangan admin (BR-13) ---
const modalPerpanjang = ref(false)
const formPerpanjang = ref({ tambahan_hari: 1, alasan: '' })

function bukaPerpanjang() {
  bersihkan()
  formPerpanjang.value = { tambahan_hari: 1, alasan: '' }
  modalPerpanjang.value = true
}

async function simpanPerpanjang() {
  bersihkan()
  const hari = Number(formPerpanjang.value.tambahan_hari)
  if (!hari || hari < 1) {
    errors.value = { tambahan_hari: ['Tambahan hari minimal 1.'] }
    return
  }
  if (!formPerpanjang.value.alasan.trim()) {
    errors.value = { alasan: ['Alasan perpanjangan wajib diisi (BR-13).'] }
    return
  }

  modalPerpanjang.value = false
  sedangKirim.value = true
  try {
    await adminApi.perpanjangLoan(id.value, {
      tambahan_hari: hari,
      alasan: formPerpanjang.value.alasan.trim(),
    })
    toast(`Batas kembali digeser ${hari} hari.`)
    await muat()
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Perpanjangan ditolak server.', 'galat')
  } finally {
    sedangKirim.value = false
  }
}

// --- Pembatalan admin (BR-25) ---
const modalBatal = ref(false)
const formBatal = ref({ alasan: '' })

function bukaBatal() {
  bersihkan()
  formBatal.value = { alasan: '' }
  modalBatal.value = true
}

async function simpanBatal() {
  bersihkan()
  if (!formBatal.value.alasan.trim()) {
    errors.value = { alasan: ['Alasan pembatalan wajib diisi (BR-25).'] }
    return
  }

  modalBatal.value = false
  sedangKirim.value = true
  try {
    await adminApi.batalLoan(id.value, { alasan: formBatal.value.alasan.trim() })
    toast('Peminjaman dibatalkan. Stok dikembalikan.')
    await muat()
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Pembatalan ditolak server.', 'galat')
  } finally {
    sedangKirim.value = false
  }
}
</script>

<template>
  <section class="space-y-6 max-w-4xl">
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">
          {{ data?.kode_pinjam }}
          <BadgeStatus v-if="data" :status="data.status" :hari-terlambat="Number(data.hari_terlambat) || 0" ukuran="besar" />
        </h2>
        <div v-if="data" class="flex flex-wrap gap-1.5 mt-1.5">
          <BadgeLencana
            v-for="l in data.lencana || []"
            :key="l"
            :lencana="l"
            :diperpanjang-kali="Number(data.diperpanjang_kali) || 0"
          />
        </div>
      </div>
      <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push('/admin/peminjaman')">&larr; Daftar</TombolBesar>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />
    <Memuat v-if="sedangMuat && !data" tipe="skeleton" :baris="4" />
    <StatusKosong v-if="!data && !sedangMuat && !pesanGalat" judul="Peminjaman tidak ditemukan" />

    <template v-if="data">
      <!-- Identitas -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs">
        <h3 class="text-base font-bold text-slate-900 pb-3 mb-4 border-b border-slate-100">Identitas Peminjam</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Nama</dt><dd class="font-bold text-slate-900">{{ data.nama_peminjam }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Telepon</dt><dd class="font-bold text-slate-900">{{ data.telepon || '—' }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Divisi</dt><dd class="font-bold text-slate-900">{{ data.divisi?.nama ?? '—' }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Tipe</dt><dd class="font-bold text-slate-900 capitalize">{{ data.tipe_peminjam }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Tgl Pinjam</dt><dd class="font-bold text-slate-900">{{ formatTanggalIndo(data.tanggal_pinjam) }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Batas Kembali</dt><dd class="font-bold text-slate-900">{{ formatTanggalIndo(data.batas_kembali) }} <span class="text-xs text-slate-400">(awal {{ formatTanggalIndo(data.batas_kembali_awal) }})</span></dd></div>
          <div v-if="data.dibatalkan_pada" class="sm:col-span-2">
            <dt class="text-slate-500 font-semibold">Dibatalkan</dt>
            <dd class="font-bold text-red-800">{{ formatWaktuIndo(data.dibatalkan_pada) }} — {{ data.alasan_batal }}</dd>
          </div>
        </dl>
      </section>

      <!-- Barang -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs">
        <h3 class="text-base font-bold text-slate-900 pb-3 mb-4 border-b border-slate-100">Barang ({{ data.jumlah_unit }} unit)</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead>
              <tr class="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-600">
                <th class="px-2 py-2">Barang</th>
                <th class="px-2 py-2 text-center">Dipinjam</th>
                <th class="px-2 py-2 text-center">Menunggu</th>
                <th class="px-2 py-2 text-center">Terverifikasi</th>
                <th class="px-2 py-2 text-center">Sisa</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in data.items" :key="i.item_id" class="border-b border-slate-100 last:border-0">
                <td class="px-2 py-2 font-medium text-slate-900">{{ i.nama }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ i.qty_dipinjam }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ i.menunggu }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ i.terverifikasi }}</td>
                <td class="px-2 py-2 text-center tabular-nums font-bold">{{ i.sisa }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Aksi -->
      <section v-if="data.status !== 'dibatalkan'" class="flex flex-wrap gap-2">
        <TombolBesar varian="sekunder" ukuran="sedang" @click="bukaKoreksi">Koreksi</TombolBesar>
        <TombolBesar varian="sekunder" ukuran="sedang" @click="bukaPerpanjang">Perpanjang</TombolBesar>
        <TombolBesar
          varian="bahaya"
          ukuran="sedang"
          :dinonaktifkan="sudahPunyaKlaim"
          @click="bukaBatal"
        >
          Batalkan
        </TombolBesar>
        <p v-if="sudahPunyaKlaim" class="w-full text-xs text-slate-500">
          Pembatalan tidak tersedia karena peminjaman sudah memiliki klaim (BR-25).
        </p>
      </section>

      <!-- Lini masa (FR-A-06) -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs">
        <h3 class="text-base font-bold text-slate-900 pb-3 mb-4 border-b border-slate-100">Lini Masa</h3>
        <LiniMasa :entri="data.lini_masa || []" />
      </section>

      <!-- Klaim -->
      <section v-if="(data.klaim || []).length > 0" class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <h3 class="text-base font-bold text-slate-900 pb-3 border-b border-slate-100">Riwayat Klaim</h3>
        <div v-for="k in data.klaim" :key="k.id" class="rounded-xl border border-slate-200 p-4 space-y-2 text-sm">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-mono font-bold text-slate-900">{{ k.kode_klaim }}</span>
            <BadgeStatus :status="k.status === 'menunggu' ? 'menunggu_verifikasi' : 'selesai'" ukuran="kecil" />
          </div>
          <p class="text-xs text-slate-500">
            Diklaim {{ formatWaktuIndo(k.diklaim_pada) }}
            <template v-if="Number(k.hari_terlambat) > 0"> &middot; terlambat {{ k.hari_terlambat }} hari saat klaim</template>
            <template v-if="k.diverifikasi_pada"> &middot; diverifikasi {{ formatWaktuIndo(k.diverifikasi_pada) }} oleh {{ k.verifikator?.nama ?? '—' }}</template>
          </p>
          <p v-if="k.catatan_peminjam" class="text-xs bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 text-slate-700">
            Catatan peminjam: {{ k.catatan_peminjam }}
          </p>
          <p v-if="k.catatan_admin" class="text-xs bg-blue-50 border border-blue-200 rounded-lg px-2.5 py-1.5 text-slate-700">
            Catatan admin: {{ k.catatan_admin }}
          </p>
          <ul class="text-xs text-slate-600 list-disc pl-4">
            <li v-for="l in k.lines" :key="l.line_id">
              {{ l.nama }} — dilaporkan {{ l.kondisi_dilaporkan }} {{ l.qty }} unit
              <template v-if="l.hasil">; hasil: baik {{ l.hasil.qty_baik }}, rusak {{ l.hasil.qty_rusak }}, hilang {{ l.hasil.qty_hilang }}, ditolak {{ l.hasil.qty_ditolak }}</template>
            </li>
          </ul>
        </div>
      </section>
    </template>

    <!-- Modal koreksi -->
    <div
      v-if="modalKoreksi"
      class="fixed inset-0 z-50 bg-slate-900/50 flex items-start md:items-center justify-center p-4 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div class="bg-white rounded-2xl p-6 max-w-lg w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Koreksi Peminjaman</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField id="k-nama" label="Nama Peminjam" wajib :galat="errors.nama_peminjam?.[0]">
            <input id="k-nama" v-model="formKoreksi.nama_peminjam" type="text" class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]">
          </FormField>

          <FormField id="k-telp" label="Telepon">
            <input id="k-telp" v-model="formKoreksi.telepon" type="tel" class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]">
          </FormField>

          <FormField id="k-tgl" label="Tanggal Pinjam" :galat="errors.tanggal_pinjam?.[0]">
            <input id="k-tgl" v-model="formKoreksi.tanggal_pinjam" type="date" class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]">
          </FormField>

          <FormField id="k-batas" label="Batas Kembali" :galat="errors.batas_kembali?.[0]">
            <input id="k-batas" v-model="formKoreksi.batas_kembali" type="date" class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]">
          </FormField>
        </div>

        <FormField id="k-alasan" label="Alasan Koreksi" wajib bantuan="Wajib dan tercatat (BR-16)." :galat="errors.alasan?.[0]">
          <input id="k-alasan" v-model="formKoreksi.alasan" type="text" maxlength="500" class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]">
        </FormField>

        <div class="space-y-2">
          <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Jumlah per barang</p>
          <div
            v-for="i in data.items"
            :key="i.item_id"
            class="flex items-center justify-between gap-3"
          >
            <span class="text-sm font-medium text-slate-900">{{ i.nama }}</span>
            <InputJumlah v-model="qtyKoreksi[i.item_id]" :min="1" :max="999" :dinonaktifkan="sedangKirim" />
          </div>
          <p v-if="errors.items?.[0]" class="text-xs font-semibold text-red-600">{{ errors.items[0] }}</p>
        </div>

        <div class="flex justify-end gap-2">
          <TombolBesar varian="sekunder" ukuran="sedang" @click="modalKoreksi = false">Batal</TombolBesar>
          <TombolBesar varian="primer" ukuran="sedang" :memuat="sedangKirim" @click="simpanKoreksi">Simpan Koreksi</TombolBesar>
        </div>
      </div>
    </div>

    <!-- Modal perpanjang -->
    <div
      v-if="modalPerpanjang"
      class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
    >
      <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Perpanjang Batas Kembali</h3>

        <FormField id="p-hari" label="Tambahan Hari" wajib bantuan="Admin tanpa kuota, boleh untuk yang terlambat (BR-13).">
          <InputJumlah id="p-hari" v-model="formPerpanjang.tambahan_hari" :min="1" :max="365" />
        </FormField>

        <FormField id="p-alasan" label="Alasan" wajib :galat="errors.alasan?.[0]">
          <input id="p-alasan" v-model="formPerpanjang.alasan" type="text" maxlength="500" class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]">
        </FormField>

        <div class="flex justify-end gap-2">
          <TombolBesar varian="sekunder" ukuran="sedang" @click="modalPerpanjang = false">Batal</TombolBesar>
          <TombolBesar varian="primer" ukuran="sedang" :memuat="sedangKirim" @click="simpanPerpanjang">Perpanjang</TombolBesar>
        </div>
      </div>
    </div>

    <!-- Modal batal -->
    <ModalKonfirmasi
      :terbuka="modalBatal"
      judul="Batalkan Peminjaman"
      pesan="Batalkan peminjaman ini? Stok langsung dikembalikan dan tidak dapat dipulihkan (BR-25)."
      teks-batal="Jangan Batalkan"
      teks-konfirmasi="Ya, Batalkan"
      varian-konfirmasi="bahaya"
      :memuat="sedangKirim"
      @update:terbuka="modalBatal = $event"
      @konfirmasi="simpanBatal"
    >
      <FormField id="b-alasan" label="Alasan Pembatalan" wajib :galat="errors.alasan?.[0]">
        <input id="b-alasan" v-model="formBatal.alasan" type="text" maxlength="500" class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]">
      </FormField>
    </ModalKonfirmasi>
  </section>
</template>
