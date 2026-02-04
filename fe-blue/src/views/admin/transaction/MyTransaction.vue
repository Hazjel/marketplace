<script setup>
import Pagination from '@/components/admin/Pagination.vue'
import { useTransactionStore } from '@/stores/transaction'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch, computed, toRaw } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { RouterLink } from 'vue-router'
import { can } from '@/helpers/permissionHelper'
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

  // Filter berdasarkan search jika ada
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

const perPage = computed(() => {
  return serverOptions.value?.row_per_page || meta.value?.per_page || 10
})

const paginatedTransactions = computed(() => {
  const page = serverOptions.value?.page || meta.value?.current_page || 1

  // Jika ada search atau clientFiltered, gunakan client-side pagination
  if (filters.value.search || (clientFiltered.value && clientFiltered.value.length)) {
    const start = (page - 1) * perPage.value
    return displayTransactions.value.slice(start, start + perPage.value)
  }

  // Jika tidak ada search, gunakan data dari server (sudah di-paginate)
  return displayTransactions.value
})

const clientTotalPages = computed(() => {
  return Math.max(1, Math.ceil((clientFiltered.value?.length || 0) / perPage.value))
})

const showPagination = computed(() => {
  // Jika ada search, gunakan client-side pagination
  if (filters.value.search) {
    return displayTransactions.value.length > perPage.value
  }

  // Jika ada clientFiltered, gunakan panjang clientFiltered
  if (clientFiltered.value && clientFiltered.value.length) {
    return clientFiltered.value.length > perPage.value
  }

  // Default: gunakan server pagination
  return (meta.value?.last_page || 1) > 1
})

const serverOptions = ref({
  page: 1,
  row_per_page: 10
})

const filters = ref({
  search: null
})

