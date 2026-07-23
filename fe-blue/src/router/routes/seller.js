import { useAuthStore } from '@/stores/auth'
import Admin from '@/layouts/Admin.vue'
import CategoryList from '@/views/admin/category/CategoryList.vue'
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

// Route platform-admin (role admin, mengelola SELURUH marketplace) --
// tetap prefix /admin, cuma valid untuk role admin platform (bukan seller
// toko biasa). Ada di build seller karena admin butuh akses backoffice.
const adminRoutes = [
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
        meta: { title: 'Category Edit', requiresAuth: true, permission: 'product-category-edit' }
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
        meta: {
          title: 'Store Wallet Detail',
          requiresAuth: true,
          permission: 'store-balance-list'
        }
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
        meta: {
          title: ' Request Withdrawal Create',
          requiresAuth: true,
          permission: 'withdrawal-create'
        }
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
      }
    ]
  }
]

// Route toko (role store) -- murni manajemen toko: produk, pesanan masuk,
// wallet, chat dengan pembeli. TIDAK ADA fitur pribadi buyer (settings,
// riwayat belanja) di sini -- itu tetap di buyer.js/blukios.store, persis
// seperti Shopee Seller Centre yang tidak punya akses riwayat belanja
// pribadi sang seller.
const storeRoutes = [
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
        meta: {
          title: 'Category List',
          requiresAuth: true,
          permission: 'product-category-list',
          requiresStore: true
        }
      },
      {
        path: 'category/create',
        name: 'user.category.create',
        component: CategoryCreate,
        meta: {
          title: 'Category Create',
          requiresAuth: true,
          permission: 'product-category-create',
          requiresStore: true
        }
      },
      {
        path: 'category/edit/:id',
        name: 'user.category.edit',
        component: CategoryEdit,
        meta: {
          title: 'Category Edit',
          requiresAuth: true,
          permission: 'product-category-edit',
          requiresStore: true
        }
      },
      {
        path: 'category/:id',
        name: 'user.category.detail',
        component: CategoryDetail,
        meta: {
          title: 'Category Detail',
          requiresAuth: true,
          permission: 'product-category-list',
          requiresStore: true
        }
      },
      {
        path: 'product',
        name: 'user.product',
        component: ProductList,
        meta: {
          title: 'Product Detail',
          requiresAuth: true,
          permission: 'product-list',
          requiresStore: true
        }
      },
      {
        path: 'product/create',
        name: 'user.product.create',
        component: ProductCreate,
        meta: {
          title: 'Product Create',
          requiresAuth: true,
          permission: 'product-create',
          requiresStore: true
        }
      },
      {
        path: 'product/:id',
        name: 'user.product.detail',
        component: ProductDetail,
        meta: {
          title: 'Product Detail',
          requiresAuth: true,
          permission: 'product-list',
          requiresStore: true
        }
      },
      {
        path: 'product/edit/:id',
        name: 'user.product.edit',
        component: ProductEdit,
        meta: {
          title: 'Product Edit',
          requiresAuth: true,
          permission: 'product-edit',
          requiresStore: true
        }
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
        meta: {
          title: 'My Store',
          requiresAuth: true,
          permission: 'store-list',
          requiresStore: true
        }
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
        meta: {
          title: 'Edit My Store',
          requiresAuth: true,
          permission: 'store-edit',
          requiresStore: true
        }
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
        meta: {
          title: 'Store Wallet',
          requiresAuth: true,
          permission: 'store-balance-list',
          requiresStore: true
        }
      },
      {
        path: 'store-balance/:id',
        name: 'user.store-balance.detail',
        component: StoreBalanceDetail,
        meta: {
          title: 'Store Wallet Detail',
          requiresAuth: true,
          permission: 'store-balance-list',
          requiresStore: true
        }
      },
      {
        path: 'my-store-balance',
        name: 'user.my-store-balance',
        component: MyStoreBalance,
        meta: {
          title: 'Manage My Wallet',
          requiresAuth: true,
          permission: 'store-balance-list',
          requiresStore: true
        }
      },
      {
        path: 'orders/incoming',
        name: 'admin.orders.incoming',
        component: () => import('@/views/admin/order/IncomingOrders.vue'),
        meta: { title: 'Incoming Orders', requiresAuth: true, permission: 'transaction-list' }
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
        meta: {
          title: ' Request Withdrawal Create',
          requiresAuth: true,
          permission: 'withdrawal-create'
        }
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
      }
    ]
  }
]

// Seller app tidak punya "homepage" publik seperti buyer app -- akses ke
// root langsung diarahkan ke dashboard yang sesuai (atau login kalau belum
// ada sesi). Tanpa ini path '/' tidak match route manapun, Vue Router
// render blank (bukan error/crash, cuma tidak ada apa-apa buat ditampilkan).
const rootRedirect = [
  {
    path: '/',
    name: 'root',
    component: { render: () => null },
    beforeEnter: async (to, from, next) => {
      const authStore = useAuthStore()

      if (authStore.token && !authStore.user) {
        try {
          await authStore.checkAuth()
        } catch (e) {
          // Token tidak valid, treat sebagai guest
        }
      }

      if (authStore.user?.role === 'admin') {
        next({ name: 'admin.dashboard' })
      } else if (authStore.user?.username) {
        next({ name: 'user.dashboard', params: { username: authStore.user.username } })
      } else {
        next({ name: 'auth.login' })
      }
    }
  }
]

export const sellerRoutes = [...adminRoutes, ...storeRoutes, ...rootRedirect]
