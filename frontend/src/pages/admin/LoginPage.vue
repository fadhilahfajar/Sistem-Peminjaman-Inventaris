<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authApi, initCsrf } from '@/api'
import FormField from '@/components/FormField.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useSesiStore } from '@/stores/sesi'

const route = useRoute()
const router = useRouter()
const sesi = useSesiStore()
const { errors, pesanGalat, memuat: sedangMasuk, tanganiGalat, bersihkan } = useGalat()

const username = ref('')
const password = ref('')

// UI-08: diarahkan dari sesi yang berakhir (401 interceptor/guard, pernah masuk).
const sesiBerakhir = ref(false)

onMounted(async () => {
  sesiBerakhir.value = route.query.sesi === 'berakhir'

  // Hapus parameter setelah dibaca agar muat ulang tidak menampilkannya lagi.
  if ('sesi' in route.query) {
    const { sesi: _sesi, ...sisa } = route.query
    await router.replace({ query: sisa })
  }
})

async function masuk() {
  if (sedangMasuk.value) return

  bersihkan()
  sedangMasuk.value = true

  try {
    // Pastikan cookie CSRF tersedia sebelum POST login.
    await initCsrf().catch(() => {})

    await authApi.login({
      username: username.value.trim(),
      password: password.value,
    })

    // Muat profil (paksa) agar guard melihat sesi aktif dan penanda "pernah masuk" terset.
    await sesi.muatProfil({ paksa: true })

    const redirect = route.query.redirect
    const tujuan = typeof redirect === 'string' && redirect.startsWith('/admin')
      ? redirect
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
      <p class="text-sm text-slate-600">Inventaris dan Peminjaman Barang Tim IT</p>
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
        id="username"
        label="Username"
        wajib
        :galat="errors.username?.[0]"
      >
        <input
          id="username"
          v-model="username"
          type="text"
          name="username"
          autocomplete="username"
          autocapitalize="off"
          spellcheck="false"
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
