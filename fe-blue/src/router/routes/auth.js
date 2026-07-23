import { useAuthStore } from '@/stores/auth'
import Auth from '@/layouts/Auth.vue'
import Login from '@/views/auth/Login.vue'
import Register from '@/views/auth/Register.vue'
import ForgotPassword from '@/views/auth/ForgotPassword.vue'
import ResetPassword from '@/views/auth/ResetPassword.vue'
import Forbidden from '@/views/App/Forbidden.vue'

// Route auth dipakai KEDUA build (buyer & seller) -- login/register sama
// persis, cuma tujuan setelah login yang beda (ditentukan di stores/auth.js
// & guard router/index.js sesuai VITE_APP_TARGET).
export const authRoutes = [
  {
    path: '/auth',
    component: Auth,
    children: [
      {
        path: 'login',
        name: 'auth.login',
        component: Login
      },
      {
        path: 'register',
        name: 'auth.register',
        component: Register
      },
      {
        path: 'google/callback',
        name: 'auth.google.callback',
        component: () => import('@/views/auth/AuthCallback.vue')
      },
      {
        path: 'verify-email',
        name: 'auth.verify-email',
        component: () => import('@/views/auth/VerifyEmail.vue'),
        meta: { requiresAuth: true, title: 'Verifikasi Email' }
      },
      {
        path: 'forgot-password',
        name: 'auth.forgot-password',
        component: ForgotPassword,
        meta: { guestOnly: true }
      },
      {
        path: 'reset-password',
        name: 'auth.reset-password',
        component: ResetPassword,
        meta: { guestOnly: true }
      }
    ]
  },
  {
    // Halaman perantara SSO: terima exchange token dari domain lain, tukar
    // jadi sesi lokal, lalu redirect ke dashboard yang sesuai build ini.
    path: '/sso/callback',
    name: 'auth.sso.callback',
    component: () => import('@/views/auth/SsoCallback.vue')
  },
  {
    // Dipakai KEDUA build -- guard permission (router/index.js) redirect ke
    // sini kalau user login tapi tidak punya izin akses suatu route.
    path: '/403',
    name: '403',
    component: Forbidden
  },
  {
    // "Buka Toko" cuma ada di build buyer -- upgrade dari buyer jadi seller
    // dimulai di sini, lalu diarahkan ke seller app lewat SSO (bukan
    // router.push, karena dashboard toko sudah tidak ada di build ini).
    path: '/open-store',
    name: 'auth.open-store',
    component: () => import('@/views/auth/StoreRegister.vue'),
    meta: { requiresAuth: true },
    beforeEnter: async (to, from, next) => {
      const authStore = useAuthStore()

      if (!authStore.user) {
        await authStore.checkAuth()
      }

      if (authStore.user?.store) {
        // Sudah punya toko -- balik ke buyer dashboard, bukan seller
        // dashboard (yang sudah tidak ada di build ini kalau target=buyer).
        next({ name: 'user.dashboard', params: { username: authStore.user.username } })
      } else {
        next()
      }
    }
  }
]
