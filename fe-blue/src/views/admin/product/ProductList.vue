<script setup>
import CardList from '@/components/admin/product/CardList.vue'
import Pagination from '@/components/admin/Pagination.vue'
import { useAuthStore } from '@/stores/auth'
import { useProductStore } from '@/stores/product'
import { debounce } from 'lodash'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch, reactive, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'

const toast = useToast()
const productStore = useProductStore()
const authStore = useAuthStore()

const { products, meta, loading, success, error } = storeToRefs(productStore)
const { fetchProductsPaginated, deleteProduct } = productStore

const { user } = storeToRefs(authStore)

const filters = reactive({
  search: ''
})

const serverOptions = ref({
  page: 1,
  row_per_page: 10
})

const totalProductsSummary = ref(0)
const selectedItems = ref([])

const allSelected = computed(() => {
  return products.value.length > 0 && selectedItems.value.length === products.value.length
})

const toggleSelection = (id) => {
  if (selectedItems.value.includes(id)) {
    selectedItems.value = selectedItems.value.filter((itemId) => itemId !== id)
  } else {
    selectedItems.value.push(id)
  }
}

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedItems.value = []
  } else {
    selectedItems.value = products.value.map((p) => p.id)
  }
}

const bulkDelete = async () => {
  if (!confirm(`Are you sure you want to delete ${selectedItems.value.length} items?`)) return

  try {
    await Promise.all(selectedItems.value.map((id) => deleteProduct(id)))

    toast.success(`Deleted ${selectedItems.value.length} items successfully`)
    selectedItems.value = []
    fetchData()
  } catch (e) {
    toast.error('Failed to delete some items')
  }
}

const fetchData = async () => {
  loading.value = true
  await fetchProductsPaginated({
    ...serverOptions.value,
    ...filters,
    store_id: user.value?.store?.id
  })

  selectedItems.value = []

  if (!filters.search) {
    totalProductsSummary.value = meta.value.total
  }
}

async function handleDelete(id) {
  if (!confirm('Are you sure?')) return
  await deleteProduct(id)
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
    productStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    productStore.error = null
  }
})
</script>

