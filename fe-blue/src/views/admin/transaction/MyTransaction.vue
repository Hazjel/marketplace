<script setup>
import { useTransactionStore } from '@/stores/transaction'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch, computed } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { RouterLink } from 'vue-router'
import { formatToClientTimeZone } from '@/helpers/format'
import { formatRupiah } from '@/helpers/format'
import { useToast } from 'vue-toastification'

const toast = useToast()
const transactionStore = useTransactionStore()
const { transactions, meta, loading, success, error } = storeToRefs(transactionStore)
const { fetchTransactionsPaginated } = transactionStore

import { useAuthStore } from '@/stores/auth'
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const filteredTransactions = computed(() => {
  const items = transactions.value || []
  if (!user.value) return []

  const userBuyerId = user.value?.buyer?.id || user.value?.buyer_id || user.value?.id

  return items
    .filter((t) => {
      const txBuyerId = t?.buyer?.id || t?.buyer_id
      return txBuyerId && userBuyerId && String(txBuyerId) === String(userBuyerId)
    })
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

const clientFiltered = ref([])

const displayTransactions = computed(() => {
  let items =
    clientFiltered.value && clientFiltered.value.length
      ? clientFiltered.value
      : filteredTransactions.value

  if (filters.value.search && filters.value.search.trim()) {
    const searchTerm = filters.value.search.trim().toLowerCase()
    items = items.filter((transaction) => {
      const storeName = transaction?.store?.name?.toLowerCase() || ''
      const buyerName = transaction?.buyer?.name?.toLowerCase() || ''
      const transactionId = transaction?.id?.toString() || ''
      const deliveryStatus = transaction?.delivery_status?.toLowerCase() || ''

      return (
        storeName.includes(searchTerm) ||
        buyerName.includes(searchTerm) ||
        transactionId.includes(searchTerm) ||
        deliveryStatus.includes(searchTerm)
      )
    })
  }

  return items
})

// Status filter
const activeStatusFilter = ref('all')
const statusFilters = [
  { key: 'all', label: 'Semua' },
  { key: 'pending', label: 'Menunggu' },
  { key: 'processing', label: 'Diproses' },
  { key: 'delivering', label: 'Dikirim' },
  { key: 'completed', label: 'Selesai' },
  { key: 'failed', label: 'Gagal' }
]

const statusFilteredTransactions = computed(() => {
  if (activeStatusFilter.value === 'all') return displayTransactions.value

  return displayTransactions.value.filter((t) => {
    const failureStatuses = ['expire', 'cancel', 'deny', 'failure', 'failed']
    if (activeStatusFilter.value === 'failed') return failureStatuses.includes(t.payment_status)
    if (activeStatusFilter.value === 'pending') return t.payment_status === 'pending'
    return t.delivery_status === activeStatusFilter.value
  })
})

const perPage = computed(() => {
  return serverOptions.value?.row_per_page || meta.value?.per_page || 10
})

const paginatedTransactions = computed(() => {
  const page = serverOptions.value?.page || meta.value?.current_page || 1
  const start = (page - 1) * perPage.value
  return statusFilteredTransactions.value.slice(start, start + perPage.value)
})

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(statusFilteredTransactions.value.length / perPage.value))
})

const showPagination = computed(() => {
  return totalPages.value > 1
})

const serverOptions = ref({
  page: 1,
  row_per_page: 10
})

const filters = ref({
  search: null
})

const fetchData = async () => {
  clientFiltered.value = []

  const params = {
    ...serverOptions.value,
    ...filters.value
  }

  if (user.value?.buyer?.id) {
    params.buyer_id = user.value.buyer.id
  }

  await fetchTransactionsPaginated(params)

  try {
    if (
      !filters.value.search &&
      (transactions.value || []).length > 0 &&
      filteredTransactions.value.length === 0
    ) {
      const all = []
      const lastPage = meta.value?.last_page || 1
      for (let p = 1; p <= lastPage; p++) {
        const resp = await axiosInstance.get('transaction/all/paginated', {
          params: { ...serverOptions.value, ...filters.value, page: p }
        })
        const pageItems = resp.data.data.data || []
        all.push(...pageItems)
      }

      const userBuyerId = user.value?.buyer?.id || user.value?.buyer_id || user.value?.id
      const matched = all
        .filter((t) => {
          const txBuyerId = t?.buyer?.id || t?.buyer_id
          return txBuyerId && userBuyerId && String(txBuyerId) === String(userBuyerId)
        })
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))

      clientFiltered.value = matched
    }
  } catch (err) {
    // fallback error handled silently
  }
}

