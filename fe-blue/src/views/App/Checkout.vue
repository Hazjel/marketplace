<script setup>
import { formatRupiah } from '@/helpers/format'
import { useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useTransactionStore } from '@/stores/transaction'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { onMounted, ref, computed } from 'vue'
import { debounce } from 'lodash'
import { useToast } from 'vue-toastification'

import { axiosInstance } from '@/plugins/axios'
import CheckoutStepper from '@/components/Molecule/CheckoutStepper.vue'
import TrustBadges from '@/components/Molecule/TrustBadges.vue'

// Store imports
const authStore = useAuthStore()
const cart = useCartStore()
const transactionStore = useTransactionStore()
const toast = useToast()
const router = useRouter()

// Store refs
const { user } = storeToRefs(authStore)
const {
  selectedCarts,
  totalSelectedItems,
  totalSelectedQuantity,
  subtotalSelected,
  discountSelected
} = storeToRefs(cart)
const { error } = storeToRefs(transactionStore)
const { createTransaction } = transactionStore

const isMidtransLoaded = ref(false)

// Saved Addresses
const savedAddresses = ref([])
const showSavedAddresses = ref(false)

const fetchSavedAddresses = async () => {
  try {
    const response = await axiosInstance.get('/address')
    savedAddresses.value = response.data.data

    // Auto-select primary
    const primary = savedAddresses.value.find((a) => a.is_primary)
    if (primary) {
      selectSavedAddress(primary)
      showSavedAddresses.value = true
    }
  } catch (error) {
    console.error('Failed to fetch addresses', error)
  }
}

const selectSavedAddress = (addr) => {
  transaction.value.address_id = addr.city_id
  transaction.value.city = addr.city
  transaction.value.address = addr.address
  transaction.value.postal_code = addr.postal_code
  addressSearch.value = addr.city
  toast.success('Address applied: ' + addr.label)
}

// Transaction data
const transaction = ref({
  buyer_id: null,
  store_id: null,
  address_id: null,
  address: null,
  city: null,
  postal_code: null,
  shipping: null,
  shipping_type: null,
  shipping_cost: 0,
  products: []
})

// Delivery related state
const couriers = ref([])
const selectedCourier = ref(null)
const showDeliveryModal = ref(false)
const deliveryFee = ref(0)

// Address search state
const addressSearch = ref('')
const addressOptions = ref([])
const showAddressOptions = ref(false)
const loadingAddress = ref(false)

// Computed properties for final calculations
const finalSubtotal = computed(() => Math.round(subtotalSelected.value + deliveryFee.value))
const finalPpn = computed(() => Math.round(subtotalSelected.value * 0.11))
const finalGrandTotal = computed(() =>
  Math.round(finalSubtotal.value + finalPpn.value - discountSelected.value)
)

const showSuccessModal = ref(false)

const loadMidtransScript = () => {
  return new Promise((resolve, reject) => {
    if (window.snap) {
      resolve()
      return
    }

    const oldScripts = document.querySelectorAll('script[src*="snap.js"]')
    oldScripts.forEach((s) => s.remove())

    const script = document.createElement('script')
    script.type = 'text/javascript'
    const clientKey = import.meta.env.VITE_MIDTRANS_CLIENT_KEY
    if (!clientKey) {
      console.error('Midtrans Client Key is missing in .env')
      reject(new Error('Midtrans configuration error'))
      return
    }
    script.setAttribute('data-client-key', clientKey)

    const isProductionEnv = import.meta.env.VITE_MIDTRANS_IS_PRODUCTION === 'true'
    const isKeyProduction = !clientKey.startsWith('SB-')
    const isProduction =
      import.meta.env.VITE_MIDTRANS_IS_PRODUCTION !== undefined ? isProductionEnv : isKeyProduction

    const snapUrl = isProduction
      ? 'https://app.midtrans.com/snap/snap.js'
      : 'https://app.sandbox.midtrans.com/snap/snap.js'

    script.src = snapUrl
    script.async = true
    script.onload = () => {
      isMidtransLoaded.value = true
      resolve()
    }
    script.onerror = (error) => {
      console.error('Failed to load Midtrans:', error)
      reject(new Error('Failed to load Midtrans payment system'))
    }

    document.head.appendChild(script)
  })
}

