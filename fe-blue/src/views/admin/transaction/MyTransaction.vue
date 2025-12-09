<script setup>
import Alert from '@/components/admin/Alert.vue';
import Pagination from '@/components/admin/Pagination.vue';
import { useTransactionStore } from '@/stores/transaction';
import { debounce } from 'lodash';
import { storeToRefs } from 'pinia';
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { can } from '@/helpers/permissionHelper';
import { formatToClientTimeZone } from '@/helpers/format';
import { formatRupiah } from '@/helpers/format';

const transactionStore = useTransactionStore()
const { transactions, meta, loading, success, error } = storeToRefs(transactionStore)
const { fetchTransactionsPaginated } = transactionStore

const serverOptions = ref({
    page: 1,
    row_per_page: 10
})

const filters = ref({
    search: null,
})

const fetchData = async () => {
    await fetchTransactionsPaginated({
        ...serverOptions.value,
        ...filters.value,
    })
}

const debounceFetchData = debounce(fetchData, 500)

const closeAlert = () => {
    transactionStore.success = null
    transactionStore.error = null
}

onMounted(fetchData)

watch(serverOptions, () => {
    fetchData()
}, {deep: true})

watch(filters, () => {
    debounceFetchData()
}, {deep: true})
</script>

<template>
    <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
                    <div class="header flex items-center justify-between">
                        <div class="flex flex-col gap-2">
                            <p class="font-bold text-xl">All Transactions</p>
                            <div class="flex items-center gap-1">
                                <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0"
                                    alt="icon">
                                <p class="font-semibold text-custom-grey">4 Total Transactions</p>
                            </div>
                        </div>
                    </div>
                    <div id="Filter" class="flex items-center justify-between">
                        <form action="#">
                            <label
                                class="flex items-center w-[370px] h-14 rounded-2xl p-4 gap-2 bg-white border border-custom-stroke focus-within:border-custom-black transition-300">
                                <img src="@/assets/images/icons/receipt-search-grey.svg" class="flex size-6 shrink-0"
                                    alt="icon">
                                <input type="text"
                                    class="appearance-none w-full placeholder:text-custom-grey font-medium focus:outline-none"
                                    placeholder="Search Transaction">
                            </label>
                        </form>
                        <div class="flex items-center gap-4">
                            <p class="font-medium text-custom-grey">Show</p>
                            <label
                                class="flex items-center h-14 rounded-2xl border border-custom-stroke py-4 px-5 pl-3 bg-white focus-within:border-custom-black transition-300">
                                <select name="" id=""
                                    class="text-custom-black font-medium appearance-none focus:outline-none p-2">
                                    <option value="" class="font-medium" selected>4 Entries</option>
                                    <option value="" class="font-medium">10 Entries</option>
                                    <option value="" class="font-medium">20 Entries</option>
                                    <option value="" class="font-medium">40 Entries</option>
                                </select>
                                <img src="@/assets/images/icons/arrow-down-black.svg"
                                    class="flex size-6 shrink-0 -ml-1" alt="icon">
                            </label>
                        </div>
                    </div>
                    <section id="List-Transactions" class="flex flex-col flex-1 gap-6 w-full">
                        <div class="list flex flex-col gap-5">
                            <div
                                class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white" v-for="transaction in transactions">
                                <div class="flex items-center justify-between">
                                    <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                        <img src="@/assets/images/icons/calendar-2-grey.svg"
                                            class="size-6 flex shrink-0" alt="icon">
                                        {{ formatToClientTimeZone(transaction.created_at) }}
                                    </p>
                                    <p
                                        class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-yellow text-[#544607]">
                                        {{ transaction.delivery_status }}
                                    </p>
                                </div>
                                <hr class="border-custom-stroke">
                                <div class="flex items-center gap-5 justify-between pr-[30px]">
                                    <div class="flex items-center gap-[14px] w-[320px]">
                                        <div
                                            class="flex size-[84px] shrink-0 rounded-[20px] bg-custom-background overflow-hidden">
                                            <img :src="transaction?.store?.logo"
                                                class="size-full object-cover" alt="photo">
                                        </div>
                                        <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                            <p class="font-bold text-lg leading-tight w-full truncate">
                                                {{ transaction?.store?.name }}
                                            </p>
                                            <p
                                                class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-5"
                                                    alt="icon">
                                                {{ formatToClientTimeZone(transaction.created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-[10px] w-[260px]">
                                        <div
                                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                            <img src="@/assets/images/icons/shopping-cart-black.svg"
                                                class="flex size-6 shrink-0" alt="icon">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <p class="font-bold text-lg leading-none">{{ transaction.transaction_details?.length }}</p>
                                            <p class="font-semibold text-custom-grey">Total Products</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-[10px] w-[260px]">
                                        <div
                                            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                                            <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0"
                                                alt="icon">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <p class="font-bold text-lg leading-none">{{ transaction.transaction_details?.reduce((total, detail) => total + detail.qty, 0) }}</p>
                                            <p class="font-semibold text-custom-grey">Total Quantity</p>
                                        </div>
                                    </div>
                                </div>
                                <hr class="border-custom-stroke">
                                <div class="flex items-center justify-between">
                                    <div class="flex flex-col gap-[6px]">
                                        <p class="font-bold text-xl text-custom-blue">{{ formatRupiah(transaction.grand_total) }}</p>
                                        <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                                            <img src="@/assets/images/icons/money-grey.svg"
                                                class="size-6 flex shrink-0" alt="icon">
                                            Grand Total
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-end gap-[14px]">
                                        <button
                                            class="flex items-center justify-center h-14 w-fit shrink-0 rounded-2xl p-4 gap-2 bg-custom-black">
                                            <span class="font-semibold text-white">Export</span>
                                            <img src="@/assets/images/icons/receive-square-white.svg"
                                                class="flex size-6 shrink-0" alt="icon">
                                        </button>
                                        <RouterLink :to="{name: 'admin.transaction.detail', params: {id: transaction.id}}"
                                            class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                                            <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon">
                                            <span class="font-semibold text-white">Details</span>
                                        </RouterLink >
                                    </div>
                                </div>
                            </div>
                        </div>
                        <nav id="Pagination">
                            <ul class="flex items-center gap-3">
                                <li class="group">
                                    <button
                                        class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold"
                                        disabled>
                                        <img src="@/assets/images/icons/arrow-right-no-tail-blue.svg"
                                            class="size-6 group-has-[:disabled]:opacity-20 rotate-180" alt="icon">
                                    </button>
                                </li>
                                <li class="group active">
                                    <button
                                        class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                                        1
                                    </button>
                                </li>
                                <li class="group">
                                    <button
                                        class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                                        2
                                    </button>
                                </li>
                                <li class="group">
                                    <button
                                        class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                                        3
                                    </button>
                                </li>
                                <li class="group">
                                    <button
                                        class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                                        4
                                    </button>
                                </li>
                                <li class="group">
                                    <button
                                        class="flex size-11 shrink-0 rounded-full items-center justify-center bg-custom-blue/10 text-custom-blue group-[&.active]:bg-custom-blue group-[&.active]:text-white font-semibold">
                                        5
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
                    </section>
                    <div id="Empty-State" class="hidden flex flex-col flex-1 items-center justify-center gap-4">
                        <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
                        <div class="flex flex-col gap-1 items-center text-center">
                            <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
                        </div>
                    </div>
                </div>
</template>
