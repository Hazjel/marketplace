<script setup>
import CardList from '@/components/admin/category/CardList.vue'
import Pagination from '@/components/admin/Pagination.vue'
import { useProductCategoryStore } from '@/stores/productCategory'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'

const toast = useToast()
const productCategoryStore = useProductCategoryStore()
const { productCategories, meta, loading, success, error } = storeToRefs(productCategoryStore)
const { fetchProductCategoriesPaginated, deleteProductCategory } = productCategoryStore
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const getRoute = (name, params = {}) => {
  if (user.value?.role === 'admin') {
    return { name: `admin.${name}`, params }
  }
  return {
    name: `user.${name}`,
    params: { username: user.value?.username, ...params }
  }
}

const serverOptions = ref({
  page: 1,
  row_per_page: 10
})

const filters = ref({
  search: null,
  is_parent: 1
})

const fetchData = async () => {
  await fetchProductCategoriesPaginated({
    ...serverOptions.value,
    ...filters.value
  })
}

async function handleDelete(id) {
  await deleteProductCategory(id)

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
    productCategoryStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    productCategoryStore.error = null
  }
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header -->
    <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 p-6 shadow-sm">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="flex size-12 items-center justify-center rounded-xl bg-white/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
            </svg>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-white">Kategori Produk</h1>
            <p class="text-blue-100">Kelola semua kategori produk marketplace</p>
          </div>
        </div>
        <RouterLink
          :to="getRoute('category.create')"
          class="flex h-11 items-center rounded-xl py-2.5 px-5 bg-white/20 hover:bg-white/30 transition-colors duration-200 gap-2 border border-white/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          <span class="font-semibold text-sm text-white">Tambah Kategori</span>
        </RouterLink>
      </div>
    </div>

    <!-- Main Content Card -->
    <div class="flex flex-col flex-1 rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm p-6 gap-6">
      <!-- Stats & Filter -->
      <div class="flex items-center gap-2 mb-1">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
        </svg>
        <p class="font-medium text-sm text-gray-500 dark:text-gray-400">
          {{ productCategories?.length }} Total Kategori
        </p>
      </div>

      <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
        <form action="#" class="w-full md:w-auto">
          <label class="flex items-center w-full md:w-[400px] h-12 rounded-xl px-4 gap-3 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus-within:border-indigo-500 dark:focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-500/20 transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input
              v-model="filters.search" type="text"
              class="appearance-none w-full placeholder:text-gray-400 dark:placeholder:text-gray-500 font-medium text-sm focus:outline-none bg-transparent dark:text-white"
              placeholder="Cari kategori..." />
          </label>
        </form>
        <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
          <p class="font-medium text-gray-500 dark:text-gray-400 text-sm">Tampilkan</p>
          <label class="flex items-center h-12 rounded-xl border border-gray-200 dark:border-white/10 py-2 px-4 bg-gray-50 dark:bg-white/5 focus-within:border-indigo-500 transition-all duration-200">
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
      <div id="List-Categories" class="flex flex-col gap-6">
        <div id="List" class="flex flex-col gap-4">
          <CardList
            v-for="category in productCategories" v-if="!loading && productCategories" :key="category.id"
            :item="category" @delete="handleDelete" />
        </div>
        <Pagination :meta="meta" :server-options="serverOptions" />
      </div>

      <!-- Empty State -->
      <div
        v-if="productCategories?.length === 0" id="Empty-State"
        class="flex flex-col flex-1 items-center justify-center py-16 gap-4">
        <div class="flex size-20 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/10">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-10 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
          </svg>
        </div>
        <div class="flex flex-col gap-1 items-center text-center">
          <p class="font-semibold text-gray-900 dark:text-white">Belum ada kategori</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Mulai tambahkan kategori produk baru</p>
        </div>
      </div>
    </div>
  </div>
</template>