// Address search functionality
const handleAddressInput = debounce(async (search) => {
  if (!search.trim()) {
    showAddressOptions.value = false
    return
  }

  loadingAddress.value = true
  try {
    const response = await fetch(
      `/tariff/api/v1/destination/search?keyword=${encodeURIComponent(search)}`,
      {
        headers: {
          'x-api-key': import.meta.env.VITE_RAJAONGKIR_API_KEY
        }
      }
    )

    const data = await response.json()
    addressOptions.value = data.data
    showAddressOptions.value = true
  } catch {
    addressOptions.value = []
  } finally {
    loadingAddress.value = false
  }
}, 500)

const handleAddressSelect = (selected) => {
  transaction.value.address_id = selected.id
  transaction.value.city = selected.city_name
  transaction.value.address = selected.label
  transaction.value.postal_code = selected.zip_code
  addressSearch.value = selected.label
  showAddressOptions.value = false
  toast.success('Alamat berhasil dipilih')
}

// Delivery calculation functionality
const handleDeliveryModal = async () => {
  if (!transaction.value.address) {
    toast.error('Silakan pilih alamat terlebih dahulu')
    return
  }

  try {
    const store = selectedCarts.value[0]

    if (!store.storeAddressId || store.storeAddressId === '-') {
      toast.error('Alamat toko tidak tersedia. Tidak bisa menghitung ongkir.')
      return
    }

    const totalWeight = store.products.reduce((sum, p) => sum + p.weight * p.quantity, 0)
    const totalValue = finalSubtotal.value

    const response = await fetch(
      `/tariff/api/v1/calculate?shipper_destination_id=${store.storeAddressId}&receiver_destination_id=${transaction.value.address_id}&item_value=${totalValue}&weight=${totalWeight}`,
      {
        headers: {
          'x-api-key': import.meta.env.VITE_KOMERCE_API_KEY
        }
      }
    )

    const data = await response.json()
    couriers.value = data.data.calculate_reguler
    showDeliveryModal.value = true
  } catch {
    toast.error('Gagal menghitung ongkir. Silakan coba lagi.')
  }
}

const handleCourierSubmit = () => {
  if (!selectedCourier.value) {
    toast.error('Silakan pilih kurir')
    return
  }

  transaction.value.shipping = selectedCourier.value.shipping_name
  transaction.value.shipping_type = selectedCourier.value.service_name
  transaction.value.shipping_cost = selectedCourier.value.shipping_cost_net
  deliveryFee.value = selectedCourier.value.shipping_cost_net
  showDeliveryModal.value = false
  toast.success('Kurir berhasil dipilih')
}

const isProcessingPayment = ref(false)

const handleSubmit = async () => {
  if (!selectedCourier.value) {
    toast.error('Silakan pilih kurir terlebih dahulu')
    return
  }

  isProcessingPayment.value = true

  try {
    const response = await createTransaction(transaction.value)

    if (!response || !response.snap_token) {
      toast.error('Gagal membuat transaksi. Silakan coba lagi.')
      isProcessingPayment.value = false
      return
    }

    cart.clearSelectedItems()

    window.snap.pay(response.snap_token, {
      onSuccess: function () {
        showSuccessModal.value = true
        isProcessingPayment.value = false
        toast.success('Pembayaran berhasil!')
      },
      onPending: function () {
        isProcessingPayment.value = false
        toast.info('Menunggu pembayaran...')
        if (user.value?.username) {
          router.push({ name: 'user.my-transaction', params: { username: user.value.username } })
        } else {
          window.location.href = '/my-transactions'
        }
      },
      onError: function () {
        toast.error('Pembayaran gagal. Silakan coba lagi.')
        isProcessingPayment.value = false
      },
      onClose: function () {
        isProcessingPayment.value = false
        toast.warning('Pembayaran tertunda. Silakan cek menu Transaksi.')
        if (user.value?.username) {
          router.push({ name: 'user.my-transaction', params: { username: user.value.username } })
        } else {
          window.location.href = '/my-transactions'
        }
      }
    })
  } catch {
    toast.error('Gagal memproses transaksi. Silakan coba lagi.')
    isProcessingPayment.value = false
  }
}

const closeModal = () => {
  showDeliveryModal.value = false
  selectedCourier.value = null
}

