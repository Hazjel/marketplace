<script setup>
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'
import ProductCard from '@/components/card/ProductCard.vue'
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
  <section id="Top-Picks" class="flex flex-col gap-9 animate-fade-in-up delay-200">
    <SectionHeader
      title="Shop Quality Picks"
      subtitle="from Top Sellers"
      :link="{ name: 'app.all-products' }"
    />
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 md:gap-6">
      <template v-for="(product, index) in products" :key="product.id">
        <ProductCard v-if="!loading" :item="product" class="transition-all duration-300" />
      </template>
    </div>
  </section>
</template>
