<script setup>
import { useAuthStore } from '@/stores/auth'
import { useTransactionStore } from '@/stores/transaction'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch, computed } from 'vue'
import { formatRupiah, formatDate } from '@/helpers/format'
import { useToast } from 'vue-toastification'

const toast = useToast()
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const transactionStore = useTransactionStore()
const { transactions, loading, success, error } = storeToRefs(transactionStore)
const { fetchTransactionsPaginated, updateTransaction } = transactionStore

const activeTab = ref('all')
const filters = ref({
  search: ''
})

const serverOptions = ref({
  page: 1,
  row_per_page: 10
})

const tabs = [
  { label: 'Semua', value: 'all' },
  { label: 'Belum Dibayar', value: 'unpaid' },
  { label: 'Pesanan Baru', value: 'paid' },
  { label: 'Siap Dikirim', value: 'processing' },
  { label: 'Dalam Pengiriman', value: 'delivering' },
  { label: 'Selesai', value: 'completed' },
  { label: 'Dibatalkan', value: 'cancelled' }
]

const fetchData = async () => {
  await fetchTransactionsPaginated({
    ...serverOptions.value,
    ...filters.value
  })
}

const filteredTransactions = computed(() => {
  if (!transactions.value) return []
  if (activeTab.value === 'all') return transactions.value

  return transactions.value.filter((t) => {
    if (activeTab.value === 'unpaid') return t.payment_status === 'unpaid'
    if (activeTab.value === 'paid')
      return t.payment_status === 'paid' && t.delivery_status === 'pending'
    if (activeTab.value === 'processing') return t.delivery_status === 'processing'
    if (activeTab.value === 'delivering') return t.delivery_status === 'delivering'
    if (activeTab.value === 'completed') return t.delivery_status === 'completed'
    if (activeTab.value === 'cancelled')
      return t.delivery_status === 'cancelled' || t.payment_status === 'failed'
    return true
  })
})

const showResiModal = ref(false)
const selectedTransactionId = ref(null)
const resiInput = ref('')

const openResiModal = (id) => {
  selectedTransactionId.value = id
  resiInput.value = ''
  showResiModal.value = true
}

const submitResi = async () => {
  if (!resiInput.value) return

  await updateTransaction({
    id: selectedTransactionId.value,
    delivery_status: 'delivering',
    tracking_number: resiInput.value
  })

  showResiModal.value = false
  fetchData()
}

const handleAccept = async (id) => {
  await updateTransaction({
    id: id,
    delivery_status: 'processing'
  })
  fetchData()
}

const handleReject = async (id) => {
  if (confirm('Apakah Anda yakin ingin menolak pesanan ini? Stok akan dikembalikan.')) {
    await updateTransaction({
      id: id,
      delivery_status: 'cancelled'
    })
    fetchData()
  }
}

const resolvePaymentBadge = (status) => {
  switch (status) {
    case 'unpaid': return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 ring-1 ring-amber-200 dark:ring-amber-900/30'
    case 'paid': return 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 ring-1 ring-green-200 dark:ring-green-900/30'
    case 'failed': return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 ring-1 ring-red-200 dark:ring-red-900/30'
    default: return 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400 ring-1 ring-gray-200 dark:ring-gray-700'
  }
}

const resolveDeliveryBadge = (status) => {
  switch (status) {
    case 'pending': return 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400 ring-1 ring-gray-200 dark:ring-gray-700'
    case 'processing': return 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400 ring-1 ring-blue-200 dark:ring-blue-900/30'
    case 'delivering': return 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400 ring-1 ring-purple-200 dark:ring-purple-900/30'
    case 'completed': return 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 ring-1 ring-green-200 dark:ring-green-900/30'
    case 'cancelled': return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 ring-1 ring-red-200 dark:ring-red-900/30'
    default: return 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400 ring-1 ring-gray-200 dark:ring-gray-700'
  }
}

const resolvePaymentLabel = (status) => {
  switch (status) {
    case 'unpaid': return 'Belum Bayar'
    case 'paid': return 'Lunas'
    case 'failed': return 'Gagal'
    default: return status
  }
}

