<script setup>
import 'swiper/swiper-bundle.css'

import Banner from '@/components/App/home/Banner.vue'
import Categories from '@/components/App/home/Categories.vue'
import Recommended from '@/components/App/home/Recommended.vue'
import Stores from '@/components/App/home/Stores.vue'
import Chatbot from '@/components/App/Chatbot.vue'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import Container from '@/components/Molecule/Container.vue'
import SectionHeader from '@/components/Molecule/SectionHeader.vue'
import { RouterLink } from 'vue-router'

import { useHead } from '@vueuse/head'
import { ref, onMounted } from 'vue'
import { useProductStore } from '@/stores/product'
import { logger } from '@/utils/logger'

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
    logger.error('Failed to load products', e)
  } finally {
    loading.value = false
    initialLoading.value = false
  }
}

onMounted(async () => {
  // Reset store products for fresh load
  productStore.products = []
  productStore.meta = { current_page: 1, last_page: 1, per_page: 30, total: 0 }

  await loadProducts()
})
</script>

<template>
  <!-- Hero HP: ink slab + chevron biru + headline display + CTA -->
  <div class="relative overflow-hidden bg-[#1a1a1a] text-white">
    <div
      class="pointer-events-none absolute -right-16 -top-10 hidden h-[150%] w-40 -skew-x-12 bg-primary opacity-90 md:block"
    ></div>
    <div
      class="pointer-events-none absolute right-28 -top-10 hidden h-[150%] w-24 -skew-x-12 bg-primary-bright opacity-40 md:block"
    ></div>
    <Container class="relative py-14 md:py-24">
      <div class="flex max-w-2xl flex-col gap-5">
        <h1 class="text-4xl font-medium leading-tight md:text-5xl lg:text-6xl">
          Semua kebutuhan gadget & elektronikmu, satu tempat.
        </h1>
        <p class="max-w-xl text-base text-white/60 md:text-lg">
          Belanja aman dari ribuan seller terpercaya. Harga bersaing, pengiriman cepat.
        </p>
        <div class="mt-2 flex flex-wrap gap-3">
          <RouterLink
            :to="{ name: 'app.all-products' }"
            class="flex h-12 items-center justify-center rounded-md bg-custom-blue px-6 font-medium text-white transition-colors hover:bg-primary-deep"
          >
            Mulai Belanja
          </RouterLink>
          <RouterLink
            :to="{ name: 'app.all-categories' }"
            class="flex h-12 items-center justify-center rounded-md border border-white/20 px-6 font-medium text-white transition-colors hover:bg-white/10"
          >
            Jelajah Kategori
          </RouterLink>
        </div>
      </div>
    </Container>
  </div>

  <!-- Main Content -->
  <Container as="main" class="flex flex-col mt-12 md:mt-20 mb-20 md:mb-28 gap-16 md:gap-24">
    <!-- Banner besar + side promo -->
    <Banner />

    <!-- Categories Section -->
    <Categories />

    <!-- Toko Official -->
    <Stores />

    <!-- Rekomendasi (sistem rekomendasi / personalized) -->
    <Recommended />

    <!-- Katalog lengkap (infinite scroll) -->
    <section class="flex flex-col gap-6 md:gap-8">
      <SectionHeader
        title="Jelajah Semua Produk"
        :link="{ name: 'app.all-products' }"
        link-text="Lihat Semua"
      />

      <!-- Initial Loading -->
      <div
        v-if="initialLoading"
        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6"
      >
        <SkeletonProductCard v-for="i in 10" :key="i" />
      </div>

      <!-- Products Grid -->
      <div
        v-else
        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6"
      >
        <ProductCard
          v-for="product in products"
          :key="product.id"
          :item="product"
        />

        <!-- Loading more skeletons -->
        <template v-if="loading && !initialLoading">
          <SkeletonProductCard v-for="i in 5" :key="'loading-' + i" />
        </template>
      </div>

      <!-- Load more button -->
      <div v-if="hasMore && products.length > 0" class="flex justify-center py-4">
        <button
          :disabled="loading"
          class="px-6 py-2.5 rounded-md border border-custom-blue text-custom-blue dark:border-blue-400 dark:text-blue-400 font-medium text-sm hover:bg-custom-blue hover:text-white dark:hover:bg-blue-400 dark:hover:text-gray-900 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          @click="loadProducts"
        >
          {{ loading ? 'Memuat...' : 'Lihat Lebih Banyak' }}
        </button>
      </div>

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
  </Container>

  <!-- Closing band ink: ajakan jadi seller (pola help-band HP) -->
  <div class="relative overflow-hidden bg-[#1a1a1a] text-white">
    <div
      class="pointer-events-none absolute -left-16 -bottom-10 hidden h-[150%] w-40 -skew-x-12 bg-primary opacity-80 md:block"
    ></div>
    <Container class="relative flex flex-col items-start gap-5 py-16 md:flex-row md:items-center md:justify-between md:py-20">
      <div class="flex max-w-lg flex-col gap-2">
        <h2 class="text-2xl font-medium leading-tight md:text-3xl">Punya produk untuk dijual?</h2>
        <p class="text-base text-white/60">
          Buka toko di Blukios gratis dan jangkau ribuan pembeli setiap hari.
        </p>
      </div>
      <RouterLink
        :to="{ name: 'auth.open-store' }"
        class="flex h-12 shrink-0 items-center justify-center rounded-md bg-custom-blue px-6 font-medium text-white transition-colors hover:bg-primary-deep"
      >
        Buka Toko Sekarang
      </RouterLink>
    </Container>
  </div>

  <!-- Chatbot -->
  <Chatbot />
</template>
