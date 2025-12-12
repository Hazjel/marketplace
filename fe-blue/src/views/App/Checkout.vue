<script setup>
import { formatRupiah } from '@/helpers/format';
import { useCartStore } from '@/stores/cart'
import { useTransactionStore } from '@/stores/transaction';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { onMounted, ref, computed } from 'vue';
import { debounce } from 'lodash';

// Store imports
const authStore = useAuthStore()
const cart = useCartStore()
const transactionStore = useTransactionStore()

// Store refs
const { user } = storeToRefs(authStore)
const { selectedCarts, totalSelectedItems, totalSelectedQuantity, subtotalSelected, ppnSelected, discountSelected } = storeToRefs(cart)
const { error } = storeToRefs(transactionStore)
const { createTransaction } = transactionStore

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
const addressSearch = ref('');
const addressOptions = ref([]);
const showAddressOptions = ref(false);
const loadingAddress = ref(false);

// Computed properties for final calculations
const finalSubtotal = computed(() => Math.round(subtotalSelected.value + deliveryFee.value))
const finalPpn = computed(() => Math.round(subtotalSelected.value * 0.11))
const finalGrandTotal = computed(() => Math.round(finalSubtotal.value + finalPpn.value - discountSelected.value))

const showSuccessModal = ref(false)

// Address search functionality
const handleAddressInput = debounce(async (search) => {
    if (!search.trim()) {
        showAddressOptions.value = false;
        return;
    }

    loadingAddress.value = true;
    try {
        const response = await fetch(`/tariff/api/v1/destination/search?keyword=${encodeURIComponent(search)}`, {
            headers: {
                'x-api-key': 'f0lW8jxe09eefaaa94eb3c99ABk9n8B6'
            }
        });

        const data = await response.json();
        addressOptions.value = data.data;
        showAddressOptions.value = true;
    } catch (err) {
        console.error('Error fetching address:', err);
        addressOptions.value = [];
    } finally {
        loadingAddress.value = false;
    }
}, 500);

const handleAddressSelect = (selected) => {
    transaction.value.address_id = selected.id;
    transaction.value.city = selected.city_name;
    transaction.value.address = selected.label;
    transaction.value.postal_code = selected.zip_code;
    addressSearch.value = selected.label;
    showAddressOptions.value = false;
};

// Delivery calculation functionality
const handleDeliveryModal = async () => {
    if (!transaction.value.address) {
        alert('Please select an address first');
        return;
    }

    try {
        const store = selectedCarts.value[0];
        const totalWeight = store.products.reduce((sum, p) => sum + p.weight * p.quantity, 0);
        const totalValue = finalSubtotal.value;

        const response = await fetch(
            `/tariff/api/v1/calculate?shipper_destination_id=${store.storeAddressId}&receiver_destination_id=${transaction.value.address_id}&item_value=${totalValue}&weight=${totalWeight}`,
            {
                headers: {
                    'x-api-key': 'f0lW8jxe09eefaaa94eb3c99ABk9n8B6'
                }
            }
        );

        const data = await response.json();
        couriers.value = data.data.calculate_reguler;
        showDeliveryModal.value = true;
    } catch (error) {
        console.error('Error calculating delivery fee:', error);
        alert('Failed to calculate delivery fee. Please try again.');
    }
};

const handleCourierSubmit = () => {
    if (!selectedCourier.value) {
        alert('Please select a courier');
        return;
    }

    transaction.value.shipping = selectedCourier.value.shipping_name;
    transaction.value.shipping_type = selectedCourier.value.service_name;
    transaction.value.shipping_cost = selectedCourier.value.shipping_cost_net;
    deliveryFee.value = selectedCourier.value.shipping_cost_net;
    showDeliveryModal.value = false;
};

