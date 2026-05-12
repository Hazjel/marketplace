<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { storeToRefs } from 'pinia'
import { onMounted, ref, computed } from 'vue'

const productCategoryStore = useProductCategoryStore()
const { productCategories, loading } = storeToRefs(productCategoryStore)
const { fetchProductCategories } = productCategoryStore

const searchQuery = ref('')

const filteredCategories = computed(() => {
  if (!searchQuery.value.trim()) return productCategories.value
  const query = searchQuery.value.toLowerCase()
  return productCategories.value.filter((cat) =>
    cat.name?.toLowerCase().includes(query)
  )
})

onMounted(() => {
  fetchProductCategories({ limit: 100 })
})
</script>

<template>
  <!-- Hero Header -->
  <header class="relative bg-gradient-to-br from-indigo-600 via-blue-600 to-cyan-500 overflow-hidden">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute -top-20 -left-20 size-80 bg-white/20 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 right-0 size-64 bg-white/15 rounded-full blur-3xl"></div>
    </div>
    <div class="relative w-full max-w-[1280px] px-4 md:px-[75px] mx-auto py-10 md:py-14">
      <div class="flex items-center gap-2 mb-4">
        <RouterLink :to="{ name: 'app.home' }" class="text-sm text-white/70 hover:text-white transition-colors">Beranda</RouterLink>
        <svg class="size-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <span class="text-sm text-white font-medium">Semua Kategori</span>
      </div>
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
          <h1 class="font-extrabold text-3xl md:text-4xl text-white">Semua Kategori</h1>
          <p class="text-white/70 mt-2 text-sm md:text-base">{{ productCategories.length }} kategori tersedia</p>
        </div>
        <div class="size-14 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center">
          <svg class="size-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
          </svg>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full max-w-[1280px] px-4 md:px-[75px] mx-auto py-8 md:py-10 mb-16">
    <!-- Search -->
    <div class="relative max-w-md mb-8">
      <svg class="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <input v-model="searchQuery" type="text"
        class="w-full h-12 pl-12 pr-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/10 transition-all"
        placeholder="Cari kategori..." />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading && productCategories.length === 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <div v-for="i in 10" :key="i" class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 p-6 flex flex-col items-center gap-4 animate-pulse">
        <div class="size-12 bg-gray-200 dark:bg-white/10 rounded-xl"></div>
        <div class="flex flex-col items-center gap-2 w-full">
          <div class="h-4 w-20 bg-gray-200 dark:bg-white/10 rounded-lg"></div>
          <div class="h-3 w-14 bg-gray-100 dark:bg-white/5 rounded-lg"></div>
        </div>
      </div>
    </div>

    <!-- Category Grid -->
    <div v-else-if="filteredCategories.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <RouterLink v-for="category in filteredCategories" :key="category.id"
        :to="{ name: 'app.browse-category', params: { slug: category.slug } }"
        class="group bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 p-6 flex flex-col items-center gap-4 hover:border-custom-blue/40 hover:shadow-xl hover:shadow-blue-500/5 hover:-translate-y-1 transition-all duration-300">
        <div class="size-14 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center group-hover:bg-custom-blue/10 group-hover:scale-110 transition-all duration-300">
          <img :src="category.image" class="size-8 object-contain" :alt="category.name" />
        </div>
        <div class="flex flex-col items-center gap-1 text-center">
          <p class="font-bold text-sm text-custom-black dark:text-white capitalize line-clamp-2 group-hover:text-custom-blue transition-colors">{{ category.name }}</p>
          <p class="text-xs text-custom-grey dark:text-gray-400">{{ category.product_count }} produk</p>
        </div>
      </RouterLink>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-center">
      <div class="size-24 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
        <svg class="size-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <h2 class="font-bold text-xl text-custom-black dark:text-white">
        {{ searchQuery ? 'Kategori tidak ditemukan' : 'Belum ada kategori' }}
      </h2>
      <p class="text-custom-grey dark:text-gray-400 mt-1">
        {{ searchQuery ? 'Coba kata kunci lain' : 'Nantikan kategori baru segera hadir' }}
      </p>
    </div>
  </main>
</template>
