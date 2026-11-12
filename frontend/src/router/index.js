import { createRouter, createWebHistory } from 'vue-router'
import LayoutKiosk from '@/layouts/LayoutKiosk.vue'
import LayoutLogin from '@/layouts/LayoutLogin.vue'
import LayoutAdmin from '@/layouts/LayoutAdmin.vue'

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
    meta: { judul: 'Dasbor' },
    children: [
      {
        path: '',
        name: 'admin.dasbor',
        component: () => import('@/pages/admin/DasborPage.vue'),
        meta: { judul: 'Dasbor' },
      },
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

router.afterEach((to) => {
  document.title = to.meta.judul ? `${to.meta.judul} - Inventaris IT` : 'Inventaris IT'
})

export default router