// Form submission
const handleSubmit = async () => {
    if (!selectedCourier.value) {
        alert('Please select a courier first');
        return;
    }

    try {
        // Sertakan biaya pengiriman dan perhitungan akhir ke payload transaksi
        transaction.value.shipping_cost = Math.round(deliveryFee.value);
        transaction.value.subtotal = Math.round(subtotalSelected.value);
        transaction.value.tax = Math.round(finalPpn.value);
        transaction.value.discount = Math.round(discountSelected.value);
        transaction.value.grand_total = Math.round(finalGrandTotal.value);

        const response = await createTransaction(transaction.value);
        
        // ✅ Validasi response sebelum mengakses snap_token
        if (!response || !response.snap_token) {
            alert('Failed to create transaction. Please try again.');
            console.error('Invalid response:', response);
            return;
        }

        window.snap.pay(response.snap_token, {
            onSuccess: function (result) {
                console.log('Payment success:', result);
                // Show modal first before any other operations
                showSuccessModal.value = true;
                // Clear the selected stores from cart
                cart.clearSelectedStores();
            },
            onPending: function (result) {
                console.log('Payment pending:', result);
            },
            onError: function (result) {
                console.error('Payment error:', result);
                alert('Payment failed. Please try again.');
            },
            onClose: function () {
                console.log('Payment popup closed');
            }
        })
    } catch (error) {
        console.error('Transaction error:', error);
        alert('Failed to process transaction. Please try again.');
    }
};

// Close modal functionality
const closeModal = () => {
    showDeliveryModal.value = false;
    selectedCourier.value = null;
};

// Initialize transaction data
onMounted(() => {
    if (selectedCarts.value.length > 0) {
        const store = selectedCarts.value[0];
        transaction.value.store_id = store.storeId;
        transaction.value.buyer_id = user.value?.buyer?.id;
        transaction.value.products = store.products.map(p => ({
            product_id: p.id,
            qty: p.quantity
        }));
    }
});
</script>

