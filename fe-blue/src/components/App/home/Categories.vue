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
import SectionHeader from '@/components/Molecule/SectionHeader.vue'

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
  <section class="flex flex-col gap-6 md:gap-8">
    <SectionHeader
      title="Kategori"
      :link="{ name: 'app.all-categories' }"
      link-text="Lihat Semua"
    />

    <!-- Category strip: kartu ikon putih ala HP, auto-fit biar tidak timpang saat sedikit -->
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3 md:gap-4">
      <RouterLink
        v-for="category in productCategories"
        :key="category.id"
        :to="{ name: 'app.browse-category', params: { slug: category.slug } }"
        class="group flex flex-col items-center gap-3 p-4 rounded-xl bg-white dark:bg-surface-card border border-gray-100 dark:border-white/5 hover:border-custom-blue/20 dark:hover:border-blue-400/30 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5"
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
