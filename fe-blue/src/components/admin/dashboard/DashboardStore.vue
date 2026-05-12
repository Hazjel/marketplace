<script setup>
import { onMounted, ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useTransactionStore } from '@/stores/transaction'
import { useProductStore } from '@/stores/product'
import { useStoreBalanceStore } from '@/stores/storeBalance'
import { storeToRefs } from 'pinia'
import { formatRupiah, formatDate } from '@/helpers/format'
import { axiosInstance } from '@/plugins/axios'
import { RouterLink } from 'vue-router'

import RevenueChart from './RevenueChart.vue'
import AnalyticsCard from '@/components/Atom/AnalyticsCard.vue'
import ActionWidget from '@/components/Molecule/Seller/ActionWidget.vue'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const transactionStore = useTransactionStore()
const productStore = useProductStore()
const storeBalanceStore = useStoreBalanceStore()
const { fetchStoreBalanceByStore } = storeBalanceStore
const { fetchChartData } = transactionStore

// Local ref since store doesn't keep single balance in state
const storeBalance = ref(null)
const chartData = ref([])

const stats = ref({
  total_products: 0,
  total_transactions: 0,
  total_reviews: 0,
  total_buyers: 0
})

const latestTransactions = ref([])
const latestReviews = ref([])
const topProducts = ref([])
const loading = ref(true)

// Computed Actions for Widget
const smartActions = computed(() => {
  const actions = []

  // Example Logic: Pending Orders (mocked for now as we don't have status count api)
  // In real app, we'd fetch count of 'pending' status
  // For now, let's assume 10% of total transactions are pending if > 0
  const pendingCount = Math.ceil(stats.value.total_transactions * 0.1)
  if (pendingCount > 0) {
    actions.push({
      label: 'Pending Shipments',
      count: pendingCount,
      icon: 'box-tick-blue-transparent.svg',
      route: { name: 'user.transaction', params: { username: user.value?.username } }
    })
  }

  // Low Stock (mocked)
  // actions.push({ label: 'Low Stock Items', count: 3, icon: 'box-remove-red.svg', route: { name: 'store.product' } });

  // Unread Reviews (mocked)
  if (stats.value.total_reviews > 0) {
    actions.push({
      label: 'New Reviews',
      count: 2,
      icon: 'message-text-blue-fill.svg',
      route: { name: 'user.product', params: { username: user.value?.username } }
    })
  }

  return actions
})

