<script setup>
import { useTransactionStore } from '@/stores/transaction'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { formatRupiah, formatDate } from '@/helpers/format'

const transactionStore = useTransactionStore()
const { transactions, loading } = storeToRefs(transactionStore)
const { fetchTransactionsPaginated } = transactionStore

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

// Fetch transactions untuk buyer
const fetchData = async () => {
  if (user.value?.buyer?.id) {
    // Fetch dengan limit besar untuk mendapatkan semua transaksi untuk statistik
    await fetchTransactionsPaginated({
      buyer_id: user.value.buyer.id,
      row_per_page: 100, // Ambil banyak data untuk statistik yang akurat
      page: 1
    })
  }
}

// Filter transactions untuk buyer ini saja
const buyerTransactions = computed(() => {
  const items = transactions.value || []
  if (!user.value) return []

  const userBuyerId = user.value?.buyer?.id || user.value?.buyer_id

  return items
    .filter((t) => {
      const txBuyerId = t?.buyer?.id || t?.buyer_id
      return txBuyerId && userBuyerId && String(txBuyerId) === String(userBuyerId)
    })
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

// Statistik
const totalExpense = computed(() => {
  return buyerTransactions.value.reduce((sum, transaction) => {
    return sum + (parseFloat(transaction.grand_total) || 0)
  }, 0)
})

// Status Counts
const countUnpaid = computed(() => {
  return buyerTransactions.value.filter((t) => t.payment_status === 'unpaid').length
})

const countProcessed = computed(() => {
  return buyerTransactions.value.filter(
    (t) => t.payment_status === 'paid' && t.delivery_status === 'pending'
  ).length
})

const countShipped = computed(() => {
  return buyerTransactions.value.filter((t) => t.delivery_status === 'shipping').length
})

const countCompleted = computed(() => {
  return buyerTransactions.value.filter((t) => t.delivery_status === 'delivered').length
})

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="grid grid-cols-1 xl:grid-cols-4 gap-4 md:gap-5">
    <!-- Total Expense Card — Gradient -->
    <div class="relative overflow-hidden rounded-2xl p-5 md:p-6 col-span-1 animate-fade-in-up bg-gradient-to-br from-blue-600 to-indigo-600">
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIxIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMDUpIi8+PC9zdmc+')] opacity-50"></div>
      <div class="absolute -right-4 -bottom-4 size-24 rounded-full bg-white/10 blur-xl"></div>
      <div class="relative z-10 flex flex-col gap-4">
        <div class="flex size-12 bg-white/20 backdrop-blur-sm items-center justify-center rounded-xl">
          <svg class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />
          </svg>
        </div>
        <div class="flex flex-col gap-1">
          <p class="font-bold text-2xl md:text-3xl text-white">Rp {{ formatRupiah(totalExpense) }}</p>
          <p class="font-medium text-sm text-blue-100">Total Pengeluaran</p>
        </div>
      </div>
    </div>

    <!-- Status Cards Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 col-span-1 xl:col-span-3">
      <!-- Unpaid -->
      <div class="flex flex-col w-full rounded-2xl p-4 md:p-5 gap-3 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 items-center justify-center text-center animate-fade-in-up delay-100 hover:shadow-lg hover:shadow-amber-500/5 transition-all duration-300 group">
        <div class="flex size-10 md:size-12 bg-amber-50 dark:bg-amber-900/20 items-center justify-center rounded-xl group-hover:scale-110 transition-transform">
          <svg class="size-5 md:size-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
          </svg>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="font-bold text-lg md:text-2xl dark:text-white">{{ countUnpaid }}</p>
          <p class="font-medium text-xs md:text-sm text-custom-grey dark:text-gray-400">Belum Bayar</p>
        </div>
      </div>

      <!-- Processed -->
      <div class="flex flex-col w-full rounded-2xl p-4 md:p-5 gap-3 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 items-center justify-center text-center animate-fade-in-up delay-150 hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-300 group">
        <div class="flex size-10 md:size-12 bg-blue-50 dark:bg-blue-900/20 items-center justify-center rounded-xl group-hover:scale-110 transition-transform">
          <svg class="size-5 md:size-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
          </svg>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="font-bold text-lg md:text-2xl dark:text-white">{{ countProcessed }}</p>
          <p class="font-medium text-xs md:text-sm text-custom-grey dark:text-gray-400">Diproses</p>
        </div>
      </div>

      <!-- Shipped -->
      <div class="flex flex-col w-full rounded-2xl p-4 md:p-5 gap-3 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 items-center justify-center text-center animate-fade-in-up delay-200 hover:shadow-lg hover:shadow-purple-500/5 transition-all duration-300 group">
        <div class="flex size-10 md:size-12 bg-purple-50 dark:bg-purple-900/20 items-center justify-center rounded-xl group-hover:scale-110 transition-transform">
          <svg class="size-5 md:size-6 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
          </svg>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="font-bold text-lg md:text-2xl dark:text-white">{{ countShipped }}</p>
          <p class="font-medium text-xs md:text-sm text-custom-grey dark:text-gray-400">Dikirim</p>
        </div>
      </div>

      <!-- Completed -->
      <div class="flex flex-col w-full rounded-2xl p-4 md:p-5 gap-3 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 items-center justify-center text-center animate-fade-in-up delay-300 hover:shadow-lg hover:shadow-green-500/5 transition-all duration-300 group">
        <div class="flex size-10 md:size-12 bg-green-50 dark:bg-green-900/20 items-center justify-center rounded-xl group-hover:scale-110 transition-transform">
          <svg class="size-5 md:size-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="font-bold text-lg md:text-2xl dark:text-white">{{ countCompleted }}</p>
          <p class="font-medium text-xs md:text-sm text-custom-grey dark:text-gray-400">Selesai</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Latest Transactions Section -->
  <div class="flex flex-col flex-1 w-full rounded-2xl bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 animate-fade-in-up delay-300 overflow-hidden">
    <!-- Section Header -->
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50 dark:border-white/5">
      <div class="flex items-center gap-3">
        <div class="size-9 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
          <svg class="size-5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <p class="font-bold text-lg text-custom-black dark:text-white">Transaksi Terbaru</p>
      </div>
    </div>

    <!-- Transaction List -->
    <div class="flex flex-col flex-1 p-5 gap-4">
      <div v-if="!loading && buyerTransactions.length > 0" class="flex flex-col gap-4">
        <div v-for="transaction in buyerTransactions.slice(0, 5)" :key="transaction.id"
          class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 hover:border-custom-blue/20 dark:hover:border-blue-500/20 hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-300 overflow-hidden">
          
          <!-- Card Header -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-50 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.02]">
            <div class="flex items-center gap-3">
              <div class="size-8 rounded-full bg-white dark:bg-white/10 border border-gray-100 dark:border-white/10 overflow-hidden flex items-center justify-center shrink-0">
                <img v-if="transaction?.store?.logo" :src="transaction?.store?.logo" class="size-full object-cover" alt="" />
                <svg v-else class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                </svg>
              </div>
              <div>
                <p class="font-bold text-sm text-custom-black dark:text-white leading-tight">{{ transaction?.store?.name || 'Store' }}</p>
                <p class="text-[11px] text-custom-grey dark:text-gray-500">{{ formatDate(transaction.created_at) }}</p>
              </div>
            </div>
          </div>

          <!-- Card Body -->
          <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
              <!-- Stats -->
              <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                  <div class="size-9 rounded-lg bg-gray-50 dark:bg-white/5 flex items-center justify-center">
                    <svg class="size-4 text-custom-grey" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                  </div>
                  <div>
                    <p class="font-bold text-sm dark:text-white">{{ transaction?.transaction_details?.length }}</p>
                    <p class="text-[11px] text-custom-grey dark:text-gray-400">Produk</p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <div class="size-9 rounded-lg bg-gray-50 dark:bg-white/5 flex items-center justify-center">
                    <svg class="size-4 text-custom-grey" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                  </div>
                  <div>
                    <p class="font-bold text-sm dark:text-white">
                      {{ transaction?.transaction_details?.reduce((total, detail) => total + detail.qty, 0) }}
                    </p>
                    <p class="text-[11px] text-custom-grey dark:text-gray-400">Qty</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Price & Action -->
            <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-end">
              <div class="text-left md:text-right">
                <p class="text-[11px] text-custom-grey dark:text-gray-500 font-medium">Total Belanja</p>
                <p class="font-bold text-base text-custom-blue dark:text-blue-400">Rp {{ formatRupiah(transaction.grand_total) }}</p>
              </div>
              <RouterLink :to="{
                name: `${user?.role === 'admin' ? 'admin' : 'user'}.transaction.detail`,
                params: { id: transaction.id, username: user?.username }
              }"
                class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all border-2 border-custom-blue/20 text-custom-blue dark:text-blue-400 hover:bg-custom-blue hover:text-white hover:border-custom-blue hover:shadow-lg hover:shadow-blue-500/20 shrink-0">
                Detail
              </RouterLink>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!loading && buyerTransactions.length === 0"
        class="flex flex-col flex-1 items-center justify-center py-12 gap-4">
        <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center">
          <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-lg text-custom-black dark:text-white">Belum ada transaksi</p>
          <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">Mulai belanja untuk melihat riwayat transaksi</p>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-12">
        <div class="size-10 border-3 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-3">Memuat data...</p>
      </div>
    </div>
  </div>
</template>
