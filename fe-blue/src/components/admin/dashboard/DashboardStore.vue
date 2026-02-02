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
    <div class="h-10 w-48 bg-gray-200 rounded-lg"></div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
      <div v-for="i in 3" :key="i" class="h-40 rounded-[20px] bg-gray-200"></div>
    </div>
    <div class="flex flex-col md:flex-row gap-5">
      <div class="flex-1 h-96 rounded-[20px] bg-gray-200"></div>
      <div class="w-full md:w-[350px] h-96 rounded-[20px] bg-gray-200"></div>
    </div>
  </div>

  <!-- Actual Content -->
  <div v-else class="flex flex-col gap-8">
    <!-- Header -->
    <div class="flex flex-col gap-1">
      <h1 class="font-bold text-2xl md:text-3xl text-custom-black">Overview</h1>
      <p class="text-custom-grey">
        Welcome back, {{ user?.store?.name }}! Here's what's happening today.
      </p>
    </div>

    <!-- 3-Column Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
      <AnalyticsCard
        title="Total Revenue"
        :value="`Rp ${formatRupiah(storeBalance?.balance || 0)}`"
        icon="wallet-2-blue-fill.svg"
        :trend="{ value: '12%', direction: 'up' }"
      />
      <AnalyticsCard
        title="Total Orders"
        :value="stats.total_transactions"
        icon="shopping-cart-blue-fill.svg"
        :trend="{ value: '8%', direction: 'up' }"
      />
      <AnalyticsCard
        title="Product Reviews"
        :value="stats.total_reviews"
        icon="message-text-blue-fill.svg"
      />
    </div>

    <!-- Main Content 2-Col Layout -->
    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Left: Charts & Tables -->
      <div class="flex flex-col flex-1 gap-6 min-w-0">
        <!-- Revenue Chart -->
        <div class="flex flex-col w-full rounded-[20px] p-6 gap-6 bg-white border border-gray-100">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-bold text-xl text-custom-black">Revenue Analytics</h3>
              <p class="text-custom-grey text-sm">Income in the last 7 days</p>
            </div>
            <div class="flex gap-2">
              <button class="px-3 py-1 text-xs font-bold bg-gray-100 rounded-lg text-custom-black">
                Weekly
              </button>
            </div>
          </div>
          <RevenueChart :data="chartData" />
        </div>

        <!-- Recent Transactions -->
        <div class="flex flex-col w-full rounded-[20px] p-6 gap-6 bg-white border border-gray-100">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-xl text-custom-black">Recent Orders</h3>
            <RouterLink
              :to="{ name: 'user.transaction', params: { username: user?.username } }"
              class="text-sm font-bold text-custom-blue"
              >View All</RouterLink
            >
          </div>

          <div
            v-if="latestTransactions.length > 0"
            id="List-Transactions"
            class="flex flex-col gap-4"
          >
            <div
              v-for="transaction in latestTransactions.slice(0, 3)"
              :key="transaction.id"
              class="flex flex-col md:flex-row md:items-center justify-between p-4 rounded-xl bg-gray-50 border border-gray-100 gap-4"
            >
              <div class="flex items-center gap-4">
                <div
                  class="size-12 rounded-full bg-white flex items-center justify-center shrink-0 border border-gray-200"
                >
                  <img src="@/assets/images/icons/bag-tick-blue-transparent.svg" class="size-6" />
                </div>
                <div class="flex flex-col">
                  <span class="font-bold text-custom-black"
                    >Order #{{ transaction.code || transaction.id.substr(0, 8) }}</span
                  >
                  <span class="text-xs text-custom-grey"
                    >{{ formatDate(transaction.created_at) }} •
                    {{ transaction.buyer?.user?.name || 'Buyer' }}</span
                  >
                </div>
              </div>
              <div class="flex items-center justify-between md:justify-end gap-6 w-full md:w-auto">
                <span class="font-bold text-custom-blue"
                  >Rp {{ formatRupiah(transaction.grand_total) }}</span
                >
                <div
                  class="px-3 py-1 rounded-full text-xs font-bold capitalize"
                  :class="{
                    'bg-green-100 text-custom-green': transaction.status === 'success',
                    'bg-yellow-100 text-custom-orange': transaction.status === 'pending',
                    'bg-red-100 text-custom-red': transaction.status === 'failed'
                  }"
                >
                  {{ transaction.status }}
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-custom-grey text-sm">No recent transactions</div>
        </div>
      </div>

      <!-- Right: Sidebar Widgets -->
      <div class="flex flex-col w-full lg:w-[320px] shrink-0 gap-6">
        <!-- Action Widget -->
        <ActionWidget :actions="smartActions" />

        <!-- Top Products (Simplified) -->
        <div class="flex flex-col w-full rounded-[20px] bg-white border border-gray-100 p-5 gap-4">
          <h3 class="font-bold text-lg text-custom-black">Top Products</h3>
          <!-- Mock List -->
          <div class="flex flex-col gap-4">
            <div
              v-for="(product, index) in topProducts"
              v-if="topProducts.length > 0"
              :key="product.id"
              class="flex items-center gap-3"
            >
              <div
                class="size-12 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center text-xs font-bold text-gray-400"
              >
                {{ index + 1 }}
              </div>
              <div class="flex flex-col flex-1 min-w-0">
                <span class="font-bold text-sm truncate text-custom-black" :title="product.name">{{
                  product.name
                }}</span>
                <span class="text-xs text-custom-grey">{{ product.sold || 0 }} Sold</span>
              </div>
              <span class="text-xs font-bold text-custom-green"
                >Rp {{ formatRupiah(product.price) }}</span
              >
            </div>
            <div v-else class="text-center py-4 text-custom-grey text-xs">No sales data yet</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
