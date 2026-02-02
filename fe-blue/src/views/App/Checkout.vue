<script setup>
import { formatRupiah } from '@/helpers/format'
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

// Store refs
const { user } = storeToRefs(authStore)
const {
  selectedCarts,
  totalSelectedItems,
  totalSelectedQuantity,
  subtotalSelected,
  ppnSelected,
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
  transaction.value.address_id = addr.city_id // Set City ID for shipping calc
  transaction.value.city = addr.city
  transaction.value.address = addr.address
  transaction.value.postal_code = addr.postal_code
  addressSearch.value = addr.city // Sync search input visual
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
    // Cek jika sudah ada
    if (window.snap) {
      resolve()
      return
    }

    // Hapus script lama jika ada
    const oldScripts = document.querySelectorAll('script[src*="snap.js"]')
    oldScripts.forEach((s) => s.remove())

    const script = document.createElement('script')
    script.type = 'text/javascript'
    // ✅ GANTI dengan Client Key Midtrans Anda
    const clientKey = import.meta.env.VITE_MIDTRANS_CLIENT_KEY
    if (!clientKey) {
      console.error('❌ Midtrans Client Key is missing in .env')
      reject(new Error('Midtrans configuration error'))
      return
    }
    script.setAttribute('data-client-key', clientKey)

    // Use explicit environment variable, fallback to key detection if not set
    const isProductionEnv = import.meta.env.VITE_MIDTRANS_IS_PRODUCTION === 'true'
    const isKeyProduction = !clientKey.startsWith('SB-')

    // Prioritize explicit config, otherwise guess based on key
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
      console.error('❌ Failed to load Midtrans:', error)
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
  } catch (err) {
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
  } catch (error) {
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

  isProcessingPayment.value = true // ✅ Start loading

  try {
    const response = await createTransaction(transaction.value)

    if (!response || !response.snap_token) {
      toast.error('Gagal membuat transaksi. Silakan coba lagi.')
      isProcessingPayment.value = false // ✅ Stop loading
      return
    }

    // 1. Clear cart immediately to prevent duplicate orders
    cart.clearSelectedItems()

    // 2. Open Snap Popup
    window.snap.pay(response.snap_token, {
      onSuccess: function (result) {
        showSuccessModal.value = true
        isProcessingPayment.value = false
        toast.success('Pembayaran berhasil!')
      },
      onPending: function (result) {
        isProcessingPayment.value = false
        toast.info('Menunggu pembayaran...')
        // Redirect to My Transactions with username param
        if (user.value?.username) {
          router.push({ name: 'user.my-transaction', params: { username: user.value.username } })
        } else {
          window.location.href = '/my-transactions' // Fallback
        }
      },
      onError: function (result) {
        toast.error('Pembayaran gagal. Silakan coba lagi.')
        isProcessingPayment.value = false
      },
      onClose: function () {
        isProcessingPayment.value = false
        toast.warning('Pembayaran tertunda. Silakan cek menu Transaksi.')
        // Redirect to My Transactions with username param
        if (user.value?.username) {
          router.push({ name: 'user.my-transaction', params: { username: user.value.username } })
        } else {
          window.location.href = '/my-transactions' // Fallback
        }
      }
    })
  } catch (error) {
    toast.error('Gagal memproses transaksi. Silakan coba lagi.')
    isProcessingPayment.value = false // ✅ Stop loading
  }
}

// Close modal functionality
const closeModal = () => {
  showDeliveryModal.value = false
  selectedCourier.value = null
}

// Initialize transaction data
onMounted(async () => {
  // Load Midtrans script
  try {
    await loadMidtransScript()
  } catch (error) {
    console.error('Midtrans load error:', error)
    toast.error('Gagal memuat sistem pembayaran. Silakan refresh halaman.')
  }

  // Initialize transaction
  if (selectedCarts.value.length > 0) {
    const store = selectedCarts.value[0]
    transaction.value.store_id = store.storeId
    transaction.value.buyer_id = user.value?.buyer?.id
    transaction.value.products = store.products.map((p) => ({
      product_id: p.id,
      qty: p.quantity
    }))
  }

  // Fetch user saved addresses
  fetchSavedAddresses()
})
</script>

