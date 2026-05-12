<script setup>
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import ReviewCard from '@/components/card/ReviewCard.vue'
import StoreHeader from '@/components/store/StoreHeader.vue'
import { useProductStore } from '@/stores/product'
import { useStoreStore } from '@/stores/store'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@vueuse/head'
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()

const storeStore = useStoreStore()
const { store, reviews } = storeToRefs(storeStore)
const { fetchStoreByUsername, followStore, unfollowStore, checkFollowStatus, fetchStoreReviews } =
  storeStore

const productStore = useProductStore()
const { products, loading: loadingProducts, storeCategories } = storeToRefs(productStore)
const { fetchProducts, fetchCategoriesByStore } = productStore

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

useHead({
  title: computed(() =>
    store.value?.name ? `${store.value.name} | Blukios` : 'Store Detail | Blukios'
  ),
  meta: [
    {
      name: 'description',
      content: computed(
        () => `Visit ${store.value?.name} on Blukios. ${store.value?.address || ''}`
      )
    },
    { property: 'og:title', content: computed(() => store.value?.name || 'Store Detail') },
    { property: 'og:image', content: computed(() => store.value?.logo || '') }
  ]
})

const isFollowing = ref(false)
const activeTab = ref('products')
const selectedCategory = ref(null)
const selectedSort = ref('default')

const fetchStore = async () => {
  const response = await fetchStoreByUsername(route.params.username)
  store.value = response

  if (user.value && store.value?.id) {
    isFollowing.value = await checkFollowStatus(store.value.id)
  }
}

const handleSortChange = () => {
  let sortParams = {}
  switch (selectedSort.value) {
    case 'newest':
      sortParams = { sort_by: 'created_at', sort_direction: 'desc' }
      break
    case 'price_low':
      sortParams = { sort_by: 'price', sort_direction: 'asc' }
      break
    case 'price_high':
      sortParams = { sort_by: 'price', sort_direction: 'desc' }
      break
    case 'sold':
      sortParams = { sort_by: 'sold', sort_direction: 'desc' }
      break
    default:
      sortParams = {}
  }

  const params = {
    limit: 12,
    store_id: store.value.id,
    product_category_id: selectedCategory.value ? selectedCategory.value.id : null,
    ...sortParams
  }

  fetchProducts(params)
}

const handleFollow = async () => {
  if (!user.value) {
    return router.push({ name: 'auth.login' })
  }
  try {
    await followStore(store.value.id)
    isFollowing.value = true
    store.value.followers_count = (store.value.followers_count || 0) + 1
  } catch (error) {
    console.error('Follow failed', error)
  }
}

const handleUnfollow = async () => {
  try {
    await unfollowStore(store.value.id)
    isFollowing.value = false
    store.value.followers_count = Math.max(0, (store.value.followers_count || 0) - 1)
  } catch (error) {
    console.error('Unfollow failed', error)
  }
}

const handleCategoryFilter = (category) => {
  selectedCategory.value = category
  const params = {
    limit: 12,
    store_id: store.value.id,
    product_category_id: category ? category.id : null
  }
  fetchProducts(params)
}

watch(activeTab, (newTab) => {
  if (newTab === 'reviews' && reviews.value.length === 0) {
    fetchStoreReviews(route.params.username)
  }
})

onMounted(async () => {
  reviews.value = []
  await fetchStore()

  fetchProducts({
    limit: 12,
    store_id: store.value.id,
    random: true
  })

  fetchCategoriesByStore(route.params.username)
})
</script>

