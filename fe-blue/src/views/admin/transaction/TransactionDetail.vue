<script setup>
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format'
import { useTransactionStore } from '@/stores/transaction'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useProductReviewStore } from '@/stores/productReview'
import { useToast } from 'vue-toastification'
import ReviewModal from '@/components/ReviewModal.vue'

const route = useRoute()
const toast = useToast()

const transaction = ref({})

const transactionStore = useTransactionStore()
const authStore = useAuthStore()
const { user, activeMode } = storeToRefs(authStore)
const { loading } = storeToRefs(transactionStore)
const { fetchTransactionById, updateTransaction, checkTransactionStatus } = transactionStore

const handleCheckStatus = async () => {
  try {
    const updatedTransaction = await checkTransactionStatus(transaction.value.id)
    transaction.value = updatedTransaction
    if (updatedTransaction.payment_status === 'paid') {
      toast.success('Status pembayaran terverifikasi: PAID')
    } else {
      toast.info('Status pembayaran saat ini: ' + updatedTransaction.payment_status)
    }
  } catch {
    toast.error('Gagal memverifikasi status pembayaran')
  }
}

onMounted(async () => {
  await fetchData()
  loadMidtransScript().catch(console.error)
})

const fetchData = async () => {
  try {
    const response = await fetchTransactionById(route.params.id)
    if (!response) throw new Error('Transaction not found')

    transaction.value = response
    transaction.value.delivery_proof_url = response.delivery_proof
      ? getImageUrl(response.delivery_proof)
      : PlaceHolder
  } catch (error) {
    console.error('Error fetching transaction:', error)
    toast.error('Gagal memuat data transaksi. Terjadi kesalahan atau data tidak ditemukan.')
  }
}

const handleUpdateData = async () => {
  try {
    const payload = {
      id: transaction.value.id,
      delivery_status: transaction.value.delivery_status
    }

    if (transaction.value.tracking_number) {
      payload.tracking_number = transaction.value.tracking_number
    }

    if (transaction.value.delivery_proof instanceof File) {
      payload.delivery_proof = transaction.value.delivery_proof
    }

    await updateTransaction(payload)
    await fetchData()
    toast.success('Status transaksi berhasil diperbarui')
  } catch (err) {
    console.error('Update failed:', err)
    toast.error('Gagal memperbarui transaksi')
  }
}

const handleAcceptOrder = () => {
  transaction.value.delivery_proof = null
  transaction.value.delivery_status = 'processing'
  handleUpdateData()
}

const fileInput = ref(null)

const handleDeliverySubmit = () => {
  transaction.value.delivery_status = 'delivering'

  handleUpdateData()
}

const handleImageChange = (e) => {
  if (!e.target.files || !e.target.files[0]) return

  const file = e.target.files[0]

  transaction.value.delivery_proof = file
  transaction.value.delivery_proof_url = URL.createObjectURL(file)
}

const getImageUrl = (path) => {
  if (!path) return PlaceHolder
  let laravelBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  // Remove '/api' suffix and trailing slashes to get the root URL
  laravelBaseUrl = laravelBaseUrl.replace(/\/api\/?$/, '').replace(/\/+$/, '')

  // Ensure path doesn't start with /
  const cleanPath = path.toString().startsWith('/') ? path.substring(1) : path

  return `${laravelBaseUrl}/storage/${cleanPath}`
}

// Review Logic
const showReviewModal = ref(false)
const selectedProductForReview = ref(null)

const handleOpenReview = (detail) => {
  selectedProductForReview.value = {
    id: detail.product_id,
    name: detail.product?.name,
    image: detail.product?.product_images?.find((image) => image.is_thumbnail)?.image || PlaceHolder
  }
  showReviewModal.value = true
}

const handleReviewSubmitted = async () => {
  await fetchData()
}

const hasReviewed = (productId) => {
  return transaction.value?.product_reviews?.some((r) => r.product_id === productId)
}

