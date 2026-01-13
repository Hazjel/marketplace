<script setup>
import Alert from '@/components/admin/Alert.vue';
import Pagination from '@/components/admin/Pagination.vue';
import { useTransactionStore } from '@/stores/transaction';
import { debounce } from 'lodash';
import { storeToRefs } from 'pinia';
import { onMounted, ref, watch, computed, toRaw } from 'vue';
import { axiosInstance } from '@/plugins/axios';
import { RouterLink } from 'vue-router';
import { can } from '@/helpers/permissionHelper';
import { formatToClientTimeZone } from '@/helpers/format';
import { formatRupiah } from '@/helpers/format';

const transactionStore = useTransactionStore()
const { transactions, meta, loading, success, error } = storeToRefs(transactionStore)
const { fetchTransactionsPaginated } = transactionStore

import { useAuthStore } from '@/stores/auth';
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const filteredTransactions = computed(() => {
    const items = transactions.value || []
    if (!user.value) return []

    const userBuyerId = user.value?.buyer?.id || user.value?.buyer_id || user.value?.id

    return items
        .filter(t => {
            const txBuyerId = t?.buyer?.id || t?.buyer_id
            return txBuyerId && userBuyerId && String(txBuyerId) === String(userBuyerId)
        })
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

const clientFiltered = ref([])

const displayTransactions = computed(() => {
    let items = (clientFiltered.value && clientFiltered.value.length) ? clientFiltered.value : filteredTransactions.value

    // Filter berdasarkan search jika ada
    if (filters.value.search && filters.value.search.trim()) {
        const searchTerm = filters.value.search.trim().toLowerCase()
        items = items.filter(transaction => {
            const storeName = transaction?.store?.name?.toLowerCase() || ''
            const buyerName = transaction?.buyer?.name?.toLowerCase() || ''
            const transactionId = transaction?.id?.toString() || ''
            const deliveryStatus = transaction?.delivery_status?.toLowerCase() || ''

            return storeName.includes(searchTerm) ||
                buyerName.includes(searchTerm) ||
                transactionId.includes(searchTerm) ||
                deliveryStatus.includes(searchTerm)
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
    search: null,
})

const fetchData = async () => {
    // Reset clientFiltered ketika fetch data baru
    clientFiltered.value = []

    const params = {
        ...serverOptions.value,
        ...filters.value,
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
        if (!filters.value.search && (transactions.value || []).length > 0 && filteredTransactions.value.length === 0) {
            const all = []
            const lastPage = meta.value?.last_page || 1
            for (let p = 1; p <= lastPage; p++) {
                const resp = await axiosInstance.get('transaction/all/paginated', { params: { ...serverOptions.value, ...filters.value, page: p } })
                const pageItems = resp.data.data.data || []
                all.push(...pageItems)
            }

            const userBuyerId = user.value?.buyer?.id || user.value?.buyer_id || user.value?.id
            const matched = all.filter(t => {
                const txBuyerId = t?.buyer?.id || t?.buyer_id
                return txBuyerId && userBuyerId && String(txBuyerId) === String(userBuyerId)
            }).sort((a, b) => new Date(b.created_at) - new Date(a.created_at))

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

const getTransactionStatusClass = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-custom-yellow text-[#544607]'
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

const closeAlert = () => {
    transactionStore.success = null
    transactionStore.error = null
}

onMounted(async () => {
    await fetchData()
})

watch(serverOptions, () => {
    fetchData()
}, { deep: true })

watch(filters, () => {
    // Reset ke page 1 ketika filter berubah
    serverOptions.value.page = 1
    debounceFetchData()
}, { deep: true })
</script>

<template>
    <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
        <div class="header flex items-center justify-between">
            <div class="flex flex-col gap-2">
                <p class="font-bold text-xl">My Transactions</p>
                <div class="flex items-center gap-1">
                    <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    <p class="font-semibold text-custom-grey">{{ displayTransactions.length }} Total Transactions</p>
                </div>
            </div>
        </div>
        <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
            <form action="#" class="w-full md:w-auto">
                <label
                    class="flex items-center w-full md:w-[370px] h-14 rounded-2xl p-4 gap-2 bg-white border border-custom-stroke focus-within:border-custom-black transition-300">
                    <img src="@/assets/images/icons/receipt-search-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    <input type="text"
                        class="appearance-none w-full placeholder:text-custom-grey font-medium focus:outline-none"
                        placeholder="Search Transaction" v-model="filters.search">
                </label>
            </form>
            <div class="flex items-center gap-4 w-full md:w-auto justify-start">
                <p class="font-medium text-custom-grey">Show</p>
                <label
                    class="flex items-center h-14 rounded-2xl border border-custom-stroke py-4 px-5 pl-3 bg-white focus-within:border-custom-black transition-300">
                    <select name="" id="" class="text-custom-black font-medium appearance-none focus:outline-none p-2"
                        v-model="serverOptions.row_per_page">
                        <option value="10" class="font-medium">10 Entries</option>
                        <option value="20" class="font-medium">20 Entries</option>
                        <option value="40" class="font-medium">40 Entries</option>
                    </select>
                    <img src="@/assets/images/icons/arrow-down-black.svg" class="flex size-6 shrink-0 -ml-1" alt="icon">
                </label>
            </div>
        </div>
        <section id="List-Transactions" class="flex flex-col flex-1 gap-6 w-full">
            <template v-if="displayTransactions.length">
                <div class="list flex flex-col gap-5">
                    <div class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white"
                        v-for="transaction in paginatedTransactions">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                {{ formatToClientTimeZone(transaction.created_at) }}
                            </p>
                            <p class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase"
                                :class="getTransactionStatusClass(transaction.delivery_status)">
                                {{ transaction.delivery_status }}
                            </p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex flex-col md:flex-row items-start md:items-center gap-5 justify-between pr-0 md:pr-[30px]">
                            <div class="flex items-center gap-[14px] w-full md:w-[320px]">
                                <div
                                    class="flex size-[84px] shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                    <img :src="transaction?.store?.logo" class="size-full object-cover" alt="photo">
                                </div>
                                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-tight w-full truncate">
                                        {{ transaction?.store?.name }}
                                    </p>
                                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-5" alt="icon">
                                        {{ formatToClientTimeZone(transaction.created_at) }}
                                    </p>
                                </div>
                            </div>
                            <!-- Stats Container: Grid on mobile (2 cols), Row on desktop -->
                            <div class="grid grid-cols-2 gap-5 w-full md:flex md:w-auto md:gap-10">
                                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-[10px] w-full md:w-[260px]">
                                    <div
                                        class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                        <img src="@/assets/images/icons/shopping-cart-black.svg"
                                            class="flex size-6 shrink-0" alt="icon">
                                    </div>
                                    <div class="flex flex-col gap-1 w-full overflow-hidden">
                                        <p class="font-bold text-lg leading-none truncate">{{
                                            transaction.transaction_details?.length }}</p>
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
                                        <p class="font-bold text-lg leading-none truncate">{{
                                            transaction.transaction_details?.reduce((total, detail) => total + detail.qty,
                                                0)}}</p>
                                        <p class="font-semibold text-custom-grey text-sm sm:text-base truncate">Total Quantity</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <div class="flex flex-col gap-[6px]">
                                <p class="font-bold text-xl text-custom-blue">{{ formatRupiah(transaction.grand_total)
                                }}</p>
                                <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/money-grey.svg" class="size-6 flex shrink-0"
                                        alt="icon">
                                    Grand Total
                                </p>
                            </div>
                            <div class="flex items-center justify-end gap-[14px] w-full md:w-auto">
                                <RouterLink :to="getDetailRoute(transaction.id)"
                                    class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                                    <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                    <span class="font-semibold text-white">Details</span>
                                </RouterLink>
                            </div>
                        </div>
                    </div>
                </div>
                <nav id="Pagination" v-if="showPagination">
                    <ul class="flex items-center gap-3">
                        <li class="group">
                            <button
                                class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold"
                                disabled>
                                <img src="@/assets/images/icons/arrow-right-no-tail-blue.svg"
                                    class="size-6 group-has-[:disabled]:opacity-20 rotate-180" alt="icon">
                            </button>
                        </li>
                        <li v-for="p in (filters.search || clientFiltered.length ? Math.ceil(displayTransactions.length / perPage) : meta.last_page || 1)"
                            :key="p" class="group" :class="{ 'active': p === serverOptions.page }">
                            <button @click="serverOptions.page = p"
                                class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                                {{ p }}
                            </button>
                        </li>
                        <li class="group">
                            <button
                                class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                                <img src="@/assets/images/icons/arrow-right-no-tail-blue.svg"
                                    class="size-6 group-has-[:disabled]:opacity-20" alt="icon">
                            </button>
                        </li>
                    </ul>
                </nav>
            </template>
            <div id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4" v-else-if="!loading">
                <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
                <div class="flex flex-col gap-1 items-center text-center">
                    <p class="font-semibold text-custom-grey">
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