const getDetailRoute = (transactionId) => {
  if (user.value?.role === 'buyer') {
    return {
      name: 'user.transaction.detail',
      params: { username: user.value.username, id: transactionId }
    }
  }
  return {
    name: 'admin.transaction.detail',
    params: { id: transactionId }
  }
}

const debounceFetchData = debounce(fetchData, 2000)

const resolveStatusStyle = (transaction) => {
  const failureStatuses = ['expire', 'cancel', 'deny', 'failure', 'failed']
  if (failureStatuses.includes(transaction.payment_status)) {
    return 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 ring-1 ring-red-100 dark:ring-red-900/30'
  }

  if (transaction.payment_status === 'pending') {
    return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 ring-1 ring-amber-100 dark:ring-amber-900/30'
  }

  switch (transaction.delivery_status) {
    case 'processing':
      return 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 ring-1 ring-blue-100 dark:ring-blue-900/30'
    case 'delivering':
      return 'bg-orange-50 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400 ring-1 ring-orange-100 dark:ring-orange-900/30'
    case 'completed':
      return 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 ring-1 ring-green-100 dark:ring-green-900/30'
    default:
      return 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400 ring-1 ring-gray-100 dark:ring-gray-700'
  }
}

const resolveStatusLabel = (transaction) => {
  const failureStatuses = ['expire', 'cancel', 'deny', 'failure', 'failed']
  if (failureStatuses.includes(transaction.payment_status)) return 'Gagal'
  if (transaction.payment_status === 'pending') return 'Menunggu'

  switch (transaction.delivery_status) {
    case 'processing': return 'Diproses'
    case 'delivering': return 'Dikirim'
    case 'completed': return 'Selesai'
    default: return transaction.delivery_status || 'Unknown'
  }
}

const resolveStatusIcon = (transaction) => {
  const failureStatuses = ['expire', 'cancel', 'deny', 'failure', 'failed']
  if (failureStatuses.includes(transaction.payment_status)) return 'x-circle'
  if (transaction.payment_status === 'pending') return 'clock'

  switch (transaction.delivery_status) {
    case 'processing': return 'package'
    case 'delivering': return 'truck'
    case 'completed': return 'check-circle'
    default: return 'help-circle'
  }
}

onMounted(async () => {
  await fetchData()
})

watch(
  serverOptions,
  () => { fetchData() },
  { deep: true }
)

watch(
  filters,
  () => {
    serverOptions.value.page = 1
    debounceFetchData()
  },
  { deep: true }
)

watch(activeStatusFilter, () => {
  serverOptions.value.page = 1
})

watch(success, (value) => {
  if (value) {
    toast.success(value)
    transactionStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    transactionStore.error = null
  }
})
</script>