<template>
    <section class="flex flex-col gap-6 w-full max-w-[1280px] px-[52px] mx-auto">
        <h1 class="font-bold text-[32px]">Checkout Page</h1>

        <div class="flex gap-5">
            <!-- Cart Items Section -->
            <section id="Carts-Container" class="flex flex-col gap-5 w-full h-fit rounded-[20px] p-5 bg-white min-w-0">
                <p class="font-bold text-xl">Your Cart</p>

                <!-- Empty state -->
                <div v-if="selectedCarts.length === 0" class="text-center p-8">
                    <p class="text-custom-grey font-medium">No stores selected for checkout</p>
                    <RouterLink :to="{ name: 'app.cart' }" class="text-custom-blue font-semibold mt-2 inline-block">
                        Back to Cart
                    </RouterLink>
                </div>

                <!-- Selected stores and products -->
                <div v-for="store in selectedCarts" :key="store.storeId" class="store-cart flex flex-col w-full gap-4">
                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                        <img src="@/assets/images/icons/shop-grey.svg" class="size-4" alt="icon">
                        {{ store.storeName }}
                    </p>

                    <div v-for="product in store.products" :key="product.id"
                        class="items-detail flex flex-col w-full rounded-[20px] border border-custom-stroke p-5 gap-5">
                        <div class="flex items-center gap-[14px] overflow-hidden">
                            <div
                                class="flex size-[92px] shrink-0 rounded-2xl bg-custom-background overflow-hidden items-center justify-center">
                                <img :src="product.product_images.find(i => i.is_thumbnail).image"
                                    class="size-full object-contain" alt="icon">
                            </div>
                            <div class="flex flex-col flex-1 gap-[6px] overflow-hidden">
                                <p class="font-bold text-lg">{{ product.name }}</p>
                                <p class="font-semibold leading-none text-custom-grey flex items-center gap-[6px]">
                                    <span class="font-bold text-custom-blue">{{ product.product_category.name }}</span>
                                    <span class="text-[22px] leading-none">•</span>
                                    <span>{{ product.weight }} KG</span>
                                </p>
                            </div>
                            <div class="flex flex-col shrink-0 items-end">
                                <p class="font-bold text-custom-blue">Rp {{ formatRupiah(product.price) }}</p>
                                <p class="font-semibold text-grey">({{ product.quantity }}x)</p>
                            </div>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/shopping-cart-grey.svg" class="size-5" alt="icon">
                                Subtotal
                            </p>
                            <p class="font-bold text-lg text-custom-blue">Rp {{ formatRupiah(product.price *
                                product.quantity) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Checkout Form Section -->
            <form @submit.prevent="handleSubmit" class="flex flex-col gap-5 w-[581px] shrink-0">
                <!-- Security Notice -->
                <div class="relative w-full rounded-[20px] bg-custom-blue overflow-hidden">
                    <img src="@/assets/images/backgrounds/round-ornament.svg"
                        class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                    <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                        <img src="@/assets/images/icons/shield-tick-white-fill.svg" class="flex size-9 shrink-0"
                            alt="icon">
                        <p class="font-bold text-lg text-white capitalize">Your personal data is securely protected by
                            us</p>
                    </div>
                </div>

                <!-- Address Form -->
                <div class="flex flex-col gap-5 rounded-[20px] p-5 bg-white">
                    <p class="font-bold text-xl">Contact & Delivery Address</p>

                    <!-- Address Search -->
                    <div class="flex flex-col gap-3">
                        <p class="font-semibold text-custom-grey">Address Searching</p>
                        <div class="group/errorState flex flex-col gap-2 relative">
                            <label class="group relative">
                                <div class="input-icon">
                                    <img src="@/assets/images/icons/global-search-grey.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                </div>
                                <p class="input-placeholder">Enter District</p>
                                <input type="text" class="custom-input" placeholder="" v-model="addressSearch"
                                    @input="handleAddressInput(addressSearch)">
                            </label>
                            <ul class="search-result" v-if="showAddressOptions">
                                <li v-for="option in addressOptions" :key="option.id"
                                    @click="handleAddressSelect(option)">
                                    {{ option.label }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Address Details -->
                    <div class="flex flex-col gap-3">
                        <p class="font-semibold text-custom-grey">Your Address</p>
                        <div class="group/errorState flex flex-col gap-2 w-full" :class="{ 'invalid': error?.address }">
                            <label
                                class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red">
                                <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border">
                                    <img src="@/assets/images/icons/location-grey.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                </div>
                                <div class="flex flex-col gap-[6px] pl-4 w-full">
                                    <p
                                        class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300">
                                        Enter Your Address
                                    </p>
                                    <textarea class="appearance-none outline-none w-full font-semibold leading-[160%]"
                                        rows="3" placeholder="" v-model="transaction.address"></textarea>
                                </div>
                            </label>
                            <span
                                class="font-semibold text-lg text-custom-red leading-none group-[&.invalid]/errorState:block"
                                v-if="error?.address">
                                {{ error?.address.join(', ') }}
                            </span>
                        </div>
                    </div>

                    <!-- City Input -->
                    <div class="flex flex-col gap-3">
                        <p class="font-semibold text-custom-grey">Your City</p>
                        <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': error?.city }">
                            <label class="group relative">
                                <div class="input-icon">
                                    <img src="@/assets/images/icons/buildings-grey.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                </div>
                                <p class="input-placeholder">Enter Your City</p>
                                <input type="text" class="custom-input" placeholder="" v-model="transaction.city">
                            </label>
                            <span class="input-error" v-if="error?.city">{{ error?.city?.join(', ') }}</span>
                        </div>
                    </div>

                    <!-- Postal Code Input -->
                    <div class="flex flex-col gap-3">
                        <p class="font-semibold text-custom-grey">Post Code</p>
                        <div class="group/errorState flex flex-col gap-2" :class="{ 'invalid': error?.postal_code }">
                            <label class="group relative">
                                <div class="input-icon">
                                    <img src="@/assets/images/icons/keyboard-grey.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                </div>
                                <p class="input-placeholder">Enter Post Code</p>
                                <input type="number" class="custom-input" placeholder=""
                                    v-model="transaction.postal_code">
                            </label>
                            <span class="input-error" v-if="error?.postal_code">
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
                            class="flex items-center justify-between rounded-[20px] border border-custom-stroke p-5 gap-[14px]">
                            <div
                                class="flex rounded-full size-14 shrink-0 items-center justify-center bg-custom-background">
                                <img src="@/assets/images/icons/car-delivery-black.svg" class="size-6" alt="icon">
                            </div>
                            <div class="w-full" v-if="!selectedCourier">
                                <p class="font-bold text-lg leading-none">No Courier Selected</p>
                                <p class="font-semibold text-custom-grey mt-[6px] leading-none">Calculate to select
                                    courier</p>
                            </div>
                            <div class="w-full" v-else>
                                <p class="font-bold text-lg leading-none">{{ selectedCourier.shipping_name }}</p>
                                <p class="font-semibold text-custom-grey mt-[6px] leading-none">
                                    {{ selectedCourier.shipping_type }} (Rp {{
                                        formatRupiah(selectedCourier.shipping_cost_net) }})
                                </p>
                            </div>
                            <button type="button" class="flex items-center gap-0.5 shrink-0"
                                @click="handleDeliveryModal">
                                <p class="font-bold text-custom-blue">Calculate Delivery</p>
                                <img src="@/assets/images/icons/arrow-right-no-tail-blue-thick.svg" class="size-4"
                                    alt="">
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="flex flex-col gap-4 rounded-[20px] p-5 bg-white">
                    <p class="font-bold text-xl">Order Summary</p>
                    <div class="flex flex-col rounded-xl border border-custom-stroke p-5 gap-4">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/shopping-cart-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                Total Items:
                            </p>
                            <p class="font-bold text-lg leading-none">{{ totalSelectedItems }} Items</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/box-grey.svg" class="size-6 flex shrink-0" alt="icon">
                                Total Quantity:
                            </p>
                            <p class="font-bold text-lg leading-none">{{ totalSelectedQuantity }}x</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/car-delivery-moves-grey.svg"
                                    class="size-6 flex shrink-0" alt="icon">
                                Delivery Fee:
                            </p>
                            <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(deliveryFee) }}</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/ticket-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                Sub Total:
                            </p>
                            <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(finalSubtotal) }}</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/receipt-2-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                PPN 11%
                            </p>
                            <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(finalPpn) }}</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/discount-shape-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                Discount
                            </p>
                            <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(discountSelected) }}</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/money-grey.svg" class="size-6 flex shrink-0" alt="icon">
                                Grand total
                            </p>
                            <p class="font-bold text-lg leading-none text-custom-blue">Rp {{
                                formatRupiah(finalGrandTotal) }}</p>
                        </div>
                    </div>
                    <button type="submit" id="Pay-Button" :disabled="selectedCarts.length === 0 || !selectedCourier"
                        class="flex items-center justify-center h-16 w-full rounded-2xl p-4 gap-2 bg-custom-blue disabled:bg-custom-stroke transition-300">
                        <span class="font-bold text-white" v-if="selectedCarts.length > 0 && selectedCourier">
                            Pay With Midtrans
                        </span>
                        <span class="font-bold text-white" v-else>
                            Complete form to checkout
                        </span>
                        <img src="@/assets/images/icons/arrow-right-circle-white-thick.svg" class="flex size-6 shrink-0"
                            alt="icon">
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Delivery Modal -->
    <div id="Delivery-Modal" class="modal flex flex-1 items-center justify-center h-full fixed top-0 w-full z-50"
        v-if="showDeliveryModal">
        <div class="backdrop absolute w-full h-full bg-[#292D32B2]" @click="closeModal"></div>
        <div id="Select-Courier" class="relative flex flex-col w-[460px] shrink-0 h-fit rounded-2xl overflow-hidden">
            <div class="header flex items-center p-5 gap-3 justify-between bg-custom-black">
                <p class="font-semibold text-lg text-white">Select Courier</p>
                <button type="button" @click="closeModal">
                    <img src="@/assets/images/icons/close-circle-white.svg" class="flex size-6 shrink-0" alt="icon">
                </button>
            </div>
            <div class="flex flex-col p-5 gap-3 bg-white">
                <div class="flex flex-col gap-3">
                    <p class="font-semibold text-custom-grey">Available Couriers</p>
                    <div class="flex flex-col gap-3 h-[346px] p-0.5 overflow-y-auto hide-scrollbar overscroll-contain">
                        <label
                            class="courier-choice flex items-center w-full rounded-[20px] ring-1 ring-custom-stroke p-5 gap-[14px] has-[:checked]:ring-2 has-[:checked]:ring-custom-blue has-[:checked]:transparent-blue-to-blue-gradient transition-300"
                            v-for="courier in couriers" :key="courier.shipping_name">
                            <div
                                class="flex rounded-full size-14 shrink-0 items-center justify-center bg-custom-background">
                                <img src="@/assets/images/icons/car-delivery-black.svg" class="size-6" alt="icon">
                            </div>
                            <div class="w-full">
                                <p class="courier-name font-bold text-lg leading-none">{{ courier.shipping_name }}</p>
                                <p class="courier-fee font-semibold text-custom-grey mt-[6px] leading-none">
                                    {{ courier.service_name }} (Rp {{ formatRupiah(courier.shipping_cost_net) }})
                                </p>
                            </div>
                            <input type="radio" name="courier" required
                                class="size-6 flex shrink-0 appearance-none rounded-full checked:border-[3px] checked:border-solid checked:border-white checked:bg-custom-blue ring-2 ring-custom-grey checked:ring-custom-blue transition-300"
                                :value="courier.code" @change="selectedCourier = courier">
                        </label>
                    </div>
                    <button type="button" id="Choose-Courier"
                        class="flex items-center justify-center h-16 w-full rounded-2xl p-4 gap-2 bg-custom-blue disabled:bg-custom-stroke transition-300"
                        @click="handleCourierSubmit">
                        <span class="font-bold text-white">Choose Courier</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="Success-Modal" class="modal flex flex-1 items-center justify-center h-full fixed top-0 w-full z-50"
        v-if="showSuccessModal">
        <div class="backdrop absolute w-full h-full bg-[#292D32B2]"></div>
        <div id="Select-Courier" class="relative flex flex-col w-[400px] shrink-0 h-fit rounded-2xl overflow-hidden">
            <div class="header flex h-[63px] items-center p-5 gap-3 justify-center bg-custom-black">
                <p class="font-semibold text-lg text-white">Payment Successful</p>
            </div>
            <div class="flex flex-col p-5 gap-3 bg-white">
                <div class="flex flex-col items-center justify-center w-full h-[207px]">
                    <img src="@/assets/images/icons/bag-tick-blue-transparent.svg" class="size-[72px]" alt="icon">
                    <div class="text-center">
                        <p class="font-bold text-xl">Thank You!</p>
                        <p class="font-semibold text-custom-grey">Your order has been placed successfully.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <RouterLink :to="{ name: 'app.my-transactions' }"
                        class="flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-2 bg-custom-blue">
                        <span class="font-bold text-white">View Transaction</span>
                    </RouterLink>
                    <RouterLink :to="{ name: 'app.home' }"
                        class="flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-2 bg-custom-blue/10">
                        <span class="font-bold text-custom-blue">Back to Homepage</span>
                    </RouterLink>
                </div>
            </div>
        </div>
    </div>
</template>