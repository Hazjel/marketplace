import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import App from '@/layouts/App.vue'
import Home from '@/views/App/Home.vue'
import BrowseCategory from '@/views/App/BrowseCategory.vue'
import ProductDetail from '@/views/App/ProductDetail.vue'
import StoreDetail from '@/views/App/StoreDetail.vue'
import Auth from '@/layouts/Auth.vue'
import Login from '@/views/auth/Login.vue'
import Admin from '@/layouts/Admin.vue'
import Dashboard from '@/views/admin/Dashboard.vue'
import CategoryList from '@/views/admin/category/CategoryList.vue'
import Forbidden from '@/views/App/Forbidden.vue'
import CategoryCreate from '@/views/admin/category/CategoryCreate.vue'
import CategoryEdit from '@/views/admin/category/CategoryEdit.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/auth',
      component: Auth,
      children: [
        {
          path: 'login',
          name: 'auth.login',
          component: Login
        }
      ]
    },
    {
      path: '/',
      component: App,
      children: [
        {
          path: '403',
          name: '403',
          component: Forbidden
        },
        {
          path: '',
          name: 'app.home',
          component: Home
        },
        {
          path: 'browse-category/:slug',
          name: 'app.browse-category',
          component: BrowseCategory
        },
        {
          path: 'product/:slug',
          name: 'app.product-detail',
          component: ProductDetail
        },
        {
          path: 'store/:username',
          name: 'app.store-detail',
          component: StoreDetail
        }
      ]
    },
    {
      path: '/admin',
      component: Admin,
      children: [
        {
          path: 'dashboard',
          name: 'admin.dashboard',
          component: Dashboard,
          meta: {
            title: 'Dashboard',
            requiresAuth: true,
            permission: 'dashboard-menu'
          }
        },
        {
          path: 'category',
          name: 'admin.category',
          component: CategoryList,
          meta: {
            title: 'Category List',
            requiresAuth: true,
            permission: 'product-category-list'
          }
        },
        {
          path: 'category/create',
          name: 'admin.category.create',
          component: CategoryCreate,
          meta: {
            title: 'Category Create',
            requiresAuth: true,
            permission: 'product-category-create'
          }
        },
        {
          path: 'category/edit/:id',
          name: 'admin.category.edit',
          component: CategoryEdit,
          meta: {
            title: 'Category Edit',
            requiresAuth: true,
            permission: 'product-category-edit'
          }
        }
      ]
    }
  ],
})

router.beforeEach(async(to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth) {
    if (authStore.token) {
      try {
        if (!authStore.user) {
          await authStore.checkAuth()
        }

        const userPermissions = authStore.user?.permissions || []

        if (to.meta.permission && !userPermissions.includes(to.meta.permission)) {
          next({ name: '403' })
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
})

export default router