const fetchData = async () => {
  // Reset clientFiltered ketika fetch data baru
  clientFiltered.value = []

  const params = {
    ...serverOptions.value,
    ...filters.value
  }

  if (user.value?.buyer?.id) {
    params.buyer_id = user.value.buyer.id
  }

  await fetchTransactionsPaginated(params)

  if (transactionStore.error) {
    // Handle error silently or show toast
  }

  // If server returned transactions but none matched the current user,
  // try fetching all pages and filtering client-side as a fallback.
  // Hanya lakukan ini jika tidak ada search (karena search sudah di-handle di displayTransactions)
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
    // Error handled primarily in store
    // console.log('[MyTransaction] full fetch fallback error:', err)
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

const emptyStateText = computed(() => {
  return filters.value.search
    ? 'No transactions found matching your search'
    : "Oops, you don't have any data yet"
})

const debounceFetchData = debounce(fetchData, 2000)

const resolveStatusStyle = (transaction) => {
  // Prioritize Payment Failures
  const failureStatuses = ['expire', 'cancel', 'deny', 'failure', 'failed']
  if (failureStatuses.includes(transaction.payment_status)) {
    return 'bg-red-100 text-red-600'
  }

  // Pending Logic
  if (transaction.payment_status === 'pending') {
    return 'bg-custom-yellow text-[#544607]'
  }

  // If paid, check delivery status
  switch (transaction.delivery_status) {
    case 'processing':
      return 'bg-custom-blue/10 text-custom-blue'
    case 'delivering':
      return 'bg-custom-orange/10 text-custom-orange'
    case 'completed':
      return 'bg-custom-green/10 text-custom-green'
    default:
      return 'bg-custom-grey/10 text-custom-grey'
  }
}

const resolveStatusLabel = (transaction) => {
  const failureStatuses = ['expire', 'cancel', 'deny', 'failure', 'failed']
  if (failureStatuses.includes(transaction.payment_status)) {
    return 'Failed'
  }

  if (transaction.payment_status === 'pending') {
    return 'Pending'
  }

  return transaction.delivery_status
}

onMounted(async () => {
  await fetchData()
})

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
    // Reset ke page 1 ketika filter berubah
    serverOptions.value.page = 1
    debounceFetchData()
  },
  { deep: true }
)
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
  <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white dark:bg-surface-card">
    <div class="header flex items-center justify-between">
      <div class="flex flex-col gap-2">
        <p class="font-bold text-xl dark:text-white">My Transactions</p>
        <div class="flex items-center gap-1">
          <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
          <p class="font-semibold text-custom-grey dark:text-gray-400">
            {{ displayTransactions.length }} Total Transactions
          </p>
        </div>
      </div>
    </div>
    <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
      <form action="#" class="w-full md:w-auto">
        <label
          class="flex items-center w-full md:w-[370px] h-14 rounded-2xl p-4 gap-2 bg-white dark:bg-surface-card border border-custom-stroke dark:border-white/10 focus-within:border-custom-black dark:focus-within:border-white transition-300">
          <img src="@/assets/images/icons/receipt-search-grey.svg" class="flex size-6 shrink-0 dark:invert"
            alt="icon" />
          <input v-model="filters.search" type="text"
            class="appearance-none w-full placeholder:text-custom-grey dark:placeholder:text-gray-500 font-medium focus:outline-none bg-transparent dark:text-white"
            placeholder="Search Transaction" />
        </label>
      </form>
      <div class="flex items-center gap-4 w-full md:w-auto justify-start">
        <p class="font-medium text-custom-grey dark:text-gray-400">Show</p>
        <label
          class="flex items-center h-14 rounded-2xl border border-custom-stroke dark:border-white/10 py-4 px-5 pl-3 bg-white dark:bg-surface-card focus-within:border-custom-black dark:focus-within:border-white transition-300">
          <select id="" v-model="serverOptions.row_per_page" name=""
            class="text-custom-black dark:text-white dark:bg-surface-card font-medium appearance-none focus:outline-none p-2">
            <option value="10" class="font-medium dark:bg-surface-card">10 Entries</option>
            <option value="20" class="font-medium dark:bg-surface-card">20 Entries</option>
            <option value="40" class="font-medium dark:bg-surface-card">40 Entries</option>
          </select>
          <img src="@/assets/images/icons/arrow-down-black.svg" class="flex size-6 shrink-0 -ml-1 dark:invert"
            alt="icon" />
        </label>
      </div>
    </div>
    <section id="List-Transactions" class="flex flex-col flex-1 gap-6 w-full">
      <template v-if="displayTransactions.length">
        <div class="list flex flex-col gap-5">
          <div v-for="transaction in paginatedTransactions" :key="transaction.id"
            class="card flex flex-col rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card hover:border-custom-blue/30 dark:hover:border-blue-500/30 hover:shadow-lg transition-all duration-300 overflow-hidden group">
            <!-- Card Header -->
            <div
              class="flex items-center justify-between p-4 border-b border-gray-50 dark:border-white/5 bg-gray-50/50 dark:bg-white/5">
              <div class="flex items-center gap-2">
                <div
                  class="flex size-8 rounded-full bg-white dark:bg-white/10 border border-gray-100 dark:border-white/5 items-center justify-center shrink-0">
                  <img :src="transaction?.store?.logo" class="size-5 object-contain" alt="store" />
                </div>
                <div class="flex flex-col">
                  <p class="font-bold text-sm text-custom-black dark:text-white leading-tight">
                    {{ transaction?.store?.name }}
                  </p>
                  <p class="text-xs text-custom-grey dark:text-gray-400">
                    {{ formatToClientTimeZone(transaction.created_at) }}
                  </p>
                </div>
              </div>
              <span class="rounded-full px-3 py-1 text-xs font-bold capitalize"
                :class="resolveStatusStyle(transaction)">
                {{ resolveStatusLabel(transaction) }}
              </span>
            </div>

            <!-- Card Body (Product Preview) -->
            <div class="p-4 flex gap-4">
              <div
                class="size-[70px] shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 p-1 flex items-center justify-center">
                <img :src="transaction.transaction_details?.[0]?.product?.thumbnail"
                  class="size-full object-contain mix-blend-multiply" alt="product" />
              </div>
              <div class="flex flex-col justify-center flex-1 min-w-0">
                <p class="font-bold text-base text-custom-black dark:text-white line-clamp-1">
                  {{ transaction.transaction_details?.[0]?.product?.name }}
                </p>
                <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">
                  {{ transaction.transaction_details?.[0]?.qty }} barang x Rp
                  {{ formatRupiah(transaction.transaction_details?.[0]?.price) }}
                </p>
                <p v-if="transaction.transaction_details?.length > 1"
                  class="text-xs font-medium text-custom-blue dark:text-blue-400 mt-1">
                  + {{ transaction.transaction_details.length - 1 }} produk lainnya
                </p>
              </div>
            </div>

            <!-- Card Footer -->
            <div class="px-4 pb-4 pt-0 flex items-center justify-between">
              <div class="flex flex-col">
                <p class="text-xs text-custom-grey dark:text-gray-400 font-medium">Total Belanja</p>
                <p class="font-bold text-base text-custom-black dark:text-white">
                  Rp {{ formatRupiah(transaction.grand_total) }}
                </p>
              </div>
              <RouterLink :to="getDetailRoute(transaction.id)"
                class="px-6 py-2 rounded-xl bg-white dark:bg-white/10 border border-custom-blue dark:border-blue-400 text-custom-blue dark:text-blue-400 font-bold text-sm hover:bg-custom-blue dark:hover:bg-blue-500 hover:text-white dark:hover:text-white transition-colors">
                Lihat Detail
              </RouterLink>
            </div>
          </div>
        </div>
        <nav v-if="showPagination" id="Pagination">
          <ul class="flex items-center gap-3">
            <li class="group">
              <button
                class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 dark:bg-blue-500/20 text-custom-blue dark:text-blue-400 group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold"
                disabled>
                <img src="@/assets/images/icons/arrow-right-no-tail-blue.svg"
                  class="size-6 group-has-[:disabled]:opacity-20 rotate-180 dark:invert" alt="icon" />
              </button>
            </li>
            <li v-for="p in filters.search || clientFiltered.length
              ? Math.ceil(displayTransactions.length / perPage)
              : meta.last_page || 1" :key="p" class="group" :class="{ active: p === serverOptions.page }">
              <button
                class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 dark:bg-blue-500/20 text-custom-blue dark:text-blue-400 group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold"
                @click="serverOptions.page = p">
                {{ p }}
              </button>
            </li>
            <li class="group">
              <button
                class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 dark:bg-blue-500/20 text-custom-blue dark:text-blue-400 group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                <img src="@/assets/images/icons/arrow-right-no-tail-blue.svg"
                  class="size-6 group-has-[:disabled]:opacity-20 dark:invert" alt="icon" />
              </button>
            </li>
          </ul>
        </nav>
      </template>
      <div v-else-if="!loading" id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4">
        <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon" />
        <div class="flex flex-col gap-1 items-center text-center">
          <p class="font-semibold text-custom-grey dark:text-gray-400">
            {{ emptyStateText }}
          </p>
        </div>
      </div>
      <div v-if="loading" class="flex items-center justify-center py-10">
        <div class="size-8 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
      </div>
    </section>
  </div>
</template>
