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
    class="flex flex-col flex-1 rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 dark:text-white border border-gray-100 dark:border-white/10 shadow-sm">
    <!-- Header -->
    <div class="header flex flex-col md:flex-row items-center justify-between gap-5">
      <div class="flex flex-col gap-2 w-full md:w-auto">
        <h1 class="font-bold text-2xl text-gray-900 dark:text-white">Semua Toko</h1>
        <div class="flex items-center gap-2">
          <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-[#2563EB]/10 dark:bg-[#2563EB]/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016A3.001 3.001 0 0021 9.349m-18 0a2.998 2.998 0 01.75-1.916l1.875-2.376A1.5 1.5 0 016.81 4.5h10.38a1.5 1.5 0 011.185.568l1.875 2.376a2.998 2.998 0 01.75 1.916" />
            </svg>
          </div>
          <p class="font-semibold text-gray-500 dark:text-gray-400 text-sm">{{ meta.total }} Total Toko</p>
        </div>
      </div>

      <!-- Tab Buttons -->
      <div
        id="TabButtons"
        class="flex items-center gap-1 h-12 w-full md:w-[420px] rounded-2xl bg-gray-100 dark:bg-gray-800 p-1 overflow-x-auto">
        <button
          type="button" class="tab-btn group w-full shrink-0" :class="{ active: filters.is_verified }"
          @click="filters.is_verified = true">
          <div
            class="flex items-center justify-center h-10 w-full shrink-0 rounded-xl py-2 px-3 gap-2 transition-all"
            :class="filters.is_verified ? 'bg-[#2563EB] shadow-md' : 'bg-transparent'">
            <span class="font-semibold text-sm text-nowrap transition-colors"
              :class="filters.is_verified ? 'text-white' : 'text-gray-500 dark:text-gray-400'">
              Terverifikasi
            </span>
          </div>
        </button>
        <button
          type="button" class="tab-btn group w-full shrink-0" :class="{ active: !filters.is_verified }"
          @click="filters.is_verified = false">
          <div
            class="flex items-center justify-center h-10 w-full shrink-0 rounded-xl py-2 px-3 gap-2 transition-all"
            :class="!filters.is_verified ? 'bg-[#2563EB] shadow-md' : 'bg-transparent'">
            <span class="font-semibold text-sm text-nowrap transition-colors"
              :class="!filters.is_verified ? 'text-white' : 'text-gray-500 dark:text-gray-400'">
              Menunggu Persetujuan
            </span>
          </div>
        </button>
      </div>
    </div>

    <!-- Filter -->
    <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
      <form action="#" class="w-full md:w-auto">
        <label
          class="flex items-center w-full md:w-[400px] h-12 rounded-2xl px-4 gap-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-white/10 focus-within:border-[#2563EB] dark:focus-within:border-[#2563EB] focus-within:ring-2 focus-within:ring-[#2563EB]/20 transition-all">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
          </svg>
          <input
            v-model="filters.search" type="text"
            class="appearance-none w-full placeholder:text-gray-400 font-medium text-sm focus:outline-none bg-transparent text-gray-900 dark:text-white"
            placeholder="Cari toko..." />
        </label>
      </form>
      <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
        <p class="font-medium text-gray-500 dark:text-gray-400 text-sm">Tampilkan</p>
        <label
          class="flex items-center h-12 rounded-2xl border border-gray-200 dark:border-white/10 py-2 px-4 bg-gray-50 dark:bg-gray-800 focus-within:border-[#2563EB] transition-all">
          <select
            v-model="serverOptions.row_per_page"
            class="text-gray-900 dark:text-white font-medium text-sm appearance-none focus:outline-none pr-6 bg-transparent">
            <option value="10" class="font-medium dark:bg-gray-800">10 Entri</option>
            <option value="20" class="font-medium dark:bg-gray-800">20 Entri</option>
            <option value="40" class="font-medium dark:bg-gray-800">40 Entri</option>
          </select>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 -ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
          </svg>
        </label>
      </div>
    </div>

    <!-- List -->
    <div id="List-Categories" class="flex flex-col gap-6">
      <div id="List" class="flex flex-col gap-4">
        <CardList
          v-for="store in stores" v-if="!loading && stores" :key="store.id" :item="store"
          @delete="handleDelete" />
      </div>
      <Pagination :meta="meta" :server-options="serverOptions" />
    </div>
  </div>
</template>
