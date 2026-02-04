<script setup>
import CardList from '@/components/admin/store-balance/CardList.vue'
import Pagination from '@/components/admin/Pagination.vue'
import { useStoreBalanceStore } from '@/stores/storeBalance'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { can } from '@/helpers/permissionHelper'
import { useToast } from 'vue-toastification'

const toast = useToast()
const storeBalanceStore = useStoreBalanceStore()
const { storeBalances, meta, loading, success, error } = storeToRefs(storeBalanceStore)
const { fetchStoreBalancesPaginated } = storeBalanceStore

const serverOptions = ref({
  page: 1,
  row_per_page: 10
})

const filters = ref({
  search: null
})

const fetchData = async () => {
  await fetchStoreBalancesPaginated({
    ...serverOptions.value,
    ...filters.value
  })
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
    storeBalanceStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    storeBalanceStore.error = null
  }
})
</script>

<template>
  <div
    class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white dark:bg-surface-card dark:text-white border border-transparent dark:border-white/10">
    <div class="header flex items-center justify-between">
      <div class="flex flex-col gap-2">
        <p class="font-bold text-xl">All Wallets</p>
        <div class="flex items-center gap-1">
          <img src="@/assets/images/icons/wallet-3-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
          <p class="font-semibold text-custom-grey">{{ storeBalances.length }} Total Wallets</p>
        </div>
      </div>
    </div>
    <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
      <form action="#" class="w-full md:w-auto">
        <label
          class="flex items-center w-full md:w-[370px] h-14 rounded-2xl p-4 gap-2 bg-white dark:bg-surface-card border border-custom-stroke dark:border-white/10 focus-within:border-custom-black dark:focus-within:border-white transition-300">
          <img src="@/assets/images/icons/wallet-search-grey.png" class="flex size-6 shrink-0 dark:invert" alt="icon" />
          <input v-model="filters.search" type="text"
            class="appearance-none w-full placeholder:text-custom-grey font-medium focus:outline-none bg-transparent"
            placeholder="Search Wallet" />
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
    <section id="List-Transactions" class="flex flex-col flex-1 gap-6 w-full">
      <div class="list flex flex-col gap-5">
        <CardList v-for="storeBalance in storeBalances" v-if="!loading && storeBalances" :key="storeBalance.id"
          :item="storeBalance" />
      </div>
      <Pagination :meta="meta" :server-options="serverOptions" />
    </section>
    <div v-if="storeBalances?.length === 0" id="Empty-State"
      class="flex flex-col flex-1 items-center justify-center gap-4">
      <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px] dark:invert" alt="icon" />
      <div class="flex flex-col gap-1 items-center text-center">
        <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
      </div>
    </div>
  </div>
</template>
