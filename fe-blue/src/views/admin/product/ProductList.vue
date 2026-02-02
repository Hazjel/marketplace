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
    // Run specific delete requests in parallel (mocked bulk delete since API might not support array)
    // Ideally backend has DELETE /products/bulk { ids: [...] }
    // For now, loop delete (not optimal but works for small batches)
    await Promise.all(selectedItems.value.map((id) => deleteProduct(id)))

    toast.success(`Deleted ${selectedItems.value.length} items successfully`)
    selectedItems.value = []
    fetchData()
  } catch (e) {
    toast.error('Failed to delete some items')
  }
}

const fetchData = async () => {
  loading.value = true // Ensure loading state is accurate
  await fetchProductsPaginated({
    ...serverOptions.value,
    ...filters,
    store_id: user.value?.store?.id
  })

  // Reset selection on page change
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
  <div class="flex flex-col md:flex-row w-full gap-4 md:gap-5">
    <div class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white animate-fade-in-up">
      <div class="flex flex-col gap-6">
        <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
          <img
            src="@/assets/images/icons/shopping-cart-blue.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </div>
        <div class="flex flex-col gap-[6px]">
          <p class="font-bold text-2xl md:text-4xl">{{ totalProductsSummary }}</p>
          <p class="font-medium text-sm md:text-lg text-custom-grey">Total Products</p>
        </div>
      </div>
    </div>
    <div
      class="flex flex-col w-full rounded-[20px] p-5 gap-6 bg-white animate-fade-in-up delay-100"
    >
      <div class="flex flex-col gap-6">
        <div class="flex size-[56px] bg-custom-blue/10 items-center justify-center rounded-full">
          <img
            src="@/assets/images/icons/presention-chart-blue.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </div>
        <div class="flex flex-col gap-[6px]">
          <p class="font-bold text-2xl md:text-4xl">
            {{ meta?.total_sold ? meta.total_sold.toLocaleString() : 0 }}
          </p>
          <p class="font-medium text-sm md:text-lg text-custom-grey">Total Sold</p>
        </div>
      </div>
    </div>
  </div>
  <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white animate-fade-in-up delay-200">
    <div class="header flex flex-col md:flex-row items-center justify-between gap-4 md:gap-0">
      <div class="flex flex-col gap-2 w-full md:w-auto items-start">
        <p class="font-bold text-xl">All Products</p>
        <div class="flex items-center gap-1">
          <img
            src="@/assets/images/icons/shopping-cart-grey.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
          <p class="font-semibold text-custom-grey">{{ meta?.total }} Total Products</p>
        </div>
      </div>
      <RouterLink
        v-if="user?.permissions?.includes('product-create')"
        :to="{ name: 'admin.product.create' }"
        class="flex h-14 w-full md:w-auto justify-center items-center rounded-full py-4 px-6 bg-custom-black gap-[6px] hover:bg-black/80 transition-300"
      >
        <span class="font-semibold text-lg text-white leading-none">Add New</span>
        <img
          src="@/assets/images/icons/add-circle-white.svg"
          class="flex size-6 shrink-0"
          alt="icon"
        />
      </RouterLink>
    </div>
    <div id="Filter" class="flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-4 w-full md:w-auto">
        <label
          class="cursor-pointer flex items-center gap-2 bg-white border border-custom-stroke rounded-2xl h-14 px-4 hover:border-custom-black transition-colors"
          title="Select All"
        >
          <input
            type="checkbox"
            :checked="allSelected"
            class="checkbox checkbox-primary rounded-lg size-5 border-2 border-gray-300 checked:bg-custom-blue checked:border-custom-blue transition-all"
            @change="toggleSelectAll"
          />
          <span class="font-semibold text-custom-black hidden md:block">All</span>
        </label>

        <form action="#" class="w-full md:w-auto">
          <label
            class="flex items-center w-full md:w-[320px] h-14 rounded-2xl p-4 gap-2 bg-white border border-custom-stroke focus-within:border-custom-black transition-300"
          >
            <img
              src="@/assets/images/icons/box-search-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
            <input
              v-model="filters.search"
              type="text"
              class="appearance-none w-full placeholder:text-custom-grey font-medium focus:outline-none"
              placeholder="Search product"
            />
          </label>
        </form>
      </div>

      <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
        <p class="font-medium text-custom-grey">Show</p>
        <label
          class="flex items-center h-14 rounded-2xl border border-custom-stroke py-4 px-5 pl-3 bg-white focus-within:border-custom-black transition-300"
        >
          <select
            id=""
            v-model="serverOptions.row_per_page"
            name=""
            class="text-custom-black font-medium appearance-none focus:outline-none p-2"
          >
            <option value="10" class="font-medium">10 Entries</option>
            <option value="20" class="font-medium">20 Entries</option>
            <option value="40" class="font-medium">40 Entries</option>
          </select>
          <img
            src="@/assets/images/icons/arrow-down-black.svg"
            class="flex size-6 shrink-0 -ml-1"
            alt="icon"
          />
        </label>
      </div>
    </div>
    <div id="List-Categories" class="flex flex-col gap-6 relative">
      <div id="List" class="flex flex-col gap-5 pb-20">
        <CardList
          v-for="product in products"
          v-if="!loading && products"
          :key="product.id"
          :item="product"
          :selected="selectedItems.includes(product.id)"
          @toggle-selection="toggleSelection"
          @delete="handleDelete"
        />
      </div>

      <!-- Floating Bulk Action Bar -->
      <div
        v-if="selectedItems.length > 0"
        class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-white shadow-2xl rounded-full px-6 py-3 flex items-center gap-4 border border-gray-100 z-50 animate-fade-in-up"
      >
        <span class="font-bold text-custom-black">{{ selectedItems.length }} Selected</span>
        <div class="h-6 w-[1px] bg-gray-200"></div>
        <button
          class="flex items-center gap-2 text-custom-red hover:bg-red-50 px-3 py-1 rounded-lg transition-colors"
          @click="bulkDelete"
        >
          <img src="@/assets/images/icons/trash-red.svg" class="size-5" />
          <span class="font-bold text-sm">Delete Selected</span>
        </button>
      </div>

      <Pagination :meta="meta" :server-options="serverOptions" />
    </div>
    <div
      v-if="products?.length === 0"
      id="Empty-State"
      class="flex flex-col flex-1 items-center justify-center gap-4"
    >
      <img src="@/assets/images/icons/note-remove-grey.svg" class="size-[52px]" alt="icon" />
      <div class="flex flex-col gap-1 items-center text-center">
        <p class="font-semibold text-custom-grey">Oops, you don't have any data yet</p>
      </div>
    </div>
  </div>
</template>
