<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  judul: {
    type: String,
    default: 'BUKTI PEMINJAMAN INVENTARIS',
  },
  kode: {
    type: String,
    required: true,
  },
  namaPeminjam: {
    type: String,
    default: '',
  },
  divisi: {
    type: String,
    default: '',
  },
  tipePeminjam: {
    type: String,
    default: '',
  },
  tanggalPinjam: {
    type: String,
    default: '',
  },
  batasKembali: {
    type: String,
    default: '',
  },
  items: {
    type: Array,
    default: () => [],
  },
  pemesanan: {
    type: Boolean,
    default: false,
  },
  catatan: {
    type: String,
    default: '',
  },
  waktuCetak: {
    type: String,
    default: '',
  },
  petunjukKembali: {
    type: String,
    default: '',
  },
})

const baruMenyalin = ref(false)

const teksPetunjuk = computed(() => {
  if (props.petunjukKembali) return props.petunjukKembali
  if (props.pemesanan) {
    return '1. Tunjukkan kode ini ke petugas IT pada tanggal mulai pemesanan untuk mengambil barang.\n2. Ajukan klaim di menu Kiosk "Cek atau Kembalikan" sebelum menyerahkan barang kembali ke petugas.'
  }
  return '1. Bawa dan serahkan barang inventaris langsung ke petugas IT sebelum atau pada batas waktu kembali.\n2. Ajukan klaim mandiri di menu "Cek atau Kembalikan" pada kiosk untuk mencatat penyerahan barang.'
})

async function salinKode() {
  try {
    await navigator.clipboard.writeText(props.kode)
    baruMenyalin.value = true
    setTimeout(() => {
      baruMenyalin.value = false
    }, 2000)
  } catch {
    // Clipboard API tidak didukung atau dicegah
  }
}

function cetak() {
  window.print()
}
</script>

