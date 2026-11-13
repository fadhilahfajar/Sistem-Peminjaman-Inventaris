<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api'
import FormField from '@/components/FormField.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useSesiStore } from '@/stores/sesi'

const route = useRoute()
const router = useRouter()
const sesi = useSesiStore()
const { errors, pesanGalat, memuat: sedangMasuk, tanganiGalat, bersihkan } = useGalat()

const email = ref('')
const password = ref('')

// UI-08: diarahkan dari sesi yang berakhir (401/419 interceptor atau guard).
const sesiBerakhir = ref(false)

onMounted(() => {
  sesiBerakhir.value = route.query.sesi === 'berakhir'
})

async function masuk() {
  if (sedangMasuk.value) return

  bersihkan()
  sedangMasuk.value = true

  try {
    await authApi.login({
      email: email.value.trim(),
      password: password.value,
    })

    const tujuan = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/admin')
      ? route.query.redirect
      : { name: 'admin.dasbor' }

    await router.replace(tujuan)
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMasuk.value = false
  }
}
</script>

<template>
  <section class="space-y-5">
    <div class="space-y-1 text-center">
      <h2 class="text-xl font-bold text-slate-900">Masuk Panel Admin</h2>
      <p class="text-sm text-slate-500">Inventaris dan Peminjaman Barang Tim IT</p>
    </div>

    <!-- UI-08: pesan sesi berakhir -->
    <div
      v-if="sesiBerakhir"
      class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm"
      role="alert"
    >
      Sesi berakhir, silakan masuk lagi.
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <form class="space-y-4" @submit.prevent="masuk">
      <FormField
        id="email"
        label="Email"
        wajib
        :galat="errors.email?.[0]"
      >
        <input
          id="email"
          v-model="email"
          type="email"
          name="email"
          autocomplete="username"
          required
          :disabled="sedangMasuk"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors"
        >
      </FormField>

      <FormField
        id="password"
        label="Password"
        wajib
        :galat="errors.password?.[0]"
      >
        <input
          id="password"
          v-model="password"
          type="password"
          name="password"
          autocomplete="current-password"
          required
          :disabled="sedangMasuk"
          class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)] focus:border-transparent transition-colors"
        >
      </FormField>

      <TombolBesar
        tipe="submit"
        varian="primer"
        ukuran="sedang"
        lebar-penuh
        :memuat="sedangMasuk"
        :dinonaktifkan="sedangMasuk"
      >
        Masuk
      </TombolBesar>
    </form>

    <p class="text-center text-xs text-slate-400">
      Panel admin hanya untuk petugas IT.
    </p>
  </section>
</template>
