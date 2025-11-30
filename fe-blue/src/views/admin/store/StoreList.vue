<script setup>
import Alert from '@/components/admin/Alert.vue';
import CardList from '@/components/admin/store/CardList.vue';
import Pagination from '@/components/admin/Pagination.vue';
import { useStoreStore } from '@/stores/store';
import { debounce } from 'lodash';
import { storeToRefs } from 'pinia';
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { can } from '@/helpers/permissionHelper';

const storeStore = useStoreStore()
const { stores, meta, loading, success, error } = storeToRefs(storeStore)
const { fetchStoresPaginated, deleteStore } = storeStore

const serverOptions = ref({
    page: 1,
    row_per_page: 10
})

const filters = ref({
    search: null,
    is_verified: true
})

const totalStoresSummary = ref(0)

const fetchData = async () => {
    await fetchStoresPaginated({
        ...serverOptions.value,
        ...filters.value,
        is_verified: filters.value.is_verified ? 1 : 0
    })

    if (!filters.value.search) {
        totalStoresSummary.value = meta.value.total
    }
}

async function handleDelete(id){
    await deleteStore(id)

    fetchData()
}

const debounceFetchData = debounce(fetchData, 500)

const closeAlert = () => {
    storeStore.success = null
    storeStore.error = null
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
                <p class="font-bold text-xl">All Stores</p>
                <div class="flex items-center gap-1">
                    <img src="@/assets/images/icons/shop-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    <p class="font-semibold text-custom-grey">{{ meta.total }} Total Stores</p>
                </div>
            </div>
            <div id="TabButtons" class="flex items-center gap-0.5 h-14 w-[460px] rounded-xl bg-custom-icon-background">
                <button type="button" class="tab-btn group w-full" :class="{ 'active': filters.is_verified }" @click="filters.is_verified = true">
                    <div class="flex items-center justify-center h-12 w-full shrink-0 rounded-xl py-[14px] px-3 gap-2 bg-custom-icon-background group-[&.active]:bg-custom-blue transition-300">
                        <span class="font-semibold group-[&.active]:text-white text-custom-grey transition-300">Verified Store</span>
                    </div>
                </button>
                <button type="button" class="tab-btn group w-full" :class="{ 'active': !filters.is_verified }" @click="filters.is_verified = false">
                    <div class="flex items-center justify-center h-12 w-full shrink-0 rounded-xl py-[14px] px-3 gap-2 bg-custom-icon-background group-[&.active]:bg-custom-blue transition-300">
                        <span class="font-semibold group-[&.active]:text-white text-custom-grey transition-300">Awaiting approval</span>
                    </div>
                </button>
            </div>
        </div>

        <Alert :success="success" :error="error" @closeAlert="closeAlert"/>

        <div id="Filter" class="flex items-center justify-between">
            <form action="#">
                <label class="flex items-center w-[370px] h-14 rounded-2xl p-4 gap-2 bg-white border border-custom-stroke focus-within:border-custom-black transition-300">
                    <img src="@/assets/images/icons/receipt-search-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    <input type="text" class="appearance-none w-full placeholder:text-custom-grey font-medium focus:outline-none" placeholder="Search store" v-model="filters.search">
                </label>
            </form>
            <div class="flex items-center gap-4">
                <p class="font-medium text-custom-grey">Show</p>
                <label class="flex items-center h-14 rounded-2xl border border-custom-stroke py-4 px-5 pl-3 bg-white focus-within:border-custom-black transition-300">
                    <select name="" id="" class="text-custom-black font-medium appearance-none focus:outline-none p-2" v-model="serverOptions.row_per_page">
                        <option value="10" class="font-medium">10 Entries</option>
                        <option value="20" class="font-medium">20 Entries</option>
                        <option value="40" class="font-medium">40 Entries</option>
                    </select>
                    <img src="@/assets/images/icons/arrow-down-black.svg" class="flex size-6 shrink-0 -ml-1" alt="icon">
                </label>
            </div>
        </div>
        <div id="List-Categories" class="flex flex-col gap-6">
            <div id="List" class="flex flex-col gap-5">
                <CardList v-for="store in stores" :key="store.id" :item="store" @delete="handleDelete" v-if="!loading && stores" />
            </div>
            <Pagination :meta="meta" :server-options="serverOptions"/>
        </div>
    </div>
</template>