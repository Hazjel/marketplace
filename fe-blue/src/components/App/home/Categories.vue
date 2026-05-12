<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'

const productCategoryStore = useProductCategoryStore()
const { productCategories } = storeToRefs(productCategoryStore)
const { fetchProductCategories } = productCategoryStore

onMounted(() => {
  fetchProductCategories({
    limit: 8,
    is_parent: true
  })
})

// Category emoji icons based on name
const getCategoryIcon = (name) => {
  const icons = {
    'Elektronik': '📱',
    'Fashion': '👕',
    'Kesehatan & Kecantikan': '💄',
    'Smartphone': '📱',
    'Laptop': '💻',
    'Aksesoris gadget': '🎧',
    'Pakaian pria': '👔',
    'Pakaian wanita': '👗',
    'Skincare': '🧴',
    'Suplemen': '💊',
  }
  return icons[name] || '🛍️'
}
</script>

<template>
  <section class="flex flex-col gap-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Kategori</h2>
      <RouterLink
        :to="{ name: 'app.all-categories' }"
        class="text-sm font-semibold text-custom-blue dark:text-blue-400 hover:underline"
      >
        Lihat Semua
      </RouterLink>
    </div>

    <!-- Category Grid -->
    <div class="grid grid-cols-4 md:grid-cols-8 gap-3 md:gap-4">
      <RouterLink
        v-for="category in productCategories"
        :key="category.id"
        :to="{ name: 'app.browse-category', params: { slug: category.slug } }"
        class="group flex flex-col items-center gap-2 p-3 rounded-xl hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
      >
        <!-- Icon Circle -->
        <div class="size-12 md:size-14 rounded-full bg-gray-100 dark:bg-white/10 flex items-center justify-center text-xl md:text-2xl group-hover:bg-blue-100 dark:group-hover:bg-blue-900/30 group-hover:scale-110 transition-all duration-200">
          <img
            v-if="category.image"
            :src="category.image"
            class="size-7 md:size-8 object-contain rounded-full"
            :alt="category.name"
          />
          <span v-else>{{ getCategoryIcon(category.name) }}</span>
        </div>
        <!-- Name -->
        <span class="text-xs md:text-sm font-medium text-gray-700 dark:text-gray-300 text-center leading-tight line-clamp-2 group-hover:text-custom-blue dark:group-hover:text-blue-400 transition-colors">
          {{ category.name }}
        </span>
        <!-- Count -->
        <span class="text-[10px] md:text-xs text-gray-400 dark:text-gray-500 font-medium">
          {{ category.product_count }} produk
        </span>
      </RouterLink>
    </div>
  </section>
</template>
