<script setup>
import { useProductStore } from '@/stores/product'
import { useStoreStore } from '@/stores/store'
import { storeToRefs } from 'pinia'
import { onMounted, watch, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ProductCard from '@/components/card/ProductCard.vue'
import StoreCard from '@/components/card/StoreCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import SkeletonStoreCard from '@/components/skeleton/SkeletonStoreCard.vue'
import FilterSidebar from '@/components/App/FilterSidebar.vue'
import { useHead } from '@vueuse/head'
import { computed } from 'vue'

const route = useRoute()
const router = useRouter()
const productStore = useProductStore()
const storeStore = useStoreStore()

const searchQuery = ref('')

useHead({
  title: computed(() =>
    searchQuery.value ? `Pencarian: ${searchQuery.value} | Blukios` : 'Pencarian | Blukios'
  ),
  meta: [
    { name: 'description', content: computed(() => `Hasil pencarian untuk ${searchQuery.value} di Blukios.`) }
  ]
})

const { products, loading: loadingProducts } = storeToRefs(productStore)
const { stores, loading: loadingStores } = storeToRefs(storeStore)

const { fetchProducts } = productStore
const { fetchStores } = storeStore

const currentFilters = ref({})
const showFilters = ref(false)
const activeTab = ref('products')

const performSearch = async () => {
  const query = route.query.q
  searchQuery.value = query

  const filters = {
    min_price: route.query.min_price,
    max_price: route.query.max_price,
    condition: route.query.condition,
    city: route.query.city,
    min_rating: route.query.min_rating,
    stock_status: route.query.stock_status,
    created_since: route.query.created_since,
    product_category_id: route.query.product_category_id
  }
  currentFilters.value = filters

  if (query) {
    await Promise.all([
      fetchProducts({ search: query, limit: 12, ...filters }),
      fetchStores({ search: query, limit: 9 })
    ])
  } else {
    products.value = []
    stores.value = []
  }
}

const handleFilterChange = (newFilters) => {
  router.push({ query: { ...route.query, ...newFilters } })
}

onMounted(() => {
  performSearch()
})

watch(() => route.query, () => { performSearch() }, { deep: true })
</script>

<template>
  <!-- Hero Header -->
  <header class="relative bg-gradient-to-r from-slate-800 via-slate-900 to-slate-800 overflow-hidden">
    <div class="absolute inset-0 opacity-20">
      <div class="absolute top-0 left-1/4 size-64 bg-blue-500/30 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 right-1/4 size-48 bg-purple-500/20 rounded-full blur-3xl"></div>
    </div>
    <div class="relative w-full max-w-[1280px] px-4 md:px-[52px] mx-auto py-10 md:py-12">
      <div class="flex items-center gap-2 mb-4">
        <RouterLink :to="{ name: 'app.home' }" class="text-sm text-white/60 hover:text-white transition-colors">Beranda</RouterLink>
        <svg class="size-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <span class="text-sm text-white/80 font-medium">Hasil Pencarian</span>
      </div>
      <h1 class="font-extrabold text-2xl md:text-3xl text-white">
        Hasil pencarian "<span class="text-blue-400">{{ searchQuery }}</span>"
      </h1>
      <div class="flex items-center gap-4 mt-3">
        <span class="text-sm text-white/60">{{ products.length }} produk</span>
        <span class="text-white/30">&middot;</span>
        <span class="text-sm text-white/60">{{ stores.length }} toko</span>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full max-w-[1280px] px-4 md:px-[52px] mx-auto py-8 md:py-10 mb-16">
    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Mobile Filter Toggle -->
      <button
        class="flex lg:hidden items-center justify-between w-full p-4 bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 rounded-xl shadow-sm"
        @click="showFilters = !showFilters">
        <div class="flex items-center gap-2">
          <svg class="size-5 text-custom-black dark:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          <span class="font-bold text-custom-black dark:text-white">Filter</span>
        </div>
        <svg class="size-5 text-gray-400 transition-transform" :class="{ 'rotate-180': showFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <!-- Sidebar -->
      <aside class="shrink-0 w-full lg:w-auto" :class="{ 'hidden lg:block': !showFilters }">
        <FilterSidebar :initial-filters="currentFilters" @filter-change="handleFilterChange" />
      </aside>

      <!-- Content -->
      <div class="flex flex-col gap-8 flex-1 min-w-0">
        <!-- Tab Buttons -->
        <div class="flex items-center gap-2 border-b border-gray-200 dark:border-white/10">
          <button class="px-4 py-3 text-sm font-bold relative transition-colors"
            :class="activeTab === 'products' ? 'text-custom-blue' : 'text-custom-grey hover:text-custom-black dark:hover:text-white'"
            @click="activeTab = 'products'">
            Produk ({{ products.length }})
            <div v-if="activeTab === 'products'" class="absolute bottom-0 left-0 w-full h-[3px] bg-custom-blue rounded-t-full"></div>
          </button>
          <button class="px-4 py-3 text-sm font-bold relative transition-colors"
            :class="activeTab === 'stores' ? 'text-custom-blue' : 'text-custom-grey hover:text-custom-black dark:hover:text-white'"
            @click="activeTab = 'stores'">
            Toko ({{ stores.length }})
            <div v-if="activeTab === 'stores'" class="absolute bottom-0 left-0 w-full h-[3px] bg-custom-blue rounded-t-full"></div>
          </button>
        </div>

        <!-- Products Tab -->
        <section v-if="activeTab === 'products'">
          <div v-if="loadingProducts" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
            <SkeletonProductCard v-for="i in 12" :key="i" />
          </div>
          <div v-else-if="products.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
            <ProductCard v-for="product in products" :key="product.id" :item="product" />
          </div>
          <div v-else class="flex flex-col items-center justify-center py-16 text-center">
            <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
              <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <h2 class="font-bold text-lg text-custom-black dark:text-white">Tidak ada produk ditemukan</h2>
            <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">Coba periksa ejaan atau gunakan kata kunci lain</p>
          </div>
        </section>

        <!-- Stores Tab -->
        <section v-if="activeTab === 'stores'">
          <div v-if="loadingStores" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
            <SkeletonStoreCard v-for="i in 6" :key="i" />
          </div>
          <div v-else-if="stores.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
            <StoreCard v-for="store in stores" :key="store.id" :item="store" />
          </div>
          <div v-else class="flex flex-col items-center justify-center py-16 text-center">
            <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
              <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="font-bold text-lg text-custom-black dark:text-white">Tidak ada toko ditemukan</h2>
            <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">Coba kata kunci pencarian yang berbeda</p>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>
