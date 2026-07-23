<script setup>
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useRecommendationStore } from '@/stores/recommendation'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'

const authStore = useAuthStore()
const recommendationStore = useRecommendationStore()
const { personalizedProducts, loadingPersonalized } = storeToRefs(recommendationStore)

onMounted(() => {
  // Guest (belum login) tetap dapat rekomendasi -- backend fallback ke
  // trending kalau user_id gak dikenal model (lihat recommendation-service)
  const userId = authStore.user?.id || 'guest'
  recommendationStore.fetchPersonalizedProducts(userId)
})
</script>

<template>
  <section class="flex flex-col gap-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg md:text-xl font-medium text-gray-900 dark:text-white">Rekomendasi Untukmu</h2>
      <RouterLink
        :to="{ name: 'app.all-products' }"
        class="text-sm font-medium text-custom-blue dark:text-blue-400 hover:underline"
      >
        Lihat Semua
      </RouterLink>
    </div>

    <!-- Loading Skeleton -->
    <div
      v-if="loadingPersonalized"
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4"
    >
      <SkeletonProductCard v-for="i in 5" :key="i" />
    </div>

    <!-- Products Grid -->
    <div
      v-else-if="personalizedProducts.length"
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4"
    >
      <ProductCard
        v-for="product in personalizedProducts"
        :key="product.id"
        :item="product"
      />
    </div>
  </section>
</template>
