import { useAuthStore } from '@/stores/auth'
import App from '@/layouts/App.vue'
import Admin from '@/layouts/Admin.vue'
import Home from '@/views/App/Home.vue'
import BrowseCategory from '@/views/App/BrowseCategory.vue'
import AppProductDetail from '@/views/App/ProductDetail.vue'
import AppStoreDetail from '@/views/App/StoreDetail.vue'
import Wishlist from '@/views/App/Wishlist.vue'
import Cart from '@/views/App/Cart.vue'
import Checkout from '@/views/App/Checkout.vue'
import TransactionList from '@/views/admin/transaction/TransactionList.vue'
import TransactionDetail from '@/views/admin/transaction/TransactionDetail.vue'
import MyTransaction from '@/views/admin/transaction/MyTransaction.vue'

// Route publik marketplace (browse, cart, checkout) + dashboard pribadi
// buyer (settings, riwayat belanja, wishlist, chat sebagai pembeli).
// Build ini SAMA SEKALI tidak punya route manage-toko -- pindah toko
// (auth.open-store, buyer.js) mengarahkan ke seller app lewat SSO.
export const buyerRoutes = [
  {
    path: '/',
    component: App,
    beforeEnter: async (to, from, next) => {
      const authStore = useAuthStore()

      if (authStore.token && !authStore.user) {
        try {
          await authStore.checkAuth()
        } catch (e) {
          // Token might be invalid, treat as guest
        }
      }

      // Admin platform tidak punya urusan di buyer app -- backoffice-nya
      // ada di seller.blukios.store/admin/*. Redirect via SSO (bukan
      // router.push, karena admin.dashboard tidak ada di build ini).
      // window.location.href di initiateSso() akan menghentikan navigasi
      // SPA ini begitu browser pindah halaman -- next() di bawah tidak
      // pernah "salah" dipanggil karena page sudah dalam proses unload.
      if (authStore.user?.role === 'admin') {
        await authStore.initiateSso(import.meta.env.VITE_SELLER_APP_URL)
        return
      }

      next()
    },
    children: [
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
        meta: { requiresAuth: true }
      },
      {
        path: '/wishlist',
        name: 'app.wishlist',
        component: Wishlist,
        meta: { requiresAuth: true, title: 'My Wishlist' }
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
        component: () => import('@/views/App/AllProducts.vue')
      },
      {
        path: '/search',
        name: 'app.search',
        component: () => import('@/views/App/SearchResults.vue')
      }
    ]
  },
  {
    path: '/',
    component: () => import('@/layouts/Company.vue'),
    children: [
      {
        path: 'about',
        name: 'app.about',
        component: () => import('@/views/App/Company/AboutUs.vue'),
        meta: { title: 'About Us' }
      },
      {
        path: 'career',
        name: 'app.career',
        component: () => import('@/views/App/Company/Career.vue'),
        meta: { title: 'Career' }
      },
      {
        path: 'privacy',
        name: 'app.privacy',
        component: () => import('@/views/App/Company/PrivacyPolicy.vue'),
        meta: { title: 'Privacy Policy' }
      },
      {
        path: 'terms',
        name: 'app.terms',
        component: () => import('@/views/App/Company/TermsConditions.vue'),
        meta: { title: 'Terms & Conditions' }
      }
    ]
  },
  {
    // Dashboard pribadi buyer -- settings, riwayat belanja, chat sebagai
    // pembeli. TIDAK ADA route manage-toko di sini (sudah pindah ke
    // seller.js, dipakai build seller).
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
        path: 'settings/address',
        name: 'user.settings.address',
        component: () => import('@/views/user/settings/AddressList.vue'),
        meta: { title: 'My Addresses', requiresAuth: true }
      },
      {
        path: 'settings/address/create',
        name: 'user.settings.address.create',
        component: () => import('@/views/user/settings/AddressForm.vue'),
        meta: { title: 'Add Address', requiresAuth: true }
      },
      {
        path: 'settings/address/:id/edit',
        name: 'user.settings.address.edit',
        component: () => import('@/views/user/settings/AddressForm.vue'),
        meta: { title: 'Edit Address', requiresAuth: true }
      },
      {
        path: 'settings/notifications',
        name: 'user.settings.notifications',
        component: () => import('@/views/user/settings/NotificationSettings.vue'),
        meta: { title: 'Notifikasi', requiresAuth: true }
      },
      {
        path: 'settings/privacy',
        name: 'user.settings.privacy',
        component: () => import('@/views/user/settings/PrivacySettings.vue'),
        meta: { title: 'Privasi', requiresAuth: true }
      },
      {
        path: 'settings/delete-account',
        name: 'user.settings.delete-account',
        component: () => import('@/views/user/settings/DeleteAccount.vue'),
        meta: { title: 'Hapus Akun', requiresAuth: true }
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
        path: 'chat',
        name: 'user.chat',
        component: () => import('@/views/App/Chat/ChatLayout.vue'),
        meta: { title: 'Conversations', requiresAuth: true, permission: 'dashboard-menu' }
      }
    ]
  }
]
