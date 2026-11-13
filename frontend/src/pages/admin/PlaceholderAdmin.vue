<script setup>
import { useRoute } from 'vue-router'
import StatusKosong from '@/components/StatusKosong.vue'

// Pengganti sementara untuk halaman admin Fase 5 yang belum dibangun (5.5–5.10).
const route = useRoute()
</script>

<template>
  <StatusKosong
    judul="Halaman belum dibangun"
    :pesan="`Layar ${route.meta.judul} akan dibuat pada task Fase 5 berikutnya.`"
  />
</template>
