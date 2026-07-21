<script setup>
import { onMounted } from 'vue'
import { formatRupiah, formatDate } from '@/helpers/format'
import { useAdminDashboard } from '@/composables/useAdminDashboard'
import defaultStoreImage from '@/assets/images/thumbnails/th-1.svg'
import defaultTransactionImage from '@/assets/images/thumbnails/th-4.svg'

import StatCard from '@/components/Atom/StatCard.vue'
import DashboardSection from '@/components/Molecule/DashboardSection.vue'
import EmptyState from '@/components/Atom/EmptyState.vue'

const { data, loading, fetch } = useAdminDashboard()

onMounted(fetch)
</script>

<template>
  <!-- Skeleton Loader -->
  <div v-if="loading" class="animate-pulse flex flex-col gap-6">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-5">
      <div v-for="i in 4" :key="i" class="h-40 rounded-2xl bg-gray-200 dark:bg-white/10"></div>
    </div>
    <div class="flex flex-col gap-5 xl:flex-row">
      <div class="h-96 w-full rounded-2xl bg-gray-200 dark:bg-white/10"></div>
      <div class="h-96 w-full rounded-2xl bg-gray-200 dark:bg-white/10"></div>
    </div>
  </div>

  <div v-else-if="data" class="flex flex-col gap-6">
    <!-- Gradient Stat Cards - Top Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-5">
      <StatCard
        title="Pendapatan Bersih (Profit)"
        :value="`Rp ${formatRupiah(data.total_admin_fee)}`"
        icon="wallet-2-blue-fill.svg"
        variant="gradient"
        color="from-[#2563EB] to-blue-700"
      />
      <StatCard
        title="Total GMV"
        :value="`Rp ${formatRupiah(data.total_revenue)}`"
        icon="wallet-2-blue-fill.svg"
        variant="gradient"
        color="from-emerald-500 to-teal-600"
      />
      <StatCard
        title="Total Seller"
        :value="data.total_sellers.toLocaleString()"
        icon="profile-2user-blue-fill.svg"
        variant="gradient"
        color="from-purple-500 to-indigo-600"
      />
      <StatCard
        title="Total Buyer"
        :value="data.total_buyers.toLocaleString()"
        icon="profile-2user-blue-fill.svg"
        variant="gradient"
        color="from-orange-500 to-rose-500"
      />
    </div>

    <!-- Bottom Section: Stores & Transactions -->
    <div class="flex flex-col gap-5 xl:flex-row">
      <!-- Left Column: Products + Stores -->
      <div class="flex flex-col gap-5 w-full xl:w-[440px] shrink-0">
        <StatCard title="Total Produk" :value="data.total_products.toLocaleString()" icon="box-tick-blue-transparent.svg" />

        <DashboardSection title="Toko Terbaru" :subtitle="`Total Toko: ${data.total_stores.toLocaleString()}`">
          <div v-if="data.latest_stores.length > 0" class="flex flex-col gap-4">
            <div
              v-for="store in data.latest_stores"
              :key="store.id"
              class="flex flex-col rounded-2xl border border-gray-100 dark:border-white/10 p-4 gap-4 bg-white dark:bg-gray-900 hover:shadow-md transition-shadow"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex size-14 shrink-0 rounded-2xl bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-white/10"
                >
                  <img
                    :src="store.logo || defaultStoreImage"
                    class="size-full object-cover"
                    alt="photo"
                    @error="$event.target.src = defaultStoreImage"
                  />
                </div>
                <div class="flex flex-col gap-1 w-full overflow-hidden">
                  <p class="font-bold text-sm leading-tight w-full truncate text-gray-900 dark:text-white">
                    {{ store.name }}
                  </p>
                  <p class="font-medium text-gray-500 dark:text-gray-400 text-xs leading-none">
                    {{ store.user?.name || 'Unknown User' }}
                  </p>
                </div>
              </div>
              <hr class="border-gray-100 dark:border-white/10" />
              <div class="flex items-center justify-between">
                <p class="font-medium text-gray-500 dark:text-gray-400 text-xs leading-none">
                  {{ formatDate(store.created_at) }}
                </p>
                <RouterLink
                  :to="{ name: 'admin.store.detail', params: { id: store.id } }"
                  class="px-3 py-2 rounded-xl bg-[#2563EB]/10 dark:bg-[#2563EB]/20 hover:ring-2 hover:ring-[#2563EB] transition-all font-semibold text-[#2563EB] text-xs"
                >
                  Detail
                </RouterLink>
              </div>
            </div>
          </div>
          <EmptyState v-else size="sm" title="Belum ada toko" />
        </DashboardSection>
      </div>

      <!-- Right Column: Transactions -->
      <div class="flex flex-col w-full gap-5">
        <DashboardSection
          title="Transaksi Terbaru"
          :subtitle="`Total Transaksi: ${data.total_transactions.toLocaleString()}`"
        >
          <div v-if="data.latest_transactions.length > 0" class="flex flex-col gap-4">
            <div
              v-for="transaction in data.latest_transactions"
              :key="transaction.id"
              class="flex flex-col rounded-2xl border border-gray-100 dark:border-white/10 p-4 gap-4 bg-white dark:bg-gray-900 hover:shadow-md transition-shadow"
            >
              <div class="flex items-center gap-3 w-full overflow-hidden">
                <div
                  class="flex size-14 shrink-0 rounded-2xl bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-white/10"
                >
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
                <div class="flex flex-col gap-1 w-full flex-grow-0 overflow-hidden">
                  <p class="font-bold text-sm leading-tight w-full truncate text-gray-900 dark:text-white">
                    {{ transaction.store?.name || 'Unknown Store' }}
                  </p>
                  <p class="font-medium text-gray-500 dark:text-gray-400 text-xs leading-none">
                    {{ transaction.buyer?.user?.name || 'Unknown Buyer' }}
                  </p>
                </div>
                <div class="flex flex-col gap-1 items-end shrink-0">
                  <p class="font-bold text-sm leading-tight text-[#2563EB] dark:text-blue-400 text-nowrap">
                    Rp {{ formatRupiah(transaction.grand_total) }}
                  </p>
                  <p class="text-xs font-medium text-gray-500 dark:text-gray-400 text-nowrap">Grand Total</p>
                </div>
              </div>
              <hr class="border-gray-100 dark:border-white/10" />
              <div class="flex items-center justify-between">
                <p class="font-medium text-gray-500 dark:text-gray-400 text-xs">
                  {{ transaction.transaction_details?.length || 0 }} Produk &bull; {{ formatDate(transaction.created_at) }}
                </p>
                <RouterLink
                  :to="{ name: 'admin.transaction.detail', params: { id: transaction.id } }"
                  class="px-3 py-2 rounded-xl bg-[#2563EB]/10 dark:bg-[#2563EB]/20 hover:ring-2 hover:ring-[#2563EB] transition-all font-semibold text-[#2563EB] text-xs"
                >
                  Detail
                </RouterLink>
              </div>
            </div>
          </div>
          <EmptyState v-else size="sm" title="Belum ada transaksi" />
        </DashboardSection>
      </div>
    </div>
  </div>
</template>