// Buyer Complete Order
const receivingProofInput = ref(null)
const handleCompleteOrderClick = () => {
  receivingProofInput.value.click()
}

const handleReceivingProofChange = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  try {
    await transactionStore.completeTransaction(transaction.value.id, { receiving_proof: file })
    await fetchData()
    toast.success('Pesanan diterima & diselesaikan')
  } catch (error) {
    console.error('Failed to complete order', error)
    toast.error('Gagal menyelesaikan pesanan')
  }
}

const getReviewsForProduct = (productId) => {
  return transaction.value?.product_reviews?.filter((r) => r.product_id === productId) || []
}

const isProcessingPayment = ref(false)

const loadMidtransScript = () => {
  return new Promise((resolve, reject) => {
    if (window.snap) {
      resolve()
      return
    }
    const script = document.createElement('script')
    script.type = 'text/javascript'
    script.src = 'https://app.sandbox.midtrans.com/snap/snap.js'
    script.setAttribute(
      'data-client-key',
      import.meta.env.VITE_MIDTRANS_CLIENT_KEY || 'YOUR_MIDTRANS_CLIENT_KEY'
    )
    script.async = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Failed to load Midtrans'))
    document.head.appendChild(script)
  })
}

const handleRepayment = () => {
  if (!transaction.value.snap_token) {
    toast.error('Tidak dapat memproses pembayaran. Token hilang.')
    return
  }

  isProcessingPayment.value = true
  window.snap.pay(transaction.value.snap_token, {
    onSuccess: async function () {
      toast.success('Pembayaran berhasil! Memverifikasi status...')
      await handleCheckStatus()
      isProcessingPayment.value = false
    },
    onPending: function () {
      toast.info('Menunggu pembayaran...')
      isProcessingPayment.value = false
    },
    onError: function () {
      toast.error('Pembayaran gagal.')
      isProcessingPayment.value = false
    },
    onClose: function () {
      isProcessingPayment.value = false
    }
  })
}

onMounted(async () => {
  await fetchData()
  loadMidtransScript().catch(console.error)
})
</script>

<template>
  <div v-if="loading" class="flex flex-1 items-center justify-center min-h-[400px]">
    <div class="flex flex-col items-center gap-3">
      <div class="size-12 border-[3px] border-custom-blue border-t-transparent rounded-full animate-spin"></div>
      <p class="text-sm text-custom-grey dark:text-gray-400">Memuat detail transaksi...</p>
    </div>
  </div>

  <div
v-else-if="!transaction || !transaction.id"
    class="flex flex-col flex-1 items-center justify-center min-h-[400px] gap-4">
    <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center">
      <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
      </svg>
    </div>
    <p class="font-bold text-xl text-custom-black dark:text-white">Transaksi Tidak Ditemukan</p>
    <p class="text-sm text-custom-grey dark:text-gray-400">Data transaksi tidak tersedia atau telah dihapus</p>
    <RouterLink :to="{ name: 'admin.my-transaction' }" class="mt-2 px-6 py-2.5 rounded-xl bg-custom-blue text-white font-bold text-sm hover:bg-blue-700 transition-colors">
      Kembali ke Transaksi
    </RouterLink>
  </div>

  <div v-else class="flex flex-col md:flex-row flex-1 gap-6">
    <div class="flex flex-col gap-5 w-full min-w-0">
      <!-- Status Banner -->
      <div
v-if="transaction?.delivery_status === 'pending'"
        class="relative w-full rounded-2xl overflow-hidden bg-gradient-to-r from-amber-400 to-amber-500 shadow-lg shadow-amber-500/20">
        <div class="absolute inset-0 opacity-10">
          <div class="absolute -top-10 -right-10 size-40 bg-white/30 rounded-full blur-2xl"></div>
        </div>
        <div class="relative flex items-center min-h-[72px] gap-3 p-5">
          <div class="size-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
            <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="font-bold text-white text-base">Menunggu Konfirmasi</p>
            <p class="text-white/80 text-sm">Pesanan sedang direview oleh penjual</p>
          </div>
        </div>
      </div>
      <div
