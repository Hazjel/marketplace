<script setup>
import CardList from '@/components/admin/user/CardList.vue'
import Pagination from '@/components/admin/Pagination.vue'
import { useUserStore } from '@/stores/user'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'

const userStore = useUserStore()
const { users, meta, loading } = storeToRefs(userStore)
const { fetchUsersPaginated } = userStore

const serverOptions = ref({
  page: 1,
  row_per_page: 10
})

const filters = ref({
  search: null
})

const fetchData = async () => {
  await fetchUsersPaginated({
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
    // setiap filter berubah, reset ke halaman pertama supaya hasil pencarian tetap muncul
    serverOptions.value.page = 1
    debounceFetchData()
  },
  { deep: true }
)
</script>

<template>
  <div
    class="flex flex-col flex-1 rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 dark:text-white border border-gray-100 dark:border-white/10 shadow-sm">
    <!-- Header -->
    <div class="header flex items-center justify-between">
      <div class="flex flex-col gap-2">
        <h1 class="font-bold text-2xl text-gray-900 dark:text-white">Semua Pengguna</h1>
        <div class="flex items-center gap-2">
          <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-[#2563EB]/10 dark:bg-[#2563EB]/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-1.053M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07m0 0a9.005 9.005 0 00-5.593-4.482M12 3a4.5 4.5 0 110 9 4.5 4.5 0 010-9z" />
            </svg>
          </div>
          <p class="font-semibold text-gray-500 dark:text-gray-400 text-sm">{{ users.length }} Total Pengguna</p>
        </div>
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
            placeholder="Cari pengguna..." />
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
    <section id="List-Transactions" class="flex flex-col flex-1 gap-6 w-full">
      <div v-if="!loading && users?.length > 0" class="list flex flex-col gap-4">
        <CardList v-for="user in users" :key="user.id" :item="user" />
      </div>
      <div v-else-if="!loading && users?.length === 0" id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4 py-16">
        <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-white/10 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9.75m3 0H9.75m0 0V15m0 2.25h.008v.008H9.75V17.25zM10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
          </svg>
        </div>
        <p class="font-semibold text-gray-500 dark:text-gray-400">Belum ada data pengguna</p>
      </div>
      <Pagination v-if="users?.length > 0" :meta="meta" :server-options="serverOptions" />
    </section>
  </div>
</template>
