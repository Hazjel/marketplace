<script setup>
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import { useWishlistStore } from '@/stores/wishlist'
import { useCartStore } from '@/stores/cart'
import { storeToRefs } from 'pinia'
import { onMounted, ref, computed } from 'vue'
import { useToast } from 'vue-toastification'

const wishlistStore = useWishlistStore()
const cartStore = useCartStore()
const toast = useToast()
const { items, loading } = storeToRefs(wishlistStore)
const { fetchWishlist, toggleWishlist } = wishlistStore

const searchQuery = ref('')
const viewMode = ref('grid') // 'grid' or 'list'
const sortBy = ref('newest') // 'newest', 'price-low', 'price-high', 'name'

const filteredItems = computed(() => {
  let result = [...(items.value || [])]

  // Search filter
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter((item) =>
      item.name?.toLowerCase().includes(query) ||
      item.store?.name?.toLowerCase().includes(query)
    )
  }

  // Sort
  switch (sortBy.value) {
    case 'price-low':
      result.sort((a, b) => (a.price || 0) - (b.price || 0))
      break
    case 'price-high':
      result.sort((a, b) => (b.price || 0) - (a.price || 0))
      break
    case 'name':
      result.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
      break
    default: // newest — maintain original order (most recently added)
      break
  }

  return result
})

const handleRemoveFromWishlist = async (product) => {
  await toggleWishlist(product)
}

const handleAddToCart = async (product) => {
  try {
    await cartStore.addToCart({
      ...product,
      store: product.store || {},
      quantity: 1
    })
    toast.success('Ditambahkan ke keranjang!')
  } catch {
    toast.error('Gagal menambahkan ke keranjang')
  }
}

onMounted(() => {
  fetchWishlist()
})
</script>

