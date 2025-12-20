<script setup>
import { useProductStore } from '@/stores/product';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';
import ProductCard from '@/components/card/ProductCard.vue';

const productStore = useProductStore()
const { products, loading } = storeToRefs(productStore)
const { fetchProducts } = productStore

onMounted(() => {
    fetchProducts({
        limit: 10,
        random: true,
    });
});
</script>
<template>
    <section id="Top-Picks" class="flex flex-col gap-9">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-[32px]">Shop Quality Picks<br>from Top Sellers</h2>
            <RouterLink :to="{ name: 'app.all-products' }"
                class="flex items-center h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black">
                <span class="font-medium text-white">VIEW ALL</span>
                <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-6 shrink-0" alt="icon">
            </RouterLink>
        </div>
        <div class="grid grid-cols-5 gap-6">
            <ProductCard v-for="product in products" :key="product.id" :item="product" v-if="!loading" />
        </div>
    </section>
</template>