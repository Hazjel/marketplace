<script setup>
import CardList from '@/components/admin/withdrawal/CardList.vue';
import Pagination from '@/components/admin/Pagination.vue';
import { useWithdrawalStore } from '@/stores/withdrawal';
import { debounce } from 'lodash';
import { storeToRefs } from 'pinia';
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { can } from '@/helpers/permissionHelper';
import { useToast } from "vue-toastification";

const toast = useToast();
const withdrawalStore = useWithdrawalStore()
const { withdrawals, meta, loading, success, error } = storeToRefs(withdrawalStore)
const { fetchWithdrawalsPaginated } = withdrawalStore

const serverOptions = ref({
    page: 1,
    row_per_page: 10
})

const filters = ref({
    search: null,
})

const fetchData = async () => {
    await fetchWithdrawalsPaginated({
        ...serverOptions.value,
        ...filters.value,
    })
}

const debounceFetchData = debounce(fetchData, 500)

onMounted(fetchData)

watch(serverOptions, () => {
    fetchData()
}, {deep: true})
watch(filters, () => {
    debounceFetchData()
}, {deep: true})
watch(success, (value) => {
    if (value) {
        toast.success(value);
        withdrawalStore.success = null;
    }
});
watch(error, (value) => {
    if (value) {
        toast.error(value);
        withdrawalStore.error = null;
    }
});
</script>

<template>
    <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
        <div class="header flex items-center justify-between">
            <div class="flex flex-col gap-2">
                <p class="font-bold text-xl">All Withdrawals</p>
                <div class="flex items-center gap-1">
                    <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    <p class="font-semibold text-custom-grey">{{ withdrawals.length }} Total Withdrawals</p>
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
            <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
                <p class="font-medium text-custom-grey">Show</p>
                <label
                    class="flex items-center h-14 rounded-2xl border border-custom-stroke py-4 px-5 pl-3 bg-white focus-within:border-custom-black transition-300">
                    <select name="" id="" class="text-custom-black font-medium appearance-none focus:outline-none p-2" v-model="serverOptions.row_per_page">
                        <option value="10" class="font-medium">10 Entries</option>
                        <option value="20" class="font-medium">20 Entries</option>
                        <option value="40" class="font-medium">40 Entries</option>
                    </select>
                    <img src="@/assets/images/icons/arrow-down-black.svg" class="flex size-6 shrink-0 -ml-1"
                        alt="icon">
                </label>
            </div>
        </div>
        <section id="List-Transactions" class="flex flex-col flex-1 gap-6 w-full">
            <div class="list flex flex-col gap-5">
                <CardList v-for="withdrawal in withdrawals" :key="withdrawal.id" :item="withdrawal" v-if="!loading && withdrawals" />
            </div>
            <Pagination :meta="meta" :server-options="serverOptions"/>
        </section>
        <div id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4" v-if="withdrawals?.length === 0">
            <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
            <div class="flex flex-col gap-1 items-center text-center">
                <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
            </div>
        </div>
    </div>
</template>