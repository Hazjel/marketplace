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
  <div class="flex flex-col flex-1 rounded-[20px] p-5 gap-6 bg-white">
    <div class="header flex items-center justify-between">
      <div class="flex flex-col gap-2">
        <p class="font-bold text-xl">All Categories</p>
        <div class="flex items-center gap-1">
          <img src="@/assets/images/icons/bag-grey.svg" class="flex size-6 shrink-0" alt="icon" />
          <p class="font-semibold text-custom-grey">
            {{ productCategories?.length }} Total Categories
          </p>
        </div>
      </div>
      <RouterLink
        :to="getRoute('category.create')"
        class="flex h-14 items-center rounded-full py-4 px-6 bg-custom-blue gap-[6px]"
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
      <form action="#" class="w-full md:w-auto">
        <label
          class="flex items-center w-full md:w-[370px] h-14 rounded-2xl p-4 gap-2 bg-white border border-custom-stroke focus-within:border-custom-black transition-300"
        >
          <img
            src="@/assets/images/icons/receipt-search-grey.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
          <input
            v-model="filters.search"
            type="text"
            class="appearance-none w-full placeholder:text-custom-grey font-medium focus:outline-none"
            placeholder="Search category"
          />
        </label>
      </form>
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
    <div id="List-Categories" class="flex flex-col gap-6">
      <div id="List" class="flex flex-col gap-5">
        <CardList
          v-for="category in productCategories"
          v-if="!loading && productCategories"
          :key="category.id"
          :item="category"
          @delete="handleDelete"
        />
      </div>
      <Pagination :meta="meta" :server-options="serverOptions" />
    </div>
    <div
      v-if="productCategories?.length === 0"
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
