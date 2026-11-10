<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { kioskApi } from '@/api'
import FormField from '@/components/FormField.vue'
import InputJumlah from '@/components/InputJumlah.vue'
import Memuat from '@/components/Memuat.vue'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import StatusKosong from '@/components/StatusKosong.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useKioskStore } from '@/stores/kiosk'
import { hariIniYmd, tambahHariYmd } from '@/utils/tanggal'

const route = useRoute()
const router = useRouter()
const kioskStore = useKioskStore()
const { errors, pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()

// Kunci Idempotensi UUID dibuat saat formulir dibuka (DESAIN 10.6, FR-K-02)
const idempotencyKey = ref(crypto.randomUUID())

// Data konfigurasi & referensi
const sedangMemuatAwal = ref(true)
const daftarDivisi = ref([])
const daftarBarang = ref([])
const maksDurasiAwal = ref(7)
const maksBookingHari = ref(7)

// Modal pemilih barang
const modalPilihBarangTerbuka = ref(false)
const cariBarang = ref('')

// Keranjang barang: list of { item_id, nama, tersedia, qty }
const keranjang = ref([])

// Isian formulir
const form = ref({
  nama_peminjam: '',
  division_id: '',
  tipe_peminjam: 'karyawan',
  telepon: '',
  tanggal_pinjam: hariIniYmd(),
  batas_kembali: tambahHariYmd(hariIniYmd(), 7),
})

const hariIni = computed(() => hariIniYmd())
const tanggalPinjamMaks = computed(() => tambahHariYmd(hariIni.value, maksBookingHari.value))
const batasKembaliMaks = computed(() =>
  tambahHariYmd(form.value.tanggal_pinjam || hariIni.value, maksDurasiAwal.value),
)

const apakahPemesanan = computed(() => {
  return form.value.tanggal_pinjam && form.value.tanggal_pinjam > hariIni.value
})

const totalUnitKeranjang = computed(() => {
  return keranjang.value.reduce((total, item) => total + (Number(item.qty) || 0), 0)
})

// Barang yang dapat dipilih ke keranjang (stok > 0 dan belum ada di keranjang)
const barangTersediaUntukDipilih = computed(() => {
  const idsDiKeranjang = new Set(keranjang.value.map((k) => k.item_id))
  const q = cariBarang.value.trim().toLowerCase()

  return daftarBarang.value.filter((b) => {
    if (b.tersedia <= 0) return false
    if (idsDiKeranjang.has(b.id)) return false
    if (!q) return true
    return b.nama.toLowerCase().includes(q)
  })
})

// Sinkronkan batas_kembali saat tanggal_pinjam berubah
watch(
  () => form.value.tanggal_pinjam,
  (tglBaru) => {
    if (!tglBaru) return
    if (form.value.batas_kembali < tglBaru) {
      form.value.batas_kembali = tglBaru
    } else {
      const maks = tambahHariYmd(tglBaru, maksDurasiAwal.value)
      if (form.value.batas_kembali > maks) {
        form.value.batas_kembali = maks
      }
    }
  },
)

async function muatDataAwal() {
  sedangMemuatAwal.value = true
  bersihkan()

  try {
    const [resConfig, resDivisi, resBarang] = await Promise.all([
      kioskApi.getConfig().catch(() => ({ data: {} })),
      kioskApi.getDivisions(),
      kioskApi.getItems(),
    ])

    if (resConfig.data?.data) {
      const cfg = resConfig.data.data
      maksDurasiAwal.value = Number(cfg.maks_durasi_awal_hari) || 7
      maksBookingHari.value = Number(cfg.maks_booking_hari) || 7
    }

    form.value.batas_kembali = tambahHariYmd(form.value.tanggal_pinjam, maksDurasiAwal.value)
    daftarDivisi.value = resDivisi.data?.data || []
    daftarBarang.value = resBarang.data?.data || []

    // Pre-fill barang dari query parameter ?item_id=X (misal dari katalog)
    const itemIdParam = Number(route.query.item_id)
    if (itemIdParam) {
      const barangTarget = daftarBarang.value.find((b) => b.id === itemIdParam)
      if (barangTarget && barangTarget.tersedia > 0) {
        tambahKeKeranjang(barangTarget)
      }
    }
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMemuatAwal.value = false
  }
}

function tambahKeKeranjang(barang) {
  const ada = keranjang.value.find((k) => k.item_id === barang.id)
  if (!ada) {
    keranjang.value.push({
      item_id: barang.id,
      nama: barang.nama,
      tersedia: barang.tersedia,
      qty: 1,
    })
  }
}

function hapusDariKeranjang(itemId) {
  keranjang.value = keranjang.value.filter((k) => k.item_id !== itemId)
}

function bukaPemilihBarang() {
  cariBarang.value = ''
  modalPilihBarangTerbuka.value = true
}

function pilihDanTutup(barang) {
  tambahKeKeranjang(barang)
  modalPilihBarangTerbuka.value = false
}

// Validasi formulir sisi klien sebelum mengirim
function validasiKlien() {
  bersihkan()
  const galatKlien = {}

  if (keranjang.value.length === 0) {
    galatKlien.items = ['Pilih minimal satu barang untuk dipinjam.']
  }

  for (const [idx, item] of keranjang.value.entries()) {
    if (!item.qty || item.qty < 1) {
      galatKlien[`items.${idx}.qty`] = ['Jumlah barang harus minimal 1 unit.']
    } else if (item.qty > item.tersedia) {
      galatKlien[`items.${idx}.qty`] = [`Jumlah melebihi stok yang tersedia (${item.tersedia} unit).`]
    }
  }

  const nama = form.value.nama_peminjam.trim()
  if (!nama) {
    galatKlien.nama_peminjam = ['Nama peminjam wajib diisi.']
  } else if (nama.length < 2) {
    galatKlien.nama_peminjam = ['Nama peminjam minimal 2 karakter.']
  } else if (nama.length > 100) {
    galatKlien.nama_peminjam = ['Nama peminjam maksimal 100 karakter.']
  }

  if (!form.value.division_id) {
    galatKlien.division_id = ['Divisi wajib dipilih.']
  }

  if (!['karyawan', 'magang'].includes(form.value.tipe_peminjam)) {
    galatKlien.tipe_peminjam = ['Tipe peminjam wajib dipilih (karyawan atau magang).']
  }

  const tlp = (form.value.telepon || '').trim()
  if (tlp && !/^[0-9]{8,15}$/.test(tlp)) {
    galatKlien.telepon = ['Nomor telepon harus berupa 8 sampai 15 digit angka.']
  }

  if (!form.value.tanggal_pinjam) {
    galatKlien.tanggal_pinjam = ['Tanggal pinjam wajib diisi.']
  } else if (form.value.tanggal_pinjam < hariIni.value) {
    galatKlien.tanggal_pinjam = ['Tanggal pinjam tidak boleh di masa lalu.']
  } else if (form.value.tanggal_pinjam > tanggalPinjamMaks.value) {
    galatKlien.tanggal_pinjam = [`Tanggal pinjam paling jauh ${maksBookingHari.value} hari dari hari ini.`]
  }

  if (!form.value.batas_kembali) {
    galatKlien.batas_kembali = ['Batas kembali wajib diisi.']
  } else if (form.value.batas_kembali < form.value.tanggal_pinjam) {
    galatKlien.batas_kembali = ['Batas kembali tidak boleh sebelum tanggal pinjam.']
  } else if (form.value.batas_kembali > batasKembaliMaks.value) {
    galatKlien.batas_kembali = [`Batas kembali maksimal ${maksDurasiAwal.value} hari dari tanggal pinjam.`]
  }

  if (Object.keys(galatKlien).length > 0) {
    errors.value = galatKlien
    pesanGalat.value = 'Mohon periksa kembali isian formulir yang ditandai merah.'
    return false
  }

  return true
}

async function kirimForm() {
  if (sedangKirim.value) return
  if (!validasiKlien()) return

  sedangKirim.value = true

  const payload = {
    nama_peminjam: form.value.nama_peminjam.trim(),
    division_id: Number(form.value.division_id),
    tipe_peminjam: form.value.tipe_peminjam,
    telepon: form.value.telepon.trim() || null,
    tanggal_pinjam: form.value.tanggal_pinjam,
    batas_kembali: form.value.batas_kembali,
    items: keranjang.value.map((k) => ({
      item_id: k.item_id,
      qty: Number(k.qty),
    })),
    idempotency_key: idempotencyKey.value,
  }

  try {
    const res = await kioskApi.buatLoan(payload)
    const dataHasil = res.data?.data

    // Simpan hasil ke store kiosk (dalam memori)
    kioskStore.setPinjamSelesai(dataHasil)

    // Arahkan ke halaman selesai
    await router.push('/pinjam/selesai')
  } catch (err) {
    tanganiGalat(err)

    // Jika terjadi konflik stok (409), muat ulang daftar barang agar stok terbaru tampil
    if (err.response?.status === 409) {
      try {
        const resBarang = await kioskApi.getItems()
        daftarBarang.value = resBarang.data?.data || []
        // Perbarui ketersediaan di keranjang
        for (const item of keranjang.value) {
          const fresh = daftarBarang.value.find((b) => b.id === item.item_id)
          if (fresh) {
            item.tersedia = fresh.tersedia
          }
        }
      } catch {
        // Abaikan kegagalan refresh
      }
    }
  } finally {
    sedangKirim.value = false
  }
}

onMounted(() => {
  muatDataAwal()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
          Formulir Peminjaman Barang
        </h2>
        <p class="text-sm md:text-base text-slate-600 mt-0.5">
          Isi keranjang barang dan identitas peminjam secara mandiri.
        </p>
      </div>

      <TombolBesar
        varian="sekunder"
        ukuran="sedang"
        class="self-start sm:self-auto"
        :dinonaktifkan="sedangKirim"
        @click="router.push('/')"
      >
        &larr; Beranda
      </TombolBesar>
    </div>

    <!-- Status Memuat Data Awal -->
    <div v-if="sedangMemuatAwal" class="py-12">
      <Memuat tipe="skeleton" :baris="6" teks="Menyiapkan formulir peminjaman..." />
    </div>

    <!-- Formulir Utama -->
    <form v-else class="space-y-8" @submit.prevent="kirimForm">
      <!-- Pesan Peringatan Galat Umum -->
      <PesanGalat
        v-if="pesanGalat"
        judul="Perhatian"
        :pesan="pesanGalat"
      />

      <!-- Bagian 1: Keranjang Barang -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-lg md:text-xl font-bold text-slate-900">
              1. Keranjang Barang Inventaris
            </h3>
            <p class="text-xs md:text-sm text-slate-500">
              Pilih perangkat dan tentukan jumlah unit yang dibutuhkan.
            </p>
          </div>

          <TombolBesar
            varian="primer"
            ukuran="sedang"
            class="self-start sm:self-auto"
            :dinonaktifkan="sedangKirim"
            @click="bukaPemilihBarang"
          >
            + Tambah Barang
          </TombolBesar>
        </div>

        <!-- Daftar Barang di Keranjang -->
        <div v-if="keranjang.length > 0" class="divide-y divide-slate-100">
          <div
            v-for="(item, idx) in keranjang"
            :key="item.item_id"
            class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
          >
            <div class="space-y-1">
              <h4 class="font-bold text-slate-900 text-base md:text-lg">
                {{ item.nama }}
              </h4>
              <p class="text-xs text-slate-500">
                Stok tersedia: <strong>{{ item.tersedia }}</strong> unit
              </p>
              <p
                v-if="errors[`items.${idx}.qty`]"
                class="text-xs font-semibold text-red-600"
              >
                {{ errors[`items.${idx}.qty`][0] }}
              </p>
            </div>

            <!-- Stepper Jumlah & Tombol Hapus -->
            <div class="flex items-center gap-3 self-end sm:self-auto">
              <InputJumlah
                v-model="item.qty"
                :min="1"
                :max="item.tersedia"
                :dinonaktifkan="sedangKirim"
              />

              <button
                type="button"
                :disabled="sedangKirim"
                class="min-h-[44px] min-w-[44px] flex items-center justify-center text-red-600 hover:text-red-800 hover:bg-red-50 rounded-xl transition-colors cursor-pointer disabled:opacity-50"
                aria-label="Hapus barang dari keranjang"
                @click="hapusDariKeranjang(item.item_id)"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>

          <div class="pt-3 flex justify-between items-center text-sm font-semibold text-slate-700">
            <span>Total Barang: {{ keranjang.length }} jenis</span>
            <span class="text-base text-slate-900 font-bold">Total: {{ totalUnitKeranjang }} unit</span>
          </div>
        </div>

        <!-- Keranjang Kosong -->
        <div v-else class="py-6">
          <StatusKosong
            judul="Keranjang Masih Kosong"
            pesan="Belum ada barang inventaris yang dipilih. Klik tombol Tambah Barang di bawah untuk memilih."
          >
            <TombolBesar
              varian="primer"
              ukuran="sedang"
              :dinonaktifkan="sedangKirim"
              @click="bukaPemilihBarang"
            >
              Pilih Barang dari Katalog
            </TombolBesar>
          </StatusKosong>
          <p v-if="errors.items" class="text-xs font-semibold text-red-600 text-center mt-2">
            {{ errors.items[0] }}
          </p>
        </div>
      </section>

      <!-- Bagian 2: Data Diri Peminjam -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5">
        <div class="pb-3 border-b border-slate-100">
          <h3 class="text-lg md:text-xl font-bold text-slate-900">
            2. Identitas Peminjam
          </h3>
          <p class="text-xs md:text-sm text-slate-500">
            Data peminjam yang bertanggung jawab atas perangkat inventaris.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Nama Lengkap -->
          <FormField
            id="nama_peminjam"
            label="Nama Lengkap"
            wajib
            :galat="errors.nama_peminjam?.[0]"
          >
            <input
              id="nama_peminjam"
              v-model="form.nama_peminjam"
              type="text"
              maxlength="100"
              placeholder="Contoh: Budi Santoso"
              :disabled="sedangKirim"
              class="w-full min-h-[48px] px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-base text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors"
            >
          </FormField>

          <!-- Divisi -->
          <FormField
            id="division_id"
            label="Divisi"
            wajib
            :galat="errors.division_id?.[0]"
          >
            <select
              id="division_id"
              v-model="form.division_id"
              :disabled="sedangKirim"
              class="w-full min-h-[48px] px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-base text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors cursor-pointer"
            >
              <option value="" disabled>
                -- Pilih Divisi --
              </option>
              <option
                v-for="div in daftarDivisi"
                :key="div.id"
                :value="div.id"
              >
                {{ div.nama }}
              </option>
            </select>
          </FormField>

          <!-- Tipe Peminjam -->
          <FormField
            label="Tipe Peminjam"
            wajib
            :galat="errors.tipe_peminjam?.[0]"
          >
            <div class="grid grid-cols-2 gap-3 pt-1">
              <label
                :class="[
                  'min-h-[48px] px-4 py-2 flex items-center justify-center gap-2 rounded-xl border-2 font-semibold text-sm cursor-pointer select-none transition-all',
                  form.tipe_peminjam === 'karyawan'
                    ? 'border-[var(--color-aksen,#1d4ed8)] bg-blue-50 text-[var(--color-aksen,#1d4ed8)]'
                    : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100',
                  sedangKirim && 'opacity-50 cursor-not-allowed',
                ]"
              >
                <input
                  v-model="form.tipe_peminjam"
                  type="radio"
                  value="karyawan"
                  :disabled="sedangKirim"
                  class="sr-only"
                >
                <span>Karyawan</span>
              </label>

              <label
                :class="[
                  'min-h-[48px] px-4 py-2 flex items-center justify-center gap-2 rounded-xl border-2 font-semibold text-sm cursor-pointer select-none transition-all',
                  form.tipe_peminjam === 'magang'
                    ? 'border-[var(--color-aksen,#1d4ed8)] bg-blue-50 text-[var(--color-aksen,#1d4ed8)]'
                    : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100',
                  sedangKirim && 'opacity-50 cursor-not-allowed',
                ]"
              >
                <input
                  v-model="form.tipe_peminjam"
                  type="radio"
                  value="magang"
                  :disabled="sedangKirim"
                  class="sr-only"
                >
                <span>Magang</span>
              </label>
            </div>
          </FormField>

          <!-- Nomor Telepon -->
          <FormField
            id="telepon"
            label="Nomor Telepon (WhatsApp)"
            bantuan="Opsional. 8 sampai 15 digit angka (contoh: 081234567890)"
            :galat="errors.telepon?.[0]"
          >
            <input
              id="telepon"
              v-model="form.telepon"
              type="tel"
              maxlength="15"
              placeholder="08xxxxxxxxxx"
              :disabled="sedangKirim"
              class="w-full min-h-[48px] px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-base text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors"
            >
          </FormField>
        </div>
      </section>

      <!-- Bagian 3: Jadwal Peminjaman / Pemesanan -->
      <section class="bg-white p-5 md:p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5">
        <div class="pb-3 border-b border-slate-100">
          <h3 class="text-lg md:text-xl font-bold text-slate-900">
            3. Jadwal Waktu
          </h3>
          <p class="text-xs md:text-sm text-slate-500">
            Tentukan tanggal mulai dan batas waktu pengembalian barang.
          </p>
        </div>

        <!-- Banner Pemberitahuan Pemesanan (Booking) -->
        <div
          v-if="apakahPemesanan"
          class="p-4 rounded-xl bg-purple-50 border border-purple-200 text-purple-900 text-sm flex items-start gap-3"
        >
          <svg class="w-5 h-5 text-purple-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <div>
            <strong class="font-bold">Mode Pemesanan (Booking) Terdeteksi:</strong>
            <p class="mt-0.5 text-purple-800">
              Tanggal pinjam di masa depan ({{ form.tanggal_pinjam }}). Stok barang akan langsung ditahan untuk Anda sejak permohonan ini dibuat.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Tanggal Pinjam -->
          <FormField
            id="tanggal_pinjam"
            label="Tanggal Pinjam"
            wajib
            :bantuan="`Bisa hari ini atau pesan hingga ${maksBookingHari} hari ke depan.`"
            :galat="errors.tanggal_pinjam?.[0]"
          >
            <input
              id="tanggal_pinjam"
              v-model="form.tanggal_pinjam"
              type="date"
              :min="hariIni"
              :max="tanggalPinjamMaks"
              :disabled="sedangKirim"
              class="w-full min-h-[48px] px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-base text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors cursor-pointer"
            >
          </FormField>

          <!-- Batas Kembali -->
          <FormField
            id="batas_kembali"
            label="Batas Kembali"
            wajib
            :bantuan="`Maksimal durasi awal ${maksDurasiAwal} hari dari tanggal pinjam.`"
            :galat="errors.batas_kembali?.[0]"
          >
            <input
              id="batas_kembali"
              v-model="form.batas_kembali"
              type="date"
              :min="form.tanggal_pinjam || hariIni"
              :max="batasKembaliMaks"
              :disabled="sedangKirim"
              class="w-full min-h-[48px] px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-base text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors cursor-pointer"
            >
          </FormField>
        </div>
      </section>

      <!-- Tombol Aksi Pengiriman (UI-02: Nonaktif saat proses) -->
      <div class="pt-4 flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
        <TombolBesar
          varian="sekunder"
          ukuran="besar"
          class="w-full sm:w-auto"
          :dinonaktifkan="sedangKirim"
          @click="router.push('/')"
        >
          Batalkan & Keluar
        </TombolBesar>

        <TombolBesar
          tipe="submit"
          varian="primer"
          ukuran="besar"
          class="w-full sm:w-auto sm:min-w-[280px]"
          :memuat="sedangKirim"
          :dinonaktifkan="sedangKirim || keranjang.length === 0"
        >
          {{ apakahPemesanan ? 'Ajukan Pemesanan' : 'Ajukan Peminjaman' }}
        </TombolBesar>
      </div>
    </form>

    <!-- Modal Pemilih Barang -->
    <ModalKonfirmasi
      :terbuka="modalPilihBarangTerbuka"
      judul="Pilih Barang Inventaris"
      teks-batal="Tutup"
      teks-konfirmasi=""
      @batal="modalPilihBarangTerbuka = false"
      @update:terbuka="modalPilihBarangTerbuka = $event"
    >
      <div class="space-y-4 py-2">
        <!-- Input Pencarian Cepat di Modal -->
        <input
          v-model="cariBarang"
          type="text"
          placeholder="Ketik nama barang..."
          class="w-full min-h-[44px] px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
        >

        <!-- Daftar Barang -->
        <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 pr-1">
          <div
            v-for="b in barangTersediaUntukDipilih"
            :key="b.id"
            class="py-3 flex items-center justify-between gap-3"
          >
            <div>
              <p class="font-bold text-slate-900 text-sm">
                {{ b.nama }}
              </p>
              <span class="text-xs font-semibold text-emerald-600">
                Tersedia {{ b.tersedia }} unit
              </span>
            </div>

            <TombolBesar
              varian="primer"
              ukuran="sedang"
              class="shrink-0"
              @click="pilihDanTutup(b)"
            >
              + Pilih
            </TombolBesar>
          </div>

          <p
            v-if="barangTersediaUntukDipilih.length === 0"
            class="text-xs text-slate-500 text-center py-6"
          >
            Tidak ada barang lain yang tersedia untuk dipilih.
          </p>
        </div>
      </div>
    </ModalKonfirmasi>
  </div>
</template>
