import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import App from '@/layouts/App.vue'
import Home from '@/views/App/Home.vue'
import BrowseCategory from '@/views/App/BrowseCategory.vue'
import AppProductDetail from '@/views/App/ProductDetail.vue'
import AppStoreDetail from '@/views/App/StoreDetail.vue'
import Auth from '@/layouts/Auth.vue'
import Login from '@/views/auth/Login.vue'
import Register from '@/views/auth/Register.vue'
import Admin from '@/layouts/Admin.vue'
import Dashboard from '@/views/admin/Dashboard.vue'
import CategoryList from '@/views/admin/category/CategoryList.vue'
import Forbidden from '@/views/App/Forbidden.vue'
import CategoryCreate from '@/views/admin/category/CategoryCreate.vue'
import CategoryEdit from '@/views/admin/category/CategoryEdit.vue'
import CategoryDetail from '@/views/admin/category/CategoryDetail.vue'
import ProductList from '@/views/admin/product/ProductList.vue'
import ProductDetail from '@/views/admin/product/ProductDetail.vue'
import ProductCreate from '@/views/admin/product/ProductCreate.vue'
import StoreList from '@/views/admin/store/StoreList.vue'
import StoreDetail from '@/views/admin/store/StoreDetail.vue'
import TransactionList from '@/views/admin/transaction/TransactionList.vue'
import TransactionDetail from '@/views/admin/transaction/TransactionDetail.vue'
import StoreBalanceList from '@/views/admin/store-balance/StoreBalanceList.vue'
import StoreBalanceDetail from '@/views/admin/store-balance/StoreBalanceDetail.vue'
import WithdrawalList from '@/views/admin/withdrawal/WithdrawalList.vue'
import WithdrawalDetail from '@/views/admin/withdrawal/WithdrawalDetail.vue'
import UserList from '@/views/admin/user/UserList.vue'
import MyStore from '@/views/admin/store/MyStore.vue'
import StoreCreate from '@/views/admin/store/StoreCreate.vue'

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
        },
        {
          path: 'register',
          name: 'auth.register',
          component: Register
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
          component: AppProductDetail
        },
        {
          path: 'store/:username',
          name: 'app.store-detail',
          component: AppStoreDetail
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
        },
        {
          path: 'category/:id',
          name: 'admin.category.detail',
          component: CategoryDetail,
          meta: {
            title: 'Category Detail',
            requiresAuth: true,
            permission: 'product-category-list'
          }
        },
        {
          path: 'product',
          name: 'admin.product',
          component: ProductList,
          meta: {
            title: 'Product Detail',
            requiresAuth: true,
            permission: 'product-list'
          }
        },
        {
          path: 'product/create',
          name: 'admin.product.create',
          component: ProductCreate,
          meta: {
            title: 'Product Create',
            requiresAuth: true,
            permission: 'product-create'
          }
        },
        {
          path: 'product/:id',
          name: 'admin.product.detail',
          component: ProductDetail,
          meta: {
            title: 'Product Detail',
            requiresAuth: true,
            permission: 'product-list'
          }
        },
        {
          path: 'store',
          name: 'admin.store',
          component: StoreList,
          meta: {
            title: 'Store Detail',
            requiresAuth: true,
            permission: 'store-list'
          }
        },
         {
          path: 'my-store',
          name: 'admin.my-store',
          component: MyStore,
          meta: {
            title: 'My Store',
            requiresAuth: true,
            permission: 'store-list'
          }
        },
        {
          path: 'create-store',
          name: 'admin.create-store',
          component: StoreCreate,
          meta: {
            title: 'Create Store',
            requiresAuth: true,
            permission: 'store-create'
          }
        },
        {
          path: 'store/:id',
          name: 'admin.store.detail',
          component: StoreDetail,
          meta: {
            title: 'Store Detail',
            requiresAuth: true,
            permission: 'store-list'
          }
        },
        {
          path: 'transaction',
          name: 'admin.transaction',
          component: TransactionList,
          meta: {
            title: 'Transaction List',
            requiresAuth: true,
            permission: 'transaction-list'
          }
        },
        {
          path: 'transaction/:id',
          name: 'admin.transaction.detail',
          component: TransactionDetail,
          meta: {
            title: 'Transaction Detail',
            requiresAuth: true,
            permission: 'transaction-list'
          }
        },
        {
          path: 'store-balance',
          name: 'admin.store-balance',
          component: StoreBalanceList,
          meta: {
            title: 'Store Wallet',
            requiresAuth: true,
            permission: 'store-balance-list'
          }
        },
        {
          path: 'store-balance/:id',
          name: 'admin.store-balance.detail',
          component: StoreBalanceDetail,
          meta: {
            title: 'Store Wallet Detail',
            requiresAuth: true,
            permission: 'store-balance-list'
          }
        },
        {
          path: 'withdrawal',
          name: 'admin.withdrawal',
          component: WithdrawalList,
          meta: {
            title: 'Withdrawal List',
            requiresAuth: true,
            permission: 'withdrawal-list'
          }
        },
        {
          path: 'withdrawal/:id',
          name: 'admin.withdrawal.detail',
          component: WithdrawalDetail,
          meta: {
            title: 'Withdrawal Detail',
            requiresAuth: true,
            permission: 'withdrawal-list'
          }
        },
        {
          path: 'user',
          name: 'admin.user',
          component: UserList,
          meta: {
            title: 'User List',
            requiresAuth: true,
            permission: 'user-list'
          }
        },
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