<template>
  <!-- Page Header -->
  <div class="rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 p-6 shadow-sm">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="flex size-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-white">Produk Saya</h1>
          <p class="text-blue-100 text-sm mt-0.5">Kelola semua produk toko Anda</p>
        </div>
      </div>
      <RouterLink
        v-if="user?.permissions?.includes('product-create')"
        :to="{ name: 'admin.product.create' }"
        class="flex h-11 items-center justify-center rounded-xl px-5 gap-2 bg-white text-blue-700 font-semibold text-sm hover:bg-blue-50 transition-colors shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        <span>Tambah Produk</span>
      </RouterLink>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="flex flex-col md:flex-row w-full gap-4">
    <div class="flex flex-col w-full rounded-2xl p-5 gap-4 bg-white dark:bg-surface-card dark:text-white border border-gray-100 dark:border-white/10 shadow-sm animate-fade-in-up">
      <div class="flex items-center gap-4">
        <div class="flex size-12 bg-blue-50 dark:bg-custom-blue/20 items-center justify-center rounded-xl">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="font-bold text-2xl">{{ totalProductsSummary }}</p>
          <p class="font-medium text-sm text-custom-grey">Total Produk</p>
        </div>
      </div>
    </div>
    <div class="flex flex-col w-full rounded-2xl p-5 gap-4 bg-white dark:bg-surface-card dark:text-white border border-gray-100 dark:border-white/10 shadow-sm animate-fade-in-up delay-100">
      <div class="flex items-center gap-4">
        <div class="flex size-12 bg-green-50 dark:bg-green-500/20 items-center justify-center rounded-xl">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
          </svg>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="font-bold text-2xl">
            {{ meta?.total_sold ? meta.total_sold.toLocaleString() : 0 }}
          </p>
          <p class="font-medium text-sm text-custom-grey">Total Terjual</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Content Card -->
  <div class="flex flex-col flex-1 rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card dark:text-white border border-gray-100 dark:border-white/10 shadow-sm animate-fade-in-up delay-200">
    <!-- Filter Bar -->
    <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-3 w-full md:w-auto">
        <!-- Select All Checkbox -->
        <label
          class="cursor-pointer flex items-center gap-2 border border-gray-200 dark:border-white/10 rounded-xl h-11 px-3 hover:border-blue-300 dark:hover:border-white/20 transition-colors"
          title="Select All">
          <input
            type="checkbox"
            :checked="allSelected"
            class="checkbox checkbox-primary rounded-lg size-4 border-2 border-gray-300 checked:bg-custom-blue checked:border-custom-blue transition-all"
            @change="toggleSelectAll" />
          <span class="font-medium text-sm text-gray-600 dark:text-gray-300 hidden md:block">Semua</span>
        </label>

        <!-- Search Input -->
        <form action="#" class="w-full md:w-auto">
          <label class="flex items-center w-full md:w-[320px] h-11 rounded-xl px-3 gap-2 bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus-within:border-blue-400 dark:focus-within:border-blue-400 focus-within:bg-white dark:focus-within:bg-surface-card transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="filters.search"
              type="text"
              class="appearance-none w-full placeholder:text-gray-400 font-medium text-sm focus:outline-none bg-transparent dark:text-white"
              placeholder="Cari produk..." />
          </label>
        </form>
      </div>

      <!-- Entries Selector -->
      <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-start">
        <p class="font-medium text-sm text-custom-grey">Tampilkan</p>
        <label class="flex items-center h-11 rounded-xl border border-gray-200 dark:border-white/10 py-2 px-3 bg-white dark:bg-surface-card focus-within:border-blue-400 transition-colors">
          <select
            v-model="serverOptions.row_per_page"
            class="text-gray-700 dark:text-white font-medium text-sm appearance-none focus:outline-none pr-6 bg-transparent">
            <option value="10" class="font-medium dark:bg-surface-card">10 Data</option>
            <option value="20" class="font-medium dark:bg-surface-card">20 Data</option>
            <option value="40" class="font-medium dark:bg-surface-card">40 Data</option>
          </select>
          <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-400 -ml-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </label>
      </div>
    </div>

    <!-- Product List -->
    <div id="List-Categories" class="flex flex-col gap-4 relative">
      <div v-if="!loading && products" id="List" class="flex flex-col gap-3 pb-20">
        <CardList
          v-for="product in products"
          :key="product.id"
          :item="product"
          :selected="selectedItems.includes(product.id)"
          @toggle-selection="toggleSelection"
          @delete="handleDelete" />
      </div>

      <!-- Floating Bulk Action Bar -->
      <div
        v-if="selectedItems.length > 0"
        class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-white dark:bg-surface-card dark:text-white shadow-2xl rounded-2xl px-6 py-3 flex items-center gap-4 border border-gray-100 dark:border-white/10 z-50 animate-fade-in-up">
        <span class="font-bold text-sm text-gray-700 dark:text-white">{{ selectedItems.length }} Dipilih</span>
        <div class="h-5 w-px bg-gray-200 dark:bg-gray-700"></div>
        <button
          class="flex items-center gap-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 px-3 py-1.5 rounded-lg transition-colors"
          @click="bulkDelete">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
          <span class="font-semibold text-sm">Hapus</span>
        </button>
      </div>

      <Pagination :meta="meta" :server-options="serverOptions" />
    </div>

    <!-- Empty State -->
    <div v-if="products?.length === 0" id="Empty-State" class="flex flex-col flex-1 items-center justify-center gap-4 py-16">
      <div class="flex size-20 items-center justify-center rounded-2xl bg-gray-50 dark:bg-white/5">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
      </div>
      <div class="flex flex-col gap-1 items-center text-center">
        <p class="font-semibold text-gray-700 dark:text-gray-300">Belum ada produk</p>
        <p class="text-sm text-custom-grey">Mulai tambahkan produk pertama Anda</p>
      </div>
      <RouterLink
        v-if="user?.permissions?.includes('product-create')"
        :to="{ name: 'admin.product.create' }"
        class="flex h-10 items-center rounded-xl px-5 gap-2 bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 transition-colors mt-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Tambah Produk
      </RouterLink>
    </div>
  </div>
</template>
