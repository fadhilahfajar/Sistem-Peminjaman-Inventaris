<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { kioskApi } from '@/api'
import FormField from '@/components/FormField.vue'
import InputJumlah from '@/components/InputJumlah.vue'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useKioskStore } from '@/stores/kiosk'
import { formatTanggalIndo } from '@/utils/tanggal'

const router = useRouter()
const kioskStore = useKioskStore()
const { errors, pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()

const kartu = computed(() => kioskStore.kartuPinjam)

// Barang dengan sisa unit yang masih dapat dikembalikan (BR-04)
const barangSisa = computed(() => (kartu.value?.items || []).filter((i) => i.sisa > 0))

// Map item_id -> { baik, rusak, hilang }
const isian = ref({})

const bolehKlaim = computed(() => kartu.value?.aksi?.klaim?.boleh === true)
const alasanTidakBoleh = computed(() => kartu.value?.aksi?.klaim?.alasan || '')

// Ringkasan total per kondisi
const totalBaik = computed(() => Object.values(isian.value).reduce((t, v) => t + (Number(v.baik) || 0), 0))
const totalRusak = computed(() => Object.values(isian.value).reduce((t, v) => t + (Number(v.rusak) || 0), 0))
const totalHilang = computed(() => Object.values(isian.value).reduce((t, v) => t + (Number(v.hilang) || 0), 0))
const totalSemua = computed(() => totalBaik.value + totalRusak.value + totalHilang.value)

// Modal konfirmasi (ringkasan sebelum kirim)
const modalTerbuka = ref(false)
const catatan = ref('')

onMounted(() => {
  // Bila kartu tidak ada di memori (mis. reload), kembali ke layar Cek (DESAIN 10.1)
  if (!kartu.value || !kartu.value.kode_pinjam) {
    router.replace('/cek')
    return
  }

  // Inisialisasi isian: semua nol
  const awal = {}
  for (const item of barangSisa.value) {
    awal[item.item_id] = { baik: 0, rusak: 0, hilang: 0 }
  }
  isian.value = awal
})

// Validasi klien FR-K-05: total per barang ≤ sisa, minimal satu unit dikembalikan
function validasiKlien() {
  bersihkan()
  const galatKlien = {}

  let adaYangDiisi = false

  for (const item of barangSisa.value) {
    const isi = isian.value[item.item_id] || { baik: 0, rusak: 0, hilang: 0 }
    const total = (Number(isi.baik) || 0) + (Number(isi.rusak) || 0) + (Number(isi.hilang) || 0)

    if (total > 0) {
      adaYangDiisi = true
    }

    if (total > item.sisa) {
      galatKlien[`item_${item.item_id}`] = `Total pengembalian melebihi sisa unit (${item.sisa}).`
    }
  }

  if (!adaYangDiisi) {
    galatKlien.umum = 'Isi minimal satu unit barang yang dikembalikan.'
  }

  const catatanBersih = catatan.value.trim()
  if (catatanBersih.length > 500) {
    galatKlien.catatan = 'Catatan maksimal 500 karakter.'
  }

  if (Object.keys(galatKlien).length > 0) {
    errors.value = galatKlien
    pesanGalat.value = 'Mohon periksa kembali isian formulir.'
    return false
  }

  return true
}

function bukaKonfirmasi() {
  if (!validasiKlien()) return
  modalTerbuka.value = true
}

async function kirimKlaim() {
  if (sedangKirim.value) return

  // Bentuk lines: [{item_id, kondisi, qty}] hanya untuk isian > 0
  const lines = []
  for (const item of barangSisa.value) {
    const isi = isian.value[item.item_id] || {}
    for (const kondisi of ['baik', 'rusak', 'hilang']) {
      const qty = Number(isi[kondisi]) || 0
      if (qty > 0) {
        lines.push({ item_id: item.item_id, kondisi, qty })
      }
    }
  }

  const payload = {
    kode: kartu.value.kode_pinjam,
    lines,
    catatan: catatan.value.trim() || null,
    idempotency_key: crypto.randomUUID(),
  }

  modalTerbuka.value = false
  sedangKirim.value = true

  try {
    const res = await kioskApi.buatReturnClaim(payload)
    kioskStore.setKlaimSelesai(res.data?.data)
    await router.push('/klaim/selesai')
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangKirim.value = false
  }
}
</script>

<template>
  <div v-if="kartu" class="space-y-6 max-w-3xl mx-auto py-2">
    <!-- Header -->
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
          Klaim Pengembalian Barang
        </h2>
        <p class="text-xs md:text-sm text-slate-600 mt-0.5">
          Kode {{ kartu.kode_pinjam }} &middot; Batas kembali {{ formatTanggalIndo(kartu.batas_kembali) }}
        </p>
      </div>

      <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push('/cek/kartu')">
        &larr; Kartu
      </TombolBesar>
    </div>

    <!-- Banner terlambat -->
    <div
      v-if="kartu.hitung_mundur?.hari_terlambat > 0"
      class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-900 text-sm"
    >
      <strong>Terlambat {{ kartu.hitung_mundur.hari_terlambat }} hari.</strong>
      Hari keterlambatan akan dicatat pada klaim ini (BR-10).
    </div>

    <!-- Pesan Galat -->
    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <!-- Aksi tidak berlaku -->
    <div
      v-if="!bolehKlaim"
      class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm"
    >
      <strong>Klaim tidak dapat diajukan:</strong> {{ alasanTidakBoleh }}
    </div>

    <form v-else class="space-y-6" @submit.prevent="bukaKonfirmasi">
      <!-- Galat umum isian -->
      <p v-if="errors.umum" class="text-sm font-semibold text-red-600">
        {{ errors.umum }}
      </p>

      <!-- Daftar barang (FR-K-05: per barang Baik, Rusak, Hilang, total ≤ sisa) -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <div class="pb-3 border-b border-slate-100">
          <h3 class="text-lg md:text-xl font-bold text-slate-900">Barang yang Masih Dipinjam</h3>
          <p class="text-xs md:text-sm text-slate-500">
            Tentukan jumlah unit per kondisi: baik, rusak, atau hilang. Total tidak boleh melebihi sisa.
          </p>
        </div>

        <div
          v-for="item in barangSisa"
          :key="item.item_id"
          class="rounded-xl border border-slate-200 p-4 space-y-3"
          :class="errors[`item_${item.item_id}`] ? 'border-red-300 bg-red-50/40' : ''"
        >
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span class="font-bold text-slate-900">{{ item.nama }}</span>
            <span class="text-xs font-semibold text-slate-500">
              Sisa: {{ item.sisa }} unit
              <template v-if="item.qty_dipinjam > item.sisa">
                (dipinjam {{ item.qty_dipinjam }})
              </template>
            </span>
          </div>

          <p
            v-if="errors[`item_${item.item_id}`]"
            class="text-xs font-semibold text-red-600"
          >
            {{ errors[`item_${item.item_id}`] }}
          </p>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <span class="text-xs font-semibold text-emerald-700 block mb-1.5">Baik</span>
              <InputJumlah
                v-model="isian[item.item_id].baik"
                :min="0"
                :max="item.sisa"
                :dinonaktifkan="sedangKirim"
              />
            </div>
            <div>
              <span class="text-xs font-semibold text-amber-700 block mb-1.5">Rusak</span>
              <InputJumlah
                v-model="isian[item.item_id].rusak"
                :min="0"
                :max="item.sisa"
                :dinonaktifkan="sedangKirim"
              />
            </div>
            <div>
              <span class="text-xs font-semibold text-red-700 block mb-1.5">Hilang</span>
              <InputJumlah
                v-model="isian[item.item_id].hilang"
                :min="0"
                :max="item.sisa"
                :dinonaktifkan="sedangKirim"
              />
            </div>
          </div>
        </div>

        <!-- Ringkasan -->
        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-sm font-semibold text-slate-700">
          <span>
            Total dilaporkan:
            <span class="text-emerald-700">{{ totalBaik }} baik</span>,
            <span class="text-amber-700">{{ totalRusak }} rusak</span>,
            <span class="text-red-700">{{ totalHilang }} hilang</span>
          </span>
          <span class="text-slate-900 font-bold">{{ totalSemua }} unit</span>
        </div>
      </section>

      <!-- Catatan opsional -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs">
        <FormField
          id="catatan"
          label="Catatan (Opsional)"
          bantuan="Jelaskan kondisi barang bila perlu. Maksimal 500 karakter."
          :galat="errors.catatan"
        >
          <textarea
            id="catatan"
            v-model="catatan"
            rows="3"
            maxlength="500"
            placeholder="Contoh: Adaptor tidak ikut dikembalikan karena dipakai rapat."
            :disabled="sedangKirim"
            class="w-full px-3.5 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm md:text-base text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors"
          ></textarea>
        </FormField>
      </section>

      <!-- Aksi -->
      <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
        <TombolBesar
          varian="sekunder"
          ukuran="besar"
          class="w-full sm:w-auto"
          :dinonaktifkan="sedangKirim"
          @click="router.push('/cek/kartu')"
        >
          Kembali ke Kartu
        </TombolBesar>

        <TombolBesar
          tipe="submit"
          varian="primer"
          ukuran="besar"
          class="w-full sm:w-auto sm:min-w-[260px]"
          :memuat="sedangKirim"
          :dinonaktifkan="sedangKirim || totalSemua === 0"
        >
          Ajukan Klaim
        </TombolBesar>
      </div>
    </form>

    <!-- Modal Konfirmasi Ringkasan -->
    <ModalKonfirmasi
      :terbuka="modalTerbuka"
      judul="Ringkasan Klaim Pengembalian"
      :pesan="`Melaporkan ${totalSemua} unit: ${totalBaik} baik, ${totalRusak} rusak, ${totalHilang} hilang. Lanjutkan?`"
      teks-batal="Periksa Lagi"
      teks-konfirmasi="Ya, Ajukan Klaim"
      :memuat="sedangKirim"
      @update:terbuka="modalTerbuka = $event"
      @konfirmasi="kirimKlaim"
    >
      <div class="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-200">
        Setelah dikirim, status unit berubah menjadi <strong>Menunggu Verifikasi</strong>.
        Serahkan barang fisik ke petugas IT untuk diperiksa.
      </div>
    </ModalKonfirmasi>
  </div>
</template>
