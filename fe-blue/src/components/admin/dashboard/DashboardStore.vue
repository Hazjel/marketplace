<script setup>
import { onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useTransactionStore } from '@/stores/transaction'
import { storeToRefs } from 'pinia'
import { formatRupiah, formatDate } from '@/helpers/format'
import { useSellerDashboard } from '@/composables/useSellerDashboard'

import StatCard from '@/components/Atom/StatCard.vue'
import DashboardSection from '@/components/Molecule/DashboardSection.vue'
import EmptyState from '@/components/Atom/EmptyState.vue'
import DashboardChart from '@/components/Atom/DashboardChart.vue'
import StatusBreakdownChart from '@/components/Atom/StatusBreakdownChart.vue'
import ActionWidget from '@/components/Molecule/Seller/ActionWidget.vue'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const { data, loading, range, fetch, setRange } = useSellerDashboard()

const transactionStore = useTransactionStore()
const { transactions: latestTransactions } = storeToRefs(transactionStore)

const rangeOptions = [
  { value: 7, label: 'Mingguan' },
  { value: 30, label: 'Bulanan' },
  { value: 90, label: '3 Bulan' }
]

// Aksi nyata dari status_breakdown — bukan lagi mocked
const smartActions = computed(() => {
  if (!data.value) return []

  const actions = []
  const { unpaid, pending } = data.value.status_breakdown

  if (unpaid > 0) {
    actions.push({
      label: 'Menunggu Pembayaran',
      count: unpaid,
      icon: 'box-tick-blue-transparent.svg',
      route: { name: 'user.transaction', params: { username: user.value?.username } }
    })
  }

  if (pending > 0) {
    actions.push({
      label: 'Perlu Dikirim',
      count: pending,
      icon: 'box-tick-blue-transparent.svg',
      route: { name: 'user.transaction', params: { username: user.value?.username } }
    })
  }

  return actions
})

