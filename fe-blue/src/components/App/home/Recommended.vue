<script setup>
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useRecommendationStore } from '@/stores/recommendation'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import SectionHeader from '@/components/Molecule/SectionHeader.vue'

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
  <section class="flex flex-col gap-6 md:gap-8">
    <SectionHeader
      title="Rekomendasi Untukmu"
      :link="{ name: 'app.all-products' }"
      link-text="Lihat Semua"
    />

    <!-- Loading Skeleton -->
    <div
      v-if="loadingPersonalized"
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6"
    >
      <SkeletonProductCard v-for="i in 5" :key="i" />
    </div>

    <!-- Products Grid -->
    <div
      v-else-if="personalizedProducts.length"
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6"
    >
      <ProductCard
        v-for="product in personalizedProducts"
        :key="product.id"
        :item="product"
      />
    </div>
  </section>
</template>