v-if="transaction?.delivery_status === 'processing'"
        class="relative w-full rounded-2xl overflow-hidden bg-gradient-to-r from-blue-500 to-blue-600 shadow-lg shadow-blue-500/20">
        <div class="absolute inset-0 opacity-10">
          <div class="absolute -top-10 -right-10 size-40 bg-white/30 rounded-full blur-2xl"></div>
        </div>
        <div class="relative flex items-center min-h-[72px] gap-3 p-5">
          <div class="size-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
            <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <div>
            <p class="font-bold text-white text-base">Sedang Diproses</p>
            <p class="text-white/80 text-sm">Penjual sedang menyiapkan pesanan untuk dikirim</p>
          </div>
        </div>
      </div>
      <div
v-if="transaction?.delivery_status === 'delivering'"
        class="relative w-full rounded-2xl overflow-hidden bg-gradient-to-r from-orange-500 to-orange-600 shadow-lg shadow-orange-500/20">
        <div class="absolute inset-0 opacity-10">
          <div class="absolute -top-10 -right-10 size-40 bg-white/30 rounded-full blur-2xl"></div>
        </div>
        <div class="relative flex items-center min-h-[72px] gap-3 p-5">
          <div class="size-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
            <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
            </svg>
          </div>
          <div>
            <p class="font-bold text-white text-base">Sedang Dikirim</p>
            <p class="text-white/80 text-sm">Pesanan dalam perjalanan ke alamat kamu</p>
          </div>
        </div>
      </div>
      <div
v-if="transaction?.delivery_status === 'completed'"
        class="relative w-full rounded-2xl overflow-hidden bg-gradient-to-r from-green-500 to-emerald-600 shadow-lg shadow-green-500/20">
        <div class="absolute inset-0 opacity-10">
          <div class="absolute -top-10 -right-10 size-40 bg-white/30 rounded-full blur-2xl"></div>
        </div>
        <div class="relative flex items-center min-h-[72px] gap-3 p-5">
          <div class="size-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
            <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="font-bold text-white text-base">Pesanan Selesai</p>
            <p class="text-white/80 text-sm">Pesanan telah diterima dengan baik</p>
          </div>
        </div>
      </div>

      <section
        class="flex flex-col w-full rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <p class="font-bold text-lg dark:text-white">Info Toko</p>
        <div class="flex items-center gap-4 w-full min-w-0">
          <div class="flex size-16 shrink-0 rounded-2xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 overflow-hidden">
            <img :src="transaction?.store?.logo" class="size-full object-cover" alt="photo" />
          </div>
          <div class="flex flex-col gap-1 w-full overflow-hidden">
            <p class="font-bold text-lg leading-tight w-full truncate dark:text-white">
              {{ transaction?.store?.name }}
            </p>
            <p class="flex items-center gap-1 text-sm text-custom-grey dark:text-gray-400">
              <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              {{ transaction?.store?.user?.name }}
            </p>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div class="flex flex-col items-center p-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10">
            <p class="font-bold text-lg text-custom-black dark:text-white">{{ transaction?.transaction_details?.length }}</p>
            <p class="text-xs text-custom-grey dark:text-gray-400">Produk</p>
          </div>
          <div class="flex flex-col items-center p-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10">
            <p class="font-bold text-lg text-custom-black dark:text-white">{{ transaction?.transaction_details?.reduce((total, detail) => total + detail.qty, 0) }}</p>
            <p class="text-xs text-custom-grey dark:text-gray-400">Kuantitas</p>
          </div>
          <div class="flex flex-col items-center p-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10">
            <p class="font-bold text-xs text-custom-black dark:text-white leading-tight text-center">{{ formatToClientTimeZone(transaction?.created_at) }}</p>
            <p class="text-xs text-custom-grey dark:text-gray-400 mt-1">Tanggal</p>
          </div>
        </div>
      </section>
      <section
        class="flex flex-col w-full rounded-2xl p-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <div class="flex items-center justify-between mb-5">
          <div class="flex flex-col gap-1">
            <p class="font-bold text-lg dark:text-white">Detail Produk</p>
            <p class="text-sm text-custom-grey dark:text-gray-400">
              {{ transaction?.transaction_details?.length }} produk dalam pesanan ini
            </p>
          </div>
        </div>
        <div id="Products" class="flex flex-col gap-4 mt-5">
          <div
