import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import App from '@/layouts/App.vue'
import Home from '@/views/App/Home.vue'
import BrowseCategory from '@/views/App/BrowseCategory.vue'
import AppProductDetail from '@/views/App/ProductDetail.vue'
import AppStoreDetail from '@/views/App/StoreDetail.vue'
import Wishlist from '@/views/App/Wishlist.vue'
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
import ProductEdit from '@/views/admin/product/ProductEdit.vue'
import StoreList from '@/views/admin/store/StoreList.vue'
import StoreDetail from '@/views/admin/store/StoreDetail.vue'
import StoreEdit from '@/views/admin/store/StoreEdit.vue'
import TransactionList from '@/views/admin/transaction/TransactionList.vue'
import TransactionDetail from '@/views/admin/transaction/TransactionDetail.vue'
import MyTransaction from '@/views/admin/transaction/MyTransaction.vue'
import StoreBalanceList from '@/views/admin/store-balance/StoreBalanceList.vue'
import StoreBalanceDetail from '@/views/admin/store-balance/StoreBalanceDetail.vue'
import MyStoreBalance from '@/views/admin/store-balance/MyStoreBalance.vue'
import WithdrawalList from '@/views/admin/withdrawal/WithdrawalList.vue'
import WithdrawalDetail from '@/views/admin/withdrawal/WithdrawalDetail.vue'
import WithdrawalCreate from '@/views/admin/withdrawal/WithdrawalCreate.vue'
import UserList from '@/views/admin/user/UserList.vue'
import MyStore from '@/views/admin/store/MyStore.vue'
import StoreCreate from '@/views/admin/store/StoreCreate.vue'
import Cart from '@/views/App/Cart.vue'
import Checkout from '@/views/App/Checkout.vue'

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
        },
        {
          path: 'google/callback',
          name: 'auth.google.callback',
          component: () => import('@/views/auth/AuthCallback.vue')
        }
      ]
    },
    {
      path: '/open-store',
      name: 'auth.open-store',
      component: () => import('@/views/auth/StoreRegister.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/',
      component: App,
      beforeEnter: async (to, from, next) => {
        const authStore = useAuthStore()

        // Ensure user is loaded if token exists
        if (authStore.token && !authStore.user) {
          try {
            await authStore.checkAuth()
          } catch (e) {
            // Token might be invalid, treat as guest
          }
        }

        // If Admin, redirect to Dashboard
        if (authStore.user?.role === 'admin') {
          return next({ name: 'admin.dashboard' })
        }

        next()
      },
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
        },
        {
          path: '/cart',
          name: 'app.cart',
          component: Cart,
          meta: { requiresAuth: true }
        },
        {
          path: '/checkout',
          name: 'app.checkout',
          component: Checkout,
          meta: {
            requiresAuth: true
          }
        },
        {
          path: '/my-transactions',
          name: 'app.my-transactions',
          component: TransactionList,
          meta: {
            requiresAuth: true,
            title: 'My Transactions'
          }
        },
        {
          path: '/wishlist',
          name: 'app.wishlist',
          component: Wishlist,
          meta: {
            requiresAuth: true,
            title: 'My Wishlist'
          }
        },
        {
          path: '/categories',
          name: 'app.all-categories',
          component: () => import('@/views/App/AllCategories.vue')
        },
        {
          path: '/stores',
          name: 'app.all-stores',
          component: () => import('@/views/App/AllStores.vue')
        },
        {
          path: '/products',
          name: 'app.all-products',
          component: () => import('@/views/App/AllProducts.vue'),
        },
        {
          path: '/search',
          name: 'app.search',
          component: () => import('@/views/App/SearchResults.vue'),
        },

        {
          path: 'transaction/:id',
          name: 'user.transaction.detail',
          component: TransactionDetail,
          meta: { requiresAuth: true, title: 'Transaction Detail' }
        },
      ]
    },
    {
      path: '/admin',
      component: Admin,
      children: [
        {
          path: 'dashboard',
          name: 'admin.dashboard',
          meta: { permission: 'dashboard-menu', requiresAuth: true, title: 'Overview' },
          component: () => import('@/views/admin/Dashboard.vue')
        },
        {
          path: 'edit-profile',
          name: 'admin.edit-profile',
          meta: { title: 'Edit Profile' },
          component: () => import('@/views/admin/profile/EditProfile.vue')
        },
        {
          path: 'category',
          name: 'admin.category',
          component: CategoryList,
          meta: { title: 'Category List', requiresAuth: true, permission: 'product-category-list' }
        },
        {
          path: 'category/create',
          name: 'admin.category.create',
          component: CategoryCreate,
          meta: { title: 'Category Create', requiresAuth: true, permission: 'product-category-create' }
        },
        {
          path: 'category/edit/:id',
          name: 'admin.category.edit',
          component: CategoryEdit,
          meta: { title: 'Category Edit', requiresAuth: true, permission: 'product-category-edit' }
        },
        {
          path: 'category/:id',
          name: 'admin.category.detail',
          component: CategoryDetail,
          meta: { title: 'Category Detail', requiresAuth: true, permission: 'product-category-list' }
        },
        {
          path: 'product',
          name: 'admin.product',
          component: ProductList,
          meta: { title: 'Product Detail', requiresAuth: true, permission: 'product-list' }
        },
        {
          path: 'product/create',
          name: 'admin.product.create',
          component: ProductCreate,
          meta: { title: 'Product Create', requiresAuth: true, permission: 'product-create' }
        },
        {
          path: 'product/:id',
          name: 'admin.product.detail',
          component: ProductDetail,
          meta: { title: 'Product Detail', requiresAuth: true, permission: 'product-list' }
        },
        {
          path: 'product/edit/:id',
          name: 'admin.product.edit',
          component: ProductEdit,
          meta: { title: 'Product Edit', requiresAuth: true, permission: 'product-edit' }
        },
        {
          path: 'store',
          name: 'admin.store',
          component: StoreList,
          meta: { title: 'Store Detail', requiresAuth: true, permission: 'store-list' }
        },
        {
          path: 'my-store',
          name: 'admin.my-store',
          component: MyStore,
          meta: { title: 'My Store', requiresAuth: true, permission: 'store-list' }
        },
        {
          path: 'create-store',
          name: 'admin.create-store',
          component: StoreCreate,
          meta: { title: 'Create Store', requiresAuth: true, permission: 'store-create' }
        },
        {
          path: 'edit-store',
          name: 'admin.edit-store',
          component: StoreEdit,
          meta: { title: 'Edit My Store', requiresAuth: true, permission: 'store-edit' }
        },
        {
          path: 'store/:id',
          name: 'admin.store.detail',
          component: StoreDetail,
          meta: { title: 'Store Detail', requiresAuth: true, permission: 'store-list' }
        },
        {
          path: 'transaction',
          name: 'admin.transaction',
          component: TransactionList,
          meta: { title: 'Transaction List', requiresAuth: true, permission: 'transaction-list' }
        },
        {
          path: 'my-transactions',
          name: 'admin.my-transaction',
          component: MyTransaction,
          meta: { title: 'My Transaction List', requiresAuth: true, permission: 'transaction-list' }
        },
        {
          path: 'transaction/:id',
          name: 'admin.transaction.detail',
          component: TransactionDetail,
          meta: { title: 'Transaction Detail', requiresAuth: true, permission: 'transaction-list' }
        },
        {
          path: 'store-balance',
          name: 'admin.store-balance',
          component: StoreBalanceList,
          meta: { title: 'Store Wallet', requiresAuth: true, permission: 'store-balance-list' }
        },
        {
          path: 'store-balance/:id',
          name: 'admin.store-balance.detail',
          component: StoreBalanceDetail,
          meta: { title: 'Store Wallet Detail', requiresAuth: true, permission: 'store-balance-list' }
        },
        {
          path: 'my-store-balance',
          name: 'admin.my-store-balance',
          component: MyStoreBalance,
          meta: { title: 'Manage My Wallet', requiresAuth: true, permission: 'store-balance-list' }
        },
        {
          path: 'withdrawal',
          name: 'admin.withdrawal',
          component: WithdrawalList,
          meta: { title: 'Withdrawal List', requiresAuth: true, permission: 'withdrawal-list' }
        },
        {
          path: 'withdrawal/:id',
          name: 'admin.withdrawal.detail',
          component: WithdrawalDetail,
          meta: { title: 'Withdrawal Detail', requiresAuth: true, permission: 'withdrawal-list' }
        },
        {
          path: 'withdrawal/create',
          name: 'admin.withdrawal.create',
          component: WithdrawalCreate,
          meta: { title: ' Request Withdrawal Create', requiresAuth: true, permission: 'withdrawal-create' }
        },
        {
          path: 'user',
          name: 'admin.user',
          component: UserList,
          meta: { title: 'User List', requiresAuth: true, permission: 'user-list' }
        },
        {
          path: 'chat',
          name: 'admin.chat',
          component: () => import('@/views/App/Chat/ChatLayout.vue'),
          meta: { title: 'Conversations', requiresAuth: true, permission: 'dashboard-menu' }
        },
      ]
    },
    {
      path: '/:username',
      component: Admin,
      children: [
        {
          path: 'dashboard',
          name: 'user.dashboard',
          meta: { permission: 'dashboard-menu', requiresAuth: true, title: 'Overview' },
          component: () => import('@/views/admin/Dashboard.vue')
        },
        {
          path: 'edit-profile',
          name: 'user.edit-profile',
          meta: { title: 'Edit Profile' },
          component: () => import('@/views/admin/profile/EditProfile.vue')
        },
        {
          path: 'category',
          name: 'user.category',
          component: CategoryList,
          meta: { title: 'Category List', requiresAuth: true, permission: 'product-category-list' }
        },
        {
          path: 'category/create',
          name: 'user.category.create',
          component: CategoryCreate,
          meta: { title: 'Category Create', requiresAuth: true, permission: 'product-category-create' }
        },
        {
          path: 'category/edit/:id',
          name: 'user.category.edit',
          component: CategoryEdit,
          meta: { title: 'Category Edit', requiresAuth: true, permission: 'product-category-edit' }
        },
        {
          path: 'category/:id',
          name: 'user.category.detail',
          component: CategoryDetail,
          meta: { title: 'Category Detail', requiresAuth: true, permission: 'product-category-list' }
        },
        {
          path: 'product',
          name: 'user.product',
          component: ProductList,
          meta: { title: 'Product Detail', requiresAuth: true, permission: 'product-list' }
        },
        {
          path: 'product/create',
          name: 'user.product.create',
          component: ProductCreate,
          meta: { title: 'Product Create', requiresAuth: true, permission: 'product-create' }
        },
        {
          path: 'product/:id',
          name: 'user.product.detail',
          component: ProductDetail,
          meta: { title: 'Product Detail', requiresAuth: true, permission: 'product-list' }
        },
        {
          path: 'product/edit/:id',
          name: 'user.product.edit',
          component: ProductEdit,
          meta: { title: 'Product Edit', requiresAuth: true, permission: 'product-edit' }
        },
        {
          path: 'store',
          name: 'user.store',
          component: StoreList,
          meta: { title: 'Store Detail', requiresAuth: true, permission: 'store-list' }
        },
        {
          path: 'my-store',
          name: 'user.my-store',
          component: MyStore,
          meta: { title: 'My Store', requiresAuth: true, permission: 'store-list' }
        },
        {
          path: 'create-store',
          name: 'user.create-store',
          component: StoreCreate,
          meta: { title: 'Create Store', requiresAuth: true, permission: 'store-create' }
        },
        {
          path: 'edit-store',
          name: 'user.edit-store',
          component: StoreEdit,
          meta: { title: 'Edit My Store', requiresAuth: true, permission: 'store-edit' }
        },
        {
          path: 'store/:id',
          name: 'user.store.detail',
          component: StoreDetail,
          meta: { title: 'Store Detail', requiresAuth: true, permission: 'store-list' }
        },
        {
          path: 'transaction',
          name: 'user.transaction',
          component: TransactionList,
          meta: { title: 'Transaction List', requiresAuth: true, permission: 'transaction-list' }
        },
        {
          path: 'my-transactions',
          name: 'user.my-transaction',
          component: MyTransaction,
          meta: { title: 'My Transaction List', requiresAuth: true, permission: 'transaction-list' }
        },
        {
          path: 'transaction/:id',
          name: 'user.transaction.detail',
          component: TransactionDetail,
          meta: { title: 'Transaction Detail', requiresAuth: true, permission: 'transaction-list' }
        },
        {
          path: 'store-balance',
          name: 'user.store-balance',
          component: StoreBalanceList,
          meta: { title: 'Store Wallet', requiresAuth: true, permission: 'store-balance-list' }
        },
        {
          path: 'store-balance/:id',
          name: 'user.store-balance.detail',
          component: StoreBalanceDetail,
          meta: { title: 'Store Wallet Detail', requiresAuth: true, permission: 'store-balance-list' }
        },
        {
          path: 'my-store-balance',
          name: 'user.my-store-balance',
          component: MyStoreBalance,
          meta: { title: 'Manage My Wallet', requiresAuth: true, permission: 'store-balance-list' }
        },
        {
          path: 'withdrawal',
          name: 'user.withdrawal',
          component: WithdrawalList,
          meta: { title: 'Withdrawal List', requiresAuth: true, permission: 'withdrawal-list' }
        },
        {
          path: 'withdrawal/:id',
          name: 'user.withdrawal.detail',
          component: WithdrawalDetail,
          meta: { title: 'Withdrawal Detail', requiresAuth: true, permission: 'withdrawal-list' }
        },
        {
          path: 'withdrawal/create',
          name: 'user.withdrawal.create',
          component: WithdrawalCreate,
          meta: { title: ' Request Withdrawal Create', requiresAuth: true, permission: 'withdrawal-create' }
        },
        {
          path: 'chat',
          name: 'user.chat',
          component: () => import('@/views/App/Chat/ChatLayout.vue'),
          meta: { title: 'Conversations', requiresAuth: true, permission: 'dashboard-menu' }
        },
        {
          path: 'user',
          name: 'user.user',
          component: UserList,
          meta: { title: 'User List', requiresAuth: true, permission: 'user-list' }
        },
      ]
    }
  ],
})

router.beforeEach(async (to, from, next) => {
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

        // Restrict 'store' mode users from accessing buyer pages (app.*)
        if (authStore.activeMode === 'store' && to.name && to.name.toString().startsWith('app.')) {
          next({ name: 'user.dashboard', params: { username: authStore.user.username } })
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
    // Also check for non-auth routes if necessary, but 'app.home' is public?
    // If 'app.home' does not require auth, we still might want to restrict if the user IS logged in and IS in store mode.

    if (authStore.token && authStore.user && authStore.activeMode === 'store' && to.name && to.name.toString().startsWith('app.')) {
      next({ name: 'user.dashboard', params: { username: authStore.user.username } })
      return
    }

    next()
  }
})

router.afterEach((to, from) => {
  window.scrollTo(0, 0)
  const defaultTitle = 'Blue E-commerce'
  document.title = to.meta.title ? `${to.meta.title} | ${defaultTitle}` : defaultTitle
})

export default router
