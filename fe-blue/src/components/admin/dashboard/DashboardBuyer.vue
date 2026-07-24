<script setup>
import { useTransactionStore } from '@/stores/transaction'
import { storeToRefs } from 'pinia'
import { onMounted, computed } from 'vue'
import { formatRupiah, formatDate } from '@/helpers/format'
import { useBuyerDashboard } from '@/composables/useBuyerDashboard'
import { dashboardRoute } from '@/helpers/routeHelper'

import StatCard from '@/components/Atom/StatCard.vue'
import DashboardSection from '@/components/Molecule/DashboardSection.vue'
import EmptyState from '@/components/Atom/EmptyState.vue'
import DashboardChart from '@/components/Atom/DashboardChart.vue'

const { data, loading, range, fetch, setRange } = useBuyerDashboard()

const transactionStore = useTransactionStore()
const { transactions: latestTransactions } = storeToRefs(transactionStore)

const rangeOptions = [
  { value: 7, label: 'Mingguan' },
  { value: 30, label: 'Bulanan' },
  { value: 90, label: '3 Bulan' }
]

// Status pengiriman (bukan lagi dihitung client dari 100 baris — langsung dari summary)
const statusTiles = computed(() => {
  if (!data.value) return []

  const b = data.value.status_breakdown

  return [
    { key: 'unpaid', label: 'Belum Bayar', count: b.unpaid, color: 'amber' },
    { key: 'pending', label: 'Diproses', count: b.pending, color: 'blue' },
    { key: 'shipping', label: 'Dikirim', count: b.shipping, color: 'purple' },
    { key: 'completed', label: 'Selesai', count: b.completed, color: 'green' }
  ]
})

onMounted(() => {
  fetch()
  transactionStore.fetchTransactionsPaginated({
    row_per_page: 5,
    sort_by: 'created_at',
    sort_direction: 'desc'
  })
})
</script>

<template>
  <!-- Skeleton Loader -->
  <div v-if="loading" class="animate-pulse flex flex-col gap-6">
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-4 md:gap-5">
      <div v-for="i in 4" :key="i" class="h-32 rounded-2xl bg-gray-200 dark:bg-white/10"></div>
    </div>
    <div class="h-96 rounded-2xl bg-gray-200 dark:bg-white/10"></div>
  </div>

  <div v-else-if="data" class="flex flex-col gap-6">
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-4 md:gap-5">
      <StatCard
        title="Total Pengeluaran"
        :value="`Rp ${formatRupiah(data.total_expense)}`"
        icon="wallet-2-blue-fill.svg"
      />

      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 col-span-1 xl:col-span-3">
        <div
          v-for="tile in statusTiles"
          :key="tile.key"
          class="flex flex-col w-full rounded-2xl p-4 md:p-5 gap-3 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 items-center justify-center text-center hover:shadow-lg transition-all duration-300"
        >
          <p class="font-medium text-lg md:text-2xl dark:text-white">{{ tile.count }}</p>
          <p class="font-medium text-xs md:text-sm text-custom-grey dark:text-gray-400">{{ tile.label }}</p>
        </div>
      </div>
    </div>

    <DashboardSection title="Riwayat Pengeluaran" :subtitle="`Pengeluaran ${range} hari terakhir`">
      <template #actions>
        <div class="flex gap-2">
          <button
            v-for="option in rangeOptions"
            :key="option.value"
            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
            :class="
              range === option.value
                ? 'bg-[#024ad8] text-white'
                : 'bg-[#024ad8]/10 dark:bg-[#024ad8]/20 text-[#024ad8]'
            "
            @click="setRange(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
      </template>
      <DashboardChart :data="data.chart" y-key="total_revenue" label="Pengeluaran" />
    </DashboardSection>

    <DashboardSection title="Transaksi Terbaru">
      <div v-if="latestTransactions.length > 0" class="flex flex-col gap-4">
        <div
          v-for="transaction in latestTransactions"
          :key="transaction.id"
          class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 hover:border-custom-blue/20 dark:hover:border-blue-500/20 hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-300 overflow-hidden"
        >
          <div
            class="flex items-center justify-between px-4 py-3 border-b border-gray-50 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.02]"
          >
            <div class="flex items-center gap-3">
              <div
                class="size-8 rounded-full bg-white dark:bg-white/10 border border-gray-100 dark:border-white/10 overflow-hidden flex items-center justify-center shrink-0"
              >
                <img
                  v-if="transaction?.store?.logo"
                  :src="transaction?.store?.logo"
                  class="size-full object-cover"
                  alt=""
                />
                <svg
                  v-else
                  class="size-4 text-gray-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"
                  />
                </svg>
              </div>
              <div>
                <p class="font-medium text-sm text-custom-black dark:text-white leading-tight">
                  {{ transaction?.store?.name || 'Store' }}
                </p>
                <p class="text-[11px] text-custom-grey dark:text-gray-500">
                  {{ formatDate(transaction.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-6">
              <div class="flex items-center gap-2">
                <div class="size-9 rounded-lg bg-gray-50 dark:bg-white/5 flex items-center justify-center">
                  <svg
                    class="size-4 text-custom-grey"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"
                    />
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-sm dark:text-white">{{ transaction?.transaction_details?.length }}</p>
                  <p class="text-[11px] text-custom-grey dark:text-gray-400">Produk</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <div class="size-9 rounded-lg bg-gray-50 dark:bg-white/5 flex items-center justify-center">
                  <svg
                    class="size-4 text-custom-grey"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"
                    />
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-sm dark:text-white">
                    {{ transaction?.transaction_details?.reduce((total, detail) => total + detail.qty, 0) }}
                  </p>
                  <p class="text-[11px] text-custom-grey dark:text-gray-400">Qty</p>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-end">
              <div class="text-left md:text-right">
                <p class="text-[11px] text-custom-grey dark:text-gray-500 font-medium">Total Belanja</p>
                <p class="font-medium text-base text-custom-blue dark:text-blue-400">
                  Rp {{ formatRupiah(transaction.grand_total) }}
                </p>
              </div>
              <RouterLink
                :to="dashboardRoute('transaction.detail', { id: transaction.id })"
                class="px-4 py-2.5 rounded-xl text-sm font-medium transition-all border-2 border-custom-blue/20 text-custom-blue dark:text-blue-400 hover:bg-custom-blue hover:text-white hover:border-custom-blue hover:shadow-lg hover:shadow-blue-500/20 shrink-0"
              >
                Detail
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
      <EmptyState v-else title="Belum ada transaksi" subtitle="Mulai belanja untuk melihat riwayat transaksi" />
    </DashboardSection>
  </div>
</template>
