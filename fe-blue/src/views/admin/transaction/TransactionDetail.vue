<script setup>
import Alert from '@/components/admin/Alert.vue';
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format';
import { useTransactionStore } from '@/stores/transaction';
import { storeToRefs } from 'pinia';
import { onMounted, ref} from 'vue';
import { RouterLink, useRoute } from 'vue-router';

const route = useRoute()

const transaction = ref({})

const transactionStore = useTransactionStore()
const { loading, success, error } = storeToRefs(transactionStore)
const { fetchTransactionById, updateTransaction } = transactionStore

const fetchData = async () => {
    const response = await fetchTransactionById(route.params.id)

    transaction.value = response
    transaction.value.delivery_proof_url = PlaceHolder
}

const handleUpdateData = async() =>{
    await updateTransaction(transaction.value)

    fetchData()
}

const handleAcceptOrder=() =>{
    transaction.value.delivery_proof = null
    transaction.value.delivery_status = 'processing'
    handleUpdateData()
}

const handleDeliverySubmit = () => {
    transaction.value.delivery_status = 'delivering'

    handleUpdateData()
}

const handleImageChange = (e) => {
    const file = e.target.files[0]

    transaction.value.delivery_proof = file
    transaction.value.delivery_proof_url = URL.createObjectURL(file)
}


const closeAlert = () => {
    transactionStore.success = null
    transactionStore.error = null
}

onMounted(fetchData)
</script>

