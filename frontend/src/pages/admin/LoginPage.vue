<script setup>
import { ref } from 'vue'

const email = ref('')
const kataSandi = ref('')
</script>

<template>
  <section>
    <h2 class="text-xl font-semibold">Masuk admin</h2>
    <!-- Kerangka saja: pengiriman login dan penjaga rute dibuat pada Task 5.1. -->
    <div class="mt-4 space-y-3">
      <label class="block text-sm">
        Email
        <input v-model="email" type="email" autocomplete="username"
          class="mt-1 w-full rounded border border-slate-300 px-3 py-2" />
      </label>
      <label class="block text-sm">
        Kata sandi
        <input v-model="kataSandi" type="password" autocomplete="current-password"
          class="mt-1 w-full rounded border border-slate-300 px-3 py-2" />
      </label>
      <button type="button" disabled
        class="w-full rounded bg-slate-800 px-3 py-2 text-white disabled:opacity-50">
        Masuk
      </button>
    </div>
  </section>
</template>
