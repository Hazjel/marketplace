<script setup>
import { onMounted, ref } from 'vue'
import { useProductStore } from '@/stores/product'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'

const productStore = useProductStore()
const products = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await productStore.searchProducts({ limit: 10 })
    if (response && Array.isArray(response)) {
      // Shuffle for variety
      products.value = [...response].sort(() => Math.random() - 0.5)
    }
  } catch (e) {
    console.error('Failed to load recommendations', e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="flex flex-col gap-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Rekomendasi Untukmu</h2>
      <RouterLink
        :to="{ name: 'app.all-products' }"
        class="text-sm font-semibold text-custom-blue dark:text-blue-400 hover:underline"
      >
        Lihat Semua
      </RouterLink>
    </div>

    <!-- Loading Skeleton -->
    <div
      v-if="loading"
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4"
    >
      <SkeletonProductCard v-for="i in 5" :key="i" />
    </div>

    <!-- Products Grid -->
    <div
      v-else
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4"
    >
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :item="product"
      />
    </div>
  </section>
</template>