onMounted(async () => {
  try {
    await loadMidtransScript()
  } catch (error) {
    console.error('Midtrans load error:', error)
    toast.error('Gagal memuat sistem pembayaran. Silakan refresh halaman.')
  }

  if (selectedCarts.value.length > 0) {
    const store = selectedCarts.value[0]
    transaction.value.store_id = store.storeId
    transaction.value.buyer_id = user.value?.buyer?.id
    transaction.value.products = store.products.map((p) => ({
      product_id: p.id,
      qty: p.quantity
    }))
  }

  fetchSavedAddresses()
})
</script>


<template>
  <section class="min-h-screen bg-gray-50 dark:bg-surface-dark pb-10">
    <!-- Header with Stepper -->
    <div class="bg-white dark:bg-surface-card border-b border-gray-100 dark:border-white/10 py-6">
      <div class="w-full max-w-[1240px] px-4 md:px-8 mx-auto">
        <CheckoutStepper :current-step="2" />
      </div>
    </div>

    <div class="w-full max-w-[1240px] px-4 md:px-8 mx-auto pt-6">
      <div class="flex flex-col lg:flex-row gap-6">
        <!-- LEFT COLUMN: Address + Items -->
        <div class="flex flex-col gap-5 flex-1 min-w-0">
          <!-- Shipping Address Card -->
          <div class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50 dark:border-white/5 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-900/20 dark:to-transparent">
              <div class="size-9 rounded-full bg-custom-blue/10 flex items-center justify-center shrink-0">
                <svg class="size-5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <div>
                <h2 class="font-bold text-base text-custom-black dark:text-white">Alamat Pengiriman</h2>
                <p class="text-xs text-custom-grey dark:text-gray-400">Pilih atau masukkan alamat tujuan</p>
              </div>
            </div>

            <div class="p-5 flex flex-col gap-4">
              <!-- Toggle Buttons -->
              <div class="flex gap-2">
                <button v-if="savedAddresses.length > 0" type="button"
                  class="px-4 py-2 rounded-full text-sm font-semibold transition-all"
                  :class="showSavedAddresses ? 'bg-custom-blue text-white shadow-md shadow-blue-500/20' : 'bg-gray-100 dark:bg-white/10 text-custom-grey dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/15'"
                  @click="showSavedAddresses = true">
                  Alamat Tersimpan
                </button>
                <button type="button"
                  class="px-4 py-2 rounded-full text-sm font-semibold transition-all"
                  :class="!showSavedAddresses ? 'bg-custom-blue text-white shadow-md shadow-blue-500/20' : 'bg-gray-100 dark:bg-white/10 text-custom-grey dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/15'"
                  @click="showSavedAddresses = false">
                  Cari Manual
                </button>
              </div>

              <!-- Saved Addresses -->
              <div v-if="showSavedAddresses && savedAddresses.length > 0" class="flex flex-col gap-3">
                <div v-for="addr in savedAddresses" :key="addr.id"
                  class="cursor-pointer border-2 rounded-xl p-4 transition-all hover:shadow-md"
                  :class="transaction.address === addr.address ? 'border-custom-blue bg-blue-50/50 dark:bg-blue-500/10' : 'border-gray-100 dark:border-white/10 hover:border-custom-blue/50'"
                  @click="selectSavedAddress(addr)">
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                      <span class="font-bold text-sm text-custom-black dark:text-white">{{ addr.label }}</span>
                      <span v-if="addr.is_primary" class="text-[10px] font-bold bg-custom-blue/10 text-custom-blue px-2 py-0.5 rounded-full">UTAMA</span>
                    </div>
                    <div class="size-5 rounded-full border-2 flex items-center justify-center transition-all"
                      :class="transaction.address === addr.address ? 'border-custom-blue bg-custom-blue' : 'border-gray-300 dark:border-gray-600'">
                      <svg v-if="transaction.address === addr.address" class="size-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                      </svg>
                    </div>
                  </div>
                  <p class="text-sm font-medium text-custom-black dark:text-gray-200">{{ addr.recipient_name }} <span class="text-custom-grey">({{ addr.phone }})</span></p>
                  <p class="text-xs text-custom-grey dark:text-gray-400 mt-1 line-clamp-2">{{ addr.address }}, {{ addr.city }}</p>
                </div>
              </div>

              <!-- Manual Search -->
              <div v-if="!showSavedAddresses || savedAddresses.length === 0" class="flex flex-col gap-4">
                <div class="relative">
                  <label class="group relative">
                    <div class="input-icon">
                      <svg class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    <p class="input-placeholder">Cari Kecamatan / Kota</p>
                    <input v-model="addressSearch" type="text" class="custom-input" placeholder=""
                      @input="handleAddressInput(addressSearch)" />
                  </label>
                  <ul v-if="showAddressOptions" class="search-result">
                    <li v-for="option in addressOptions" :key="option.id" @click="handleAddressSelect(option)">
                      {{ option.label }}
                    </li>
                  </ul>
                </div>

                <!-- Detail Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div class="group/errorState flex flex-col gap-2" :class="{ invalid: error?.city }">
                    <label class="group relative">
                      <div class="input-icon">
                        <svg class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                      </div>
                      <p class="input-placeholder">Kota</p>
                      <input v-model="transaction.city" type="text" class="custom-input" placeholder="" />
                    </label>
                    <span v-if="error?.city" class="text-xs font-medium text-red-500">{{ error?.city?.join(', ') }}</span>
                  </div>
                  <div class="group/errorState flex flex-col gap-2" :class="{ invalid: error?.postal_code }">
                    <label class="group relative">
                      <div class="input-icon">
                        <svg class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                      </div>
                      <p class="input-placeholder">Kode Pos</p>
                      <input v-model="transaction.postal_code" type="number" class="custom-input" placeholder="" />
                    </label>
                    <span v-if="error?.postal_code" class="text-xs font-medium text-red-500">{{ error?.postal_code?.join(', ') }}</span>
                  </div>
                </div>

                <div class="group/errorState flex flex-col gap-2" :class="{ invalid: error?.address }">
                  <label class="flex flex-col gap-2 rounded-2xl border-2 border-gray-100 dark:border-white/10 focus-within:border-custom-blue p-4 transition-all">
                    <span class="text-xs font-semibold text-custom-grey uppercase tracking-wider">Alamat Lengkap</span>
                    <textarea v-model="transaction.address"
                      class="appearance-none outline-none w-full font-medium text-sm text-custom-black dark:text-white bg-transparent resize-none leading-relaxed"
                      rows="3" placeholder="Masukkan detail alamat lengkap..."></textarea>
                  </label>
                  <span v-if="error?.address" class="text-xs font-medium text-red-500">{{ error?.address.join(', ') }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Cart Items Card -->
          <div class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50 dark:border-white/5">
              <div class="size-9 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center shrink-0">
                <svg class="size-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
              </div>
              <div>
                <h2 class="font-bold text-base text-custom-black dark:text-white">Pesanan Kamu</h2>
                <p class="text-xs text-custom-grey dark:text-gray-400">{{ totalSelectedItems }} item dari {{ selectedCarts.length }} toko</p>
              </div>
            </div>

            <!-- Empty state -->
            <div v-if="selectedCarts.length === 0" class="p-8 text-center">
              <div class="size-16 mx-auto rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
                <svg class="size-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
              </div>
              <p class="text-custom-grey font-medium">Belum ada toko yang dipilih</p>
              <RouterLink :to="{ name: 'app.cart' }" class="text-custom-blue font-bold text-sm mt-2 inline-flex items-center gap-1 hover:underline">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Keranjang
              </RouterLink>
            </div>

            <!-- Store groups -->
            <div v-for="(store, storeIdx) in selectedCarts" :key="store.storeId" class="border-b border-gray-50 dark:border-white/5 last:border-b-0">
              <!-- Store Header -->
              <div class="flex items-center gap-2 px-5 py-3 bg-gray-50/50 dark:bg-white/[0.02]">
                <div class="size-6 rounded-full bg-custom-blue/10 flex items-center justify-center">
                  <svg class="size-3.5 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <span class="font-bold text-sm text-custom-black dark:text-white">{{ store.storeName }}</span>
              </div>

              <!-- Products -->
              <div class="px-5 py-3 flex flex-col gap-3">
                <div v-for="product in store.products" :key="product.id" class="flex items-center gap-3">
                  <div class="size-14 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 p-1.5 flex items-center justify-center">
                    <img :src="product.product_images?.find((i) => i.is_thumbnail)?.image || product.thumbnail"
                      class="size-full object-contain mix-blend-multiply dark:mix-blend-normal rounded-lg" alt=""
                      @error="(e) => (e.target.src = '/src/assets/images/thumbnails/th-1.svg')" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-custom-black dark:text-white line-clamp-1">{{ product.name }}</p>
                    <p class="text-xs text-custom-grey dark:text-gray-400 mt-0.5">{{ product.weight }}kg &middot; {{ product.product_category?.name }}</p>
                  </div>
                  <div class="text-right shrink-0">
                    <p class="font-bold text-sm text-custom-black dark:text-white">Rp {{ formatRupiah(product.price) }}</p>
                    <p class="text-xs text-custom-grey dark:text-gray-400">x{{ product.quantity }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Courier Selection Card -->
          <div class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50 dark:border-white/5">
              <div class="size-9 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center shrink-0">
                <svg class="size-5 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
              </div>
              <div class="flex-1">
                <h2 class="font-bold text-base text-custom-black dark:text-white">Pilih Pengiriman</h2>
                <p class="text-xs text-custom-grey dark:text-gray-400">Estimasi ongkir berdasarkan berat & lokasi</p>
              </div>
            </div>

            <div class="p-5">
              <div v-if="!selectedCourier"
                class="flex items-center gap-4 p-4 rounded-xl border-2 border-dashed border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] cursor-pointer hover:border-custom-blue/50 hover:bg-blue-50/30 transition-all"
                @click="handleDeliveryModal">
                <div class="size-12 rounded-full bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 flex items-center justify-center shrink-0">
                  <svg class="size-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                  </svg>
                </div>
                <div class="flex-1">
                  <p class="font-bold text-sm text-custom-black dark:text-white">Pilih Kurir</p>
                  <p class="text-xs text-custom-grey dark:text-gray-400">Klik untuk cek ongkir & pilih layanan pengiriman</p>
                </div>
                <svg class="size-5 text-custom-blue shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
              </div>

              <div v-else
                class="flex items-center gap-4 p-4 rounded-xl border-2 border-custom-blue/30 bg-blue-50/50 dark:bg-blue-900/10 cursor-pointer hover:shadow-md transition-all"
                @click="handleDeliveryModal">
                <div class="size-12 rounded-full bg-custom-blue/10 flex items-center justify-center shrink-0">
                  <svg class="size-6 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <div class="flex-1">
                  <p class="font-bold text-sm text-custom-black dark:text-white">{{ selectedCourier.shipping_name }}</p>
                  <p class="text-xs text-custom-grey dark:text-gray-400">{{ selectedCourier.service_name }} &middot; Rp {{ formatRupiah(selectedCourier.shipping_cost_net) }}</p>
                </div>
                <span class="text-xs font-bold text-custom-blue hover:underline">Ubah</span>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: Order Summary (Sticky) -->
        <div class="w-full lg:w-[380px] shrink-0">
          <form class="lg:sticky lg:top-6 flex flex-col gap-5" @submit.prevent="handleSubmit">
            <!-- Summary Card -->
            <div class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 overflow-hidden">
              <div class="px-5 py-4 border-b border-gray-50 dark:border-white/5">
                <h2 class="font-bold text-base text-custom-black dark:text-white">Ringkasan Belanja</h2>
              </div>

              <div class="p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-custom-grey dark:text-gray-400">Total Harga ({{ totalSelectedQuantity }} barang)</span>
                  <span class="text-sm font-semibold text-custom-black dark:text-white">Rp {{ formatRupiah(subtotalSelected) }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-custom-grey dark:text-gray-400">Ongkos Kirim</span>
                  <span class="text-sm font-semibold text-custom-black dark:text-white">
                    <template v-if="deliveryFee > 0">Rp {{ formatRupiah(deliveryFee) }}</template>
                    <template v-else>-</template>
                  </span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-custom-grey dark:text-gray-400">PPN 11%</span>
                  <span class="text-sm font-semibold text-custom-black dark:text-white">Rp {{ formatRupiah(finalPpn) }}</span>
                </div>
                <div v-if="discountSelected > 0" class="flex items-center justify-between">
                  <span class="text-sm text-green-600">Diskon</span>
                  <span class="text-sm font-semibold text-green-600">-Rp {{ formatRupiah(discountSelected) }}</span>
                </div>

                <hr class="border-gray-100 dark:border-white/10 my-1" />

                <div class="flex items-center justify-between">
                  <span class="font-bold text-base text-custom-black dark:text-white">Total Tagihan</span>
                  <span class="font-bold text-lg text-custom-blue">Rp {{ formatRupiah(finalGrandTotal) }}</span>
                </div>
              </div>

              <!-- Pay Button -->
              <div class="px-5 pb-5">
                <button id="Pay-Button" type="submit"
                  :disabled="selectedCarts.length === 0 || !selectedCourier || isProcessingPayment || user?.role === 'admin'"
                  class="flex items-center justify-center w-full h-14 rounded-2xl font-bold text-white transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="selectedCarts.length > 0 && selectedCourier && !isProcessingPayment ? 'bg-custom-blue hover:bg-blue-700 shadow-lg shadow-blue-500/25 hover:shadow-xl hover:shadow-blue-500/30 hover:-translate-y-0.5' : 'bg-gray-300 dark:bg-gray-700'">
                  <template v-if="isProcessingPayment">
                    <div class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                    Memproses...
                  </template>
                  <template v-else-if="selectedCarts.length > 0 && selectedCourier">
                    <svg class="size-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Bayar Sekarang
                  </template>
                  <template v-else-if="user?.role === 'admin'">Admin tidak bisa Checkout</template>
                  <template v-else>Lengkapi data untuk checkout</template>
                </button>
              </div>
            </div>

            <!-- Trust Badges -->
            <TrustBadges />
          </form>
        </div>
      </div>
    </div>
  </section>


  <!-- Delivery Modal -->
  <Teleport to="body">
    <div v-if="showDeliveryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal"></div>
      <div class="relative w-full max-w-md bg-white dark:bg-surface-card rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in-95">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-white/10">
          <div>
            <h3 class="font-bold text-lg text-custom-black dark:text-white">Pilih Kurir</h3>
            <p class="text-xs text-custom-grey dark:text-gray-400">Pilih layanan pengiriman yang tersedia</p>
          </div>
          <button type="button" class="size-8 rounded-full bg-gray-100 dark:bg-white/10 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-white/20 transition-colors" @click="closeModal">
            <svg class="size-4 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Courier List -->
        <div class="p-5 max-h-[50vh] overflow-y-auto flex flex-col gap-3">
          <label v-for="courier in couriers" :key="courier.shipping_name"
            class="flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all hover:shadow-md"
            :class="selectedCourier?.shipping_name === courier.shipping_name ? 'border-custom-blue bg-blue-50/50 dark:bg-blue-900/10' : 'border-gray-100 dark:border-white/10 hover:border-custom-blue/40'">
            <div class="size-10 rounded-full bg-orange-100 dark:bg-orange-900/20 flex items-center justify-center shrink-0">
              <svg class="size-5 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
              </svg>
            </div>
            <div class="flex-1">
              <p class="font-bold text-sm text-custom-black dark:text-white">{{ courier.shipping_name }}</p>
              <p class="text-xs text-custom-grey dark:text-gray-400">{{ courier.service_name }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-sm text-custom-blue">Rp {{ formatRupiah(courier.shipping_cost_net) }}</p>
            </div>
            <input type="radio" name="courier" class="sr-only" :value="courier.code" @change="selectedCourier = courier" />
          </label>
        </div>

        <!-- Modal Footer -->
        <div class="p-5 border-t border-gray-100 dark:border-white/10">
          <button type="button"
            class="w-full h-12 rounded-xl bg-custom-blue text-white font-bold text-sm hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!selectedCourier"
            @click="handleCourierSubmit">
            Konfirmasi Kurir
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Success Modal -->
  <Teleport to="body">
    <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-sm bg-white dark:bg-surface-card rounded-2xl shadow-2xl overflow-hidden text-center">
        <div class="p-8 flex flex-col items-center gap-4">
          <div class="size-20 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
            <svg class="size-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h3 class="font-bold text-xl text-custom-black dark:text-white">Pembayaran Berhasil!</h3>
            <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">Pesanan kamu sedang diproses</p>
          </div>
        </div>
        <div class="px-6 pb-6 flex flex-col gap-3">
          <RouterLink :to="{ name: 'admin.my-transaction' }"
            class="flex items-center justify-center h-12 w-full rounded-xl bg-custom-blue text-white font-bold text-sm hover:bg-blue-700 transition-colors">
            Lihat Transaksi
          </RouterLink>
          <RouterLink :to="{ name: 'app.home' }"
            class="flex items-center justify-center h-12 w-full rounded-xl bg-gray-100 dark:bg-white/10 text-custom-black dark:text-white font-bold text-sm hover:bg-gray-200 dark:hover:bg-white/15 transition-colors">
            Kembali ke Beranda
          </RouterLink>
        </div>
      </div>
    </div>
  </Teleport>
</template>
