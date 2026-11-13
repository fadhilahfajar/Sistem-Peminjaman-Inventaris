import { createRouter, createWebHistory } from 'vue-router'
import LayoutKiosk from '@/layouts/LayoutKiosk.vue'
import LayoutLogin from '@/layouts/LayoutLogin.vue'
import LayoutAdmin from '@/layouts/LayoutAdmin.vue'
import { useSesiStore } from '@/stores/sesi'

const routes = [
  {
    path: '/',
    component: LayoutKiosk,
    meta: { judul: 'Beranda' },
    children: [
      {
        path: '',
        name: 'kiosk.beranda',
        component: () => import('@/pages/kiosk/BerandaPage.vue'),
        meta: { judul: 'Beranda' },
      },
      {
        path: 'katalog',
        name: 'kiosk.katalog',
        component: () => import('@/pages/kiosk/KatalogPage.vue'),
        meta: { judul: 'Katalog Barang' },
      },
      {
        path: 'pinjam',
        name: 'kiosk.pinjam',
        component: () => import('@/pages/kiosk/PinjamPage.vue'),
        meta: { judul: 'Pinjam Barang' },
      },
      {
        path: 'pinjam/selesai',
        name: 'kiosk.pinjam-selesai',
        component: () => import('@/pages/kiosk/PinjamSelesaiPage.vue'),
        meta: { judul: 'Peminjaman Selesai' },
      },
      {
        path: 'cek',
        name: 'kiosk.cek',
        component: () => import('@/pages/kiosk/CekPage.vue'),
        meta: { judul: 'Cek Peminjaman' },
      },
      {
        path: 'cek/kartu',
        name: 'kiosk.cek-kartu',
        component: () => import('@/pages/kiosk/KartuPage.vue'),
        meta: { judul: 'Kartu Peminjaman' },
      },
      {
        path: 'klaim',
        name: 'kiosk.klaim',
        component: () => import('@/pages/kiosk/KlaimPage.vue'),
        meta: { judul: 'Klaim Pengembalian' },
      },
      {
        path: 'klaim/selesai',
        name: 'kiosk.klaim-selesai',
        component: () => import('@/pages/kiosk/KlaimSelesaiPage.vue'),
        meta: { judul: 'Bukti Klaim' },
      },
      {
        path: 'perpanjang',
        name: 'kiosk.perpanjang',
        component: () => import('@/pages/kiosk/PerpanjangPage.vue'),
        meta: { judul: 'Perpanjangan' },
      },
      {
        path: 'perpanjang/selesai',
        name: 'kiosk.perpanjang-selesai',
        component: () => import('@/pages/kiosk/PerpanjangSelesaiPage.vue'),
        meta: { judul: 'Bukti Perpanjangan' },
      },
      {
        path: 'batal',
        name: 'kiosk.batal',
        component: () => import('@/pages/kiosk/BatalPage.vue'),
        meta: { judul: 'Batalkan Pemesanan' },
      },
    ],
  },
  {
    path: '/admin/login',
    component: LayoutLogin,
    meta: { judul: 'Login' },
    children: [
      {
        path: '',
        name: 'admin.login',
        component: () => import('@/pages/admin/LoginPage.vue'),
        meta: { judul: 'Login' },
      },
    ],
  },
  {
    path: '/admin',
    component: LayoutAdmin,
    meta: { judul: 'Dasbor', butuhSesi: true },
    children: [
      {
        path: '',
        name: 'admin.dasbor',
        component: () => import('@/pages/admin/DasborPage.vue'),
        meta: { judul: 'Dasbor', butuhSesi: true },
      },
      // Layar admin 5.5–5.10 dibangun pada task masing-masing; sementara memakai placeholder.
      ...Object.entries({
        peminjaman: 'Daftar Peminjaman',
        verifikasi: 'Antrean Verifikasi',
        barang: 'Barang',
        'stok-rusak': 'Stok Rusak',
        laporan: 'Laporan',
        kategori: 'Kategori',
        divisi: 'Divisi',
        pengaturan: 'Pengaturan',
        akun: 'Akun Admin',
        audit: 'Jejak Audit',
      }).map(([path, judul]) => ({
        path,
        name: `admin.${path}`,
        component: () => import('@/pages/admin/PlaceholderAdmin.vue'),
        meta: { judul, butuhSesi: true },
      })),
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

// Guard sesi admin (Task 5.1): seluruh /admin kecuali /admin/login menuntut sesi aktif
// lewat /api/auth/me. Kehabisan sesi di tengah pemakaian ditangani interceptor (401/419).
router.beforeEach(async (to) => {
  if (!to.matched.some((r) => r.meta.butuhSesi)) {
    return true
  }

  const sesi = useSesiStore()

  if (!sesi.termuat) {
    try {
      await sesi.muatProfil()
    } catch {
      // 401/419: sesi tidak ada atau berakhir.
    }
  }

  if (!sesi.masuk) {
    return {
      name: 'admin.login',
      query: to.path === '/admin' ? {} : { redirect: to.fullPath },
    }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.judul ? `${to.meta.judul} - Inventaris IT` : 'Inventaris IT'
})

export default router
