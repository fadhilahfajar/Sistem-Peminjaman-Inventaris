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
        Form Peminjaman Barang
      </h2>
      <TombolBesar varian="sekunder" ukuran="sedang" @click="router.push('/')">
        &larr; Beranda
      </TombolBesar>
    </div>

    <StatusKosong
      judul="Layanan Peminjaman"
      pesan="Form peminjaman dan keranjang barang sedang dalam tahap pembangunan (Task 4.5)."
    >
      <TombolBesar varian="primer" ukuran="sedang" @click="router.push('/katalog')">
        Lihat Katalog Barang
      </TombolBesar>
    </StatusKosong>
  </div>
</template>