<template>
  <!-- Hero Header -->
  <header class="relative bg-gradient-to-br from-blue-600 via-blue-500 to-indigo-600 overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
      <div class="absolute top-0 left-0 size-64 bg-white/20 rounded-full -translate-x-1/2 -translate-y-1/2 blur-3xl"></div>
      <div class="absolute bottom-0 right-0 size-96 bg-white/10 rounded-full translate-x-1/3 translate-y-1/3 blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-[1280px] px-4 md:px-[52px] mx-auto py-10 md:py-14">
      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 mb-4">
        <RouterLink :to="{ name: 'app.home' }" class="text-sm text-white/70 hover:text-white transition-colors">
          Beranda
        </RouterLink>
        <svg class="size-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-sm text-white font-medium">Wishlist Saya</span>
      </div>

      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
          <h1 class="font-extrabold text-3xl md:text-4xl text-white">Wishlist Saya</h1>
          <p class="text-white/70 mt-2 text-sm md:text-base">
            {{ items.length }} produk yang kamu simpan
          </p>
        </div>

        <!-- Heart Icon -->
        <div class="size-16 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center">
          <svg class="size-8 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full max-w-[1280px] px-4 md:px-[52px] mx-auto py-8 md:py-10">
    <!-- Controls Bar -->
    <div v-if="items.length > 0" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-8">
      <!-- Search -->
      <div class="relative flex-1">
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input v-model="searchQuery" type="text"
          class="w-full h-12 pl-12 pr-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white placeholder:text-gray-400 focus:outline-none focus:border-custom-blue focus:ring-2 focus:ring-custom-blue/10 transition-all"
          placeholder="Cari di wishlist..." />
      </div>

      <!-- Sort -->
      <select v-model="sortBy"
        class="h-12 px-4 rounded-xl bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-sm font-medium text-custom-black dark:text-white focus:outline-none focus:border-custom-blue appearance-none cursor-pointer min-w-[160px]">
        <option value="newest">Terbaru</option>
        <option value="price-low">Harga Terendah</option>
        <option value="price-high">Harga Tertinggi</option>
        <option value="name">Nama A-Z</option>
      </select>

      <!-- View Toggle -->
      <div class="flex items-center bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 rounded-xl overflow-hidden h-12">
        <button class="size-12 flex items-center justify-center transition-colors"
          :class="viewMode === 'grid' ? 'bg-custom-blue text-white' : 'text-gray-400 hover:text-custom-blue'"
          @click="viewMode = 'grid'">
          <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
          </svg>
        </button>
        <button class="size-12 flex items-center justify-center transition-colors"
          :class="viewMode === 'list' ? 'bg-custom-blue text-white' : 'text-gray-400 hover:text-custom-blue'"
          @click="viewMode = 'list'">
          <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-5">
      <SkeletonProductCard v-for="i in 10" :key="i" />
    </div>

    <!-- Grid View -->
    <div v-else-if="filteredItems.length > 0 && viewMode === 'grid'"
      class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-5">
      <ProductCard v-for="product in filteredItems" :key="product.id" :item="product" />
    </div>

    <!-- List View -->
    <div v-else-if="filteredItems.length > 0 && viewMode === 'list'" class="flex flex-col gap-3">
      <div v-for="product in filteredItems" :key="product.id"
        class="bg-white dark:bg-surface-card rounded-2xl border border-gray-100 dark:border-white/10 p-4 flex items-center gap-4 hover:shadow-lg hover:border-custom-blue/20 transition-all duration-300 group">
        <!-- Product Image -->
        <RouterLink :to="{ name: 'app.product-detail', params: { slug: product.slug } }"
          class="size-20 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 p-2 flex items-center justify-center overflow-hidden">
          <img :src="product.thumbnail" class="size-full object-contain group-hover:scale-105 transition-transform duration-300" :alt="product.name" />
        </RouterLink>

        <!-- Product Info -->
        <div class="flex-1 min-w-0">
          <RouterLink :to="{ name: 'app.product-detail', params: { slug: product.slug } }">
            <h3 class="font-semibold text-sm text-custom-black dark:text-white line-clamp-1 group-hover:text-custom-blue transition-colors">
              {{ product.name }}
            </h3>
          </RouterLink>
          <p class="font-bold text-base text-custom-blue dark:text-blue-400 mt-1">
            Rp {{ product.price?.toLocaleString('id-ID') }}
          </p>
          <div class="flex items-center gap-2 mt-1">
            <span class="text-xs text-custom-grey dark:text-gray-400">{{ product.store?.name || 'Store' }}</span>
            <span class="text-xs text-gray-300">&middot;</span>
            <span class="text-xs text-custom-grey dark:text-gray-400">{{ product.total_sold || 0 }} terjual</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 shrink-0">
          <button @click="handleAddToCart(product)"
            class="h-10 px-4 rounded-xl bg-custom-blue text-white text-xs font-bold hover:bg-blue-700 transition-colors flex items-center gap-1.5"
            :disabled="product.stock <= 0">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span class="hidden sm:inline">Keranjang</span>
          </button>
          <button @click="handleRemoveFromWishlist(product)"
            class="size-10 rounded-xl border border-red-200 dark:border-red-900/30 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors flex items-center justify-center">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading && items.length === 0" class="flex flex-col items-center justify-center min-h-[450px] gap-6">
      <div class="relative">
        <div class="size-28 rounded-full bg-gradient-to-br from-pink-50 to-red-50 dark:from-pink-900/20 dark:to-red-900/20 flex items-center justify-center">
          <svg class="size-14 text-pink-300 dark:text-pink-500" fill="currentColor" viewBox="0 0 24 24">
            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
        </div>
        <div class="absolute -bottom-1 -right-1 size-8 rounded-full bg-white dark:bg-surface-card border-2 border-gray-100 dark:border-white/10 flex items-center justify-center">
          <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
        </div>
      </div>
      <div class="text-center">
        <h2 class="font-bold text-xl text-custom-black dark:text-white">Wishlist masih kosong</h2>
        <p class="text-custom-grey dark:text-gray-400 mt-2 max-w-sm">
          Temukan produk yang kamu suka dan simpan di sini untuk dibeli nanti
        </p>
      </div>
      <RouterLink :to="{ name: 'app.home' }"
        class="h-12 px-8 rounded-full bg-custom-blue text-white font-bold text-sm flex items-center justify-center gap-2 hover:shadow-lg hover:shadow-blue-500/25 hover:-translate-y-0.5 transition-all">
        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        Mulai Belanja
      </RouterLink>
    </div>

    <!-- No Search Results -->
    <div v-else-if="!loading && items.length > 0 && filteredItems.length === 0" class="flex flex-col items-center justify-center py-16">
      <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
        <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <p class="font-bold text-lg text-custom-black dark:text-white">Tidak ditemukan</p>
      <p class="text-sm text-custom-grey dark:text-gray-400 mt-1">Coba ubah kata kunci pencarian</p>
    </div>
  </main>
</template>
