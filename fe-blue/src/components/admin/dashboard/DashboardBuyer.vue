<script setup>
import { useTransactionStore } from '@/stores/transaction';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { formatRupiah, formatDate } from '@/helpers/format';

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
        .filter(t => {
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

const totalProducts = computed(() => {
    return buyerTransactions.value.reduce((sum, transaction) => {
        return sum + (transaction.transaction_items?.reduce((itemSum, item) => itemSum + (parseInt(item.quantity) || 0), 0) || 0)
    }, 0)
})

const totalTransaction = computed(() => {
    return buyerTransactions.value.length
})

// Status Counts
const countUnpaid = computed(() => {
    return buyerTransactions.value.filter(t => t.payment_status === 'unpaid').length
})

const countProcessed = computed(() => {
    return buyerTransactions.value.filter(t => 
        t.payment_status === 'paid' && 
        t.delivery_status === 'pending'
    ).length
})

const countShipped = computed(() => {
    return buyerTransactions.value.filter(t => t.delivery_status === 'shipping').length
})

const countCompleted = computed(() => {
    return buyerTransactions.value.filter(t => t.delivery_status === 'delivered').length
})

onMounted(() => {
    fetchData()
})
</script>

<template>
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-4 md:gap-5">
        <!-- Total Expense (1 Column) -->
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white border border-custom-stroke col-span-1 animate-fade-in-up">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/wallet-2-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-2xl md:text-3xl">Rp {{ formatRupiah(totalExpense) }}</p>
                    <p class="font-medium text-sm md:text-lg text-custom-grey">
                        Total Expense
                    </p>
                </div>
            </div>
        </div>

        <!-- Transaction Statuses (3 Columns -> Inner Grid 4 Cols for ALL screens) -->
        <div class="grid grid-cols-4 gap-2 md:gap-4 col-span-1 xl:col-span-3">
             <!-- Unpaid -->
            <div class="flex flex-col w-full rounded-[12px] md:rounded-[20px] p-2 md:p-4 gap-2 md:gap-3 bg-white border border-custom-stroke items-center justify-center text-center animate-fade-in-up delay-100">
                <div class="flex size-8 md:size-10 bg-custom-icon-background items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/card-black.svg" class="flex size-4 md:size-5 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-0.5 md:gap-1">
                    <p class="font-bold text-sm md:text-xl">{{ countUnpaid }}</p>
                    <p class="font-medium text-[10px] md:text-sm text-custom-grey">Unpaid</p>
                </div>
            </div>

            <!-- Processed -->
            <div class="flex flex-col w-full rounded-[12px] md:rounded-[20px] p-2 md:p-4 gap-2 md:gap-3 bg-white border border-custom-stroke items-center justify-center text-center animate-fade-in-up delay-100">
                <div class="flex size-8 md:size-10 bg-custom-icon-background items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/box-black.svg" class="flex size-4 md:size-5 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-0.5 md:gap-1">
                    <p class="font-bold text-sm md:text-xl">{{ countProcessed }}</p>
                    <p class="font-medium text-[10px] md:text-sm text-custom-grey">Processed</p>
                </div>
            </div>

            <!-- Shipped -->
            <div class="flex flex-col w-full rounded-[12px] md:rounded-[20px] p-2 md:p-4 gap-2 md:gap-3 bg-white border border-custom-stroke items-center justify-center text-center animate-fade-in-up delay-200">
                <div class="flex size-8 md:size-10 bg-custom-icon-background items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/car-delivery-black.svg" class="flex size-4 md:size-5 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-0.5 md:gap-1">
                    <p class="font-bold text-sm md:text-xl">{{ countShipped }}</p>
                    <p class="font-medium text-[10px] md:text-sm text-custom-grey">Shipped</p>
                </div>
            </div>

            <!-- Completed -->
            <div class="flex flex-col w-full rounded-[12px] md:rounded-[20px] p-2 md:p-4 gap-2 md:gap-3 bg-white border border-custom-stroke items-center justify-center text-center animate-fade-in-up delay-300">
                <div class="flex size-8 md:size-10 bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/clipboard-tick-blue-fill.svg" class="flex size-4 md:size-5 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-0.5 md:gap-1">
                    <p class="font-bold text-sm md:text-xl">{{ countCompleted }}</p>
                    <p class="font-medium text-[10px] md:text-sm text-custom-grey">Done</p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex flex-col flex-1 w-full rounded-[20px] p-5 gap-6 bg-white animate-fade-in-up delay-300">
        <div class="flex flex-col flex-1 gap-5">
            <p class="font-bold text-xl">Latest Transactions</p>
            <div id="List-Transactions" class="flex flex-col gap-5" v-if="!loading && buyerTransactions.length > 0">
                <div v-for="transaction in buyerTransactions.slice(0, 5)" :key="transaction.id"
                    class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-5 justify-between pr-0 md:pr-[30px]">
                        <div class="flex items-center gap-[14px] w-full md:w-[320px]">
                            <div class="flex size-[84px] shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                <img :src="transaction.store?.logo || transaction.store?.image || '/src/assets/images/thumbnails/th-1.svg'"
                                    class="size-full object-cover"
                                    :onerror="`this.src='/src/assets/images/thumbnails/th-1.svg'`" alt="store logo">
                            </div>
                            <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                <p class="font-bold text-lg leading-tight w-full truncate">
                                    {{ transaction.store?.name || 'Store' }}
                                </p>
                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon">
                                    {{ transaction.store?.user?.name || 'Seller' }}
                                </p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-5 w-full md:flex md:w-auto md:gap-10">
                            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-[10px] w-full md:w-[260px]">
                                <div
                                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                    <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                </div>
                                <div class="flex flex-col gap-1 w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-none truncate">{{ transaction?.transaction_details?.length }}
                                    </p>
                                    <p class="font-semibold text-custom-grey text-sm sm:text-base truncate">Total Products</p>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-[10px] w-full md:w-[260px]">
                                <div
                                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                    <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                </div>
                                <div class="flex flex-col gap-1 w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-none truncate">
                                        {{transaction?.transaction_details?.reduce((total, detail) => total + detail.qty,
                                            0)}}
                                    </p>
                                    <p class="font-semibold text-custom-grey text-sm sm:text-base truncate">Total Quantity</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="border-custom-stroke">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex flex-col gap-[6px]">
                            <p class="font-bold text-xl text-custom-blue">Rp {{ formatRupiah(transaction.grand_total) }}
                            </p>
                            <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/money-grey.svg" class="size-6 flex shrink-0" alt="icon">
                                Grand Total
                            </p>
                        </div>
                        <div class="flex items-center justify-end gap-[14px] w-full md:w-auto">
                            <RouterLink
                                :to="{ name: `${user?.role === 'admin' ? 'admin' : 'user'}.transaction.detail`, params: { id: transaction.id, username: user?.username } }"
                                class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                                <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon">
                                <span class="font-semibold text-white">Details</span>
                            </RouterLink>
                        </div>
                    </div>
                </div>
            </div>
            <div id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4"
                v-if="!loading && buyerTransactions.length === 0">
                <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
                <div class="flex flex-col gap-1 items-center text-center">
                    <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
                </div>
            </div>
            <div v-if="loading" class="flex items-center justify-center py-10">
                <div class="size-8 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
            </div>
        </div>
    </div>
</template>