const fetchData = async () => {
  loading.value = true
  try {
    const storeId = user.value?.store?.id

    if (!storeId) return

    // Fetch all data in parallel
    const [chartDataRes, balanceRes, productsRes, transactionsRes, reviewsRes, topProductsRes] =
      await Promise.all([
        fetchChartData(),
        fetchStoreBalanceByStore(),
        productStore.fetchProductsPaginated({ row_per_page: 1, store_id: storeId }),
        transactionStore.fetchTransactionsPaginated({
          row_per_page: 5,
          sort_by: 'created_at',
          sort_direction: 'desc'
        }),
        axiosInstance.get('product-review/all/paginated', {
          params: {
            row_per_page: 5,
            store_id: storeId,
            sort_by: 'created_at',
            sort_direction: 'desc'
          }
        }),
        axiosInstance.get('product/all/paginated', {
          params: {
            row_per_page: 5,
            store_id: storeId,
            sort_by: 'sold',
            sort_direction: 'desc'
          }
        })
      ])

    // 1. Chart Data
    chartData.value = chartDataRes

    // 2. Total Revenue (Balance)
    storeBalance.value = balanceRes

    // 3. Total Products
    const { meta: productMeta } = storeToRefs(productStore)
    stats.value.total_products = productMeta.value?.total || 0

    // 4. Total Transactions
    const { meta: transactionMeta, transactions: transactionData } = storeToRefs(transactionStore)
    stats.value.total_transactions = transactionMeta.value?.total || 0
    latestTransactions.value = transactionData.value || []

    // 5. Total Reviews
    if (reviewsRes.data.data) {
      if (Array.isArray(reviewsRes.data.data)) {
        latestReviews.value = reviewsRes.data.data
        stats.value.total_reviews = reviewsRes.data.meta?.total || latestReviews.value.length
      } else {
        stats.value.total_reviews = reviewsRes.data.data.meta?.total || 0
        latestReviews.value = reviewsRes.data.data.data || []
      }
    }

    // 6. Top Products
    if (topProductsRes.data.data) {
      topProducts.value = topProductsRes.data.data.data || []
    }
  } catch (error) {
    console.error('Error fetching dashboard data:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <!-- Skeleton Loader -->
  <div v-if="loading" class="animate-pulse flex flex-col gap-6">
    <div class="h-10 w-48 bg-gray-200 dark:bg-white/10 rounded-lg"></div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
      <div v-for="i in 3" :key="i" class="h-40 rounded-2xl bg-gray-200 dark:bg-white/10"></div>
    </div>
    <div class="flex flex-col md:flex-row gap-5">
      <div class="flex-1 h-96 rounded-2xl bg-gray-200 dark:bg-white/10"></div>
      <div class="w-full md:w-[350px] h-96 rounded-2xl bg-gray-200 dark:bg-white/10"></div>
    </div>
  </div>

  <!-- Actual Content -->
  <div v-else class="flex flex-col gap-8">
    <!-- Header -->
    <div class="flex flex-col gap-1">
      <h1 class="font-bold text-2xl md:text-3xl text-gray-900 dark:text-white font-['Plus_Jakarta_Sans']">Ringkasan</h1>
      <p class="text-gray-500 dark:text-gray-400">
        Selamat datang kembali, {{ user?.store?.name }}! Berikut perkembangan hari ini.
      </p>
    </div>

    <!-- 3-Column Gradient Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
      <!-- Revenue Card -->
      <div class="relative overflow-hidden rounded-2xl p-6 bg-gradient-to-br from-[#2563EB] to-blue-700 text-white shadow-lg shadow-[#2563EB]/20">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-10 -mt-10"></div>
        <div class="relative flex flex-col gap-4">
          <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-white/80">Total Pendapatan</p>
            <p class="text-2xl font-bold mt-1">Rp {{ formatRupiah(storeBalance?.balance || 0) }}</p>
          </div>
          <div class="flex items-center gap-1 text-xs font-medium text-emerald-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
            </svg>
            <span>+12% dari minggu lalu</span>
          </div>
        </div>
      </div>

      <!-- Orders Card -->
      <div class="relative overflow-hidden rounded-2xl p-6 bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-10 -mt-10"></div>
        <div class="relative flex flex-col gap-4">
          <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-white/80">Total Pesanan</p>
            <p class="text-2xl font-bold mt-1">{{ stats.total_transactions }}</p>
          </div>
          <div class="flex items-center gap-1 text-xs font-medium text-emerald-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
            </svg>
            <span>+8% dari minggu lalu</span>
          </div>
        </div>
      </div>

      <!-- Reviews Card -->
      <div class="relative overflow-hidden rounded-2xl p-6 bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/20">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-10 -mt-10"></div>
        <div class="relative flex flex-col gap-4">
          <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-white/80">Ulasan Produk</p>
            <p class="text-2xl font-bold mt-1">{{ stats.total_reviews }}</p>
          </div>
          <div class="flex items-center gap-1 text-xs font-medium text-purple-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
            </svg>
            <span>Rating rata-rata 4.8</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content 2-Col Layout -->
    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Left: Charts & Tables -->
      <div class="flex flex-col flex-1 gap-6 min-w-0">
        <!-- Revenue Chart -->
        <div
          class="flex flex-col w-full rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-bold text-xl text-gray-900 dark:text-white">Analitik Pendapatan</h3>
              <p class="text-gray-500 dark:text-gray-400 text-sm">Pemasukan 7 hari terakhir</p>
            </div>
            <div class="flex gap-2">
              <button
                class="px-3 py-1.5 text-xs font-bold bg-[#2563EB]/10 dark:bg-[#2563EB]/20 rounded-lg text-[#2563EB]">
                Mingguan
              </button>
            </div>
          </div>
          <RevenueChart :data="chartData" />
        </div>

        <!-- Recent Transactions -->
        <div
          class="flex flex-col w-full rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-xl text-gray-900 dark:text-white">Pesanan Terbaru</h3>
            <RouterLink
              :to="{ name: 'user.transaction', params: { username: user?.username } }"
              class="text-sm font-bold text-[#2563EB] hover:underline">Lihat Semua</RouterLink>
          </div>

          <div v-if="latestTransactions.length > 0" id="List-Transactions" class="flex flex-col gap-4">
            <div
              v-for="transaction in latestTransactions.slice(0, 3)" :key="transaction.id"
              class="flex flex-col md:flex-row md:items-center justify-between p-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-white/10 gap-4">
              <div class="flex items-center gap-4">
                <div
                  class="size-12 rounded-xl bg-white dark:bg-gray-700 flex items-center justify-center shrink-0 border border-gray-200 dark:border-white/10">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                  </svg>
                </div>
                <div class="flex flex-col">
                  <span class="font-bold text-sm text-gray-900 dark:text-white">Order #{{ transaction.code ||
                    transaction.id.substr(0, 8) }}</span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(transaction.created_at) }} &bull;
                    {{ transaction.buyer?.user?.name || 'Buyer' }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between md:justify-end gap-4 w-full md:w-auto">
                <span class="font-bold text-sm text-[#2563EB] dark:text-blue-400">Rp {{ formatRupiah(transaction.grand_total)
                }}</span>
                <div
                  class="px-2.5 py-1 rounded-full text-xs font-bold capitalize ring-1" :class="{
                  'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:ring-emerald-500/30': transaction.status === 'success',
                  'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-400 dark:ring-amber-500/30': transaction.status === 'pending',
                  'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/20 dark:text-red-400 dark:ring-red-500/30': transaction.status === 'failed'
                }">
                  {{ transaction.status }}
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-10 text-gray-500 dark:text-gray-400 text-sm">Belum ada transaksi</div>
        </div>
      </div>

      <!-- Right: Sidebar Widgets -->
      <div class="flex flex-col w-full lg:w-[320px] shrink-0 gap-6">
        <!-- Action Widget -->
        <ActionWidget :actions="smartActions" />

        <!-- Top Products -->
        <div
          class="flex flex-col w-full rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 p-5 gap-4 shadow-sm">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">Produk Terlaris</h3>
          <div class="flex flex-col gap-4">
            <template v-if="topProducts.length > 0">
              <div v-for="(product, index) in topProducts" :key="product.id" class="flex items-center gap-3">
                <div
                  class="size-10 rounded-xl bg-gradient-to-br from-[#2563EB]/10 to-purple-500/10 dark:from-[#2563EB]/20 dark:to-purple-500/20 border border-gray-100 dark:border-white/10 flex items-center justify-center text-xs font-bold text-[#2563EB]">
                  {{ index + 1 }}
                </div>
                <div class="flex flex-col flex-1 min-w-0">
                  <span class="font-bold text-sm truncate text-gray-900 dark:text-white" :title="product.name">{{
                    product.name }}</span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ product.sold || 0 }} Terjual</span>
                </div>
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Rp {{ formatRupiah(product.price)
                  }}</span>
              </div>
            </template>
            <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400 text-xs">
              Belum ada data penjualan
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
