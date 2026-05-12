<script setup>
import CardList from '@/components/admin/transaction/CardList.vue'
import Pagination from '@/components/admin/Pagination.vue'
import { useTransactionStore } from '@/stores/transaction'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { can } from '@/helpers/permissionHelper'
import { useToast } from 'vue-toastification'

const toast = useToast()
const transactionStore = useTransactionStore()
const { transactions, meta, loading, success, error } = storeToRefs(transactionStore)
const { fetchTransactionsPaginated, deleteTransaction } = transactionStore

const serverOptions = ref({
  page: 1,
  row_per_page: 10,
  sort_by: 'created_at',
  descending: true
})

const filters = ref({
  search: null
})

const activeStatusFilter = ref('all')
const statusFilters = [
  { key: 'all', label: 'Semua' },
  { key: 'pending', label: 'Menunggu' },
  { key: 'processing', label: 'Diproses' },
  { key: 'delivering', label: 'Dikirim' },
  { key: 'completed', label: 'Selesai' },
  { key: 'cancelled', label: 'Dibatalkan' }
]

const fetchData = async () => {
  await fetchTransactionsPaginated({
    ...serverOptions.value,
    ...filters.value
  })
}

async function handleDelete(id) {
  await deleteTransaction(id)

  fetchData()
}

const debounceFetchData = debounce(fetchData, 500)

onMounted(fetchData)

watch(
  serverOptions,
  () => {
    fetchData()
  },
  { deep: true }
)
watch(
  filters,
  () => {
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

import { computed } from 'vue'
const filteredByStatus = computed(() => {
  if (!transactions.value) return []
  if (activeStatusFilter.value === 'all') return transactions.value

  return transactions.value.filter((t) => {
    if (activeStatusFilter.value === 'pending') return t.payment_status === 'pending' || t.payment_status === 'unpaid'
    if (activeStatusFilter.value === 'processing') return t.delivery_status === 'processing'
    if (activeStatusFilter.value === 'delivering') return t.delivery_status === 'delivering'
    if (activeStatusFilter.value === 'completed') return t.delivery_status === 'completed'
    if (activeStatusFilter.value === 'cancelled') return t.delivery_status === 'cancelled' || t.payment_status === 'failed'
    return true
  })
})
</script>

<template>
  <div class="flex flex-col flex-1 gap-6 animate-fade-in-up">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div>
        <h1 class="font-bold text-2xl text-custom-black dark:text-white">Semua Transaksi</h1>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">
          <svg class="inline size-4 mr-1 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
          </svg>
          {{ meta.total }} total transaksi
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
          placeholder="Cari transaksi..." />
      </div>
      <select v-model="serverOptions.row_per_page"
        class="h-12 px-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white focus:outline-none focus:border-custom-blue appearance-none cursor-pointer">
        <option value="10">10 / hal</option>
        <option value="20">20 / hal</option>
        <option value="40">40 / hal</option>
      </select>
    </div>

    <!-- Transaction List -->
    <section class="flex flex-col flex-1 gap-4 w-full">
      <div v-if="!loading && filteredByStatus.length > 0" class="flex flex-col gap-4">
        <CardList v-for="transaction in filteredByStatus" :key="transaction.id"
          :item="transaction" @delete="handleDelete" />
      </div>

      <!-- Pagination -->
      <Pagination v-if="!loading && filteredByStatus.length > 0" :meta="meta" :server-options="serverOptions" />

      <!-- Empty State -->
      <div v-if="!loading && filteredByStatus.length === 0"
        class="flex flex-col items-center justify-center py-16 bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10">
        <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
          <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
          </svg>
        </div>
        <p class="font-bold text-lg text-custom-black dark:text-white">Belum ada transaksi</p>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">
          {{ filters.search ? 'Tidak ditemukan transaksi yang cocok' : 'Data transaksi akan muncul di sini' }}
        </p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-16">
        <div class="size-10 border-3 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-3">Memuat transaksi...</p>
      </div>
    </section>
  </div>
</template>
