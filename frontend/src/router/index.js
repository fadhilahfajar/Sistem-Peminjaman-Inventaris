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
      {
        path: 'peminjaman',
        name: 'admin.peminjaman',
        component: () => import('@/pages/admin/PeminjamanPage.vue'),
        meta: { judul: 'Daftar Peminjaman', butuhSesi: true },
      },
      {
        path: 'peminjaman/:id',
        name: 'admin.peminjaman-detail',
        component: () => import('@/pages/admin/DetailPeminjamanPage.vue'),
        meta: { judul: 'Detail Peminjaman', butuhSesi: true },
      },
      {
        path: 'verifikasi',
        name: 'admin.verifikasi',
        component: () => import('@/pages/admin/VerifikasiPage.vue'),
        meta: { judul: 'Antrean Verifikasi', butuhSesi: true },
      },
      {
        path: 'verifikasi/:id',
        name: 'admin.verifikasi-detail',
        component: () => import('@/pages/admin/VerifikasiDetailPage.vue'),
        meta: { judul: 'Verifikasi Klaim', butuhSesi: true },
      },
      {
        path: 'barang',
        name: 'admin.barang',
        component: () => import('@/pages/admin/BarangPage.vue'),
        meta: { judul: 'Barang', butuhSesi: true },
      },
      {
        path: 'barang/baru',
        name: 'admin.barang-baru',
        component: () => import('@/pages/admin/BarangFormPage.vue'),
        meta: { judul: 'Tambah Barang', butuhSesi: true },
      },
      {
        path: 'barang/:id/ubah',
        name: 'admin.barang-ubah',
        component: () => import('@/pages/admin/BarangFormPage.vue'),
        meta: { judul: 'Ubah Barang', butuhSesi: true },
      },
      {
        path: 'stok-rusak',
        name: 'admin.stok-rusak',
        component: () => import('@/pages/admin/StokRusakPage.vue'),
        meta: { judul: 'Stok Rusak', butuhSesi: true },
      },
      {
        path: 'kategori',
        name: 'admin.kategori',
        component: () => import('@/pages/admin/KategoriPage.vue'),
        meta: { judul: 'Kategori', butuhSesi: true },
      },
      {
        path: 'divisi',
        name: 'admin.divisi',
        component: () => import('@/pages/admin/DivisiPage.vue'),
        meta: { judul: 'Divisi', butuhSesi: true },
      },
      {
        path: 'pengaturan',
        name: 'admin.pengaturan',
        component: () => import('@/pages/admin/PengaturanPage.vue'),
        meta: { judul: 'Pengaturan', butuhSesi: true },
      },
      {
        path: 'akun',
        name: 'admin.akun',
        component: () => import('@/pages/admin/AkunPage.vue'),
        meta: { judul: 'Akun Admin', butuhSesi: true },
      },
      {
        path: 'audit',
        name: 'admin.audit',
        component: () => import('@/pages/admin/AuditPage.vue'),
        meta: { judul: 'Jejak Audit', butuhSesi: true },
      },
      {
        path: 'laporan',
        name: 'admin.laporan',
        component: () => import('@/pages/admin/LaporanPage.vue'),
        meta: { judul: 'Laporan', butuhSesi: true },
      },
    ],
  },
  // Halaman galat ramah tanpa informasi internal (Task 5.11, DESAIN 10.3 / 10.6)
  {
    path: '/403',
    name: 'galat.403',
    component: () => import('@/pages/galat/GalatPage.vue'),
    meta: { judul: 'Akses Ditolak', statusGalat: '403' },
  },
  {
    path: '/404',
    name: 'galat.404',
    component: () => import('@/pages/galat/GalatPage.vue'),
    meta: { judul: 'Halaman Tidak Ditemukan', statusGalat: '404' },
  },
  {
    path: '/419',
    name: 'galat.419',
    component: () => import('@/pages/galat/GalatPage.vue'),
    meta: { judul: 'Sesi Berakhir', statusGalat: '419' },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'galat.catch-all',
    component: () => import('@/pages/galat/GalatPage.vue'),
    meta: { judul: 'Halaman Tidak Ditemukan', statusGalat: '404' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

// Guard sesi admin: tentukan status masuk dari /api/auth/me (sekali, disimpan) SEBELUM
// menampilkan rute admin, agar LayoutAdmin tidak tampil lalu dibuang.
router.beforeEach(async (to) => {
  if (!to.matched.some((r) => r.meta.butuhSesi)) {
    return true
  }

  const sesi = useSesiStore()

  if (!sesi.termuat) {
    await sesi.muatProfil({ diamkanGalat: true, lewatiArahUlangAuth: true })
  }

  if (!sesi.masuk) {
    const query = {}
    // Sesi habis (pernah masuk) -> tampilkan pesan sekali. Belum pernah masuk -> tanpa pesan.
    if (sesi.pernahMasukSesiIni()) {
      query.sesi = 'berakhir'
    }
    if (to.fullPath && to.fullPath !== '/admin' && to.fullPath !== '/admin/') {
      query.redirect = to.fullPath
    }
    return { name: 'admin.login', query }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.judul ? `${to.meta.judul} - Inventaris IT` : 'Inventaris IT'
})

export default router
