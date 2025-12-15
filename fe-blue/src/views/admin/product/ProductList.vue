<script setup>
import Alert from '@/components/admin/Alert.vue';
import CardList from '@/components/admin/product/CardList.vue';
import Pagination from '@/components/admin/Pagination.vue';
import { useProductStore } from '@/stores/product';
import { debounce } from 'lodash';
import { storeToRefs } from 'pinia';
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { can } from '@/helpers/permissionHelper';

const productStore = useProductStore()
const { products, meta, loading, success, error } = storeToRefs(productStore)
const { fetchProductsPaginated, deleteProduct } = productStore

const serverOptions = ref({
    page: 1,
    row_per_page: 10
})

const filters = ref({
    search: null
})

const totalProductsSummary = ref(0)

const fetchData = async () => {
    await fetchProductsPaginated({
        ...serverOptions.value,
        ...filters.value
    })

    if (!filters.value.search) {
        totalProductsSummary.value = meta.value.total
    }
}

async function handleDelete(id) {
    await deleteProduct(id)

    fetchData()
}

const debounceFetchData = debounce(fetchData, 500)

const closeAlert = () => {
    productStore.success = null
    productStore.error = null
}

onMounted(fetchData)

watch(serverOptions, () => {
    fetchData()
}, { deep: true })

watch(filters, () => {
    debounceFetchData()
}, { deep: true })
</script>

<template>
    <div class="flex w-full gap-5">
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">{{ totalProductsSummary }}</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Products
                    </p>
                </div>
            </div>
        </div>
        <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white">
            <div class="flex flex-col gap-6">
                <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
                    <img src="@/assets/images/icons/presention-chart-blue.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-4xl">{{ meta.total_sold ? meta.total_sold.toLocaleString() : 0 }}</p>
                    <p class="font-medium text-lg text-custom-grey">
                        Total Sold
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
        <div class="header flex items-center justify-between">
            <div class="flex flex-col gap-2">
                <p class="font-bold text-xl">All Products</p>
                <div class="flex items-center gap-1">
                    <img src="@/assets/images/icons/shopping-cart-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    <p class="font-semibold text-custom-grey">{{ meta.total }} Total Products</p>
                </div>
            </div>
            <RouterLink :to="{ name: 'admin.product.create' }"
                class="flex h-14 items-center rounded-full py-4 px-6 bg-custom-blue gap-[6px]"
                v-if="can('product-create')">
                <span class="font-semibold text-lg text-white leading-none">Add New</span>
                <img src="@/assets/images/icons/add-circle-white.svg" class="flex size-6 shrink-0" alt="icon">
            </RouterLink>
        </div>
        <alert :success="success" :error="error" @closeAlert="() => { success = null; error = null; }"></alert>
        <div id="Filter" class="flex items-center justify-between">
            <form action="#">
                <label
                    class="flex items-center w-[370px] h-14 rounded-2xl p-4 gap-2 bg-white border border-custom-stroke focus-within:border-custom-black transition-300">
                    <img src="@/assets/images/icons/box-search-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    <input type="text"
                        class="appearance-none w-full placeholder:text-custom-grey font-medium focus:outline-none"
                        placeholder="Search product" v-model="filters.search">
                </label>
            </form>
            <div class="flex items-center gap-4">
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
        <div id="List-Categories" class="flex flex-col gap-6">
            <div id="List" class="flex flex-col gap-5">
                <CardList v-for="product in products" :key="product.id" :item="product" @delete="handleDelete"
                    v-if="!loading && products" />
            </div>
            <Pagination :meta="meta" :server-options="serverOptions" />
        </div>
        <div id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4"
            v-if="products?.length === 0">
            <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon">
            <div class="flex flex-col gap-1 items-center text-center">
                <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
            </div>
        </div>
    </div>
</template>