<template>
    <div class="flex flex-1 gap-5">
        <div class="flex flex-col gap-5 w-full">
            <div class="relative w-full rounded-[20px] bg-custom-yellow overflow-hidden" v-if="transaction?.delivery_status === 'pending'">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/timer-chocolate.svg" class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-[#544607]">Order pending. Kindly wait for review 🙌</p>
                </div>
            </div>
            <div class="relative w-full rounded-[20px] bg-custom-blue overflow-hidden" v-if="transaction?.delivery_status === 'processing'">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/truck-time-white-fill.svg" class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-white">Prepare the item for pickup by the courier</p>
                </div>
            </div>
            <div class="relative w-full rounded-[20px] bg-custom-orange overflow-hidden" v-if="transaction?.delivery_status === 'delivering'">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/truck-time-white-fill.svg" class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-white">The order is heading to customer address</p>
                </div>
            </div>
            <div class="relative w-full rounded-[20px] bg-custom-green overflow-hidden" v-if="transaction?.delivery_status === 'completed'">
                <img src="@/assets/images/backgrounds/round-ornament.svg"
                    class="size-full object-contain object-right opacity-55 absolute" alt="icon">
                <div class="relative flex items-center min-h-[68px] gap-[10px] p-4">
                    <img src="@/assets/images/icons/truck-time-white-fill.svg" class="flex size-9 shrink-0" alt="icon">
                    <p class="font-bold text-lg text-white">The order is arrived to customer address</p>
                </div>
            </div>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
                <p class="font-bold text-xl">Order Reviews</p>
                <div class="flex items-center gap-[14px] w-full min-w-0">
                    <div class="flex size-[92px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                        <img :src="transaction?.store?.logo" class="size-full object-cover" alt="photo">
                    </div>
                    <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                        <p class="font-bold text-[22px] leading-tight w-full truncate">
                            {{ transaction?.store?.name }}
                        </p>
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon">
                            {{ transaction?.store?.user?.name }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.transaction_details?.length }}</p>
                            <p class="font-semibold text-custom-grey">Total Products</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.transaction_details?.reduce((total, detail) => total + detail.qty, 0) }}</p>
                            <p class="font-semibold text-custom-grey">Total Quantity</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/calendar-2-black.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ formatToClientTimeZone(transaction?.created_at) }}</p>
                            <p class="font-semibold text-custom-grey">Date Transaction</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 bg-white">
                <button data-accordion-type="content" data-expand="Products" class="flex justify-between">
                    <div class="flex flex-col gap-2">
                        <p class="font-bold text-xl">Product Details</p>
                        <div class="flex items-center gap-1">
                            <img src="@/assets/images/icons/shopping-cart-grey.svg" class="flex size-6 shrink-0"
                                alt="icon">
                            <p class="font-semibold text-custom-grey">{{ transaction?.transaction_details?.length }} Total Products</p>
                        </div>
                    </div>
                    <img src="@/assets/images/icons/arrow-circle-up.svg" class="size-6 flex shrink-0 transition-300"
                        alt="icon">
                </button>
                <div id="Products" class="flex flex-col gap-4 mt-5">
                    <div class="card flex flex-col rounded-2xl border border-custom-stroke p-4 gap-5" v-for="product in transaction?.transaction_details">
                        <div class="flex items-center w-full gap-5">
                            <div class="flex items-center gap-[14px] w-full min-w-0 overflow-hidden">
                                <div class="flex size-[92px] rounded-2xl bg-custom-background overflow-hidden shrink-0">
                                    <img :src="product?.product?.product_images?.find(image => image.is_thumbnail)?.image ?? PlaceHolder" class="size-full object-contain"
                                        alt="thumbnail">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full truncate">
                                        {{ product?.product?.name }}
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/bag-grey.svg" class="size-5" alt="icon">
                                        {{ product?.product?.product_category?.name }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 shrink-0 text-right">
                                <p class="font-bold text-custom-blue">Rp {{ formatRupiah(product?.product?.price) }}</p>
                                <p class="font-semibold leading-none text-custom-grey">{{ product.qty }}</p>
                            </div>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/shopping-cart-grey.svg" class="size-5" alt="icon">
                                Subtotal
                            </p>
                            <p class="font-bold text-lg text-custom-blue">Rp {{ formatRupiah(product.subtotal) }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="flex flex-col gap-5 w-[470px] shrink-0">
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
                <p class="font-bold text-xl">Customer Details</p>
                <div class="flex items-center gap-[10px] w-full min-w-0">
                    <div class="flex size-[92px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                        <img src="@/assets/images/photos/photo-2.png" class="size-full object-cover" alt="photo">
                    </div>
                    <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                        <p class="font-bold text-[22px] leading-tight w-full truncate">
                            {{ transaction?.buyer?.user?.name }}
                        </p>
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                            {{ transaction?.buyer?.phone_number }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/sms-black.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.buyer?.user?.email }}</p>
                            <p class="font-semibold text-custom-grey">Email Address</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/buildings-black.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.city }}</p>
                            <p class="font-semibold text-custom-grey">City Location</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/routing-black.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.address }}</p>
                            <p class="font-semibold text-custom-grey">Street Address</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center gap-[10px] w-[260px]">
                        <div
                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                            <img src="@/assets/images/icons/keyboard-black.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-1">
                            <p class="font-bold text-lg leading-none">{{ transaction?.postal_code }}</p>
                            <p class="font-semibold text-custom-grey">Post Code</p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
                <p class="font-bold text-xl">Transaction Details</p>
                <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-4">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/bag-grey.svg" class="size-6" alt="icon">
                            Subtotal
                        </p>
                        <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(transaction?.transaction_details?.reduce((total, detail) => total + detail.subtotal, 0)) }}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/car-delivery-grey.svg" class="size-6" alt="icon">
                            Delivery Fee
                        </p>
                        <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(transaction?.shipping_cost) }}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/receipt-2-grey.svg" class="size-6" alt="icon">
                            PPN 11%
                        </p>
                        <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(transaction?.tax) }}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/discount-shape-grey.svg" class="size-6" alt="icon">
                            Discount
                        </p>
                        <p class="font-bold text-lg leading-none">Rp 0</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/money-grey.svg" class="size-6" alt="icon">
                            Grand Total
                        </p>
                        <p class="font-bold text-lg leading-none text-custom-blue">Rp {{ formatRupiah(transaction?.grand_total) }}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                    <hr class="border-custom-stroke last:hidden">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                            <img src="@/assets/images/icons/money-grey.svg" class="size-6" alt="icon">
                            Payment Status
                        </p>
                        <p class="font-bold text-lg leading-none text-custom-blue"> {{ transaction?.payment_status }}</p>
                    </div>
                    <hr class="border-custom-stroke last:hidden">
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white" v-if="transaction?.delivery_status === 'pending'">
                <p class="font-bold text-xl">Order Status</p>
                <div class="grid grid-cols-3 relative min-h-[90px] w-full">
                    <div id="Progress-Bar"
                        class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
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
                            class="flex size-8 shrink-0 rounded-full bg-custom-stroke overflow-hidden items-center justify-center">
                            <span class="font-bold">2</span>
                        </div>
                        <p class="font-bold text-center">Processing</p>
                    </div>
                    <div class="relative flex flex-col py-4 gap-[6px] items-center">
                        <div
                            class="flex size-8 shrink-0 rounded-full bg-custom-stroke overflow-hidden items-center justify-center">
                            <span class="font-bold">?</span>
                        </div>
                        <p class="font-bold text-center">2+ More</p>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon">
                        Delivery Status
                    </p>
                    <p
                        class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-yellow text-[#544607]">
                        pending
                    </p>
                </div>
                <div class="flex flex-col text-center gap-4" v-if="transaction?.payment_status === 'paid'">
                                <button @click="handleAcceptOrder"
                                    class="h-14 w-full rounded-full flex items-center justify-center py-4 px-6 bg-custom-blue disabled:bg-custom-stroke transition-300">
                                    <span class="font-semibold text-lg text-white">Accept Order</span>
                                </button>
                                <div class="flex items-center justify-center gap-[6px]">
                                    <p class="font-semibold text-custom-grey">Why can't I decline the order?</p>
                                    <img src="@/assets/images/icons/info-circle-grey.svg" class="size-[18px]"
                                        alt="icon">
                                </div>
                            </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white" v-if="transaction?.delivery_status === 'processing'">
                <p class="font-bold text-xl">Order Status</p>
                <div class="grid grid-cols-3 relative min-h-[90px] w-full">
                    <div id="Progress-Bar"
                        class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
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
                            class="flex size-8 shrink-0 rounded-full bg-custom-stroke overflow-hidden items-center justify-center">
                            <span class="font-bold">?</span>
                        </div>
                        <p class="font-bold text-center">2+ More</p>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon">
                        Delivery Status
                    </p>
                    <p
                        class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-blue/10 text-custom-blue">
                        processing
                    </p>
                </div>
                <div class="flex items-center justify-between w-full">
                                <div
                                    class="group relative flex size-[100px] rounded-2xl overflow-hidden items-center justify-center bg-custom-background">
                                    <img id="Thumbnail" :src="transaction.delivery_proof_url"
                                        data-default="@/assets/images/icons/gallery-default.svg"
                                        class="size-full object-contain" alt="icon" />
                                    <input type="file" id="File-Input" accept="image/*"
                                        class="absolute inset-0 opacity-0 cursor-pointer" @change="handleImageChange"/>
                                </div>
                                <button type="button" id="Add-Photo"
                                    class="flex items-center justify-center rounded-2xl py-4 px-6 bg-custom-black text-white font-semibold text-lg">
                                    Add Photo
                                </button>
                            </div>
                            <div class="flex flex-col gap-3">
                                <p class="font-semibold text-custom-grey">Tracking Number</p>
                                <div class="group/errorState flex flex-col gap-2">
                                    <label class="group relative">
                                        <div class="input-icon">
                                            <img src="@/assets/images/icons/barcode-grey.svg"
                                                class="flex size-6 shrink-0" alt="icon">
                                        </div>
                                        <p class="input-placeholder">
                                            Enter Tracking Number
                                        </p>
                                        <input type="string" id="Tracking" class="custom-input" placeholder="" v-model="transaction.tracking_number">
                                    </label>
                                    <span class="input-error">Lorem dolor error message here</span>
                                </div>
                            </div>
                            <button type="submit" id="Update-Status"
                                class="h-14 w-full rounded-full flex items-center justify-center py-4 px-6 bg-custom-blue disabled:bg-custom-stroke transition-300"
                                @click="handleUpdateData">
                                <span class="font-semibold text-lg text-white">Update Status</span>
                            </button>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white" v-if="transaction?.delivery_status === 'delivering'">
                <p class="font-bold text-xl">Order Status</p>
                <div class="grid grid-cols-3 relative min-h-[90px] w-full">
                    <div id="Progress-Bar"
                        class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
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
                            class="flex size-8 shrink-0 rounded-full bg-custom-stroke overflow-hidden items-center justify-center">
                            <span class="font-bold">4</span>
                        </div>
                        <p class="font-bold text-center">Completed</p>
                    </div>
                </div>
                <div class="h-[260px] w-full rounded-2xl overflow-hidden bg-custom-background">
                    <img :src="transaction?.delivery_proof" class="size-full object-cover"
                        alt="thumbnail">
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon">
                        Delivery Status
                    </p>
                    <p
                        class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-orange/10 text-custom-orange">
                        Delivering
                    </p>
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/routing-grey.svg" class="size-6" alt="icon">
                        Tracking Number
                    </p>
                    <p class="font-semibold text-lg leading-none">{{ transaction?.tracking_number ?? '-' }}</p>
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white" v-if="transaction?.delivery_status === 'completed'">
                <p class="font-bold text-xl">Order Status</p>
                <div class="grid grid-cols-3 relative min-h-[90px] w-full">
                    <div id="Progress-Bar"
                        class="absolute w-full top-[26px] h-3 rounded-full bg-custom-stroke overflow-hidden">
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
                    <img src="@/assets/images/thumbnails/delivering.svg" class="size-full object-cover"
                        alt="thumbnail">
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/car-delivery-moves-grey.svg" class="size-6" alt="icon">
                        Delivery Status
                    </p>
                    <p
                        class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-green/10 text-custom-green">
                        completed
                    </p>
                </div>
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1 font-medium text-custom-grey leading-none">
                        <img src="@/assets/images/icons/routing-grey.svg" class="size-6" alt="icon">
                        Tracking Number
                    </p>
                    <p class="font-semibold text-lg leading-none">{{ transaction?.tracking_number ?? '-' }}</p>
                </div>
            </section>
            <section class="flex flex-col w-full rounded-[20px] p-5 gap-5 bg-white">
                <p class="font-bold text-xl">Customer Reviews</p>
                <div class="flex flex-col rounded-2xl border border-custom-stroke p-4 gap-4">
                    <div class="flex items-center justify-between">
                        <p class="font-bold tracking-tight text-xl leading-none">
                            <span class="text-[32px]">4.0</span>/5.0
                        </p>
                        <div class="flex">
                            <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-8 shrink-0 p-0.5"
                                alt="star">
                            <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-8 shrink-0 p-0.5"
                                alt="star">
                            <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-8 shrink-0 p-0.5"
                                alt="star">
                            <img src="@/assets/images/icons/Star-pointy.svg" class="flex size-8 shrink-0 p-0.5"
                                alt="star">
                            <img src="@/assets/images/icons/Star-pointy-outline.svg" class="flex size-8 shrink-0 p-0.5"
                                alt="star">
                        </div>
                    </div>
                    <hr class="border-custom-stroke">
                    <p class="font-medium text-lg text-custom-grey">“The iPhone is super fast, the MacBook
                        is perfect for work, and the AirPods sound crystal clear. Plus, the store's service
                        was amazing—fast delivery and great support!”</p>
                </div>
            </section>
        </div>
    </div>
</template>