onMounted(() => {
  fetch()
  transactionStore.fetchTransactionsPaginated({
    row_per_page: 3,
    sort_by: 'created_at',
    sort_direction: 'desc'
  })
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
  <div v-else-if="data" class="flex flex-col gap-8">
    <!-- Header -->
    <div class="flex flex-col gap-1">
      <h1 class="font-medium text-2xl md:text-3xl text-gray-900 dark:text-white font-['Plus_Jakarta_Sans']">Ringkasan</h1>
      <p class="text-gray-500 dark:text-gray-400">
        Selamat datang kembali, {{ user?.store?.name }}! Berikut perkembangan hari ini.
      </p>
    </div>

    <!-- 3-Column Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
      <StatCard
        title="Total Pendapatan"
        :value="`Rp ${formatRupiah(data.balance)}`"
        icon="wallet-2-blue-fill.svg"
        variant="gradient"
        color="from-[#2563EB] to-blue-700"
        :trend="data.trend?.revenue"
      />
      <StatCard
        title="Total Pesanan"
        :value="data.total_orders"
        icon="box-tick-blue-transparent.svg"
        variant="gradient"
        color="from-emerald-500 to-teal-600"
        :trend="data.trend?.orders"
      />
      <StatCard
        v-if="data.total_reviews > 0"
        title="Ulasan Produk"
        :value="`${data.average_rating} / 5 (${data.total_reviews})`"
        icon="message-text-blue-fill.svg"
        variant="gradient"
        color="from-purple-500 to-indigo-600"
      />
      <StatCard
        v-else
        title="Ulasan Produk"
        value="Belum ada ulasan"
        icon="message-text-blue-fill.svg"
        variant="gradient"
        color="from-purple-500 to-indigo-600"
      />
    </div>

    <!-- Main Content 2-Col Layout -->
    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Left: Charts & Tables -->
      <div class="flex flex-col flex-1 gap-6 min-w-0">
        <DashboardSection title="Analitik Pendapatan" :subtitle="`Pemasukan ${range} hari terakhir`">
          <template #actions>
            <div class="flex gap-2">
              <button
                v-for="option in rangeOptions"
                :key="option.value"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                :class="
                  range === option.value
                    ? 'bg-[#2563EB] text-white'
                    : 'bg-[#2563EB]/10 dark:bg-[#2563EB]/20 text-[#2563EB]'
                "
                @click="setRange(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </template>
          <DashboardChart :data="data.chart" y-key="total_revenue" label="Pendapatan" />
        </DashboardSection>

        <DashboardSection
          title="Distribusi Status Pesanan"
          subtitle="Ringkasan status pesanan saat ini"
        >
          <StatusBreakdownChart :breakdown="data.status_breakdown" />
        </DashboardSection>

        <DashboardSection
          title="Pesanan Terbaru"
          :link="{ name: 'user.transaction', params: { username: user?.username } }"
        >
          <div v-if="latestTransactions.length > 0" class="flex flex-col gap-4">
            <div
              v-for="transaction in latestTransactions"
              :key="transaction.id"
              class="flex flex-col md:flex-row md:items-center justify-between p-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-white/10 gap-4"
            >
              <div class="flex items-center gap-4">
                <div
                  class="size-12 rounded-xl bg-white dark:bg-gray-700 flex items-center justify-center shrink-0 border border-gray-200 dark:border-white/10"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="w-5 h-5 text-[#2563EB]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
                    />
                  </svg>
                </div>
                <div class="flex flex-col">
                  <span class="font-medium text-sm text-gray-900 dark:text-white">
                    Order #{{ transaction.code || transaction.id.substr(0, 8) }}
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatDate(transaction.created_at) }} &bull; {{ transaction.buyer?.user?.name || 'Buyer' }}
                  </span>
                </div>
              </div>
              <div class="flex items-center justify-between md:justify-end gap-4 w-full md:w-auto">
                <span class="font-medium text-sm text-[#2563EB] dark:text-blue-400">
                  Rp {{ formatRupiah(transaction.grand_total) }}
                </span>
                <div
                  class="px-2.5 py-1 rounded-full text-xs font-medium capitalize ring-1"
                  :class="{
                    'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:ring-emerald-500/30':
                      transaction.payment_status === 'paid',
                    'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-400 dark:ring-amber-500/30':
                      transaction.payment_status === 'unpaid',
                    'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/20 dark:text-red-400 dark:ring-red-500/30':
                      transaction.payment_status === 'failed'
                  }"
                >
                  {{ transaction.payment_status }}
                </div>
              </div>
            </div>
          </div>
          <EmptyState v-else size="sm" title="Belum ada transaksi" />
        </DashboardSection>
      </div>

      <!-- Right: Sidebar Widgets -->
      <div class="flex flex-col w-full lg:w-[320px] shrink-0 gap-6">
        <ActionWidget :actions="smartActions" />

        <div
          class="flex flex-col w-full rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 p-5 gap-4 shadow-sm"
        >
          <h3 class="font-medium text-lg text-gray-900 dark:text-white">Produk Terlaris</h3>
          <div class="flex flex-col gap-4">
            <template v-if="data.top_products.length > 0">
              <div v-for="(product, index) in data.top_products" :key="product.id" class="flex items-center gap-3">
                <div
                  class="size-10 rounded-xl bg-gradient-to-br from-[#2563EB]/10 to-purple-500/10 dark:from-[#2563EB]/20 dark:to-purple-500/20 border border-gray-100 dark:border-white/10 flex items-center justify-center text-xs font-medium text-[#2563EB]"
                >
                  {{ index + 1 }}
                </div>
                <div class="flex flex-col flex-1 min-w-0">
                  <span class="font-medium text-sm truncate text-gray-900 dark:text-white" :title="product.name">
                    {{ product.name }}
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ product.total_sold || 0 }} Terjual</span>
                </div>
                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                  Rp {{ formatRupiah(product.price) }}
                </span>
              </div>
            </template>
            <EmptyState v-else size="sm" title="Belum ada data penjualan" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
