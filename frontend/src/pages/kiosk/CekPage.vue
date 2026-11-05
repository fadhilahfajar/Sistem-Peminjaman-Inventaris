<script setup>
import { useRouter } from 'vue-router'
import StatusKosong from '@/components/StatusKosong.vue'
import TombolBesar from '@/components/TombolBesar.vue'

const router = useRouter()
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-900">
        Cek Peminjaman & Pengembalian
      </h2>
      <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push('/')">
        &larr; Beranda
      </TombolBesar>
    </div>

    <StatusKosong
      judul="Cek Status & Klaim"
      pesan="Layar cek peminjaman dan kartu status akan diimplementasikan pada Task 4.7."
    >
      <TombolBesar varian="primer" ukuran="sedang" @click="router.push('/katalog')">
        Lihat Katalog Barang
      </TombolBesar>
    </StatusKosong>
  </div>
</template>
