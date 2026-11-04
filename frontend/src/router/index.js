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
