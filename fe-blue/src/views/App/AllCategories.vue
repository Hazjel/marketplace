<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'

const productCategoryStore = useProductCategoryStore()
const { productCategories, loading } = storeToRefs(productCategoryStore)
const { fetchProductCategories } = productCategoryStore

onMounted(() => {
  fetchProductCategories({
    limit: 100 // Fetch more/all for the view all page
  })
})
</script>

<template>
  <div
    class="flex flex-col gap-8 w-full max-w-[1280px] px-4 md:px-[75px] mx-auto mt-8 md:mt-10 mb-20 md:mb-24"
  >
    <div class="flex items-center justify-between">
      <h1 class="font-bold text-[32px] text-custom-black">All Categories</h1>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading && productCategories.length === 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-6">
      <div
        v-for="i in 10"
        :key="i"
        class="flex flex-col h-full rounded-[20px] ring-1 ring-custom-stroke dark:ring-white/10 py-8 px-6 items-center gap-6"
      >
        <div class="size-9 bg-gray-200 dark:bg-white/10 rounded-full animate-pulse"></div>
        <div class="flex flex-col items-center gap-2 w-full">
          <div class="h-4 w-20 bg-gray-200 dark:bg-white/10 rounded animate-pulse"></div>
          <div class="h-3 w-16 bg-gray-200 dark:bg-white/10 rounded animate-pulse"></div>
        </div>
      </div>
    </div>

    <!-- Category Grid -->
    <div v-else-if="productCategories.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-6">
      <RouterLink
        v-for="category in productCategories"
        :key="category.id"
        :to="{ name: 'app.browse-category', params: { slug: category.slug } }"
        class="group card"
      >
        <div
          class="flex flex-col h-full rounded-[20px] ring-1 ring-custom-stroke dark:ring-white/10 dark:bg-surface-card py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300"
        >
          <img :src="category.image" class="size-9" :alt="category.name" />
          <div class="flex flex-col items-center gap-1">
            <p class="font-bold text-xs capitalize text-center line-clamp-2 dark:text-white">{{ category.name }}</p>
            <p class="font-medium text-custom-grey leading-none">
              {{ category.product_count }} products
            </p>
          </div>
        </div>
      </RouterLink>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-center">
      <img src="@/assets/images/icons/box-search-grey.svg" class="size-20 opacity-50 mb-4" alt="No categories" />
      <h2 class="font-bold text-2xl text-custom-grey">No categories available</h2>
      <p class="text-custom-grey mt-1">Check back later for new categories.</p>
    </div>
  </div>
</template>