<template>
  <!-- Breadcrumb Header -->
  <header class="bg-white dark:bg-surface-card border-b border-gray-100 dark:border-white/10">
    <div class="w-full max-w-[1280px] px-4 md:px-[52px] mx-auto py-4">
      <div class="flex items-center gap-2 text-sm">
        <RouterLink :to="{ name: 'app.home' }" class="text-custom-grey hover:text-custom-blue transition-colors">Beranda</RouterLink>
        <svg class="size-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <RouterLink :to="{ name: 'app.all-stores' }" class="text-custom-grey hover:text-custom-blue transition-colors">Toko</RouterLink>
        <svg class="size-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <span class="font-semibold text-custom-blue">{{ store?.name }}</span>
      </div>
    </div>
  </header>

  <main class="w-full max-w-[1280px] px-4 md:px-[52px] mx-auto py-6 md:py-8 mb-16">
    <!-- Store Header -->
    <section class="mb-8">
      <StoreHeader :store="store" :is-following="isFollowing" @follow="handleFollow" @unfollow="handleUnfollow" />
    </section>

    <!-- Promo Banners -->
    <section class="mb-8">
      <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4 md:mx-0 md:px-0 hide-scrollbar">
        <div class="w-[280px] md:w-[320px] shrink-0 overflow-hidden rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-lg transition-shadow">
          <img src="@/assets/images/thumbnails/promo-potrait-1-small.png" class="size-full object-cover" alt="Store promo banner" />
        </div>
        <div class="w-[280px] md:w-[320px] shrink-0 overflow-hidden rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-lg transition-shadow">
          <img src="@/assets/images/thumbnails/promo-potrait-2-small.png" class="size-full object-cover" alt="Store promo banner" />
        </div>
      </div>
    </section>

    <!-- Tabs Navigation -->
    <div class="sticky top-0 z-30 bg-white/95 dark:bg-surface-card/95 backdrop-blur-sm border-b border-gray-200 dark:border-white/10 -mx-4 px-4 md:mx-0 md:px-0 mb-8">
      <div class="flex items-center gap-1">
        <button v-for="tab in [
          { key: 'home', label: 'Beranda', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
          { key: 'products', label: 'Produk', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
          { key: 'reviews', label: 'Ulasan', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' }
        ]" :key="tab.key"
          class="flex items-center gap-2 px-5 py-4 font-semibold text-sm transition-all relative"
          :class="activeTab === tab.key ? 'text-custom-blue' : 'text-custom-grey dark:text-gray-400 hover:text-custom-black dark:hover:text-white'"
          @click="activeTab = tab.key">
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
          </svg>
          {{ tab.label }}
          <div v-if="activeTab === tab.key" class="absolute bottom-0 left-0 w-full h-[3px] bg-custom-blue rounded-t-full"></div>
        </button>
      </div>
    </div>

    <!-- Tab Contents -->
    <div class="min-h-[500px]">
      <!-- Home Tab -->
      <section v-if="activeTab === 'home'" class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
          <h2 class="font-bold text-xl text-custom-black dark:text-white">Produk Unggulan</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-5">
          <template v-if="loadingProducts">
            <SkeletonProductCard v-for="i in 5" :key="i" />
          </template>
          <template v-else>
            <ProductCard v-for="product in products.slice(0, 5)" :key="product.id" :item="product" />
          </template>
        </div>

        <div v-if="!loadingProducts && products.length === 0" class="flex flex-col items-center justify-center py-16">
          <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
            <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <p class="font-bold text-lg text-custom-black dark:text-white">Belum ada produk</p>
          <p class="text-sm text-custom-grey dark:text-gray-400">Toko ini belum menambahkan produk</p>
        </div>
      </section>

      <!-- Products Tab -->
      <section v-if="activeTab === 'products'" class="flex flex-col md:flex-row gap-8">
        <!-- Sidebar Filter (Desktop) -->
        <aside class="hidden md:flex flex-col w-60 shrink-0">
          <div class="rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-surface-card p-5 flex flex-col gap-4 sticky top-20">
            <h3 class="font-bold text-sm text-custom-black dark:text-white uppercase tracking-wider">Etalase Toko</h3>
            <div class="flex flex-col gap-0.5">
              <button
                class="text-left px-3 py-2.5 rounded-xl text-sm font-medium transition-all"
                :class="!selectedCategory ? 'bg-custom-blue/10 text-custom-blue font-semibold' : 'text-custom-grey dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 hover:text-custom-black dark:hover:text-white'"
                @click="handleCategoryFilter(null)">
                Semua Produk
              </button>
              <button v-for="category in storeCategories" :key="category.id"
                class="text-left px-3 py-2.5 rounded-xl text-sm font-medium transition-all"
                :class="selectedCategory?.id === category.id ? 'bg-custom-blue/10 text-custom-blue font-semibold' : 'text-custom-grey dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 hover:text-custom-black dark:hover:text-white'"
                @click="handleCategoryFilter(category)">
                {{ category.name }}
                <span class="text-xs text-gray-400 ml-1">({{ category.products_count }})</span>
              </button>
            </div>
          </div>
        </aside>

        <!-- Product Grid & Sorting -->
        <div class="flex flex-col w-full gap-5">
          <!-- Mobile Filter (Horizontal) -->
          <div class="md:hidden flex overflow-x-auto pb-2 -mx-4 px-4 gap-2 hide-scrollbar">
            <button
              class="whitespace-nowrap px-4 py-2 rounded-full text-sm font-semibold transition-all"
              :class="!selectedCategory ? 'bg-custom-blue text-white shadow-md shadow-blue-500/20' : 'bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-custom-grey'"
              @click="handleCategoryFilter(null)">
              Semua
            </button>
            <button v-for="category in storeCategories" :key="category.id"
              class="whitespace-nowrap px-4 py-2 rounded-full text-sm font-semibold transition-all"
              :class="selectedCategory?.id === category.id ? 'bg-custom-blue text-white shadow-md shadow-blue-500/20' : 'bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 text-custom-grey'"
              @click="handleCategoryFilter(category)">
              {{ category.name }}
            </button>
          </div>

          <!-- Sorting Bar -->
          <div class="flex items-center justify-between bg-white dark:bg-surface-card rounded-xl border border-gray-100 dark:border-white/10 px-4 py-3">
            <p class="text-sm text-custom-grey dark:text-gray-400">
              <span class="font-bold text-custom-black dark:text-white">{{ products.length }}</span> produk
            </p>
            <select v-model="selectedSort"
              class="bg-transparent text-sm font-bold text-custom-black dark:text-white focus:outline-none cursor-pointer appearance-none"
              @change="handleSortChange">
              <option value="default">Paling Sesuai</option>
              <option value="newest">Terbaru</option>
              <option value="price_low">Harga Terendah</option>
              <option value="price_high">Harga Tertinggi</option>
              <option value="sold">Terlaris</option>
            </select>
          </div>

          <!-- Products Grid -->
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
            <template v-if="loadingProducts">
              <SkeletonProductCard v-for="n in 8" :key="n" />
            </template>
            <ProductCard v-for="product in products" v-else :key="product.id" :item="product" />
          </div>

          <div v-if="!loadingProducts && products.length === 0"
            class="py-16 flex flex-col items-center justify-center text-center">
            <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
              <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </div>
            <h3 class="font-bold text-lg text-custom-black dark:text-white">Belum ada produk</h3>
            <p class="text-sm text-custom-grey dark:text-gray-400">Toko ini belum memiliki produk di etalase ini.</p>
          </div>
        </div>
      </section>

      <!-- Reviews Tab -->
      <section v-if="activeTab === 'reviews'" class="flex flex-col gap-6">
        <div v-if="reviews.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <ReviewCard v-for="review in reviews" :key="review.id" :review="review" />
        </div>

        <div v-else class="flex flex-col items-center justify-center py-16 text-center">
          <div class="size-20 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center mb-4">
            <svg class="size-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
          </div>
          <h3 class="font-bold text-lg text-custom-black dark:text-white">Belum ada ulasan</h3>
          <p class="text-sm text-custom-grey dark:text-gray-400">Toko ini belum memiliki ulasan dari pembeli</p>
        </div>
      </section>
    </div>
  </main>
</template>