<template>
  <section class="flex flex-col gap-6 w-full max-w-[1280px] px-4 md:px-[52px] mx-auto pt-4">
    <CheckoutStepper :current-step="2" />

    <div class="flex flex-col md:flex-row gap-5">
      <!-- Cart Items Section -->
      <section
        id="Carts-Container"
        class="flex flex-col gap-5 w-full h-fit rounded-[20px] p-5 bg-white min-w-0"
      >
        <p class="font-bold text-xl">Your Cart</p>

        <!-- Empty state -->
        <div v-if="selectedCarts.length === 0" class="text-center p-8">
          <p class="text-custom-grey font-medium">No stores selected for checkout</p>
          <RouterLink
            :to="{ name: 'app.cart' }"
            class="text-custom-blue font-semibold mt-2 inline-block"
          >
            Back to Cart
          </RouterLink>
        </div>

        <!-- Selected stores and products -->
        <div
          v-for="store in selectedCarts"
          :key="store.storeId"
          class="store-cart flex flex-col w-full gap-4"
        >
          <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
            <img src="@/assets/images/icons/shop-grey.svg" class="size-4" alt="icon" />
            {{ store.storeName }}
          </p>

          <div class="flex flex-col gap-4 pl-4 border-l-2 border-gray-100">
            <div
              v-for="product in store.products"
              :key="product.id"
              class="flex items-start gap-4 w-full"
            >
              <div
                class="flex size-[64px] shrink-0 rounded-xl bg-gray-50 p-2 items-center justify-center"
              >
                <img
                  :src="
                    product.product_images?.find((i) => i.is_thumbnail)?.image || product.thumbnail
                  "
                  class="size-full object-contain mix-blend-multiply"
                  alt="icon"
                  @error="(e) => (e.target.src = '/src/assets/images/thumbnails/th-1.svg')"
                />
              </div>
              <div class="flex flex-col flex-1 gap-1">
                <p class="font-bold text-sm leading-tight line-clamp-2">{{ product.name }}</p>
                <p class="text-xs text-custom-grey font-medium">
                  {{ product.weight }} KG • {{ product.product_category.name }}
                </p>
                <div class="flex items-center justify-between mt-1">
                  <p class="font-bold text-sm text-custom-black">
                    Rp {{ formatRupiah(product.price) }}
                  </p>
                  <p class="font-semibold text-xs text-custom-grey">x{{ product.quantity }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Checkout Form Section -->
      <form class="flex flex-col gap-5 w-full md:w-[581px] shrink-0" @submit.prevent="handleSubmit">
        <!-- Security Notice -->
        <div class="relative w-full rounded-[20px] bg-custom-blue overflow-hidden">
          <img
            src="@/assets/images/backgrounds/round-ornament.svg"
            class="size-full object-contain object-right opacity-55 absolute"
            alt="icon"
          />
          <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
            <img
              src="@/assets/images/icons/shield-tick-white-fill.svg"
              class="flex size-9 shrink-0"
              alt="icon"
            />
            <p class="font-bold text-lg text-white capitalize">
              Your personal data is securely protected by us
            </p>
          </div>
        </div>

        <!-- Address Form -->
        <div class="flex flex-col gap-5 rounded-[20px] p-5 bg-white">
          <p class="font-bold text-xl">Contact & Delivery Address</p>

          <!-- Address Search -->
          <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
              <p class="font-semibold text-custom-grey">Address Details</p>
              <button
                v-if="savedAddresses.length > 0 && !showSavedAddresses"
                type="button"
                class="text-sm font-bold text-custom-blue hover:underline"
                @click="showSavedAddresses = true"
              >
                Select Saved Address
              </button>
              <button
                v-else-if="showSavedAddresses"
                type="button"
                class="text-sm font-bold text-custom-red hover:underline"
                @click="showSavedAddresses = false"
              >
                Cancel Selection
              </button>
            </div>

            <!-- Saved Addresses Grid -->
            <div v-if="showSavedAddresses" class="grid grid-cols-1 gap-3 mb-2">
              <div
                v-for="addr in savedAddresses"
                :key="addr.id"
                class="cursor-pointer border border-custom-stroke rounded-xl p-4 hover:border-custom-blue hover:bg-blue-50 transition-all group"
                :class="{
                  'border-custom-blue bg-blue-50 ring-1 ring-custom-blue':
                    transaction.address === addr.address
                }"
                @click="selectSavedAddress(addr)"
              >
                <div class="flex items-center justify-between mb-1">
                  <span class="font-bold text-custom-black">{{ addr.label }}</span>
                  <span
                    v-if="addr.is_primary"
                    class="text-[10px] font-bold bg-blue-100 text-custom-blue px-2 py-0.5 rounded-full"
                    >PRIMARY</span
                  >
                </div>
                <p class="text-sm font-semibold text-custom-black">
                  {{ addr.recipient_name }} ({{ addr.phone }})
                </p>
                <p class="text-xs text-custom-grey line-clamp-1 mt-1">
                  {{ addr.address }}, {{ addr.city }}
                </p>
              </div>
            </div>

            <!-- Manual Search (Fallback) -->
            <div v-if="!showSavedAddresses || savedAddresses.length === 0">
              <p class="font-semibold text-custom-grey text-xs uppercase tracking-wider mb-1">
                Or search manually
              </p>
              <div class="group/errorState flex flex-col gap-2 relative">
                <label class="group relative">
                  <div class="input-icon">
                    <img
                      src="@/assets/images/icons/global-search-grey.svg"
                      class="flex size-6 shrink-0"
                      alt="icon"
                    />
                  </div>
                  <p class="input-placeholder">Enter District / City</p>
                  <input
                    v-model="addressSearch"
                    type="text"
                    class="custom-input"
                    placeholder=""
                    @input="handleAddressInput(addressSearch)"
                  />
                </label>
                <ul v-if="showAddressOptions" class="search-result">
                  <li
                    v-for="option in addressOptions"
                    :key="option.id"
                    @click="handleAddressSelect(option)"
                  >
                    {{ option.label }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Address Details -->
          <div class="flex flex-col gap-3">
            <p class="font-semibold text-custom-grey">Your Address</p>
            <div
              class="group/errorState flex flex-col gap-2 w-full"
              :class="{ invalid: error?.address }"
            >
              <label
                class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red"
              >
                <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border">
                  <img
                    src="@/assets/images/icons/location-grey.svg"
                    class="flex size-6 shrink-0"
                    alt="icon"
                  />
                </div>
                <div class="flex flex-col gap-[6px] pl-4 w-full">
                  <p
                    class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300"
                  >
                    Enter Your Address
                  </p>
                  <textarea
                    v-model="transaction.address"
                    class="appearance-none outline-none w-full font-semibold leading-[160%]"
                    rows="3"
                    placeholder=""
                  ></textarea>
                </div>
              </label>
              <span
                v-if="error?.address"
                class="font-semibold text-lg text-custom-red leading-none group-[&.invalid]/errorState:block"
              >
                {{ error?.address.join(', ') }}
              </span>
            </div>
          </div>

          <!-- City Input -->
          <div class="flex flex-col gap-3">
            <p class="font-semibold text-custom-grey">Your City</p>
            <div class="group/errorState flex flex-col gap-2" :class="{ invalid: error?.city }">
              <label class="group relative">
                <div class="input-icon">
                  <img
                    src="@/assets/images/icons/buildings-grey.svg"
                    class="flex size-6 shrink-0"
                    alt="icon"
                  />
                </div>
                <p class="input-placeholder">Enter Your City</p>
                <input v-model="transaction.city" type="text" class="custom-input" placeholder="" />
              </label>
              <span v-if="error?.city" class="input-error">{{ error?.city?.join(', ') }}</span>
            </div>
          </div>

          <!-- Postal Code Input -->
          <div class="flex flex-col gap-3">
            <p class="font-semibold text-custom-grey">Post Code</p>
            <div
              class="group/errorState flex flex-col gap-2"
              :class="{ invalid: error?.postal_code }"
            >
              <label class="group relative">
                <div class="input-icon">
                  <img
                    src="@/assets/images/icons/keyboard-grey.svg"
                    class="flex size-6 shrink-0"
                    alt="icon"
                  />
                </div>
                <p class="input-placeholder">Enter Post Code</p>
                <input
                  v-model="transaction.postal_code"
                  type="number"
                  class="custom-input"
                  placeholder=""
                />
              </label>
              <span v-if="error?.postal_code" class="input-error">
                {{ error?.postal_code?.join(', ') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Courier Selection -->
        <div class="flex flex-col gap-5 rounded-[20px] p-5 bg-white">
          <p class="font-bold text-xl">Courier Selection</p>
          <div class="flex flex-col gap-3">
            <div
              class="flex items-center justify-between rounded-[20px] border border-custom-stroke p-5 gap-[14px]"
            >
              <div
                class="flex rounded-full size-14 shrink-0 items-center justify-center bg-custom-background"
              >
                <img src="@/assets/images/icons/car-delivery-black.svg" class="size-6" alt="icon" />
              </div>
              <div v-if="!selectedCourier" class="w-full">
                <p class="font-bold text-lg leading-none">No Courier Selected</p>
                <p class="font-semibold text-custom-grey mt-[6px] leading-none">
                  Calculate to select courier
                </p>
              </div>
              <div v-else class="w-full">
                <p class="font-bold text-lg leading-none">{{ selectedCourier.shipping_name }}</p>
                <p class="font-semibold text-custom-grey mt-[6px] leading-none">
                  {{ selectedCourier.shipping_type }} (Rp
                  {{ formatRupiah(selectedCourier.shipping_cost_net) }})
                </p>
              </div>
              <button
                type="button"
                class="flex items-center gap-0.5 shrink-0"
                @click="handleDeliveryModal"
              >
                <p class="font-bold text-custom-blue">Calculate Delivery</p>
                <img
                  src="@/assets/images/icons/arrow-right-no-tail-blue-thick.svg"
                  class="size-4"
                  alt=""
                />
              </button>
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="flex flex-col gap-4 rounded-[20px] p-5 bg-white">
          <p class="font-bold text-xl">Order Summary</p>
          <div class="flex flex-col rounded-xl border border-custom-stroke p-5 gap-4">
            <div class="flex items-center justify-between">
              <p
                class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none"
              >
                <img
                  src="@/assets/images/icons/shopping-cart-grey.svg"
                  class="size-6 flex shrink-0"
                  alt="icon"
                />
                Total Items:
              </p>
              <p class="font-bold text-lg leading-none">{{ totalSelectedItems }} Items</p>
            </div>
            <hr class="border-custom-stroke" />
            <div class="flex items-center justify-between">
              <p
                class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none"
              >
                <img
                  src="@/assets/images/icons/box-grey.svg"
                  class="size-6 flex shrink-0"
                  alt="icon"
                />
                Total Quantity:
              </p>
              <p class="font-bold text-lg leading-none">{{ totalSelectedQuantity }}x</p>
            </div>
            <hr class="border-custom-stroke" />
            <div class="flex items-center justify-between">
              <p
                class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none"
              >
                <img
                  src="@/assets/images/icons/car-delivery-moves-grey.svg"
                  class="size-6 flex shrink-0"
                  alt="icon"
                />
                Delivery Fee:
              </p>
              <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(deliveryFee) }}</p>
            </div>
            <hr class="border-custom-stroke" />
            <div class="flex items-center justify-between">
              <p
                class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none"
              >
                <img
                  src="@/assets/images/icons/ticket-grey.svg"
                  class="size-6 flex shrink-0"
                  alt="icon"
                />
                Sub Total:
              </p>
              <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(finalSubtotal) }}</p>
            </div>
            <hr class="border-custom-stroke" />
            <div class="flex items-center justify-between">
              <p
                class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none"
              >
                <img
                  src="@/assets/images/icons/receipt-2-grey.svg"
                  class="size-6 flex shrink-0"
                  alt="icon"
                />
                PPN 11%
              </p>
              <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(finalPpn) }}</p>
            </div>
            <hr class="border-custom-stroke" />
            <div class="flex items-center justify-between">
              <p
                class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none"
              >
                <img
                  src="@/assets/images/icons/discount-shape-grey.svg"
                  class="size-6 flex shrink-0"
                  alt="icon"
                />
                Discount
              </p>
              <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(discountSelected) }}</p>
            </div>
            <hr class="border-custom-stroke" />
            <div class="flex items-center justify-between">
              <p
                class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none"
              >
                <img
                  src="@/assets/images/icons/money-grey.svg"
                  class="size-6 flex shrink-0"
                  alt="icon"
                />
                Grand total
              </p>
              <p class="font-bold text-lg leading-none text-custom-blue">
                Rp {{ formatRupiah(finalGrandTotal) }}
              </p>
            </div>
          </div>
          <button
            id="Pay-Button"
            type="submit"
            :disabled="selectedCarts.length === 0 || !selectedCourier || isProcessingPayment"
            class="flex items-center justify-center h-16 w-full rounded-2xl p-4 gap-2 bg-custom-blue disabled:bg-custom-stroke transition-300"
          >
            <!-- Loading State -->
            <template v-if="isProcessingPayment">
              <div
                class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin"
              ></div>
              <span class="font-bold text-white">Processing Payment...</span>
            </template>

            <!-- Ready to Pay -->
            <template v-else-if="selectedCarts.length > 0 && selectedCourier">
              <span class="font-bold text-white">Pay With Midtrans</span>
              <img
                src="@/assets/images/icons/arrow-right-circle-white-thick.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </template>

            <!-- Incomplete Form -->
            <template v-else>
              <span class="font-bold text-white">Complete form to checkout</span>
            </template>
          </button>

          <TrustBadges />
        </div>
      </form>
    </div>
  </section>

  <!-- Delivery Modal -->
  <div
    v-if="showDeliveryModal"
    id="Delivery-Modal"
    class="modal flex flex-1 items-center justify-center h-full fixed top-0 w-full z-50"
  >
    <div class="backdrop absolute w-full h-full bg-[#292D32B2]" @click="closeModal"></div>
    <div
      id="Select-Courier"
      class="relative flex flex-col w-full max-w-[460px] mx-4 shrink-0 h-fit rounded-2xl overflow-hidden"
    >
      <div class="header flex items-center p-5 gap-3 justify-between bg-custom-black">
        <p class="font-semibold text-lg text-white">Select Courier</p>
        <button type="button" @click="closeModal">
          <img
            src="@/assets/images/icons/close-circle-white.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </button>
      </div>
      <div class="flex flex-col p-5 gap-3 bg-white">
        <div class="flex flex-col gap-3">
          <p class="font-semibold text-custom-grey">Available Couriers</p>
          <div
            class="flex flex-col gap-3 h-[346px] p-0.5 overflow-y-auto hide-scrollbar overscroll-contain"
          >
            <label
              v-for="courier in couriers"
              :key="courier.shipping_name"
              class="courier-choice flex items-center w-full rounded-[20px] ring-1 ring-custom-stroke p-5 gap-[14px] has-[:checked]:ring-2 has-[:checked]:ring-custom-blue has-[:checked]:transparent-blue-to-blue-gradient transition-300"
            >
              <div
                class="flex rounded-full size-14 shrink-0 items-center justify-center bg-custom-background"
              >
                <img src="@/assets/images/icons/car-delivery-black.svg" class="size-6" alt="icon" />
              </div>
              <div class="w-full">
                <p class="courier-name font-bold text-lg leading-none">
                  {{ courier.shipping_name }}
                </p>
                <p class="courier-fee font-semibold text-custom-grey mt-[6px] leading-none">
                  {{ courier.service_name }} (Rp {{ formatRupiah(courier.shipping_cost_net) }})
                </p>
              </div>
              <input
                type="radio"
                name="courier"
                required
                class="size-6 flex shrink-0 appearance-none rounded-full checked:border-[3px] checked:border-solid checked:border-white checked:bg-custom-blue ring-2 ring-custom-grey checked:ring-custom-blue transition-300"
                :value="courier.code"
                @change="selectedCourier = courier"
              />
            </label>
          </div>
          <button
            id="Choose-Courier"
            type="button"
            class="flex items-center justify-center h-16 w-full rounded-2xl p-4 gap-2 bg-custom-blue disabled:bg-custom-stroke transition-300"
            @click="handleCourierSubmit"
          >
            <span class="font-bold text-white">Choose Courier</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div
    v-if="showSuccessModal"
    id="Success-Modal"
    class="modal flex flex-1 items-center justify-center h-full fixed top-0 w-full z-50"
  >
    <div class="backdrop absolute w-full h-full bg-[#292D32B2]"></div>
    <div
      id="Select-Courier"
      class="relative flex flex-col w-[400px] shrink-0 h-fit rounded-2xl overflow-hidden"
    >
      <div class="header flex h-[63px] items-center p-5 gap-3 justify-center bg-custom-black">
        <p class="font-semibold text-lg text-white">Payment Successful</p>
      </div>
      <div class="flex flex-col p-5 gap-3 bg-white">
        <div class="flex flex-col items-center justify-center w-full h-[207px]">
          <img
            src="@/assets/images/icons/bag-tick-blue-transparent.svg"
            class="size-[72px]"
            alt="icon"
          />
          <div class="text-center">
            <p class="font-bold text-xl">Thank You!</p>
            <p class="font-semibold text-custom-grey">Your order has been placed successfully.</p>
          </div>
        </div>
        <div class="flex flex-col gap-3">
          <RouterLink
            :to="{ name: 'admin.my-transaction' }"
            class="flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-2 bg-custom-blue"
          >
            <span class="font-bold text-white">View Transaction</span>
          </RouterLink>
          <RouterLink
            :to="{ name: 'app.home' }"
            class="flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-2 bg-custom-blue/10"
          >
            <span class="font-bold text-custom-blue">Back to Homepage</span>
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>