<template>
  <div class="struk-cetak-wrapper w-full max-w-lg mx-auto bg-white border border-slate-200 rounded-2xl p-6 shadow-sm print:border-none print:shadow-none print:p-0 print:max-w-none">
    <!-- Tombol Aksi di Layar (Sembunyi saat cetak) -->
    <div class="print:hidden flex items-center justify-end gap-2 mb-4">
      <button
        type="button"
        class="min-h-[44px] px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs md:text-sm font-semibold rounded-xl flex items-center gap-1.5 cursor-pointer transition-colors active:scale-95"
        @click="salinKode"
      >
        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        <span>{{ baruMenyalin ? 'Kode Tersalin!' : 'Salin Kode' }}</span>
      </button>

      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-slate-900 hover:bg-black text-white text-xs md:text-sm font-semibold rounded-xl flex items-center gap-2 cursor-pointer transition-colors shadow-xs active:scale-95 focus-visible:outline-2 focus-visible:outline-slate-900"
        @click="cetak"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        <span>Cetak Struk</span>
      </button>
    </div>

    <!-- Area Struk Cetak Fisik -->
    <div class="struk-kertas border-t-4 border-slate-900 pt-4 font-mono text-slate-900 print:text-black">
      <!-- Kop Struk -->
      <div class="text-center pb-4 border-b border-dashed border-slate-300 print:border-black">
        <h2 class="text-base font-extrabold tracking-wider uppercase">
          {{ judul }}
        </h2>
        <p class="text-xs text-slate-500 print:text-black mt-0.5">
          TIM IT INVENTARIS
        </p>
        <p class="text-[10px] text-slate-400 print:text-black mt-0.5">
          Sistem Peminjaman Mandiri (Kiosk)
        </p>
      </div>

      <!-- Kode Transaksi Utama (Besar & Tegas) -->
      <div class="py-4 text-center border-b border-dashed border-slate-300 print:border-black bg-slate-50 print:bg-transparent rounded-lg my-3">
        <span class="text-[11px] text-slate-500 print:text-black font-semibold uppercase tracking-widest block">
          {{ pemesanan ? 'KODE PEMESANAN' : 'KODE PEMINJAMAN' }}
        </span>
        <span class="text-3xl md:text-4xl font-extrabold tracking-widest text-slate-900 print:text-black block my-1">
          {{ kode }}
        </span>
        <span class="text-[10px] text-slate-500 print:text-black block">
          Simpan & foto kode ini untuk pengambilan / pengembalian
        </span>
      </div>

      <!-- Detail Peminjam -->
      <div class="text-xs space-y-1.5 py-3 border-b border-dashed border-slate-300 print:border-black">
        <div v-if="namaPeminjam" class="flex justify-between items-start gap-2">
          <span class="text-slate-500 print:text-black shrink-0">Peminjam:</span>
          <span class="font-bold text-right break-words">{{ namaPeminjam }}</span>
        </div>
        <div v-if="divisi" class="flex justify-between items-start gap-2">
          <span class="text-slate-500 print:text-black shrink-0">Divisi:</span>
          <span class="font-bold text-right">{{ divisi }}</span>
        </div>
        <div v-if="tipePeminjam" class="flex justify-between items-start gap-2">
          <span class="text-slate-500 print:text-black shrink-0">Tipe:</span>
          <span class="font-bold text-right capitalize">{{ tipePeminjam }}</span>
        </div>

        <!-- Tanggal Pinjam dengan Penanda PEMESANAN bila masa depan (FR-K-03) -->
        <div v-if="tanggalPinjam" class="flex justify-between items-start gap-2">
          <span class="text-slate-500 print:text-black shrink-0">Tgl Mulai:</span>
          <span class="font-bold text-right">
            {{ tanggalPinjam }}
            <span
              v-if="pemesanan"
              class="ml-1 inline-block px-1.5 py-0.2 bg-purple-100 text-purple-800 print:bg-transparent print:text-black print:border print:border-black rounded text-[10px] font-extrabold"
            >
              [PEMESANAN]
            </span>
          </span>
        </div>

        <div v-if="batasKembali" class="flex justify-between items-start gap-2">
          <span class="text-slate-500 print:text-black shrink-0">Batas Kembali:</span>
          <span class="font-bold text-right">{{ batasKembali }}</span>
        </div>
      </div>

      <!-- Daftar Barang -->
      <div v-if="items && items.length > 0" class="py-3 border-b border-dashed border-slate-300 print:border-black">
        <div class="text-xs font-bold uppercase tracking-wider mb-2 text-slate-700 print:text-black flex justify-between">
          <span>Barang</span>
          <span>Jumlah</span>
        </div>
        <div class="space-y-1.5 text-xs">
          <div
            v-for="(item, idx) in items"
            :key="idx"
            class="flex justify-between items-baseline gap-2"
          >
            <span class="truncate pr-2">{{ item.nama || item.item_nama || `Barang #${item.item_id}` }}</span>
            <span class="font-bold shrink-0">{{ item.qty || item.qty_dipinjam }} unit</span>
          </div>
        </div>
      </div>

      <!-- Catatan bila ada -->
      <div v-if="catatan" class="py-2.5 text-xs border-b border-dashed border-slate-300 print:border-black">
        <span class="text-slate-500 print:text-black block mb-0.5 font-semibold">Catatan:</span>
        <p class="italic text-slate-800 print:text-black whitespace-pre-line">{{ catatan }}</p>
      </div>

      <!-- Petunjuk Mengembalikan (FR-K-03) -->
      <div class="py-3 border-b border-dashed border-slate-300 print:border-black text-xs space-y-1">
        <span class="font-bold uppercase tracking-wider text-slate-700 print:text-black block">
          Petunjuk Pengembalian:
        </span>
        <p class="text-slate-600 print:text-black text-[11px] leading-relaxed whitespace-pre-line">
          {{ teksPetunjuk }}
        </p>
      </div>

      <!-- Footer Struk & Waktu Cetak -->
      <div class="pt-3 text-center text-[10px] text-slate-500 print:text-black space-y-1">
        <p class="font-semibold">Simpan bukti ini sebagai tanda terima sah.</p>
        <p v-if="waktuCetak">Dicetak: {{ waktuCetak }}</p>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  /* Sembunyikan elemen latar belakang, navbar, layout luar sesuai T-39 & UI-06 */
  html, body {
    background: #fff !important;
    color: #000 !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  body * {
    visibility: hidden;
  }

  .struk-cetak-wrapper,
  .struk-cetak-wrapper * {
    visibility: visible;
  }

  .struk-cetak-wrapper {
    position: absolute;
    left: 0;
    top: 0;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 8mm !important;
    border: none !important;
    box-shadow: none !important;
  }

  .struk-kertas {
    border-top-width: 2px !important;
    border-color: #000 !important;
  }
}
</style>
