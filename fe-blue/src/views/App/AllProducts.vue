<script setup>
import ProductCard from '@/components/card/ProductCard.vue';
import { useProductStore } from '@/stores/product';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import { onMounted, watch } from 'vue';

const productStore = useProductStore()
const { products, loading } = storeToRefs(productStore)
const { fetchProducts } = productStore
const route = useRoute()

const loadProducts = () => {
    fetchProducts({
        limit: 100, // Fetch all products
        ...route.query // Pass query params like sort=newest
    })
}

onMounted(() => {
    loadProducts()
})

watch(() => route.query, () => {
    loadProducts()
})
</script>

<template>
    <div class="flex flex-col gap-8 w-full max-w-[1280px] px-[75px] mx-auto mt-10 mb-24">
        <div class="flex items-center justify-between">
            <h1 class="font-bold text-[32px] text-custom-black">Top Picks</h1>
        </div>

        <div class="grid grid-cols-4 gap-6">
            <ProductCard v-for="product in products" :key="product.id" :item="product" />
        </div>
    </div>
</template>
