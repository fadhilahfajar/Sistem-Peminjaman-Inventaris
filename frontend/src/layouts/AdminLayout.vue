<script setup>
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
</script>

<template>
  <div class="min-h-screen bg-slate-100 text-slate-900 md:flex">
    <aside
      class="bg-slate-800 p-4 text-slate-100 md:block md:w-56"
      :class="ui.sidebarTerbuka ? 'block' : 'hidden'"
    >
      <p class="font-semibold">Panel Admin</p>
      <nav class="mt-4 text-sm">
        <RouterLink :to="{ name: 'admin.dasbor' }" class="block rounded px-2 py-1 hover:bg-slate-700">
          Dasbor
        </RouterLink>
      </nav>
    </aside>
    <div class="flex-1">
      <header class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
        <button
          type="button"
          class="rounded border border-slate-300 px-2 py-1 text-sm md:hidden"
          @click="ui.toggleSidebar()"
        >
          Menu
        </button>
        <span class="text-sm text-slate-600">Inventaris IT</span>
      </header>
      <main class="p-4">
        <RouterView />
      </main>
    </div>
  </div>
</template>
