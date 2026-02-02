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
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-5">
    <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
      <div class="flex flex-col gap-6">
        <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
          <img
            src="@/assets/images/icons/wallet-2-blue-fill.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </div>
        <div class="flex flex-col gap-[6px]">
          <p class="font-bold text-2xl md:text-4xl">
            {{ loading ? '...' : `Rp ${formatRupiah(stats.total_revenue_fee)}` }}
          </p>
          <p class="font-medium text-sm md:text-lg text-custom-grey">Net Revenue (Profit)</p>
        </div>
      </div>
    </div>
    <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
      <div class="flex flex-col gap-6">
        <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
          <img
            src="@/assets/images/icons/card-blue-fill.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </div>
        <div class="flex flex-col gap-[6px]">
          <p class="font-bold text-2xl md:text-4xl">
            {{ loading ? '...' : `Rp ${formatRupiah(stats.total_revenue)}` }}
          </p>
          <p class="font-medium text-sm md:text-lg text-custom-grey">Total GMV</p>
        </div>
      </div>
    </div>
    <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
      <div class="flex flex-col gap-6">
        <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
          <img
            src="@/assets/images/icons/profile-tick-blue-fill.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </div>
        <div class="flex flex-col gap-[6px]">
          <p class="font-bold text-2xl md:text-4xl">
            {{ loading ? '...' : stats.total_sellers.toLocaleString() }}
          </p>
          <p class="font-medium text-sm md:text-lg text-custom-grey">Total Sellers</p>
        </div>
      </div>
    </div>
    <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
      <div class="flex flex-col gap-6">
        <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
          <img
            src="@/assets/images/icons/profile-2user-blue-fill.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </div>
        <div class="flex flex-col gap-[6px]">
          <p class="font-bold text-2xl md:text-4xl">
            {{ loading ? '...' : stats.total_buyers.toLocaleString() }}
          </p>
          <p class="font-medium text-sm md:text-lg text-custom-grey">Total Buyers</p>
        </div>
      </div>
    </div>
  </div>
  <div class="flex flex-col gap-5 xl:flex-row">
    <div class="flex flex-col gap-5 w-full xl:w-[440px] shrink-0">
      <div class="flex flex-col w-full shrink-0 rounded-[20px] p-5 gap-6 bg-white">
        <div class="flex flex-col gap-6">
          <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
            <img
              src="@/assets/images/icons/shopping-cart-blue-fill.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-[6px]">
            <p class="font-bold text-4xl">
              {{ loading ? '...' : stats.total_products.toLocaleString() }}
            </p>
            <p class="font-medium text-lg text-custom-grey">Total Products</p>
          </div>
        </div>
      </div>
      <div class="flex flex-col flex-1 w-full shrink-0 rounded-[20px] p-5 gap-6 bg-white">
        <div class="flex flex-col gap-6">
          <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
            <img
              src="@/assets/images/icons/shop-blue-fill.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-[6px]">
            <p class="font-bold text-4xl">
              {{ loading ? '...' : stats.total_stores.toLocaleString() }}
            </p>
            <p class="font-medium text-lg text-custom-grey">Total Stores</p>
          </div>
        </div>
        <hr class="border-custom-stroke" />
        <div class="flex flex-col flex-1 gap-5">
          <p class="font-bold text-xl">Latest Stores</p>
          <div
            v-if="!loading && latestStores.length > 0"
            id="List-Stores"
            class="flex flex-col gap-5"
          >
            <div
              v-for="store in latestStores"
              :key="store.id"
              class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white"
            >
              <div class="flex items-center gap-[14px]">
                <div
                  class="flex size-16 shrink-0 rounded-[20px] bg-custom-background overflow-hidden"
                >
                  <img
                    :src="store.logo || defaultStoreImage"
                    class="size-full object-cover"
                    alt="photo"
                    @error="$event.target.src = defaultStoreImage"
                  />
                </div>
                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                  <p class="font-bold text-lg leading-tight w-full truncate">
                    {{ store.name }}
                  </p>
                  <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon" />
                    {{ store.user?.name || 'Unknown User' }}
                  </p>
                </div>
              </div>
              <hr class="border-custom-stroke" />
              <div class="flex items-center justify-between">
                <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                  <img
                    src="@/assets/images/icons/calendar-2-grey.svg"
                    class="size-6 flex shrink-0"
                    alt="icon"
                  />
                  Created on {{ formatDate(store.created_at) }}
                </p>
                <RouterLink
                  v-if="store.id"
                  :to="{ name: 'admin.store.detail', params: { id: store.id } }"
                  class="flex w-[96px] h-[56px] shrink-0 rounded-2xl bg-custom-blue/10 hover:ring-2 hover:ring-custom-blue transition-300 font-semibold text-custom-blue leading-none items-center justify-center text-center"
                >
                  View Details
                </RouterLink>
                <span v-else class="font-semibold text-custom-blue cursor-not-allowed opacity-50">
                  View Details
                </span>
              </div>
            </div>
          </div>
          <div
            v-else-if="!loading && latestStores.length === 0"
            id="Empty-State"
            class="flex flex-col flex-1 items-center justify-center gap-4"
          >
            <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon" />
            <div class="flex flex-col gap-1 items-center text-center">
              <p class="font-semibold text-custom-grey">No stores available yet</p>
            </div>
          </div>
          <div v-else-if="loading" class="flex items-center justify-center p-8">
            <p class="text-custom-grey">Loading...</p>
          </div>
        </div>
      </div>
    </div>
    <div class="flex flex-col w-full gap-5">
      <div class="flex flex-col flex-1 w-full shrink-0 rounded-[20px] p-5 gap-6 bg-white">
        <div class="flex flex-col gap-6">
          <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
            <img
              src="@/assets/images/icons/note-text-blue-fill.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-[6px]">
            <p class="font-bold text-4xl">
              {{ loading ? '...' : stats.total_transactions.toLocaleString() }}
            </p>
            <p class="font-medium text-lg text-custom-grey">Total Transaction</p>
          </div>
        </div>
        <hr class="border-custom-stroke" />
        <div class="flex flex-col flex-1 gap-5">
          <p class="font-bold text-xl">Latest Transactions</p>
          <div
            v-if="!loading && latestTransactions.length > 0"
            id="List-Transactions"
            class="flex flex-col gap-5"
          >
            <div
              v-for="transaction in latestTransactions"
              :key="transaction.id"
              class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white"
            >
              <div class="flex items-center gap-[14px] w-full overflow-hidden">
                <div
                  class="flex size-16 shrink-0 rounded-[20px] bg-custom-background overflow-hidden"
                >
                  <!-- Added more robust image handling -->
                  <img
                    :src="
                      transaction.transaction_details?.[0]?.product?.product_images?.[0]?.image ||
                      defaultTransactionImage
                    "
                    class="size-full object-cover"
                    alt="photo"
                    @error="$event.target.src = defaultTransactionImage"
                  />
                </div>
                <div class="flex flex-col gap-[6px] w-full flex-grow-0 overflow-hidden">
                  <p class="font-bold text-lg leading-tight w-full">
                    {{ transaction.store?.name || 'Unknown Store' }}
                  </p>
                  <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon" />
                    {{ transaction.user?.name || 'Unknown Buyer' }}
                  </p>
                </div>
                <div class="flex flex-col gap-2 items-end">
                  <p class="font-bold text-lg leading-tight text-custom-blue text-nowrap">
                    Rp {{ formatRupiah(transaction.total_price || transaction.grand_total) }}
                  </p>
                  <p
                    class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-nowrap"
                  >
                    Grand Total
                  </p>
                </div>
              </div>
              <hr class="border-custom-stroke" />
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-[10px]">
                  <div
                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
                  >
                    <img
                      src="@/assets/images/icons/shopping-cart-black.svg"
                      class="flex size-6 shrink-0"
                      alt="icon"
                    />
                  </div>
                  <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">
                      {{ transaction.transaction_details?.length || 0 }}
                    </p>
                    <p class="font-semibold text-custom-grey">Total Products</p>
                  </div>
                </div>
                <RouterLink
                  v-if="transaction.id"
                  :to="{ name: 'admin.transaction.detail', params: { id: transaction.id } }"
                  class="flex w-[96px] h-[56px] shrink-0 rounded-2xl bg-custom-blue/10 hover:ring-2 hover:ring-custom-blue transition-300 items-center justify-center text-center"
                >
                  <span class="font-semibold text-custom-blue leading-none"> Details </span>
                </RouterLink>
              </div>
            </div>
          </div>
          <div
            v-else-if="!loading && latestTransactions.length === 0"
            id="Empty-State"
            class="flex flex-col flex-1 items-center justify-center gap-4"
          >
            <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon" />
            <div class="flex flex-col gap-1 items-center text-center">
              <p class="font-semibold text-custom-grey">No transactions available yet</p>
            </div>
          </div>
          <div v-else-if="loading" class="flex items-center justify-center p-8">
            <p class="text-custom-grey">Loading...</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
