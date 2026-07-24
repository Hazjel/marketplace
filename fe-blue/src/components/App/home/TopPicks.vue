<script setup>
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import SectionHeader from '@/components/Molecule/SectionHeader.vue'

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
  <section class="flex flex-col gap-6 md:gap-8">
    <SectionHeader
      title="Produk Pilihan"
      :link="{ name: 'app.all-products' }"
      link-text="Lihat Semua"
    />

    <!-- Loading Skeleton -->
    <div
      v-if="loading"
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
    </div>
  </section>
</template>
