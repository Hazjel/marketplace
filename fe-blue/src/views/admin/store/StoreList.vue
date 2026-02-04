<script setup>
import CardList from '@/components/admin/store/CardList.vue'
import Pagination from '@/components/admin/Pagination.vue'
import { useStoreStore } from '@/stores/store'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { can } from '@/helpers/permissionHelper'
import { useToast } from 'vue-toastification'

const toast = useToast()
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

async function handleDelete(id) {
  await deleteStore(id)

  fetchData()
}

const debounceFetchData = debounce(fetchData, 500)

onMounted(fetchData)

watch(
  serverOptions,
  () => {
    fetchData()
  },
  { deep: true }
)
watch(
  filters,
  () => {
    debounceFetchData()
  },
  { deep: true }
)
watch(success, (value) => {
  if (value) {
    toast.success(value)
    storeStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    storeStore.error = null
  }
})
</script>

<template>
  <div
    class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white dark:bg-surface-card dark:text-white border border-transparent dark:border-white/10">
    <div class="header flex flex-col md:flex-row items-center justify-between gap-5">
      <div class="flex flex-col gap-2 w-full md:w-auto">
        <p class="font-bold text-xl">All Stores</p>
        <div class="flex items-center gap-1">
          <img src="@/assets/images/icons/shop-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
          <p class="font-semibold text-custom-grey">{{ meta.total }} Total Stores</p>
        </div>
      </div>
      <div id="TabButtons"
        class="flex items-center gap-0.5 h-14 w-full md:w-[460px] rounded-xl bg-custom-icon-background overflow-x-auto">
        <button type="button" class="tab-btn group w-full shrink-0" :class="{ active: filters.is_verified }"
          @click="filters.is_verified = true">
          <div
            class="flex items-center justify-center h-12 w-full shrink-0 rounded-xl py-[14px] px-3 gap-2 bg-custom-icon-background group-[&.active]:bg-custom-blue transition-300">
            <span class="font-semibold group-[&.active]:text-white text-custom-grey transition-300 text-nowrap">Verified
              Store</span>
          </div>
        </button>
        <button type="button" class="tab-btn group w-full shrink-0" :class="{ active: !filters.is_verified }"
          @click="filters.is_verified = false">
          <div
            class="flex items-center justify-center h-12 w-full shrink-0 rounded-xl py-[14px] px-3 gap-2 bg-custom-icon-background group-[&.active]:bg-custom-blue transition-300">
            <span class="font-semibold group-[&.active]:text-white text-custom-grey transition-300 text-nowrap">Awaiting
              approval</span>
          </div>
        </button>
      </div>
    </div>

    <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
      <form action="#" class="w-full md:w-auto">
        <label
          class="flex items-center w-full md:w-[370px] h-14 rounded-2xl p-4 gap-2 bg-white dark:bg-surface-card border border-custom-stroke dark:border-white/10 focus-within:border-custom-black dark:focus-within:border-white transition-300">
          <img src="@/assets/images/icons/receipt-search-grey.svg" class="flex size-6 shrink-0 dark:invert"
            alt="icon" />
          <input v-model="filters.search" type="text"
            class="appearance-none w-full placeholder:text-custom-grey font-medium focus:outline-none bg-transparent"
            placeholder="Search store" />
        </label>
      </form>
      <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
        <p class="font-medium text-custom-grey">Show</p>
        <label
          class="flex items-center h-14 rounded-2xl border border-custom-stroke dark:border-white/10 py-4 px-5 pl-3 bg-white dark:bg-surface-card focus-within:border-custom-black transition-300">
          <select id="" v-model="serverOptions.row_per_page" name=""
            class="text-custom-black dark:text-white font-medium appearance-none focus:outline-none p-2 bg-transparent">
            <option value="10" class="font-medium dark:bg-surface-card">10 Entries</option>
            <option value="20" class="font-medium dark:bg-surface-card">20 Entries</option>
            <option value="40" class="font-medium dark:bg-surface-card">40 Entries</option>
          </select>
          <img src="@/assets/images/icons/arrow-down-black.svg" class="flex size-6 shrink-0 -ml-1 dark:invert"
            alt="icon" />
        </label>
      </div>
    </div>
    <div id="List-Categories" class="flex flex-col gap-6">
      <div id="List" class="flex flex-col gap-5">
        <CardList v-for="store in stores" v-if="!loading && stores" :key="store.id" :item="store"
          @delete="handleDelete" />
      </div>
      <Pagination :meta="meta" :server-options="serverOptions" />
    </div>
  </div>
</template>
