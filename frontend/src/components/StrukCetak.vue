<script setup>
defineProps({
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
  catatan: {
    type: String,
    default: '',
  },
  waktuCetak: {
    type: String,
    default: '',
  },
})

function cetak() {
  window.print()
}
</script>

<template>
  <div class="struk-cetak-wrapper w-full max-w-lg mx-auto bg-white border border-slate-200 rounded-2xl p-6 shadow-sm print:border-none print:shadow-none print:p-0 print:max-w-none">
    <!-- Tombol Cetak di layar, sembunyikan saat cetak -->
    <div class="print:hidden flex justify-end mb-4">
      <button
        type="button"
        class="min-h-[44px] px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl flex items-center gap-2 cursor-pointer transition-colors focus-visible:outline-2 focus-visible:outline-slate-800"
        @click="cetak"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        Cetak Struk
      </button>
    </div>

    <!-- Area Struk Cetak -->
    <div class="border-t-4 border-slate-900 pt-4 font-mono text-slate-900 print:text-black">
      <div class="text-center pb-4 border-b border-dashed border-slate-300">
        <h2 class="text-base font-bold tracking-wider uppercase">
          {{ judul }}
        </h2>
        <p class="text-xs text-slate-500 print:text-black mt-0.5">
          Sistem Peminjaman & Inventaris IT
        </p>
      </div>

      <!-- Kode Transaksi Besar -->
      <div class="py-4 text-center border-b border-dashed border-slate-300 bg-slate-50 print:bg-transparent rounded-lg my-3">
        <span class="text-xs text-slate-500 uppercase tracking-widest block">Kode Peminjaman</span>
        <span class="text-2xl font-extrabold tracking-widest text-slate-900">{{ kode }}</span>
      </div>

      <!-- Detail Peminjam -->
      <div class="text-xs space-y-1.5 py-3 border-b border-dashed border-slate-300">
        <div v-if="namaPeminjam" class="flex justify-between">
          <span class="text-slate-500">Peminjam:</span>
          <span class="font-bold text-right">{{ namaPeminjam }}</span>
        </div>
        <div v-if="divisi" class="flex justify-between">
          <span class="text-slate-500">Divisi:</span>
          <span class="font-bold text-right">{{ divisi }}</span>
        </div>
        <div v-if="tipePeminjam" class="flex justify-between">
          <span class="text-slate-500">Tipe:</span>
          <span class="font-bold text-right capitalize">{{ tipePeminjam }}</span>
        </div>
        <div v-if="tanggalPinjam" class="flex justify-between">
          <span class="text-slate-500">Tgl Pinjam:</span>
          <span class="font-bold text-right">{{ tanggalPinjam }}</span>
        </div>
        <div v-if="batasKembali" class="flex justify-between">
          <span class="text-slate-500">Batas Kembali:</span>
          <span class="font-bold text-right">{{ batasKembali }}</span>
        </div>
      </div>

      <!-- Daftar Barang -->
      <div v-if="items && items.length > 0" class="py-3 border-b border-dashed border-slate-300">
        <div class="text-xs font-bold uppercase tracking-wider mb-2 text-slate-600">
          Daftar Barang
        </div>
        <div class="space-y-1.5 text-xs">
          <div
            v-for="(item, idx) in items"
            :key="idx"
            class="flex justify-between items-center"
          >
            <span class="truncate pr-2">{{ item.nama || item.item_nama || `Barang #${item.item_id}` }}</span>
            <span class="font-bold shrink-0">{{ item.qty || item.qty_dipinjam }} unit</span>
          </div>
        </div>
      </div>

      <!-- Catatan bila ada -->
      <div v-if="catatan" class="py-2.5 text-xs border-b border-dashed border-slate-300">
        <span class="text-slate-500 block mb-0.5">Catatan:</span>
        <p class="italic text-slate-700">{{ catatan }}</p>
      </div>

      <!-- Footer Struk -->
      <div class="pt-4 text-center text-[10px] text-slate-500 space-y-1">
        <p>Simpan kode untuk pengecekan, perpanjangan, atau pengembalian barang.</p>
        <p v-if="waktuCetak">Dicetak pada: {{ waktuCetak }}</p>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
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
    width: 100%;
  }
}
</style>
