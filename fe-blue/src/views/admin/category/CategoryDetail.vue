<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { RouterLink } from 'vue-router'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'

const toast = useToast()
const route = useRoute()

const productCategoryStore = useProductCategoryStore()
const { loading, error, success } = storeToRefs(productCategoryStore)
const { fetchProductCategoryById, deleteProductCategory } = productCategoryStore

const productCategory = ref({})

const fetchData = async () => {
  const response = await fetchProductCategoryById(route.params.id)

  productCategory.value = response
}
async function handleDelete(id) {
  await deleteProductCategory(id)

  fetchData()
}

onMounted(fetchData)

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
      <div class="flex items-center gap-4">
        <div class="flex size-12 items-center justify-center rounded-xl bg-white/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-white">Detail Kategori</h1>
          <p class="text-blue-100">Informasi lengkap dan sub-kategori</p>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="flex flex-col xl:flex-row gap-6">
      <!-- Category Info Card -->
      <section class="flex-1">
        <div class="rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm p-6">
          <!-- Category Header -->
          <div class="flex items-center gap-4 mb-6">
            <div class="flex size-[80px] shrink-0 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 p-4 ring-4 ring-indigo-100 dark:ring-indigo-900/30">
              <img :src="productCategory?.image" class="size-full object-contain" alt="icon" />
            </div>
            <div class="flex flex-col flex-1 min-w-0 gap-1.5">
              <p class="font-bold text-xl dark:text-white truncate">{{ productCategory?.name }}</p>
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                {{ productCategory?.tagline }}
              </p>
            </div>
          </div>

          <hr class="border-gray-100 dark:border-white/10 mb-5" />

          <!-- Stats -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex items-center gap-3 rounded-xl bg-blue-50 dark:bg-blue-900/10 p-4 border border-blue-100 dark:border-blue-800/30">
              <div class="flex size-11 shrink-0 rounded-lg bg-blue-100 dark:bg-blue-900/30 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
              </div>
              <div class="flex flex-col">
                <p class="font-bold text-lg dark:text-white">{{ productCategory?.product_count }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Produk</p>
              </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-purple-50 dark:bg-purple-900/10 p-4 border border-purple-100 dark:border-purple-800/30">
              <div class="flex size-11 shrink-0 rounded-lg bg-purple-100 dark:bg-purple-900/30 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                </svg>
              </div>
              <div class="flex flex-col">
                <p class="font-bold text-lg dark:text-white">{{ productCategory?.children_count }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Sub Kategori</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Subcategories -->
      <section class="flex flex-col w-full xl:w-[560px] shrink-0 rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm py-6 gap-5">
        <div class="flex items-center justify-between px-6">
          <div class="flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
              </svg>
            </div>
            <p class="font-bold text-lg dark:text-white">Sub Kategori</p>
          </div>
        </div>

        <div class="px-6">
          <RouterLink
            :to="{ name: 'admin.category.create', query: { parent_id: productCategory?.id } }"
            class="flex h-11 items-center justify-center rounded-xl py-2.5 px-5 bg-blue-600 hover:bg-blue-700 gap-2 transition-colors duration-200 w-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span class="font-semibold text-sm text-white">Tambah Sub Kategori Baru</span>
          </RouterLink>
        </div>

        <hr class="border-gray-100 dark:border-white/10" />

        <!-- Subcategory List -->
        <div id="List-Subcategory" class="flex flex-col gap-3 px-6">
          <template v-for="childrens in productCategory?.childrens">
            <div class="flex items-center gap-4 rounded-xl bg-gray-50 dark:bg-white/5 p-3 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors duration-200">
              <div class="flex size-14 shrink-0 rounded-xl bg-white dark:bg-surface-card items-center justify-center p-2.5 border border-gray-100 dark:border-white/10">
                <img :src="childrens.image ?? PlaceHolder" class="size-full object-contain" alt="icon" />
              </div>
              <div class="flex flex-col gap-0.5 flex-1 min-w-0">
                <p class="font-semibold text-sm dark:text-white truncate">{{ childrens?.name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ childrens?.product_count }} Produk
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  class="flex items-center justify-center size-9 shrink-0 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors duration-200"
                  @click="handleDelete(childrens.id)">
                  <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                  </svg>
                </button>
                <RouterLink
                  :to="{
                    name: 'admin.category.edit',
                    params: { id: childrens.id },
                    query: { parent_id: productCategory.id }
                  }"
                  class="flex items-center justify-center size-9 shrink-0 rounded-lg bg-gray-900 dark:bg-white/10 hover:bg-gray-800 dark:hover:bg-white/20 transition-colors duration-200">
                  <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                  </svg>
                </RouterLink>
              </div>
            </div>
          </template>

          <!-- Empty subcategories -->
          <div v-if="!productCategory?.childrens?.length" class="flex flex-col items-center justify-center py-10 gap-3">
            <div class="flex size-14 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
              </svg>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada sub kategori</p>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
