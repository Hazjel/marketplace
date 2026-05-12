<script setup>
import StoreCard from '@/components/card/StoreCard.vue'
import SkeletonStoreCard from '@/components/skeleton/SkeletonStoreCard.vue'
import { useStoreStore } from '@/stores/store'
import { storeToRefs } from 'pinia'
import { onMounted, ref, computed } from 'vue'

const storeStore = useStoreStore()
const { stores, loading } = storeToRefs(storeStore)
const { fetchStores } = storeStore

const searchQuery = ref('')
const sortBy = ref('default')

const filteredStores = computed(() => {
  let result = [...(stores.value || [])]

  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter((store) =>
      store.name?.toLowerCase().includes(query) ||
      store.address?.toLowerCase().includes(query)
    )
  }

  switch (sortBy.value) {
    case 'name':
      result.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
      break
    case 'followers':
      result.sort((a, b) => (b.followers_count || 0) - (a.followers_count || 0))
      break
    case 'products':
      result.sort((a, b) => (b.products_count || 0) - (a.products_count || 0))
      break
    default:
      break
  }

  return result
})

onMounted(() => {
  fetchStores({ limit: 100 })
})
</script>

<template>
  <!-- Hero Header -->
  <header class="relative bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-600 overflow-hidden">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute -top-20 -right-20 size-80 bg-white/20 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 left-0 size-64 bg-white/15 rounded-full blur-3xl"></div>
    </div>
    <div class="relative w-full max-w-[1280px] px-4 md:px-[75px] mx-auto py-10 md:py-14">
      <div class="flex items-center gap-2 mb-4">
        <RouterLink :to="{ name: 'app.home' }" class="text-sm text-white/70 hover:text-white transition-colors">Beranda</RouterLink>
        <svg class="size-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <span class="text-sm text-white font-medium">Semua Toko</span>
      </div>
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
          <h1 class="font-extrabold text-3xl md:text-4xl text-white">Penjual Terpercaya</h1>
          <p class="text-white/70 mt-2 text-sm md:text-base">{{ stores.length }} toko terverifikasi siap melayani kamu</p>
        </div>
        <div class="size-14 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center">
          <svg class="size-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full max-w-[1280px] px-4 md:px-[75px] mx-auto py-8 md:py-10 mb-16">
    <!-- Controls Bar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-8">
      <!-- Search -->
      <div class="relative flex-1 max-w-md">
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input v-model="searchQuery" type="text"
          class="w-full h-12 pl-12 pr-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/10 transition-all"
          placeholder="Cari toko..." />
      </div>

      <!-- Sort -->
      <select v-model="sortBy"
        class="h-12 px-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white focus:outline-none focus:border-custom-blue appearance-none cursor-pointer min-w-[160px]">
        <option value="default">Default</option>
        <option value="name">Nama A-Z</option>
        <option value="followers">Followers Terbanyak</option>
        <option value="products">Produk Terbanyak</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading && stores.length === 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
      <SkeletonStoreCard v-for="i in 6" :key="i" />
    </div>

    <!-- Store Grid -->
    <div v-else-if="filteredStores.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
      <StoreCard v-for="store in filteredStores" :key="store.id" :item="store" />
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-center">
      <div class="size-24 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
        <svg class="size-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h2 class="font-bold text-xl text-custom-black dark:text-white">
        {{ searchQuery ? 'Toko tidak ditemukan' : 'Belum ada toko' }}
      </h2>
      <p class="text-custom-grey dark:text-gray-400 mt-1">
        {{ searchQuery ? 'Coba kata kunci lain' : 'Nantikan penjual baru segera bergabung' }}
      </p>
      <RouterLink :to="{ name: 'app.home' }"
        class="mt-6 h-12 px-8 rounded-full bg-custom-blue text-white font-bold text-sm flex items-center justify-center gap-2 hover:shadow-lg hover:shadow-blue-500/25 hover:-translate-y-0.5 transition-all">
        Kembali ke Beranda
      </RouterLink>
    </div>
  </main>
</template>
