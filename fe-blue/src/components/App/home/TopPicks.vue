<script setup>
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'

const productStore = useProductStore()
const { products, loading } = storeToRefs(productStore)
const { fetchProducts } = productStore

onMounted(() => {
  fetchProducts({
    limit: 12,
    random: true
  })
})
</script>

<template>
  <section class="flex flex-col gap-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Produk Pilihan</h2>
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
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4"
    >
      <SkeletonProductCard v-for="i in 6" :key="i" />
    </div>

    <!-- Products Grid -->
    <div
      v-else
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4"
    >
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :item="product"
      />
    </div>
  </section>
</template>