v-for="(product, index) in transaction?.transaction_details" :key="product.id || index"
            class="card flex flex-col rounded-2xl border border-custom-stroke dark:border-white/10 p-4 gap-5">
            <div class="flex flex-col sm:flex-row w-full gap-5">
              <div class="flex items-center gap-[14px] w-full min-w-0 overflow-hidden">
                <div
                  class="flex size-[92px] rounded-2xl bg-custom-background overflow-hidden shrink-0 border border-gray-100 dark:border-white/10">
                  <img
:src="product?.product?.product_images?.find((image) => image.is_thumbnail)
                    ?.image ?? PlaceHolder
                    " class="size-full object-contain" alt="thumbnail" />
                </div>
                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                  <p class="font-bold text-lg leading-tight w-full truncate dark:text-white">
                    {{ product?.product?.name }}
                  </p>
                  <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                    <img src="@/assets/images/icons/bag-grey.svg" class="size-5 dark:invert" alt="icon" />
                    {{ product?.product?.product_category?.name }}
                  </p>
                </div>
              </div>
              <div
                class="flex flex-row sm:flex-col gap-2 shrink-0 justify-between sm:justify-end sm:text-right w-full sm:w-auto mt-2 sm:mt-0 ml-[106px] sm:ml-0">
                <p class="font-bold text-custom-blue dark:text-custom-blue">
                  Rp {{ formatRupiah(product?.product?.price) }}
                </p>
                <p class="font-semibold leading-none text-custom-grey">{{ product.qty }}</p>
              </div>
            </div>
            <hr class="border-custom-stroke dark:border-white/10" />
            <div class="flex items-center justify-between">
              <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                <img src="@/assets/images/icons/shopping-cart-grey.svg" class="size-5 dark:invert" alt="icon" />
                Subtotal
              </p>
              <p class="font-bold text-lg text-custom-blue">
                Rp {{ formatRupiah(product.subtotal) }}
              </p>
            </div>

            <!-- Review Button -->
            <div
v-if="transaction?.delivery_status === 'completed' && activeMode === 'buyer'"
              class="flex justify-end pt-2">
              <div
v-if="hasReviewed(product.product_id)"
                class="px-4 py-2 bg-green-50 text-green-600 rounded-full text-sm font-bold border border-green-100 flex items-center gap-2">
                <span class="text-green-500">✓</span> Ulasan Terkirim
              </div>
              <button
