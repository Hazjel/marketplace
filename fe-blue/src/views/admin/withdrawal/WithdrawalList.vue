<script setup>
import CardList from '@/components/admin/withdrawal/CardList.vue'
import Pagination from '@/components/admin/Pagination.vue'
import { useWithdrawalStore } from '@/stores/withdrawal'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { can } from '@/helpers/permissionHelper'
import { useToast } from 'vue-toastification'

const toast = useToast()
const withdrawalStore = useWithdrawalStore()
const { withdrawals, meta, loading, success, error } = storeToRefs(withdrawalStore)
const { fetchWithdrawalsPaginated } = withdrawalStore

const serverOptions = ref({
  page: 1,
  row_per_page: 10
})

const filters = ref({
  search: null
})

const fetchData = async () => {
  await fetchWithdrawalsPaginated({
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
    withdrawalStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    withdrawalStore.error = null
  }
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header -->
    <div class="rounded-2xl bg-gradient-to-r from-green-600 to-emerald-600 p-6 shadow-sm">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="flex size-12 items-center justify-center rounded-xl bg-white/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
            </svg>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-white">Penarikan Dana</h1>
            <p class="text-green-100">Kelola semua permintaan penarikan</p>
          </div>
        </div>
        <div class="flex items-center gap-2 rounded-xl bg-white/20 px-4 py-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
          </svg>
          <span class="font-semibold text-white">{{ withdrawals.length }} Total</span>
        </div>
      </div>
    </div>

    <!-- Main Content Card -->
    <div class="flex flex-col flex-1 rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm p-6 gap-6">
      <!-- Filter Section -->
      <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
        <form action="#" class="w-full md:w-auto">
          <label class="flex items-center w-full md:w-[400px] h-12 rounded-xl px-4 gap-3 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus-within:border-green-500 dark:focus-within:border-green-400 focus-within:ring-2 focus-within:ring-green-500/20 transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input
              v-model="filters.search" type="text"
              class="appearance-none w-full placeholder:text-gray-400 dark:placeholder:text-gray-500 font-medium text-sm focus:outline-none bg-transparent dark:text-white"
              placeholder="Cari penarikan..." />
          </label>
        </form>
        <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
          <p class="font-medium text-gray-500 dark:text-gray-400 text-sm">Tampilkan</p>
          <label class="flex items-center h-12 rounded-xl border border-gray-200 dark:border-white/10 py-2 px-4 bg-gray-50 dark:bg-white/5 focus-within:border-green-500 transition-all duration-200">
            <select
              v-model="serverOptions.row_per_page"
              class="text-gray-700 dark:text-white font-medium text-sm appearance-none focus:outline-none pr-6 bg-transparent">
              <option value="10" class="dark:bg-surface-card">10 Entri</option>
              <option value="20" class="dark:bg-surface-card">20 Entri</option>
              <option value="40" class="dark:bg-surface-card">40 Entri</option>
            </select>
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-400 -ml-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
          </label>
        </div>
      </div>

      <!-- List -->
      <section id="List-Transactions" class="flex flex-col flex-1 gap-6 w-full">
        <div class="list flex flex-col gap-4">
          <CardList
            v-for="withdrawal in withdrawals" v-if="!loading && withdrawals" :key="withdrawal.id"
            :item="withdrawal" />
        </div>
        <Pagination :meta="meta" :server-options="serverOptions" />
      </section>

      <!-- Empty State -->
      <div
        v-if="withdrawals?.length === 0" id="Empty-State"
        class="flex flex-col flex-1 items-center justify-center py-16 gap-4">
        <div class="flex size-20 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
          </svg>
        </div>
        <div class="flex flex-col gap-1 items-center text-center">
          <p class="font-semibold text-gray-900 dark:text-white">Belum ada permintaan penarikan</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Data penarikan akan muncul di sini</p>
        </div>
      </div>
    </div>
  </div>
</template>
