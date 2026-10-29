import { createRouter, createWebHistory } from 'vue-router'
import KioskLayout from '@/layouts/KioskLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'

const routes = [
  {
    path: '/',
    component: KioskLayout,
    children: [
      { path: '', name: 'kiosk.beranda', component: () => import('@/pages/kiosk/BerandaPage.vue') },
    ],
  },
  {
    path: '/admin/login',
    component: AuthLayout,
    children: [
      { path: '', name: 'admin.login', component: () => import('@/pages/admin/LoginPage.vue') },
    ],
  },
  {
    path: '/admin',
    component: AdminLayout,
    children: [
      { path: '', name: 'admin.dasbor', component: () => import('@/pages/admin/DasborPage.vue') },
    ],
  },
]

export default createRouter({
  history: createWebHistory(),
  routes,
})
