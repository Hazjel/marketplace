<script setup>
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { onMounted, watch, ref } from 'vue'
import { useHead } from '@vueuse/head'

const productStore = useProductStore()
const { products, loading } = storeToRefs(productStore)
const { fetchProducts } = productStore
const route = useRoute()
const router = useRouter()

const sortBy = ref(route.query.sort || 'default')

useHead({
  title: 'Top Picks | Blukios',
  meta: [{ name: 'description', content: 'Discover our top picks for gadgets and accessories.' }]
})

const loadProducts = () => {
  const params = { limit: 100 }
  if (sortBy.value && sortBy.value !== 'default') {
    params.sort = sortBy.value
  }
  fetchProducts(params)
}

const handleSortChange = () => {
  router.replace({ query: { ...route.query, sort: sortBy.value } })
}

onMounted(() => {
  loadProducts()
})

watch(() => route.query, () => {
  sortBy.value = route.query.sort || 'default'
  loadProducts()
})
</script>

<template>
  <!-- Hero Header -->
  <header class="relative bg-gradient-to-br from-violet-600 via-purple-600 to-fuchsia-500 overflow-hidden">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute -top-20 -left-20 size-80 bg-white/20 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 right-0 size-96 bg-white/10 rounded-full blur-3xl"></div>
    </div>
    <div class="relative w-full max-w-[1280px] px-4 md:px-[75px] mx-auto py-10 md:py-14">
      <div class="flex items-center gap-2 mb-4">
        <RouterLink :to="{ name: 'app.home' }" class="text-sm text-white/70 hover:text-white transition-colors">Beranda</RouterLink>
        <svg class="size-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <span class="text-sm text-white font-medium">Semua Produk</span>
      </div>
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
          <h1 class="font-extrabold text-3xl md:text-4xl text-white">Top Picks</h1>
          <p class="text-white/70 mt-2 text-sm md:text-base">Produk pilihan terbaik untuk kamu</p>
        </div>
        <div class="size-14 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center">
          <svg class="size-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full max-w-[1280px] px-4 md:px-[75px] mx-auto py-8 md:py-10 mb-16">
    <!-- Controls -->
    <div class="flex items-center justify-between mb-8">
      <p class="text-sm text-custom-grey dark:text-gray-400">
        <span class="font-bold text-custom-black dark:text-white">{{ products.length }}</span> produk ditemukan
      </p>
      <select v-model="sortBy"
        class="h-10 px-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white focus:outline-none focus:border-custom-blue appearance-none cursor-pointer"
        @change="handleSortChange">
        <option value="default">Paling Sesuai</option>
        <option value="newest">Terbaru</option>
        <option value="price_low">Harga Terendah</option>
        <option value="price_high">Harga Tertinggi</option>
        <option value="sold">Terlaris</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading && products.length === 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-5">
      <SkeletonProductCard v-for="i in 15" :key="i" />
    </div>

    <!-- Products Grid -->
    <div v-else-if="products.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-5">
      <ProductCard v-for="product in products" :key="product.id" :item="product" />
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-center">
      <div class="size-24 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
        <svg class="size-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
      </div>
      <h2 class="font-bold text-xl text-custom-black dark:text-white">Belum ada produk</h2>
      <p class="text-custom-grey dark:text-gray-400 mt-1">Nantikan produk baru segera hadir</p>
      <RouterLink :to="{ name: 'app.home' }"
        class="mt-6 h-12 px-8 rounded-full bg-custom-blue text-white font-bold text-sm flex items-center justify-center hover:shadow-lg hover:shadow-blue-500/25 hover:-translate-y-0.5 transition-all">
        Kembali ke Beranda
      </RouterLink>
    </div>
  </main>
</template>
