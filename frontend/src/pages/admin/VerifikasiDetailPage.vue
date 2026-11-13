<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '@/api'
import BadgeStatus from '@/components/BadgeStatus.vue'
import FormField from '@/components/FormField.vue'
import InputJumlah from '@/components/InputJumlah.vue'
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

// { [line_id]: { qty_baik, qty_rusak, qty_hilang, qty_ditolak } }
const isian = ref({})

const menunggu = computed(() => data.value?.status === 'menunggu')

async function muat() {
  sedangMuat.value = true
  bersihkan()
  try {
    const res = await adminApi.getReturnClaim(id.value)
    data.value = res.data?.data || null
    isian.value = Object.fromEntries(
      (data.value?.lines || []).map((l) => [
        l.line_id,
        l.hasil
          ? { qty_baik: l.hasil.qty_baik, qty_rusak: l.hasil.qty_rusak, qty_hilang: l.hasil.qty_hilang, qty_ditolak: l.hasil.qty_ditolak }
          : { qty_baik: 0, qty_rusak: 0, qty_hilang: 0, qty_ditolak: 0 },
      ]),
    )
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

onMounted(async () => {
  await muat()
  if (!data.value) {
    router.replace('/admin/verifikasi')
  }
})

// Selisih tiap baris: qty laporan dikurangi total empat kotak. 0 = sudah pas.
function selisih(l) {
  const i = isian.value[l.line_id]
  if (!i) return l.qty
  return l.qty - (i.qty_baik + i.qty_rusak + i.qty_hilang + i.qty_ditolak)
}

const semuaPas = computed(() =>
  (data.value?.lines || []).every((l) => selisih(l) === 0),
)

// BR-06: catatan wajib bila hasil beda dari kondisi dilaporkan atau ada unit ditolak.
function hasilBedaLaporan(l) {
  const i = isian.value[l.line_id]
  if (!i) return false
  const peta = { baik: 'qty_baik', rusak: 'qty_rusak', hilang: 'qty_hilang' }
  const kunci = peta[l.kondisi_dilaporkan]
  const cocok = i[kunci] === l.qty
  const lainnya = Object.entries(i).some(([k, v]) => k !== kunci && Number(v) > 0)
  return !(cocok && !lainnya)
}

const perluCatatan = computed(() =>
  (data.value?.lines || []).some((l) => hasilBedaLaporan(l)),
)

const catatanAdmin = ref('')

// Aksi cepat: semua baris sesuai laporan peminjam.
function setujuiSesuaiLaporan() {
  const peta = { baik: 'qty_baik', rusak: 'qty_rusak', hilang: 'qty_hilang' }
  for (const l of data.value.lines || []) {
    const kunci = peta[l.kondisi_dilaporkan]
    isian.value[l.line_id] = { qty_baik: 0, qty_rusak: 0, qty_hilang: 0, qty_ditolak: 0 }
    isian.value[l.line_id][kunci] = l.qty
  }
}

const modalKonfirmasi = ref(false)

function mintaSimpan() {
  bersihkan()
  if (!semuaPas.value) {
    errors.value = { lines: ['Ada baris yang jumlahnya belum sama dengan dilaporkan.'] }
    return
  }
  if (perluCatatan.value && !catatanAdmin.value.trim()) {
    errors.value = { catatan_admin: ['Catatan admin wajib bila hasil berbeda dari laporan atau ada unit ditolak (BR-06).'] }
    return
  }
  modalKonfirmasi.value = true
}

async function simpan() {
  modalKonfirmasi.value = false
  sedangKirim.value = true
  try {
    await adminApi.verifyReturnClaim(id.value, {
      lines: (data.value.lines || []).map((l) => ({ line_id: l.line_id, ...isian.value[l.line_id] })),
      catatan_admin: catatanAdmin.value.trim() || null,
    })
    toast('Klaim diverifikasi. Stok sudah diperbarui.')
    await muat()
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Verifikasi ditolak server.', 'galat')
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
          {{ data?.kode_klaim }}
          <BadgeStatus
            v-if="data"
            :status="data.status === 'menunggu' ? 'menunggu_verifikasi' : 'selesai'"
            :hari-terlambat="Number(data.hari_terlambat) || 0"
            ukuran="besar"
          />
        </h2>
      </div>
      <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push('/admin/verifikasi')">&larr; Antrean</TombolBesar>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />
    <Memuat v-if="sedangMuat && !data" tipe="skeleton" :baris="4" />
    <StatusKosong v-if="!data && !sedangMuat && !pesanGalat" judul="Klaim tidak ditemukan" />

    <template v-if="data">
      <!-- Konteks klaim -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs">
        <h3 class="text-base font-bold text-slate-900 pb-3 mb-4 border-b border-slate-100">Konteks Klaim</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Peminjam</dt><dd class="font-bold text-slate-900">{{ data.peminjaman?.nama_peminjam }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Kode Pinjam</dt><dd class="font-bold text-slate-900 font-mono">{{ data.peminjaman?.kode_pinjam }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Telepon</dt><dd class="font-bold text-slate-900">{{ data.peminjaman?.telepon || '—' }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Divisi</dt><dd class="font-bold text-slate-900">{{ data.peminjaman?.divisi?.nama ?? '—' }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Tanggal Pinjam</dt><dd class="font-bold text-slate-900">{{ formatTanggalIndo(data.peminjaman?.tanggal_pinjam) }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Batas Kembali</dt><dd class="font-bold text-slate-900">{{ formatTanggalIndo(data.peminjaman?.batas_kembali) }}</dd></div>
          <div class="flex justify-between sm:block"><dt class="text-slate-500 font-semibold">Diklaim</dt><dd class="font-bold text-slate-900">{{ formatWaktuIndo(data.diklaim_pada) }}</dd></div>
          <div class="flex justify-between sm:block">
            <dt class="text-slate-500 font-semibold">Terlambat saat klaim</dt>
            <dd class="font-bold" :class="Number(data.hari_terlambat) > 0 ? 'text-red-700' : 'text-slate-900'">
              {{ Number(data.hari_terlambat) > 0 ? `${data.hari_terlambat} hari` : 'Tidak' }}
            </dd>
          </div>
        </dl>

        <p v-if="data.catatan_peminjam" class="text-sm text-slate-700 bg-amber-50 border border-amber-300 rounded-lg px-3 py-2.5 mt-4">
          <span class="font-bold">Catatan peminjam (disorot):</span> {{ data.catatan_peminjam }}
        </p>
      </section>

      <!-- Isi klaim -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <div class="pb-3 border-b border-slate-100">
          <h3 class="text-base font-bold text-slate-900">Isi Klaim ({{ data.jumlah_unit }} unit)</h3>
          <p class="text-xs text-slate-500 mt-0.5">
            <template v-if="menunggu">Bagi tiap baris ke Baik, Rusak, Hilang, Ditolak. Total harus sama dengan dilaporkan (FR-A-04).</template>
            <template v-else>Hasil verifikasi (baca saja).</template>
          </p>
        </div>

        <p v-if="errors.lines?.[0]" class="text-sm font-semibold text-red-700">{{ errors.lines[0] }}</p>

        <div
          v-for="l in data.lines"
          :key="l.line_id"
          class="rounded-xl border border-slate-200 p-4 space-y-3"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-semibold text-slate-900">{{ l.nama }}</p>
            <p class="text-xs text-slate-500">
              Dilaporkan <span class="font-bold capitalize">{{ l.kondisi_dilaporkan }}</span> &middot;
              <span class="tabular-nums font-bold text-slate-900">{{ l.qty }} unit</span>
            </p>
          </div>

          <div v-if="menunggu" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <FormField v-for="k in ['qty_baik', 'qty_rusak', 'qty_hilang', 'qty_ditolak']" :key="k" :id="`v-${l.line_id}-${k}`" :label="({ qty_baik: 'Baik', qty_rusak: 'Rusak', qty_hilang: 'Hilang', qty_ditolak: 'Ditolak' })[k]">
              <InputJumlah
                :id="`v-${l.line_id}-${k}`"
                v-model="isian[l.line_id][k]"
                :min="0"
                :max="l.qty"
              />
            </FormField>
          </div>

          <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200">
              <p class="text-xs font-bold uppercase text-slate-500">Baik</p>
              <p class="font-bold tabular-nums text-slate-900">{{ l.hasil?.qty_baik ?? 0 }}</p>
            </div>
            <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200">
              <p class="text-xs font-bold uppercase text-slate-500">Rusak</p>
              <p class="font-bold tabular-nums text-slate-900">{{ l.hasil?.qty_rusak ?? 0 }}</p>
            </div>
            <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200">
              <p class="text-xs font-bold uppercase text-slate-500">Hilang</p>
              <p class="font-bold tabular-nums text-slate-900">{{ l.hasil?.qty_hilang ?? 0 }}</p>
            </div>
            <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200">
              <p class="text-xs font-bold uppercase text-slate-500">Ditolak</p>
              <p class="font-bold tabular-nums text-slate-900">{{ l.hasil?.qty_ditolak ?? 0 }}</p>
            </div>
          </div>

          <template v-if="menunggu">
            <p class="text-xs">
              <span v-if="selisih(l) === 0" class="font-semibold text-emerald-700">Jumlah pas.</span>
              <span v-else class="font-semibold text-red-700">Selisih {{ selisih(l) > 0 ? `${selisih(l)} belum dibagi` : `${-selisih(l)} kelebihan` }}.</span>
            </p>
            <p v-if="hasilBedaLaporan(l) && !catatanAdmin.trim()" class="text-xs font-semibold text-amber-700">
              Hasil berbeda dari laporan — catatan admin wajib (BR-06).
            </p>
          </template>
        </div>

        <!-- Catatan admin -->
        <FormField
          v-if="menunggu"
          id="v-catatan"
          label="Catatan Admin"
          :wajib="perluCatatan"
          bantuan="Wajib bila hasil berbeda dari laporan atau ada unit ditolak (BR-06)."
          :galat="errors.catatan_admin?.[0]"
        >
          <textarea
            id="v-catatan"
            v-model="catatanAdmin"
            rows="3"
            maxlength="500"
            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
          ></textarea>
        </FormField>
        <p v-else-if="data.catatan_admin" class="text-sm text-slate-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2.5">
          <span class="font-bold">Catatan admin:</span> {{ data.catatan_admin }}
        </p>

        <p v-if="!menunggu" class="text-xs text-slate-500">
          Diverifikasi {{ formatWaktuIndo(data.diverifikasi_pada) }} oleh {{ data.verifikator?.nama ?? '—' }}
        </p>
      </section>

      <!-- Aksi -->
      <section v-if="menunggu" class="flex flex-wrap items-center gap-2">
        <TombolBesar varian="sekunder" ukuran="sedang" :dinonaktifkan="sedangKirim" @click="setujuiSesuaiLaporan">
          Setujui sesuai laporan
        </TombolBesar>
        <TombolBesar varian="primer" ukuran="sedang" :memuat="sedangKirim" :dinonaktifkan="!semuaPas" @click="mintaSimpan">
          Simpan Verifikasi
        </TombolBesar>
        <p class="w-full text-xs text-slate-500">
          Simpan aktif setelah semua baris dibagi pas. Stok berubah sesuai hasil (Baik, Rusak, Hilang); Ditolak tidak mengubah stok.
        </p>
      </section>
    </template>

    <!-- Konfirmasi simpan verifikasi -->
    <ModalKonfirmasi
      :terbuka="modalKonfirmasi"
      judul="Simpan Verifikasi"
      pesan="Simpan hasil verifikasi ini? Stok langsung berubah sesuai hasil dan klaim tidak bisa diverifikasi ulang."
      teks-batal="Periksa Lagi"
      teks-konfirmasi="Ya, Simpan"
      :memuat="sedangKirim"
      @update:terbuka="modalKonfirmasi = $event"
      @konfirmasi="simpan"
    />
  </section>
</template>
