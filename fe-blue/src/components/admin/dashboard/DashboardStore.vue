<script setup>
import { onMounted, ref, computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useTransactionStore } from '@/stores/transaction';
import { useProductStore } from '@/stores/product';
import { useStoreBalanceStore } from '@/stores/storeBalance';
import { storeToRefs } from 'pinia';
import { formatRupiah, formatDate } from '@/helpers/format';
import { axiosInstance } from '@/plugins/axios';
import { RouterLink } from 'vue-router';

import RevenueChart from './RevenueChart.vue'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const transactionStore = useTransactionStore()
const productStore = useProductStore()
const storeBalanceStore = useStoreBalanceStore()
const { fetchStoreBalanceByStore } = storeBalanceStore
const { fetchChartData } = transactionStore

// Local ref since store doesn't keep single balance in state
const storeBalance = ref(null)
const chartData = ref([])

const stats = ref({
    total_products: 0,
    total_transactions: 0,
    total_reviews: 0,
    total_buyers: 0,
})

const latestTransactions = ref([])
const latestReviews = ref([])
const loading = ref(true)

const fetchData = async () => {
    loading.value = true
    try {
        const storeId = user.value?.store?.id

        if (!storeId) return

        // Fetch all data in parallel
        const [
            chartDataRes,
            balanceRes,
            productsRes, // We handle side-effects of this call inside the fetch if store handles it, or wait
            transactionsRes, // Same for transaction store
            reviewsRes
        ] = await Promise.all([
            fetchChartData(),
            fetchStoreBalanceByStore(),
            productStore.fetchProductsPaginated({ row_per_page: 1, store_id: storeId }),
            transactionStore.fetchTransactionsPaginated({ row_per_page: 5, sort_by: 'created_at', sort_direction: 'desc' }),
            axiosInstance.get('product-review/all/paginated', {
                params: {
                    row_per_page: 5,
                    store_id: storeId,
                    sort_by: 'created_at',
                    sort_direction: 'desc'
                }
            })
        ])

        // 1. Chart Data
        chartData.value = chartDataRes

        // 2. Total Revenue (Balance)
        storeBalance.value = balanceRes

        // 3. Total Products (Data is in store state)
        // Note: fetchProductsPaginated updates the store state directly
        const { meta: productMeta } = storeToRefs(productStore)
        stats.value.total_products = productMeta.value?.total || 0

        // 4. Total Transactions & Latest Transactions (Data is in store state)
        const { meta: transactionMeta, transactions: transactionData } = storeToRefs(transactionStore)
        stats.value.total_transactions = transactionMeta.value?.total || 0
        latestTransactions.value = transactionData.value || []

        // 5. Total Reviews & Latest Reviews
        if (reviewsRes.data.data) {
            if (Array.isArray(reviewsRes.data.data)) {
                latestReviews.value = reviewsRes.data.data
                stats.value.total_reviews = reviewsRes.data.meta?.total || latestReviews.value.length
            } else {
                 stats.value.total_reviews = reviewsRes.data.data.meta?.total || 0
                 latestReviews.value = reviewsRes.data.data.data || []
            }
        }

    } catch (error) {
        console.error('Error fetching dashboard data:', error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchData()
})
</script>

<template>
    <!-- Skeleton Loader -->
    <div v-if="loading" class="animate-pulse flex flex-col gap-6">
        <!-- Stats Skeleton -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
            <div v-for="i in 3" :key="i" class="h-40 rounded-[20px] bg-gray-200"></div>
        </div>
        <!-- Chart Skeleton -->
        <div class="h-96 rounded-[20px] bg-gray-200 w-full"></div>
        <!-- Lists Skeleton -->
        <div class="flex flex-col md:flex-row gap-5">
             <div class="flex-1 h-96 rounded-[20px] bg-gray-200"></div>
             <div class="flex-1 h-96 rounded-[20px] bg-gray-200"></div>
        </div>
    </div>

    <!-- Actual Content -->
    <div v-else class="flex flex-col gap-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
            <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
                <div class="flex flex-col gap-6">
                    <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                        <img src="@/assets/images/icons/wallet-2-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px]">
                        <p class="font-bold text-2xl md:text-4xl text-custom-black">Rp {{ formatRupiah(storeBalance?.balance || 0) }}</p>
                        <p class="font-medium text-sm md:text-lg text-custom-grey">
                            Total Revenue (Balance)
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
                <div class="flex flex-col gap-6">
                    <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                        <img src="@/assets/images/icons/shopping-cart-blue-fill.svg" class="flex size-6 shrink-0"
                            alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px]">
                        <p class="font-bold text-2xl md:text-4xl text-custom-black">{{ stats.total_products }}</p>
                        <p class="font-medium text-sm md:text-lg text-custom-grey">
                            Total Products
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
                <div class="flex flex-col gap-6">
                    <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                        <img src="@/assets/images/icons/message-text-blue-fill.svg" class="flex size-6 shrink-0"
                            alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px]">
                        <p class="font-bold text-2xl md:text-4xl text-custom-black">{{ stats.total_reviews }}</p>
                        <p class="font-medium text-sm md:text-lg text-custom-grey">
                            Total Reviews
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Revenue Chart Section -->
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white col-span-1 md:col-span-3">
            <div class="flex flex-col gap-2">
                <h3 class="font-bold text-xl text-custom-black">Revenue Analytics</h3>
                <p class="text-custom-grey text-sm">Income in the last 7 days</p>
            </div>
            <RevenueChart :data="chartData" />
        </div>

        <div class="flex flex-col gap-5 flex-1 md:flex-row">
            <div class="flex flex-col gap-5 w-full shrink-0 md:w-[470px]">
                <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
                    <div class="flex flex-col gap-6">
                        <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                            <img src="@/assets/images/icons/stickynote-blue-fill.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </div>
                        <div class="flex flex-col gap-[6px]">
                            <p class="font-bold text-4xl text-custom-black">{{ stats.total_transactions }}</p>
                            <p class="font-medium text-lg text-custom-grey">
                                Total Transaction
                            </p>
                        </div>
                    </div>
                    <hr class="border-custom-stroke">
                    <div class="flex flex-col flex-1 gap-5">
                        <p class="font-bold text-xl text-custom-black">Latest Transactions</p>
                        <div id="List-Transactions" class="flex flex-col gap-5" v-if="latestTransactions.length > 0">
                            <div v-for="transaction in latestTransactions" :key="transaction.id"
                                class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                                <div class="flex flex-col md:flex-row md:items-center gap-4 justify-between">
                                    <div class="flex items-center gap-[10px] w-full min-w-0">
                                        <div
                                            class="flex size-14 shrink-0 rounded-full bg-custom-background overflow-hidden items-center justify-center">
                                            <img :src="transaction.buyer?.user?.profile_picture || '/src/assets/images/photos/photo-1.png'"
                                                class="size-full object-cover" alt="photo"
                                                onerror="this.src='/src/assets/images/photos/photo-1.png'">
                                        </div>
                                        <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                            <p class="font-bold text-lg leading-tight w-full truncate text-custom-black">
                                                {{ transaction.buyer?.user?.name || 'Buyer' }}
                                            </p>
                                            <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                                                {{ transaction.buyer?.phone_number || '-' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex flex-row md:flex-col gap-2 items-center md:items-end justify-between md:justify-center w-full md:w-auto pl-[66px] md:pl-0">
                                        <p class="font-bold text-lg leading-tight text-custom-blue text-nowrap">
                                            Rp {{ formatRupiah(transaction.grand_total) }}
                                        </p>
                                        <p
                                            class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-nowrap">
                                            Grand Total
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-custom-stroke">
                                <div class="flex flex-col md:flex-row items-start md:items-center gap-4 justify-between">
                                    <div class="flex items-center gap-[10px] w-full md:w-[260px]">
                                        <div
                                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                            <img src="@/assets/images/icons/shopping-cart-black.svg"
                                                class="flex size-6 shrink-0" alt="icon">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <p class="font-bold text-lg leading-none text-custom-black">{{
                                                transaction.transaction_details?.length || 0 }}</p>
                                            <p class="font-semibold text-custom-grey">Total Products</p>
                                        </div>
                                    </div>
                                    <RouterLink
                                        :to="{ name: 'user.transaction.detail', params: { username: user?.username, id: transaction.id } }"
                                        class="flex w-full md:w-[96px] justify-center h-[56px] shrink-0 rounded-2xl py-[18px] px-5 bg-custom-blue/10 gap-2 hover:ring-2 hover:ring-custom-blue transition-300">
                                        <span class="font-semibold text-custom-blue leading-none">
                                            Details
                                        </span>
                                    </RouterLink>
                                </div>
                            </div>
                        </div>
                        <div id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4" v-else>
                            <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
                            <div class="flex flex-col gap-1 items-center text-center">
                                <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col flex-1 w-full rounded-[20px] p-5 gap-6 bg-white">
                <div class="flex flex-col flex-1 gap-5">
                    <p class="font-bold text-xl text-custom-black">Latest Reviews</p>
                    <div id="List-Reviews" class="flex flex-col gap-5" v-if="latestReviews.length > 0">
                        <div v-for="review in latestReviews" :key="review.id"
                            class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                            <div class="flex items-center gap-[10px] min-w-0">
                                <div class="flex size-14 shrink-0 rounded-full bg-custom-background overflow-hidden">
                                    <img :src="review.user?.avatar || '/src/assets/images/photos/photo-8.png'"
                                        class="size-full object-cover text-xs" alt="photo"
                                        onerror="this.src='/src/assets/images/photos/photo-8.png'">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full overflow-hidden justify-center">
                                    <p class="font-bold text-lg leading-tight w-full truncate text-custom-black">
                                        {{ review.user?.name || 'User' }}
                                    </p>
                                    <!-- Phone number removed -->
                                </div>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex flex-col gap-2">
                                <p class="font-medium text-custom-grey">“{{ review.review }}”</p>
                                <div class="flex">
                                    <template v-for="i in 5">
                                        <img v-if="i <= review.rating" src="@/assets/images/icons/Star-pointy.svg"
                                            class="flex size-6 shrink-0 p-0.5" alt="star">
                                        <img v-else src="@/assets/images/icons/Star-pointy-outline.svg"
                                            class="flex size-6 shrink-0 p-0.5" alt="star">
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4" v-else>
                        <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
                        <div class="flex flex-col gap-1 items-center text-center">
                            <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>