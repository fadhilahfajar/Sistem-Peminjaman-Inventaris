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
import { formatTanggalIndo, tambahHariYmd } from '@/utils/tanggal'

const router = useRouter()
const kioskStore = useKioskStore()
const { errors, pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()

const kartu = computed(() => kioskStore.kartuPinjam)

const tambahanHari = ref(1)
const alasan = ref('')
const modalTerbuka = ref(false)

const bolehPerpanjang = computed(() => kartu.value?.aksi?.perpanjang?.boleh === true)
const alasanTidakBoleh = computed(() => kartu.value?.aksi?.perpanjang?.alasan || '')

// Batas maksimum hari per perpanjangan dari data kartu (param PengaturanService)
const maksHari = computed(() => Number(kartu.value?.perpanjangan?.maks_hari_per_perpanjangan) || 7)

const batasLama = computed(() => kartu.value?.batas_kembali || '')
const batasBaru = computed(() => tambahHariYmd(batasLama.value, Number(tambahanHari.value) || 0))

onMounted(() => {
  if (!kartu.value || !kartu.value.kode_pinjam) {
    router.replace('/cek')
  }
})

function validasiKlien() {
  bersihkan()
  const galatKlien = {}

  const hari = Number(tambahanHari.value)
  if (!hari || hari < 1) {
    galatKlien.tambahan_hari = 'Tambahan hari minimal 1 hari.'
  } else if (hari > maksHari.value) {
    galatKlien.tambahan_hari = `Tambahan hari maksimal ${maksHari.value} hari.`
  }

  if (alasan.value.trim().length > 500) {
    galatKlien.alasan = 'Alasan maksimal 500 karakter.'
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

async function kirimPerpanjangan() {
  if (sedangKirim.value) return

  const payload = {
    kode_pinjam: kartu.value.kode_pinjam,
    tambahan_hari: Number(tambahanHari.value),
    alasan: alasan.value.trim() || null,
  }

  modalTerbuka.value = false
  sedangKirim.value = true

  try {
    const res = await kioskApi.perpanjangLoan(payload)
    kioskStore.setPerpanjangSelesai(res.data?.data)
    await router.push('/perpanjang/selesai')
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangKirim.value = false
  }
}
</script>

<template>
  <div v-if="kartu" class="space-y-6 max-w-2xl mx-auto py-2">
    <!-- Header -->
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
          Perpanjangan Batas Kembali
        </h2>
        <p class="text-xs md:text-sm text-slate-600 mt-0.5">
          Kode {{ kartu.kode_pinjam }} &middot; Kuota terpakai {{ kartu.perpanjangan?.terpakai || 0 }} dari {{ kartu.perpanjangan?.maks || 0 }}
        </p>
      </div>

      <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push('/cek/kartu')">
        &larr; Kartu
      </TombolBesar>
    </div>

    <!-- Pesan Galat -->
    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <!-- Aksi tidak berlaku (BR-11: tombol nonaktif dengan alasan) -->
    <div
      v-if="!bolehPerpanjang"
      class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm"
    >
      <strong>Perpanjangan tidak dapat diajukan:</strong> {{ alasanTidakBoleh }}
    </div>

    <form v-else class="space-y-6" @submit.prevent="bukaKonfirmasi">
      <!-- Formulir tambahan hari -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5">
        <div class="pb-3 border-b border-slate-100">
          <h3 class="text-lg md:text-xl font-bold text-slate-900">Pilih Tambahan Hari</h3>
          <p class="text-xs md:text-sm text-slate-500">
            Batas kembali baru dihitung dari batas saat ini. Maksimal {{ maksHari }} hari per perpanjangan.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <FormField
            id="tambahan_hari"
            label="Tambahan Hari"
            wajib
            :bantuan="`1 sampai ${maksHari} hari`"
            :galat="errors.tambahan_hari?.[0]"
          >
            <InputJumlah
              id="tambahan_hari"
              v-model="tambahanHari"
              :min="1"
              :max="maksHari"
              :dinonaktifkan="sedangKirim"
            />
          </FormField>

          <FormField
            id="alasan"
            label="Alasan (Opsional)"
            bantuan="Maksimal 500 karakter."
            :galat="errors.alasan?.[0]"
          >
            <input
              id="alasan"
              v-model="alasan"
              type="text"
              maxlength="500"
              placeholder="Contoh: Proyek tunda satu minggu."
              :disabled="sedangKirim"
              class="w-full min-h-[48px] px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-base text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors"
            >
          </FormField>
        </div>

        <!-- Ringkasan batas lama dan baru (FR-K-07) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
            <span class="text-[11px] font-semibold text-slate-500 uppercase block mb-1">Batas Lama</span>
            <span class="font-bold text-slate-900 text-base">{{ formatTanggalIndo(batasLama) }}</span>
          </div>
          <div class="p-4 bg-blue-50 rounded-xl border border-blue-200">
            <span class="text-[11px] font-semibold text-blue-500 uppercase block mb-1">Batas Baru</span>
            <span class="font-bold text-blue-950 text-base">{{ formatTanggalIndo(batasBaru) }}</span>
          </div>
        </div>
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
          :dinonaktifkan="sedangKirim"
        >
          Ajukan Perpanjangan
        </TombolBesar>
      </div>
    </form>

    <!-- Modal Konfirmasi -->
    <ModalKonfirmasi
      :terbuka="modalTerbuka"
      judul="Konfirmasi Perpanjangan"
      :pesan="`Batas kembali digeser dari ${formatTanggalIndo(batasLama)} ke ${formatTanggalIndo(batasBaru)} (tambahan ${tambahanHari} hari). Lanjutkan?`"
      teks-batal="Periksa Lagi"
      teks-konfirmasi="Ya, Perpanjang"
      :memuat="sedangKirim"
      @update:terbuka="modalTerbuka = $event"
      @konfirmasi="kirimPerpanjangan"
    />
  </div>
</template>
