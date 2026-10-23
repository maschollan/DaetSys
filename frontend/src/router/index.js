import { createRouter, createWebHistory } from 'vue-router'

import useAuthStore from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/unauthorized',
    name: 'unauthorized',
    component: () => import('@/views/UnauthorizedView.vue'),
    meta: { public: true },
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    children: [
      { path: '', redirect: { name: 'dashboard' } },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@/views/dashboard/DashboardView.vue'),
        meta: { title: 'Dashboard', breadcrumb: 'Dashboard' },
      },
      {
        path: 'assets',
        name: 'assets',
        component: () => import('@/views/assets/AssetListView.vue'),
        meta: { title: 'Aset', breadcrumb: 'Aset' },
      },
      {
        path: 'assets/:qrToken',
        name: 'assets.detail',
        component: () => import('@/views/assets/AssetDetailView.vue'),
        meta: { title: 'Detail Aset', breadcrumb: 'Aset / Detail' },
      },
      {
        path: 'locations',
        name: 'locations',
        component: () => import('@/views/locations/LocationListView.vue'),
        meta: { title: 'Lokasi', breadcrumb: 'Lokasi' },
      },
      {
        path: 'categories',
        name: 'categories',
        component: () => import('@/views/categories/CategoryListView.vue'),
        meta: { title: 'Kategori', breadcrumb: 'Kategori' },
      },
      {
        path: 'transfers',
        name: 'transfers',
        component: () => import('@/views/transfers/TransferListView.vue'),
        meta: { title: 'Transfer', breadcrumb: 'Transfer' },
      },
      {
        path: 'transfers/:uniqId',
        name: 'transfers.detail',
        component: () => import('@/views/transfers/TransferDetailView.vue'),
        meta: { title: 'Detail Transfer', breadcrumb: 'Transfer / Detail' },
      },
      {
        path: 'track',
        name: 'track',
        component: () => import('@/views/track/TrackQrView.vue'),
        meta: { title: 'Lacak QR', breadcrumb: 'Lacak QR' },
      },
      {
        path: 'notifications',
        name: 'notifications',
        component: () => import('@/views/notifications/NotificationsView.vue'),
        meta: { title: 'Notifikasi', breadcrumb: 'Notifikasi' },
      },
      {
        path: 'audit-logs',
        name: 'audit-logs',
        component: () => import('@/views/audit-logs/AuditLogListView.vue'),
        meta: { title: 'Jejak Audit', breadcrumb: 'Jejak Audit', roles: ['admin', 'auditor'] },
      },
      {
        path: 'reports',
        name: 'reports',
        component: () => import('@/views/reports/ReportsView.vue'),
        meta: { title: 'Laporan', breadcrumb: 'Laporan' },
      },
      {
        path: 'users',
        name: 'users',
        component: () => import('@/views/users/UserListView.vue'),
        meta: { title: 'Pengguna', breadcrumb: 'Pengguna', roles: ['admin'] },
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/views/profile/ProfileView.vue'),
        meta: { title: 'Profil', breadcrumb: 'Profil' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: { name: 'dashboard' },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Token tersedia tetapi cache user hilang: pulihkan profil dari API
  // sebelum keputusan navigasi diambil.
  if (auth.token && !auth.user) {
    await auth.bootstrap()
  }

  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Pembatasan halaman berdasarkan role yang dibaca dari sesi; otorisasi
  // sesungguhnya tetap ditegakkan backend (PLAN.md §7).
  const requiredRoles = to.meta?.roles
  if (requiredRoles && !requiredRoles.includes(auth.role)) {
    return { name: 'unauthorized' }
  }

  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

router.afterEach((to) => {
  document.title = to.meta?.title ? `${to.meta.title} — DaetSys` : 'DaetSys'
})

export default router
