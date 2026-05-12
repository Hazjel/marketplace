<script setup>
import { ref, onMounted } from 'vue'
import { formatRupiah, formatDate } from '@/helpers/format'
import { axiosInstance } from '@/plugins/axios'
import { RouterLink } from 'vue-router'
import defaultStoreImage from '@/assets/images/thumbnails/th-1.svg'
import defaultTransactionImage from '@/assets/images/thumbnails/th-4.svg'

// Dashboard data
const stats = ref({
  total_revenue: 0,
  total_revenue_fee: 0,
  total_sellers: 0,
  total_buyers: 0,
  total_products: 0,
  total_transactions: 0,
  total_stores: 0
})

const latestStores = ref([])
const latestTransactions = ref([])
const loading = ref(true)

// Fetch dashboard data
const fetchDashboardData = async () => {
  loading.value = true
  try {
    // We use Promise.allSettled so if one fails, the others still load
    const results = await Promise.allSettled([
      axiosInstance.get('store/all/paginated', { params: { row_per_page: 1 } }), // Index 0: Sellers (via Stores)
      axiosInstance.get('user/all/paginated', { params: { row_per_page: 1, roles: 'buyer' } }), // Index 1: Buyers (Filtered by role)
      axiosInstance.get('product/all/paginated', { params: { row_per_page: 1 } }), // Index 2: Products
      axiosInstance.get('transaction/all/paginated', { params: { row_per_page: 1 } }), // Index 3: Transactions
      axiosInstance.get('store/all/paginated', { params: { row_per_page: 1, is_verified: 1 } }) // Index 4: Stores
    ])

    // Helper to get count or default to 0
    const getCount = (result) => {
      return result.status === 'fulfilled' && result.value.data.data?.meta?.total
        ? result.value.data.data.meta.total
        : 0
    }

    stats.value = {
      total_revenue:
        results[3].status === 'fulfilled'
          ? results[3].value.data.data?.meta?.total_revenue || 0
          : 0,
      total_revenue_fee:
        results[3].status === 'fulfilled'
          ? results[3].value.data.data?.meta?.total_admin_fee || 0
          : 0,
      total_sellers: getCount(results[0]),
      total_buyers: getCount(results[1]),
      total_products: getCount(results[2]),
      total_transactions: getCount(results[3]),
      total_stores: getCount(results[4])
    }

    // Log failures for debugging
    if (results[0].status === 'rejected')
      console.warn('Failed to fetch sellers:', results[0].reason)
    if (results[1].status === 'rejected') console.warn('Failed to fetch buyers:', results[1].reason)
    if (results[2].status === 'rejected')
      console.warn('Failed to fetch products:', results[2].reason)
    if (results[3].status === 'rejected')
      console.warn('Failed to fetch transactions:', results[3].reason)

    // Fetch latest stores
    try {
      const storesResponse = await axiosInstance.get('store/all/paginated', {
        params: { row_per_page: 3, is_verified: 1, sort_by: 'created_at', sort_direction: 'desc' }
      })
      latestStores.value = storesResponse.data.data?.data || storesResponse.data.data || []
    } catch (e) {
      console.error('Failed to fetch latest stores:', e)
    }

    // Fetch latest transactions
    try {
      const transactionsResponse = await axiosInstance.get('transaction/all/paginated', {
        params: { row_per_page: 3, sort_by: 'created_at', sort_direction: 'desc' }
      })
      latestTransactions.value =
        transactionsResponse.data.data?.data || transactionsResponse.data.data || []
    } catch (e) {
      console.error('Failed to fetch latest transactions:', e)
    }
  } catch (error) {
    console.error('Critical Error fetching dashboard data:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDashboardData()
})
</script>

<template>
  <!-- Gradient Stat Cards - Top Row -->
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-5">
    <!-- Net Revenue -->
    <div class="relative overflow-hidden rounded-2xl p-6 bg-gradient-to-br from-[#2563EB] to-blue-700 text-white shadow-lg shadow-[#2563EB]/20">
      <div class="absolute top-0 right-0 w-28 h-28 bg-white/10 rounded-full -mr-8 -mt-8"></div>
      <div class="relative flex flex-col gap-4">
        <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-white/20 backdrop-blur-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />
          </svg>
        </div>
        <div>
          <p class="text-2xl font-bold">{{ loading ? '...' : `Rp ${formatRupiah(stats.total_revenue_fee)}` }}</p>
          <p class="text-sm font-medium text-white/70 mt-1">Pendapatan Bersih (Profit)</p>
        </div>
      </div>
    </div>

    <!-- Total GMV -->
    <div class="relative overflow-hidden rounded-2xl p-6 bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20">
      <div class="absolute top-0 right-0 w-28 h-28 bg-white/10 rounded-full -mr-8 -mt-8"></div>
      <div class="relative flex flex-col gap-4">
        <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-white/20 backdrop-blur-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
          </svg>
        </div>
        <div>
          <p class="text-2xl font-bold">{{ loading ? '...' : `Rp ${formatRupiah(stats.total_revenue)}` }}</p>
          <p class="text-sm font-medium text-white/70 mt-1">Total GMV</p>
        </div>
      </div>
    </div>

    <!-- Total Sellers -->
    <div class="relative overflow-hidden rounded-2xl p-6 bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/20">
      <div class="absolute top-0 right-0 w-28 h-28 bg-white/10 rounded-full -mr-8 -mt-8"></div>
      <div class="relative flex flex-col gap-4">
        <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-white/20 backdrop-blur-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
          </svg>
        </div>
        <div>
          <p class="text-2xl font-bold">{{ loading ? '...' : stats.total_sellers.toLocaleString() }}</p>
          <p class="text-sm font-medium text-white/70 mt-1">Total Seller</p>
        </div>
      </div>
    </div>

    <!-- Total Buyers -->
    <div class="relative overflow-hidden rounded-2xl p-6 bg-gradient-to-br from-orange-500 to-rose-500 text-white shadow-lg shadow-orange-500/20">
      <div class="absolute top-0 right-0 w-28 h-28 bg-white/10 rounded-full -mr-8 -mt-8"></div>
      <div class="relative flex flex-col gap-4">
        <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-white/20 backdrop-blur-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-1.053M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07m0 0a9.005 9.005 0 00-5.593-4.482M12 3a4.5 4.5 0 110 9 4.5 4.5 0 010-9z" />
          </svg>
        </div>
        <div>
          <p class="text-2xl font-bold">{{ loading ? '...' : stats.total_buyers.toLocaleString() }}</p>
          <p class="text-sm font-medium text-white/70 mt-1">Total Buyer</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom Section: Stores & Transactions -->
  <div class="flex flex-col gap-5 xl:flex-row">
    <!-- Left Column: Products + Stores -->
    <div class="flex flex-col gap-5 w-full xl:w-[440px] shrink-0">
      <!-- Products Stat -->
      <div class="rounded-2xl p-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm">
        <div class="flex flex-col gap-4">
          <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-[#2563EB]/10 dark:bg-[#2563EB]/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
            </svg>
          </div>
          <div>
            <p class="font-bold text-3xl text-gray-900 dark:text-white">{{ loading ? '...' : stats.total_products.toLocaleString() }}</p>
            <p class="font-medium text-gray-500 dark:text-gray-400 mt-1">Total Produk</p>
          </div>
        </div>
      </div>

      <!-- Stores -->
      <div class="flex flex-col flex-1 rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm">
        <div class="flex flex-col gap-4">
          <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-[#2563EB]/10 dark:bg-[#2563EB]/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0a2.998 2.998 0 01.75-1.916l1.875-2.376A1.5 1.5 0 016.81 4.5h10.38a1.5 1.5 0 011.185.568l1.875 2.376a2.998 2.998 0 01.75 1.916" />
            </svg>
          </div>
          <div>
            <p class="font-bold text-3xl text-gray-900 dark:text-white">{{ loading ? '...' : stats.total_stores.toLocaleString() }}</p>
            <p class="font-medium text-gray-500 dark:text-gray-400 mt-1">Total Toko</p>
          </div>
        </div>

        <hr class="border-gray-100 dark:border-white/10" />

        <div class="flex flex-col flex-1 gap-5">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">Toko Terbaru</h3>
          <div v-if="!loading && latestStores.length > 0" id="List-Stores" class="flex flex-col gap-4">
            <div
              v-for="store in latestStores" :key="store.id"
              class="flex flex-col rounded-2xl border border-gray-100 dark:border-white/10 p-4 gap-4 bg-white dark:bg-gray-900 hover:shadow-md transition-shadow">
              <div class="flex items-center gap-3">
                <div class="flex size-14 shrink-0 rounded-2xl bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-white/10">
                  <img
                    :src="store.logo || defaultStoreImage" class="size-full object-cover" alt="photo"
                    @error="$event.target.src = defaultStoreImage" />
                </div>
                <div class="flex flex-col gap-1 w-full overflow-hidden">
                  <p class="font-bold text-sm leading-tight w-full truncate text-gray-900 dark:text-white">
                    {{ store.name }}
                  </p>
                  <p class="flex items-center gap-1 font-medium text-gray-500 dark:text-gray-400 text-xs leading-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    {{ store.user?.name || 'Unknown User' }}
                  </p>
                </div>
              </div>
              <hr class="border-gray-100 dark:border-white/10" />
              <div class="flex items-center justify-between">
                <p class="flex items-center gap-1.5 font-medium text-gray-500 dark:text-gray-400 text-xs leading-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                  </svg>
                  {{ formatDate(store.created_at) }}
                </p>
                <RouterLink
                  v-if="store.id" :to="{ name: 'admin.store.detail', params: { id: store.id } }"
                  class="px-3 py-2 rounded-xl bg-[#2563EB]/10 dark:bg-[#2563EB]/20 hover:ring-2 hover:ring-[#2563EB] transition-all font-semibold text-[#2563EB] text-xs">
                  Detail
                </RouterLink>
                <span v-else class="font-semibold text-[#2563EB] cursor-not-allowed opacity-50 text-xs">
                  Detail
                </span>
              </div>
            </div>
          </div>
          <div
            v-else-if="!loading && latestStores.length === 0" id="Empty-State"
            class="flex flex-col flex-1 items-center justify-center gap-4 py-8">
            <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-white/10 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35" />
              </svg>
            </div>
            <p class="font-semibold text-gray-500 dark:text-gray-400 text-sm">Belum ada toko</p>
          </div>
          <div v-else-if="loading" class="flex items-center justify-center p-8">
            <p class="text-gray-500 dark:text-gray-400 text-sm">Memuat...</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Transactions -->
    <div class="flex flex-col w-full gap-5">
      <div class="flex flex-col flex-1 rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm">
        <div class="flex flex-col gap-4">
          <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-[#2563EB]/10 dark:bg-[#2563EB]/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
            </svg>
          </div>
          <div>
            <p class="font-bold text-3xl text-gray-900 dark:text-white">{{ loading ? '...' : stats.total_transactions.toLocaleString() }}</p>
            <p class="font-medium text-gray-500 dark:text-gray-400 mt-1">Total Transaksi</p>
          </div>
        </div>

        <hr class="border-gray-100 dark:border-white/10" />

        <div class="flex flex-col flex-1 gap-5">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">Transaksi Terbaru</h3>
          <div v-if="!loading && latestTransactions.length > 0" id="List-Transactions" class="flex flex-col gap-4">
            <div
              v-for="transaction in latestTransactions" :key="transaction.id"
              class="flex flex-col rounded-2xl border border-gray-100 dark:border-white/10 p-4 gap-4 bg-white dark:bg-gray-900 hover:shadow-md transition-shadow">
              <div class="flex items-center gap-3 w-full overflow-hidden">
                <div class="flex size-14 shrink-0 rounded-2xl bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-white/10">
                  <img
                    :src="transaction.transaction_details?.[0]?.product?.product_images?.[0]?.image || defaultTransactionImage"
                    class="size-full object-cover" alt="photo"
                    @error="$event.target.src = defaultTransactionImage" />
                </div>
                <div class="flex flex-col gap-1 w-full flex-grow-0 overflow-hidden">
                  <p class="font-bold text-sm leading-tight w-full truncate text-gray-900 dark:text-white">
                    {{ transaction.store?.name || 'Unknown Store' }}
                  </p>
                  <p class="flex items-center gap-1 font-medium text-gray-500 dark:text-gray-400 text-xs leading-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    {{ transaction.user?.name || 'Unknown Buyer' }}
                  </p>
                </div>
                <div class="flex flex-col gap-1 items-end shrink-0">
                  <p class="font-bold text-sm leading-tight text-[#2563EB] dark:text-blue-400 text-nowrap">
                    Rp {{ formatRupiah(transaction.total_price || transaction.grand_total) }}
                  </p>
                  <p class="text-xs font-medium text-gray-500 dark:text-gray-400 text-nowrap">
                    Grand Total
                  </p>
                </div>
              </div>
              <hr class="border-gray-100 dark:border-white/10" />
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="flex size-10 shrink-0 rounded-xl bg-gray-100 dark:bg-gray-800 items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                  </div>
                  <div class="flex flex-col">
                    <p class="font-bold text-sm leading-none text-gray-900 dark:text-white">
                      {{ transaction.transaction_details?.length || 0 }}
                    </p>
                    <p class="font-medium text-gray-500 dark:text-gray-400 text-xs mt-0.5">Total Produk</p>
                  </div>
                </div>
                <RouterLink
                  v-if="transaction.id"
                  :to="{ name: 'admin.transaction.detail', params: { id: transaction.id } }"
                  class="px-3 py-2 rounded-xl bg-[#2563EB]/10 dark:bg-[#2563EB]/20 hover:ring-2 hover:ring-[#2563EB] transition-all font-semibold text-[#2563EB] text-xs">
                  Detail
                </RouterLink>
              </div>
            </div>
          </div>
          <div
            v-else-if="!loading && latestTransactions.length === 0" id="Empty-State"
            class="flex flex-col flex-1 items-center justify-center gap-4 py-8">
            <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-white/10 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08" />
              </svg>
            </div>
            <p class="font-semibold text-gray-500 dark:text-gray-400 text-sm">Belum ada transaksi</p>
          </div>
          <div v-else-if="loading" class="flex items-center justify-center p-8">
            <p class="text-gray-500 dark:text-gray-400 text-sm">Memuat...</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
