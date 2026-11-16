<script setup>
import { onMounted, ref } from 'vue'
import { adminApi, authApi } from '@/api'
import FormField from '@/components/FormField.vue'
import Memuat from '@/components/Memuat.vue'
import ModalKonfirmasi from '@/components/ModalKonfirmasi.vue'
import PesanGalat from '@/components/PesanGalat.vue'
import StatusKosong from '@/components/StatusKosong.vue'
import TombolBesar from '@/components/TombolBesar.vue'
import { useGalat } from '@/composables/useGalat'
import { useToast } from '@/composables/useToast'
import { useSesiStore } from '@/stores/sesi'
import { formatWaktuIndo } from '@/utils/tanggal'

const sesi = useSesiStore()
const { errors, pesanGalat, memuat: sedangKirim, tanganiGalat, bersihkan } = useGalat()
const { tampil: toast } = useToast()

const daftar = ref([])
const sedangMuat = ref(false)

async function muat() {
  sedangMuat.value = true
  bersihkan()
  try {
    const res = await adminApi.getUsers()
    daftar.value = res.data?.data || []
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangMuat.value = false
  }
}

onMounted(muat)

// --- Modal Tambah Admin ---
const modalTambah = ref(false)
const formTambah = ref({ name: '', email: '', password: '' })

function bukaTambah() {
  bersihkan()
  formTambah.value = { name: '', email: '', password: '' }
  modalTambah.value = true
}

async function simpanTambah() {
  bersihkan()
  const galatKlien = {}
  if (!formTambah.value.name.trim()) {
    galatKlien.name = ['Nama admin wajib diisi.']
  }
  if (!formTambah.value.email.trim()) {
    galatKlien.email = ['Email wajib diisi.']
  }
  if (!formTambah.value.password || formTambah.value.password.length < 12) {
    galatKlien.password = ['Password minimal 12 karakter.']
  }

  if (Object.keys(galatKlien).length > 0) {
    errors.value = galatKlien
    return
  }

  sedangKirim.value = true
  try {
    await adminApi.createUser({
      name: formTambah.value.name.trim(),
      email: formTambah.value.email.trim(),
      password: formTambah.value.password,
    })
    toast(`Akun admin "${formTambah.value.name}" berhasil dibuat.`)
    modalTambah.value = false
    await muat()
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangKirim.value = false
  }
}

// --- Toggle Aktif / Nonaktif Akun ---
const targetToggle = ref(null)
const konfirmasiNonaktif = ref(false)

function mintaToggle(user) {
  bersihkan()
  targetToggle.value = user
  if (user.aktif) {
    konfirmasiNonaktif.value = true
  } else {
    jalankanToggle(user)
  }
}

async function jalankanToggle(user) {
  konfirmasiNonaktif.value = false
  sedangKirim.value = true
  try {
    await adminApi.updateUser(user.id, { aktif: !user.aktif })
    toast(user.aktif ? `Akun "${user.name}" dinonaktifkan.` : `Akun "${user.name}" diaktifkan.`)
    await muat()
  } catch (err) {
    tanganiGalat(err)
    toast(pesanGalat.value || 'Gagal mengubah status akun.', 'galat')
  } finally {
    sedangKirim.value = false
    targetToggle.value = null
  }
}

// --- Modal Ganti Password Sendiri ---
const modalGantiPass = ref(false)
const formPass = ref({ password_lama: '', password_baru: '', password_baru_confirmation: '' })

function bukaGantiPassword() {
  bersihkan()
  formPass.value = { password_lama: '', password_baru: '', password_baru_confirmation: '' }
  modalGantiPass.value = true
}

async function simpanGantiPassword() {
  bersihkan()
  const galatKlien = {}
  if (!formPass.value.password_lama) {
    galatKlien.password_lama = ['Password lama wajib diisi.']
  }
  if (!formPass.value.password_baru || formPass.value.password_baru.length < 12) {
    galatKlien.password_baru = ['Password baru minimal 12 karakter.']
  }
  if (formPass.value.password_baru !== formPass.value.password_baru_confirmation) {
    galatKlien.password_baru_confirmation = ['Konfirmasi password baru tidak cocok.']
  }

  if (Object.keys(galatKlien).length > 0) {
    errors.value = galatKlien
    return
  }

  sedangKirim.value = true
  try {
    await authApi.changePassword({
      password_lama: formPass.value.password_lama,
      password_baru: formPass.value.password_baru,
      password_baru_confirmation: formPass.value.password_baru_confirmation,
    })
    toast('Kata sandi berhasil diganti.')
    modalGantiPass.value = false
  } catch (err) {
    tanganiGalat(err)
  } finally {
    sedangKirim.value = false
  }
}
</script>