<template>
  <div class="flex flex-col flex-1 gap-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div>
        <h1 class="font-bold text-2xl text-custom-black dark:text-white">Transaksi Saya</h1>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">
          {{ statusFilteredTransactions.length }} transaksi ditemukan
        </p>
      </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 hide-scrollbar">
      <button v-for="filter in statusFilters" :key="filter.key"
        class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition-all shrink-0"
        :class="activeStatusFilter === filter.key
          ? 'bg-custom-blue text-white shadow-md shadow-blue-500/20'
          : 'bg-white dark:bg-surface-card text-custom-grey dark:text-gray-400 border border-gray-200 dark:border-white/10 hover:border-custom-blue/50 hover:text-custom-blue'"
        @click="activeStatusFilter = filter.key">
        {{ filter.label }}
      </button>
    </div>

    <!-- Search & Controls -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
      <div class="relative flex-1">
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input v-model="filters.search" type="text"
          class="w-full h-12 pl-12 pr-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/10 transition-all"
          placeholder="Cari transaksi, toko, atau ID..." />
      </div>
      <select v-model="serverOptions.row_per_page"
        class="h-12 px-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white focus:outline-none focus:border-custom-blue appearance-none cursor-pointer">
        <option value="10">10 / hal</option>
        <option value="20">20 / hal</option>
        <option value="40">40 / hal</option>
      </select>
    </div>

    <!-- Transaction Cards -->
    <section class="flex flex-col gap-4">
      <template v-if="statusFilteredTransactions.length && !loading">
        <div v-for="transaction in paginatedTransactions" :key="transaction.id"
          class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 hover:border-custom-blue/20 dark:hover:border-blue-500/20 hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-300 overflow-hidden group">
          <!-- Card Header -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-50 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.02]">
            <div class="flex items-center gap-3">
              <div class="size-8 rounded-full bg-white dark:bg-white/10 border border-gray-100 dark:border-white/10 overflow-hidden flex items-center justify-center shrink-0">
                <img v-if="transaction?.store?.logo" :src="transaction?.store?.logo" class="size-full object-cover" alt="" />
                <svg v-else class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <p class="font-bold text-sm text-custom-black dark:text-white leading-tight">{{ transaction?.store?.name || 'Store' }}</p>
                <p class="text-[11px] text-custom-grey dark:text-gray-500">{{ formatToClientTimeZone(transaction.created_at) }}</p>
              </div>
            </div>
            <span class="rounded-full px-3 py-1 text-[11px] font-bold capitalize" :class="resolveStatusStyle(transaction)">
              {{ resolveStatusLabel(transaction) }}
            </span>
          </div>

          <!-- Card Body -->
          <div class="p-4 flex gap-4">
            <div class="size-16 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 p-1.5 flex items-center justify-center">
              <img :src="transaction.transaction_details?.[0]?.product?.thumbnail"
                class="size-full object-contain mix-blend-multiply dark:mix-blend-normal rounded-lg" alt="" />
            </div>
            <div class="flex flex-col justify-center flex-1 min-w-0">
              <p class="font-bold text-sm text-custom-black dark:text-white line-clamp-1">
                {{ transaction.transaction_details?.[0]?.product?.name }}
              </p>
              <p class="text-xs text-custom-grey dark:text-gray-400 mt-1">
                {{ transaction.transaction_details?.[0]?.qty }} barang x Rp {{ formatRupiah(transaction.transaction_details?.[0]?.price) }}
              </p>
              <p v-if="transaction.transaction_details?.length > 1" class="text-xs font-semibold text-custom-blue dark:text-blue-400 mt-1">
                +{{ transaction.transaction_details.length - 1 }} produk lainnya
              </p>
            </div>
          </div>

          <!-- Card Footer -->
          <div class="px-4 pb-4 flex items-center justify-between">
            <div>
              <p class="text-[11px] text-custom-grey dark:text-gray-500 font-medium">Total Belanja</p>
              <p class="font-bold text-base text-custom-black dark:text-white">Rp {{ formatRupiah(transaction.grand_total) }}</p>
            </div>
            <RouterLink :to="getDetailRoute(transaction.id)"
              class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all border-2 border-custom-blue/20 text-custom-blue dark:text-blue-400 hover:bg-custom-blue hover:text-white hover:border-custom-blue hover:shadow-lg hover:shadow-blue-500/20">
              Lihat Detail
            </RouterLink>
          </div>
        </div>
      </template>

      <!-- Empty State -->
      <div v-else-if="!loading" class="flex flex-col items-center justify-center py-16 bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10">
        <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
          <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
          </svg>
        </div>
        <p class="font-bold text-lg text-custom-black dark:text-white">Belum ada transaksi</p>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">
          {{ filters.search ? 'Tidak ditemukan transaksi yang cocok' : 'Mulai belanja untuk melihat riwayat transaksi' }}
        </p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-16">
        <div class="size-10 border-3 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-3">Memuat transaksi...</p>
      </div>
    </section>

    <!-- Pagination -->
    <nav v-if="showPagination && !loading" class="flex items-center justify-center gap-2 pt-2">
      <button
        class="size-10 rounded-xl flex items-center justify-center text-sm font-semibold transition-all"
        :class="serverOptions.page > 1 ? 'bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-custom-black dark:text-white hover:border-custom-blue hover:text-custom-blue' : 'bg-gray-100 dark:bg-white/5 text-gray-400 cursor-not-allowed'"
        :disabled="serverOptions.page <= 1"
        @click="serverOptions.page--">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <button v-for="p in totalPages" :key="p"
        class="size-10 rounded-xl flex items-center justify-center text-sm font-semibold transition-all"
        :class="p === serverOptions.page ? 'bg-custom-blue text-white shadow-md shadow-blue-500/20' : 'bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-custom-black dark:text-white hover:border-custom-blue hover:text-custom-blue'"
        @click="serverOptions.page = p">
        {{ p }}
      </button>
      <button
        class="size-10 rounded-xl flex items-center justify-center text-sm font-semibold transition-all"
        :class="serverOptions.page < totalPages ? 'bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-custom-black dark:text-white hover:border-custom-blue hover:text-custom-blue' : 'bg-gray-100 dark:bg-white/5 text-gray-400 cursor-not-allowed'"
        :disabled="serverOptions.page >= totalPages"
        @click="serverOptions.page++">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </nav>
  </div>
</template>
