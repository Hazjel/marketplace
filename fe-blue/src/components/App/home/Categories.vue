<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Smartphone,
  Laptop,
  Headphones,
  Shirt,
  Sparkles,
  Pill,
  ShoppingBag,
  Car,
  Home,
  Gamepad2,
  Baby,
  UtensilsCrossed,
  BookOpen,
  Dumbbell,
  PawPrint,
  Wrench,
  Music,
  Camera
} from 'lucide-vue-next'
import { markRaw } from 'vue'

const productCategoryStore = useProductCategoryStore()
const { productCategories } = storeToRefs(productCategoryStore)
const { fetchProductCategories } = productCategoryStore

onMounted(() => {
  fetchProductCategories({
    limit: 8,
    is_parent: true
  })
})

// Lucide icon mapping — covers common marketplace categories
// If a new category is added, it falls back to ShoppingBag icon
const categoryIconMap = {
  'elektronik': markRaw(Smartphone),
  'smartphone': markRaw(Smartphone),
  'laptop': markRaw(Laptop),
  'aksesoris gadget': markRaw(Headphones),
  'aksesoris': markRaw(Headphones),
  'fashion': markRaw(Shirt),
  'pakaian pria': markRaw(Shirt),
  'pakaian wanita': markRaw(Shirt),
  'kesehatan & kecantikan': markRaw(Sparkles),
  'kecantikan': markRaw(Sparkles),
  'skincare': markRaw(Sparkles),
  'suplemen': markRaw(Pill),
  'kesehatan': markRaw(Pill),
  'otomotif': markRaw(Car),
  'rumah tangga': markRaw(Home),
  'rumah & taman': markRaw(Home),
  'gaming': markRaw(Gamepad2),
  'ibu & bayi': markRaw(Baby),
  'makanan & minuman': markRaw(UtensilsCrossed),
  'buku': markRaw(BookOpen),
  'pendidikan': markRaw(BookOpen),
  'olahraga': markRaw(Dumbbell),
  'hewan peliharaan': markRaw(PawPrint),
  'perlengkapan': markRaw(Wrench),
  'hobi': markRaw(Music),
  'fotografi': markRaw(Camera),
}

const getCategoryIcon = (name) => {
  const key = name?.toLowerCase()
  return categoryIconMap[key] || markRaw(ShoppingBag)
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
        <div class="size-12 md:size-14 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40 group-hover:scale-110 transition-all duration-200">
          <!-- If category has uploaded image, use it -->
          <img
            v-if="category.image"
            :src="category.image"
            class="size-7 md:size-8 object-contain rounded-full"
            :alt="category.name"
          />
          <!-- Otherwise, use Lucide icon -->
          <component
            v-else
            :is="getCategoryIcon(category.name)"
            class="size-5 md:size-6 text-custom-blue dark:text-blue-400"
            :stroke-width="1.8"
          />
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