<template>
  <section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4 pb-2 border-b border-slate-200">
      <div>
        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900">Kelola Akun Admin</h2>
        <p class="text-sm text-slate-500 mt-0.5">
          Daftar akun pengelola panel, tambah admin baru, dan kelola akses.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <TombolBesar varian="sekunder" ukuran="sedang" @click="bukaGantiPassword">
          Ganti Password Sendiri
        </TombolBesar>
        <TombolBesar varian="primer" ukuran="sedang" @click="bukaTambah">
          + Tambah Admin
        </TombolBesar>
      </div>
    </div>

    <PesanGalat v-if="pesanGalat" :pesan="pesanGalat" />

    <Memuat v-if="sedangMuat" tipe="skeleton" :baris="3" />
    <StatusKosong v-else-if="daftar.length === 0" judul="Belum ada akun admin" />

    <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-xs divide-y divide-slate-100 overflow-hidden">
      <div
        v-for="u in daftar"
        :key="u.id"
        class="flex flex-wrap items-center justify-between gap-4 p-4 md:p-5"
      >
        <div class="min-w-[200px]">
          <div class="flex items-center gap-2">
            <p class="font-bold text-slate-900">{{ u.name }}</p>
            <span
              v-if="u.id === sesi.user?.id"
              class="px-2 py-0.5 rounded text-xs font-bold bg-blue-50 text-blue-800 border border-blue-200"
            >
              Akun Anda
            </span>
          </div>
          <p class="text-sm text-slate-600 font-mono">{{ u.email }}</p>
          <p class="text-xs text-slate-400 mt-0.5">
            Login terakhir: {{ u.login_terakhir ? formatWaktuIndo(u.login_terakhir) : 'Belum pernah' }}
          </p>
        </div>

        <div class="flex items-center gap-3">
          <span
            :class="[
              'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border',
              u.aktif ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-300',
            ]"
          >
            {{ u.aktif ? 'Aktif' : 'Nonaktif' }}
          </span>

          <TombolBesar
            v-if="u.id !== sesi.user?.id"
            varian="sekunder"
            ukuran="sedang"
            :dinonaktifkan="sedangKirim"
            @click="mintaToggle(u)"
          >
            {{ u.aktif ? 'Nonaktifkan' : 'Aktifkan' }}
          </TombolBesar>
        </div>
      </div>
    </div>

    <!-- Modal Tambah Admin -->
    <div
      v-if="modalTambah"
      class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
    >
      <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Tambah Akun Admin Baru</h3>

        <FormField id="u-nama" label="Nama Lengkap" wajib :galat="errors.name?.[0]">
          <input
            id="u-nama"
            v-model="formTambah.name"
            type="text"
            maxlength="100"
            placeholder="Contoh: Budi Santoso"
            class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
          >
        </FormField>

        <FormField id="u-email" label="Email" wajib :galat="errors.email?.[0]">
          <input
            id="u-email"
            v-model="formTambah.email"
            type="email"
            maxlength="150"
            placeholder="admin@perusahaan.com"
            class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
          >
        </FormField>

        <FormField id="u-pass" label="Password Awal" wajib bantuan="Minimal 12 karakter." :galat="errors.password?.[0]">
          <input
            id="u-pass"
            v-model="formTambah.password"
            type="password"
            class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
          >
        </FormField>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <TombolBesar varian="sekunder" ukuran="sedang" @click="modalTambah = false">Batal</TombolBesar>
          <TombolBesar varian="primer" ukuran="sedang" :memuat="sedangKirim" @click="simpanTambah">
            Simpan
          </TombolBesar>
        </div>
      </div>
    </div>

    <!-- Modal Ganti Password Sendiri -->
    <div
      v-if="modalGantiPass"
      class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
    >
      <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Ganti Password Sendiri</h3>

        <FormField id="gp-lama" label="Password Lama" wajib :galat="errors.password_lama?.[0]">
          <input
            id="gp-lama"
            v-model="formPass.password_lama"
            type="password"
            class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
          >
        </FormField>

        <FormField id="gp-baru" label="Password Baru" wajib bantuan="Minimal 12 karakter." :galat="errors.password_baru?.[0]">
          <input
            id="gp-baru"
            v-model="formPass.password_baru"
            type="password"
            class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
          >
        </FormField>

        <FormField id="gp-konf" label="Konfirmasi Password Baru" wajib :galat="errors.password_baru_confirmation?.[0]">
          <input
            id="gp-konf"
            v-model="formPass.password_baru_confirmation"
            type="password"
            class="w-full min-h-[44px] px-3 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-aksen,#1d4ed8)]"
          >
        </FormField>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <TombolBesar varian="sekunder" ukuran="sedang" @click="modalGantiPass = false">Batal</TombolBesar>
          <TombolBesar varian="primer" ukuran="sedang" :memuat="sedangKirim" @click="simpanGantiPassword">
            Simpan Password
          </TombolBesar>
        </div>
      </div>
    </div>

    <!-- Konfirmasi Nonaktifkan Akun -->
    <ModalKonfirmasi
      :terbuka="konfirmasiNonaktif"
      judul="Nonaktifkan Akun Admin"
      :pesan="`Nonaktifkan akun &quot;${targetToggle?.name}&quot;? Akun yang dinonaktifkan tidak akan dapat masuk ke sistem.`"
      teks-batal="Jangan"
      teks-konfirmasi="Ya, Nonaktifkan"
      varian-konfirmasi="bahaya"
      :memuat="sedangKirim"
      @update:terbuka="konfirmasiNonaktif = $event"
      @konfirmasi="jalankanToggle(targetToggle)"
    />
  </section>
</template>
