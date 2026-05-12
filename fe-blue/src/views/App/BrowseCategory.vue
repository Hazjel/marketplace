<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import FilterSidebar from '@/components/App/FilterSidebar.vue'
import { useHead } from '@vueuse/head'
import { computed } from 'vue'

const route = useRoute()
const productCategory = ref({})

useHead({
  title: computed(() =>
    productCategory.value?.name ? `${productCategory.value.name} | Blukios` : 'Category | Blukios'
  ),
  meta: [
    {
      name: 'description',
      content: computed(
        () => `Explore high quality ${productCategory.value?.name || 'products'} on Blukios. Authenticity Guaranteed.`
      )
    }
  ]
})

const productCategoryStore = useProductCategoryStore()
const { fetchProductCategoryBySlug } = productCategoryStore

const productStore = useProductStore()
const { products, loading: loadingProducts, meta } = storeToRefs(productStore)
const { fetchProductsPaginated, loadMoreProducts } = productStore

const showFilters = ref(false)
const currentFilters = ref({})

const fetchProductCategory = async () => {
  const response = await fetchProductCategoryBySlug(route.params.slug)
  productCategory.value = response
}

const fetchProducts = async (filters = {}) => {
  if (!productCategory.value.id) return
  await fetchProductsPaginated({
    product_category_id: productCategory.value.id,
    row_per_page: 12,
    ...filters
  })
}

const handleLoadMore = async () => {
  await loadMoreProducts({
    product_category_id: productCategory.value.id,
    row_per_page: 8,
    page: meta.value.current_page + 1,
    ...currentFilters.value
  })
}

const handleFilterChange = (newFilters) => {
  currentFilters.value = newFilters
  fetchProducts(newFilters)
}

onMounted(async () => {
  await fetchProductCategory()
  await fetchProducts()
})

watch(
  () => route.params.slug,
  async () => {
    await fetchProductCategory()
    await fetchProducts(currentFilters.value)
  }
)
</script>

<template>
  <!-- Hero Header -->
  <header class="relative bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-600 overflow-hidden">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute -top-20 -left-20 size-80 bg-white/20 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 right-0 size-64 bg-white/15 rounded-full blur-3xl"></div>
    </div>
    <div class="relative w-full max-w-[1280px] px-4 md:px-[52px] mx-auto py-10 md:py-14">
      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 mb-4">
        <RouterLink :to="{ name: 'app.home' }" class="text-sm text-white/70 hover:text-white transition-colors">Beranda</RouterLink>
        <svg class="size-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <RouterLink :to="{ name: 'app.all-categories' }" class="text-sm text-white/70 hover:text-white transition-colors">Kategori</RouterLink>
        <svg class="size-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <span class="text-sm text-white font-medium">{{ productCategory?.name }}</span>
      </div>

      <div class="flex flex-col md:flex-row items-start md:items-end justify-between gap-4">
        <div>
          <h1 class="font-extrabold text-3xl md:text-4xl text-white capitalize">{{ productCategory?.name || 'Kategori' }}</h1>
          <div class="flex items-center gap-4 mt-3">
            <div class="flex items-center gap-1.5">
              <svg class="size-4 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
              <span class="text-sm text-white/70">{{ productCategory?.product_count || 0 }} Produk</span>
            </div>
            <div class="flex items-center gap-1.5">
              <svg class="size-4 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              <span class="text-sm text-white/70">Keaslian Terjamin</span>
            </div>
          </div>
        </div>
        <div v-if="productCategory?.image" class="size-16 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center p-2">
          <img :src="productCategory.image" class="size-full object-contain" :alt="productCategory.name" />
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full max-w-[1280px] px-4 md:px-[52px] mx-auto py-8 md:py-10 mb-16">
    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Mobile Filter Toggle -->
      <button
        class="flex lg:hidden items-center justify-between w-full p-4 bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 rounded-xl"
        @click="showFilters = !showFilters">
        <div class="flex items-center gap-2">
          <svg class="size-5 text-custom-black dark:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          <span class="font-bold text-custom-black dark:text-white">Filter Produk</span>
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
        <!-- Results info -->
        <div class="flex items-center justify-between">
          <p class="text-sm text-custom-grey dark:text-gray-400">
            <span class="font-bold text-custom-black dark:text-white">{{ products.length }}</span> produk ditemukan
          </p>
        </div>

        <!-- Loading -->
        <div v-if="loadingProducts && products.length === 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
          <SkeletonProductCard v-for="i in 12" :key="i" />
        </div>

        <!-- Products Grid -->
        <div v-else-if="products.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5">
          <ProductCard v-for="product in products" :key="product.id" :item="product" />
        </div>

        <!-- Empty State -->
        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
          <div class="size-24 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
            <svg class="size-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <h2 class="font-bold text-xl text-custom-black dark:text-white">Belum ada produk di kategori ini</h2>
          <p class="text-custom-grey dark:text-gray-400 mt-1">Coba cek kategori lain atau kembali ke beranda</p>
          <RouterLink :to="{ name: 'app.home' }"
            class="mt-6 h-12 px-8 rounded-full bg-custom-blue text-white font-bold text-sm flex items-center justify-center hover:shadow-lg hover:shadow-blue-500/25 hover:-translate-y-0.5 transition-all">
            Ke Beranda
          </RouterLink>
        </div>

        <!-- Load More -->
        <button v-if="meta.current_page < meta.last_page && products.length > 0"
          class="mx-auto h-12 px-8 rounded-full bg-white dark:bg-surface-card border-2 border-custom-blue text-custom-blue font-bold text-sm flex items-center justify-center gap-2 hover:bg-custom-blue hover:text-white hover:shadow-lg hover:shadow-blue-500/20 transition-all"
          @click="handleLoadMore">
          <span>Muat Lebih Banyak</span>
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>
    </div>
  </main>
</template>