const resolveDeliveryLabel = (status) => {
  switch (status) {
    case 'pending': return 'Menunggu'
    case 'processing': return 'Diproses'
    case 'delivering': return 'Dikirim'
    case 'completed': return 'Selesai'
    case 'cancelled': return 'Dibatalkan'
    default: return status
  }
}

onMounted(fetchData)

watch(activeTab, () => {
  serverOptions.value.page = 1
  fetchData()
})

watch(success, (val) => {
  if (val) {
    toast.success(val)
    transactionStore.success = null
  }
})
watch(error, (val) => {
  if (val) {
    toast.error(val)
    transactionStore.error = null
  }
})
</script>

<template>
  <div class="flex flex-col gap-6 animate-fade-in-up">
    <!-- Page Header with Gradient -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 p-6 md:p-8">
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIxIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMDUpIi8+PC9zdmc+')] opacity-50"></div>
      <div class="relative z-10">
        <h1 class="font-bold text-2xl md:text-3xl text-white">Pesanan Masuk</h1>
        <p class="text-blue-100 mt-1 text-sm">Kelola dan proses pesanan dari pelanggan</p>
      </div>
      <div class="absolute -right-6 -bottom-6 size-32 rounded-full bg-white/5 blur-2xl"></div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 hide-scrollbar">
      <button v-for="tab in tabs" :key="tab.value"
        class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition-all shrink-0"
        :class="activeTab === tab.value
          ? 'bg-custom-blue text-white shadow-md shadow-blue-500/20'
          : 'bg-white dark:bg-surface-card text-custom-grey dark:text-gray-400 border border-gray-200 dark:border-white/10 hover:border-custom-blue/50 hover:text-custom-blue'"
        @click="activeTab = tab.value">
        {{ tab.label }}
      </button>
    </div>

    <!-- Order Cards -->
    <div class="flex flex-col gap-4">
      <div v-for="t in filteredTransactions" :key="t.id"
        class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-300 overflow-hidden">
        
        <!-- Card Header -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50 dark:border-white/5 bg-gray-50/50 dark:bg-white/[0.02]">
          <div class="flex items-center gap-3">
            <div class="size-8 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
              <svg class="size-4 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
              </svg>
            </div>
            <div>
              <p class="font-bold text-sm text-custom-black dark:text-white">{{ t.code }}</p>
              <p class="text-[11px] text-custom-grey dark:text-gray-500">{{ formatDate(t.created_at) }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold capitalize" :class="resolvePaymentBadge(t.payment_status)">
              {{ resolvePaymentLabel(t.payment_status) }}
            </span>
            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold capitalize" :class="resolveDeliveryBadge(t.delivery_status)">
              {{ resolveDeliveryLabel(t.delivery_status) }}
            </span>
          </div>
        </div>

        <!-- Card Body -->
        <div class="p-5 flex flex-col md:flex-row gap-5">
          <!-- Product Items -->
          <div class="flex-1 flex flex-col gap-3">
            <div v-for="detail in t.transaction_details" :key="detail.id" class="flex items-center gap-3">
              <div class="size-14 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 p-1.5 flex items-center justify-center overflow-hidden">
                <img :src="detail.product.product_images?.find((i) => i.is_thumbnail)?.image || detail.product.product_images?.[0]?.image"
                  class="size-full object-cover rounded-lg" alt="" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-bold text-sm text-custom-black dark:text-white line-clamp-1">{{ detail.product.name }}</p>
                <p class="text-xs text-custom-grey dark:text-gray-400 mt-0.5">
                  {{ detail.qty }} x {{ formatRupiah(detail.product.price) }}
                </p>
              </div>
            </div>

            <!-- Shipping Info -->
            <div class="flex items-center gap-3 mt-2 pt-3 border-t border-dashed border-gray-100 dark:border-white/10">
              <div class="size-8 rounded-full bg-gray-50 dark:bg-white/5 flex items-center justify-center">
                <svg class="size-4 text-custom-grey" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                </svg>
              </div>
              <div class="min-w-0">
                <p class="text-xs font-bold text-custom-black dark:text-white uppercase">{{ t.shipping }} - {{ t.shipping_type }}</p>
                <p class="text-xs text-custom-grey dark:text-gray-400">{{ t.city }}, {{ t.postal_code }}</p>
              </div>
            </div>
          </div>

          <!-- Price & Actions -->
          <div class="flex flex-col justify-between items-start md:items-end gap-4 min-w-[200px] border-t md:border-t-0 md:border-l pt-4 md:pt-0 md:pl-5 border-dashed border-gray-100 dark:border-white/10">
            <div class="text-left md:text-right w-full">
              <p class="text-xs text-custom-grey dark:text-gray-500 font-medium">Total Belanja</p>
              <p class="font-bold text-xl text-custom-blue dark:text-blue-400">{{ formatRupiah(t.grand_total) }}</p>
            </div>

            <div class="flex flex-row md:flex-col gap-2 w-full md:w-auto">
              <!-- Actions for New Paid Orders -->
              <template v-if="t.payment_status === 'paid' && t.delivery_status === 'pending'">
                <button
                  class="px-5 py-2.5 rounded-xl bg-custom-blue text-white font-semibold text-sm hover:bg-blue-700 transition-all shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30"
                  @click="handleAccept(t.id)">
                  <svg class="inline size-4 mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                  Terima
                </button>
                <button
                  class="px-5 py-2.5 rounded-xl bg-red-50 text-red-600 font-semibold text-sm hover:bg-red-100 transition-all dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30 ring-1 ring-red-100 dark:ring-red-900/30"
                  @click="handleReject(t.id)">
                  <svg class="inline size-4 mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  Tolak
                </button>
              </template>

              <!-- Actions for Processing Orders -->
              <template v-if="t.delivery_status === 'processing'">
                <button
                  class="px-5 py-2.5 rounded-xl bg-custom-blue text-white font-semibold text-sm hover:bg-blue-700 transition-all shadow-md shadow-blue-500/20"
                  @click="openResiModal(t.id)">
                  <svg class="inline size-4 mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                  </svg>
                  Input Resi
                </button>
              </template>

              <!-- Tracking Info -->
              <div v-if="t.tracking_number"
                class="bg-blue-50 dark:bg-blue-900/10 p-3 rounded-xl border border-dashed border-blue-200 dark:border-blue-900/30">
                <p class="text-[11px] text-custom-blue font-semibold">No. Resi</p>
                <p class="font-bold font-mono text-sm text-custom-black dark:text-white mt-0.5">{{ t.tracking_number }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredTransactions.length === 0 && !loading"
        class="flex flex-col items-center justify-center py-16 bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10">
        <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
          <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
          </svg>
        </div>
        <p class="font-bold text-lg text-custom-black dark:text-white">Belum ada pesanan</p>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">Pesanan baru akan muncul di sini</p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-16">
        <div class="size-10 border-3 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm text-custom-grey dark:text-gray-400 mt-3">Memuat pesanan...</p>
      </div>
    </div>

    <!-- Resi Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="showResiModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showResiModal = false"></div>
          <div class="relative bg-white dark:bg-surface-card rounded-2xl p-6 w-full max-w-md shadow-2xl border border-gray-100 dark:border-white/10">
            <!-- Modal Header -->
            <div class="flex items-center gap-3 mb-5">
              <div class="size-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                <svg class="size-5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
              </div>
              <div>
                <h3 class="font-bold text-lg text-custom-black dark:text-white">Input Nomor Resi</h3>
                <p class="text-xs text-custom-grey dark:text-gray-400">Masukkan nomor resi pengiriman</p>
              </div>
            </div>

            <div class="flex flex-col gap-4">
              <label class="flex flex-col gap-2">
                <span class="text-sm font-semibold text-custom-black dark:text-white">Nomor Resi</span>
                <input v-model="resiInput" type="text"
                  class="w-full h-12 px-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/10 transition-all"
                  placeholder="Contoh: JP1234567890" />
              </label>
              <div class="flex gap-3 mt-2">
                <button
                  class="flex-1 py-3 rounded-xl bg-gray-100 dark:bg-white/5 font-semibold text-custom-grey dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/10 transition-all"
                  @click="showResiModal = false">
                  Batal
                </button>
                <button
                  class="flex-1 py-3 rounded-xl bg-custom-blue text-white font-semibold hover:bg-blue-700 transition-all shadow-md shadow-blue-500/20"
                  @click="submitResi">
                  Kirim
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