v-else
                class="px-5 py-2.5 rounded-full bg-custom-blue text-white text-sm font-bold hover:shadow-lg hover:shadow-custom-blue/30 transition-all"
                @click="handleOpenReview(product)">
                Beri Ulasan
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>
    <div class="flex flex-col gap-5 w-full md:w-[440px] shrink-0">
      <section
        class="flex flex-col w-full rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <p class="font-bold text-lg dark:text-white">Detail Pembeli</p>
        <div class="flex items-center gap-4 w-full min-w-0">
          <div
            class="flex size-14 shrink-0 rounded-2xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 overflow-hidden">
            <img :src="transaction?.buyer?.user?.profile_picture" class="size-full object-cover" alt="photo" />
          </div>
          <div class="flex flex-col gap-1 w-full overflow-hidden">
            <p class="font-bold text-base leading-tight w-full truncate dark:text-white">
              {{ transaction?.buyer?.user?.name }}
            </p>
            <p class="flex items-center gap-1 text-sm text-custom-grey dark:text-gray-400">
              <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              {{ transaction?.buyer?.user?.buyer?.phone_number ?? transaction?.buyer?.phone_number }}
            </p>
          </div>
        </div>
        <div class="flex flex-col rounded-xl border border-gray-100 dark:border-white/10 divide-y divide-gray-100 dark:divide-white/10 overflow-hidden">
          <div class="flex items-center gap-3 p-4">
            <div class="size-9 shrink-0 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
              <svg class="size-4 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="flex flex-col min-w-0">
              <p class="font-semibold text-sm text-custom-black dark:text-white truncate">{{ transaction?.buyer?.user?.email }}</p>
              <p class="text-xs text-custom-grey dark:text-gray-400">Email</p>
            </div>
          </div>
          <div class="flex items-center gap-3 p-4">
            <div class="size-9 shrink-0 rounded-lg bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
              <svg class="size-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div class="flex flex-col min-w-0">
              <p class="font-semibold text-sm text-custom-black dark:text-white">{{ transaction?.city }}</p>
              <p class="text-xs text-custom-grey dark:text-gray-400">Kota</p>
            </div>
          </div>
          <div class="flex items-center gap-3 p-4">
            <div class="size-9 shrink-0 rounded-lg bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
              <svg class="size-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <div class="flex flex-col min-w-0">
              <p class="font-semibold text-sm text-custom-black dark:text-white truncate">{{ transaction?.address }}</p>
              <p class="text-xs text-custom-grey dark:text-gray-400">Alamat</p>
            </div>
          </div>
          <div class="flex items-center gap-3 p-4">
            <div class="size-9 shrink-0 rounded-lg bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center">
              <svg class="size-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
              </svg>
            </div>
            <div class="flex flex-col min-w-0">
              <p class="font-semibold text-sm text-custom-black dark:text-white">{{ transaction?.postal_code }}</p>
              <p class="text-xs text-custom-grey dark:text-gray-400">Kode Pos</p>
            </div>
          </div>
        </div>
      </section>
      <section
        class="flex flex-col w-full rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <p class="font-bold text-lg dark:text-white">Ringkasan Pembayaran</p>
        <div class="flex flex-col rounded-xl border border-gray-100 dark:border-white/10 p-4 gap-3">
          <div class="flex items-center justify-between">
            <span class="text-sm text-custom-grey dark:text-gray-400">Subtotal</span>
            <span class="text-sm font-semibold text-custom-black dark:text-white">
              Rp {{ formatRupiah(transaction?.transaction_details?.reduce((total, detail) => total + detail.subtotal, 0)) }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-custom-grey dark:text-gray-400">Ongkos Kirim</span>
            <span class="text-sm font-semibold text-custom-black dark:text-white">
              Rp {{ formatRupiah(transaction?.shipping_cost) }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-custom-grey dark:text-gray-400">PPN 11%</span>
            <span class="text-sm font-semibold text-custom-black dark:text-white">Rp {{ formatRupiah(transaction?.tax) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-custom-grey dark:text-gray-400">Diskon</span>
            <span class="text-sm font-semibold text-custom-black dark:text-white">Rp 0</span>
          </div>
          <hr class="border-gray-100 dark:border-white/10 my-1" />
          <div class="flex items-center justify-between">
            <span class="font-bold text-base text-custom-black dark:text-white">Grand Total</span>
            <span class="font-bold text-lg text-custom-blue">
              Rp {{ formatRupiah(transaction?.grand_total) }}
            </span>
          </div>
          <hr class="border-gray-100 dark:border-white/10 my-1" />
          <div class="flex items-center justify-between">
            <span class="text-sm text-custom-grey dark:text-gray-400">Status Pembayaran</span>
            <span class="font-bold text-sm capitalize"
              :class="transaction?.payment_status === 'paid' ? 'text-green-600' : transaction?.payment_status === 'unpaid' ? 'text-red-500' : 'text-custom-blue'">
              {{ transaction?.payment_status }}
            </span>
          </div>

          <!-- Repayment Button -->
          <button
v-if="activeMode === 'buyer' && transaction?.payment_status === 'unpaid'"
            :disabled="isProcessingPayment"
            class="flex items-center justify-center h-12 w-full rounded-xl bg-custom-blue text-white font-bold text-sm hover:bg-blue-700 shadow-lg shadow-blue-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all mt-2"
            @click="handleRepayment">
            <template v-if="isProcessingPayment">
              <div class="size-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
              Memproses...
            </template>
            <template v-else>Bayar Sekarang</template>
          </button>
        </div>
      </section>
      <section
v-if="transaction?.delivery_status === 'pending'"
        class="flex flex-col w-full rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <p class="font-bold text-lg dark:text-white">Status Pesanan</p>
        <div class="grid grid-cols-3 relative min-h-[90px] w-full">
          <div
id="Progress-Bar"
            class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke dark:bg-white/10 overflow-hidden">
            <div class="w-1/3 h-full bg-custom-lime-green"></div>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
              <span class="font-bold">1</span>
            </div>
            <p class="font-bold text-center">Book Review</p>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-stroke dark:bg-white/10 overflow-hidden items-center justify-center">
              <span class="font-bold dark:text-custom-grey">2</span>
            </div>
            <p class="font-bold text-center dark:text-white">Processing</p>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-stroke dark:bg-white/10 overflow-hidden items-center justify-center">
              <span class="font-bold dark:text-custom-grey">?</span>
            </div>
            <p class="font-bold text-center dark:text-white">2+ More</p>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
            <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon" />
            Delivery Status
          </p>
          <p
            class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-yellow text-[#544607]">
            pending
          </p>
        </div>
        <div
v-if="
          transaction?.payment_status === 'paid' &&
          activeMode === 'store' &&
          user?.store?.id == transaction?.store?.id
        " class="flex flex-col text-center gap-4">
          <button
            class="h-14 w-full rounded-full flex items-center justify-center py-4 px-6 bg-custom-blue disabled:bg-custom-stroke transition-300"
            @click="handleAcceptOrder">
            <span class="font-semibold text-lg text-white">Accept Order</span>
          </button>
          <div class="flex items-center justify-center gap-[6px]">
            <p class="font-semibold text-custom-grey">Why can't I decline the order?</p>
            <img src="@/assets/images/icons/info-circle-grey.svg" class="size-[18px]" alt="icon" />
          </div>
        </div>
      </section>
      <section
v-if="transaction?.delivery_status === 'processing'"
        class="flex flex-col w-full rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <p class="font-bold text-lg dark:text-white">Status Pesanan</p>
        <div class="grid grid-cols-3 relative min-h-[90px] w-full">
          <div id="Progress-Bar" class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
            <div class="w-2/3 h-full bg-custom-lime-green"></div>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
              <span class="font-bold">1</span>
            </div>
            <p class="font-bold text-center">Book Review</p>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
              <span class="font-bold">2</span>
            </div>
            <p class="font-bold text-center">Processing</p>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-stroke dark:bg-white/10 overflow-hidden items-center justify-center">
              <span class="font-bold dark:text-custom-grey">?</span>
            </div>
            <p class="font-bold text-center dark:text-white">2+ More</p>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
            <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon" />
            Delivery Status
          </p>
          <p
            class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-blue/10 text-custom-blue">
            processing
          </p>
        </div>
        <template v-if="activeMode === 'store' && user?.store?.id === transaction?.store?.id">
          <div class="flex items-center justify-between w-full">
            <div
              class="group relative flex size-[100px] rounded-2xl overflow-hidden items-center justify-center bg-custom-background dark:bg-custom-background">
              <img
id="Thumbnail" :src="transaction.delivery_proof_url"
                data-default="@/assets/images/icons/gallery-default.svg" class="size-full object-contain" alt="icon" />
            </div>
            <button
id="Add-Photo" type="button"
              class="flex items-center justify-center rounded-2xl py-4 px-6 bg-custom-black text-white font-semibold text-lg"
              @click="fileInput.click()">
              Add Photo
            </button>
          </div>
          <div class="flex flex-col gap-3">
            <p class="font-semibold text-custom-grey">Tracking Number</p>
            <div class="group/errorState flex flex-col gap-2">
              <label class="group relative">
                <div class="input-icon">
                  <img src="@/assets/images/icons/barcode-grey.svg" class="flex size-6 shrink-0" alt="icon" />
                </div>
                <p class="input-placeholder">Enter Tracking Number</p>
                <input
id="Tracking" v-model="transaction.tracking_number" type="string" class="custom-input"
                  placeholder="" />
              </label>
              <span class="input-error">Lorem dolor error message here</span>
            </div>
          </div>
          <button
id="Update-Status" type="submit"
            class="h-14 w-full rounded-full flex items-center justify-center py-4 px-6 bg-custom-blue disabled:bg-custom-stroke transition-300"
            @click="handleDeliverySubmit">
            <span class="font-semibold text-lg text-white">Update Status</span>
          </button>
        </template>
      </section>
      <section
v-if="transaction?.delivery_status === 'delivering'"
        class="flex flex-col w-full rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <p class="font-bold text-lg dark:text-white">Status Pesanan</p>
        <div class="grid grid-cols-3 relative min-h-[90px] w-full">
          <div id="Progress-Bar" class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
            <div class="w-2/3 h-full bg-custom-lime-green"></div>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
              <span class="font-bold">2</span>
            </div>
            <p class="font-bold text-center">Processing</p>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
              <span class="font-bold">3</span>
            </div>
            <p class="font-bold text-center">Delivering</p>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-stroke dark:bg-white/10 overflow-hidden items-center justify-center">
              <span class="font-bold dark:text-custom-grey">4</span>
            </div>
            <p class="font-bold text-center dark:text-white">Completed</p>
          </div>
        </div>
        <div class="h-[260px] w-full rounded-2xl overflow-hidden bg-custom-background">
          <img
:src="transaction?.delivery_proof_url || PlaceHolder" class="size-full object-cover" alt="thumbnail"
            @error="(e) => (e.target.src = PlaceHolder)" />
        </div>

        <div class="flex items-center justify-between">
          <div class="flex flex-col gap-2">
            <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
              <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon" />
              Delivery Status
            </p>
            <p
              class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-orange/10 text-custom-orange w-fit">
              Delivering
            </p>
          </div>

          <button
v-if="activeMode === 'buyer'"
            class="flex items-center justify-center h-12 px-6 rounded-full bg-custom-blue text-white font-semibold shadow-lg hover:bg-blue-600 transition-300"
            @click="handleCompleteOrderClick">
            Order Received
          </button>
        </div>
        <div class="flex items-center justify-between">
          <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
            <img src="@/assets/images/icons/routing-grey.svg" class="size-6" alt="icon" />
            Tracking Number
          </p>
          <p class="font-semibold text-lg leading-none dark:text-white">
            {{ transaction?.tracking_number }}({{ transaction?.shipping }})
          </p>
        </div>
      </section>
      <section
v-if="transaction?.delivery_status === 'completed'"
        class="flex flex-col w-full rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <p class="font-bold text-lg dark:text-white">Status Pesanan</p>
        <div class="grid grid-cols-3 relative min-h-[90px] w-full">
          <div
id="Progress-Bar"
            class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke dark:bg-white/10 overflow-hidden">
            <div class="w-full h-full bg-custom-lime-green"></div>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
              <span class="font-bold">2</span>
            </div>
            <p class="font-bold text-center">Processing</p>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
              <span class="font-bold">3</span>
            </div>
            <p class="font-bold text-center">Delivering</p>
          </div>
          <div class="relative flex flex-col py-4 gap-[6px] items-center">
            <div
              class="flex size-8 shrink-0 rounded-full bg-custom-lime-green overflow-hidden items-center justify-center">
              <span class="font-bold">4</span>
            </div>
            <p class="font-bold text-center">Completed</p>
          </div>
        </div>
        <div class="h-[260px] w-full rounded-2xl overflow-hidden bg-custom-background">
          <img
:src="transaction?.delivery_proof_url || PlaceHolder" class="size-full object-cover" alt="thumbnail"
            @error="(e) => (e.target.src = PlaceHolder)" />
        </div>
        <div class="flex items-center justify-between">
          <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
            <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon" />
            Delivery Status
          </p>
          <p
            class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-green/10 text-custom-green">
            completed
          </p>
        </div>
        <div class="flex items-center justify-between">
          <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
            <img src="@/assets/images/icons/routing-grey.svg" class="size-6" alt="icon" />
            Tracking Number
          </p>
          <p class="font-semibold text-lg leading-none dark:text-white">{{ transaction?.tracking_number }}</p>
        </div>
      </section>
      <section
        class="flex flex-col w-full rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
        <p class="font-bold text-lg dark:text-white">Ulasan Pembeli</p>

        <div v-if="transaction?.product_reviews?.length > 0" class="flex flex-col gap-4">
          <div v-for="detail in transaction.transaction_details" :key="detail.id">
            <div
v-for="review in getReviewsForProduct(detail.product_id)" :key="review.id"
              class="flex flex-col rounded-2xl border border-custom-stroke dark:border-white/10 p-4 gap-4">
              <div class="flex items-center justify-between">
                <div class="flex flex-col">
                  <p class="font-bold text-lg dark:text-white">{{ detail.product.name }}</p>
                  <div class="flex items-center gap-2">
                    <p class="font-bold tracking-tight text-xl leading-none dark:text-white">
                      <span class="text-[32px]">{{ review.rating }}.0</span>/5.0
                    </p>
                    <div class="flex">
                      <template v-for="i in 5" :key="i">
                        <img
v-if="i <= review.rating" src="@/assets/images/icons/Star-pointy.svg"
                          class="flex size-8 shrink-0 p-0.5" alt="star" />
                        <img
v-else src="@/assets/images/icons/Star-pointy-outline.svg"
                          class="flex size-8 shrink-0 p-0.5" alt="star" />
                      </template>
                    </div>
                  </div>
                </div>
              </div>
              <hr class="border-custom-stroke dark:border-white/10" />
              <p class="font-medium text-lg text-custom-grey">“{{ review.review }}”</p>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-8 gap-4">
          <p
v-if="activeMode === 'buyer' && transaction?.delivery_status === 'completed'"
            class="font-medium text-custom-grey">
            You haven't reviewed this order yet.
          </p>
          <p v-else class="font-medium text-custom-grey">No reviews yet.</p>
        </div>
      </section>
    </div>
  </div>
  <!-- Global hidden inputs -->
  <input ref="receivingProofInput" type="file" class="hidden" accept="image/*" @change="handleReceivingProofChange" />
  <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleImageChange" />

  <Teleport to="body">
    <ReviewModal
v-if="transaction?.id && selectedProductForReview" :show="showReviewModal"
      :transaction-id="transaction.id" :product-id="selectedProductForReview.id"
      :product-name="selectedProductForReview.name" :product-image="selectedProductForReview.image"
      @close="showReviewModal = false" @submitted="handleReviewSubmitted" />
  </Teleport>
</template>
