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

onMounted(() => {
    fetchData()
})
</script>

<template>
    <div class="flex gap-5">
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/wallet-2-blue-fill.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">Rp {{ formatRupiah(totalExpense) }}</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Expense
                    </p>
                </div>
            </div>
        </div>
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/stickynote-blue-fill.svg" class="flex size-6 shrink-0"
                        alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">{{ totalTransaction }}</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Transaction
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex flex-col flex-1 w-full rounded-[20px] p-5 gap-6 bg-white">
        <div class="flex flex-col flex-1 gap-5">
            <p class="font-bold text-xl">Latest Transactions</p>
            <div id="List-Transactions" class="flex flex-col gap-5" v-if="!loading && buyerTransactions.length > 0">
                <div
                    v-for="transaction in buyerTransactions.slice(0, 5)"
                    :key="transaction.id"
                    class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
                    <div class="flex items-center gap-5 justify-between pr-[30px]">
                        <div class="flex items-center gap-[14px] w-[320px]">
                            <div class="flex size-[84px] shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                <img 
                                    :src="transaction.store?.logo || transaction.store?.image || '/src/assets/images/thumbnails/th-1.svg'" 
                                    class="size-full object-cover"
                                    :onerror="`this.src='/src/assets/images/thumbnails/th-1.svg'`"
                                    alt="store logo">
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
                        <div class="flex items-center gap-[10px] w-[260px]">
                            <div
                                class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0" alt="icon">
                            </div>
                            <div class="flex flex-col gap-1">
                                <p class="font-bold text-lg leading-none">
                                    {{ transaction?.transaction_details?.reduce((total, detail) => total + detail.qty, 0) }}
                                </p>
                                <p class="font-semibold text-custom-grey">Total Quantity</p>
                            </div>
                        </div>
                    </div>
                    <hr class="border-custom-stroke">
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col gap-[6px]">
                            <p class="font-bold text-xl text-custom-blue">Rp {{ formatRupiah(transaction.grand_total) }}</p>
                            <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/money-grey.svg" class="size-6 flex shrink-0" alt="icon">
                                Grand Total
                            </p>
                        </div>
                        <div class="flex items-center justify-end gap-[14px]">
                            <RouterLink 
                                :to="{ name: 'admin.transaction.detail', params: { id: transaction.id } }"
                                class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                                <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon">
                                <span class="font-semibold text-white">Details</span>
                            </RouterLink>
                        </div>
                    </div>
                </div>
            </div>
            <div id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4" v-if="!loading && buyerTransactions.length === 0">
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