<script setup>
import 'swiper/swiper-bundle.css'

import Banner from '@/components/App/home/Banner.vue'
import Categories from '@/components/App/home/Categories.vue'
import Chatbot from '@/components/App/Chatbot.vue'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'

import { useHead } from '@vueuse/head'
import { ref, onMounted, onUnmounted } from 'vue'
import { useProductStore } from '@/stores/product'

useHead({
  title: 'Blukios — Belanja Semua Kebutuhanmu',
  meta: [
    {
      name: 'description',
      content:
        'Blukios adalah marketplace multi-vendor terpercaya. Belanja gadget, fashion, skincare, dan semua kebutuhanmu dari seller terpercaya.'
    }
  ]
})

// Lazy load / infinite scroll products
const productStore = useProductStore()
const products = ref([])
const loading = ref(false)
const initialLoading = ref(true)
const page = ref(1)
const hasMore = ref(true)
const observer = ref(null)
const loadTrigger = ref(null)

const loadProducts = async () => {
  if (loading.value || !hasMore.value) return

  loading.value = true
  try {
    await productStore.loadMoreProducts({
      page: page.value,
      row_per_page: 30
    })

    // Sync from store
    products.value = [...productStore.products]

    // Check if there are more pages
    if (productStore.meta.current_page >= productStore.meta.last_page) {
      hasMore.value = false
    } else {
      page.value++
    }
  } catch (e) {
    console.error('Failed to load products', e)
  } finally {
    loading.value = false
    initialLoading.value = false
  }
}

// Intersection Observer for infinite scroll
const setupObserver = () => {
  observer.value = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting && hasMore.value && !loading.value) {
        loadProducts()
      }
    },
    { rootMargin: '200px' }
  )

  if (loadTrigger.value) {
    observer.value.observe(loadTrigger.value)
  }
}

onMounted(async () => {
  // Reset store products for fresh load
  productStore.products = []
  productStore.meta = { current_page: 1, last_page: 1, per_page: 30, total: 0 }

  await loadProducts()
  setupObserver()
})

onUnmounted(() => {
  if (observer.value) {
    observer.value.disconnect()
  }
})
</script>

<template>
  <!-- Hero Banner -->
  <Banner />

  <!-- Main Content -->
  <main class="flex flex-col w-full max-w-[1280px] px-4 lg:px-6 mx-auto mt-6 md:mt-10 mb-20 md:mb-24 gap-8 md:gap-10">
    <!-- Categories Section -->
    <Categories />

    <!-- Products Section (Lazy Load / Infinite Scroll) -->
    <section class="flex flex-col gap-5">
      <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Produk Untukmu</h2>

      <!-- Initial Loading -->
      <div
        v-if="initialLoading"
        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4"
      >
        <SkeletonProductCard v-for="i in 12" :key="i" />
      </div>

      <!-- Products Grid -->
      <div
        v-else
        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4"
      >
        <ProductCard
          v-for="product in products"
          :key="product.id"
          :item="product"
        />

        <!-- Loading more skeletons -->
        <template v-if="loading && !initialLoading">
          <SkeletonProductCard v-for="i in 6" :key="'loading-' + i" />
        </template>
      </div>

      <!-- Load trigger (invisible element at bottom) -->
      <div ref="loadTrigger" class="h-1 w-full"></div>

      <!-- End of products message -->
      <div v-if="!hasMore && products.length > 0" class="flex justify-center py-6">
        <p class="text-sm text-gray-400 dark:text-gray-500">Kamu sudah melihat semua produk</p>
      </div>

      <!-- Empty state -->
      <div v-if="!initialLoading && products.length === 0" class="flex flex-col items-center justify-center py-12 gap-3">
        <svg class="size-16 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <p class="text-gray-400 dark:text-gray-500 font-medium">Belum ada produk tersedia</p>
      </div>
    </section>
  </main>

  <!-- Chatbot -->
  <Chatbot />
</template>
