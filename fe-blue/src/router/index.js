import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authRoutes } from './routes/auth'
import { buyerRoutes } from './routes/buyer'
import { sellerRoutes } from './routes/seller'

// Build ini render route buyer ATAU seller, tidak pernah keduanya --
// ditentukan saat build lewat VITE_APP_TARGET (lihat package.json script
// build:buyer / build:seller dan .env.buyer / .env.seller).
const target = import.meta.env.VITE_APP_TARGET === 'seller' ? 'seller' : 'buyer'
const targetRoutes = target === 'seller' ? sellerRoutes : buyerRoutes

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [...authRoutes, ...targetRoutes]
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth) {
    if (authStore.token) {
      try {
        if (!authStore.user) {
          await authStore.checkAuth()
          if (!authStore.user) throw new Error('Unauthorized')
        }

        const userPermissions = authStore.user?.permissions || []

        if (to.meta.permission && !userPermissions.includes(to.meta.permission)) {
          next({ name: '403' })
          return
        }

        // Middleware: Check if route requires Store role
        if (
          to.meta.requiresStore &&
          authStore.user?.role !== 'store' &&
          authStore.user?.role !== 'admin'
        ) {
          // Redirect to open store page or home
          next({ name: 'auth.open-store' })
          return
        }

        next()
      } catch (error) {
        next({ name: 'auth.login' })
      }
    } else {
      next({ name: 'auth.login' })
    }
  } else {
    next()
  }
})

router.afterEach((to, from) => {
  window.scrollTo(0, 0)
  const defaultTitle = 'Blukios'
  document.title = to.meta.title ? `${to.meta.title} | ${defaultTitle}` : defaultTitle
})

export default router
