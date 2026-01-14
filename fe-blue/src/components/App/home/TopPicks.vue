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
    <section id="Top-Picks" class="flex flex-col gap-9 animate-fade-in-up delay-200">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-extrabold text-lg md:text-[32px] leading-tight">Shop Quality Picks<br class="hidden md:block"> from Top Sellers</h2>
            <RouterLink :to="{ name: 'app.all-products' }"
                class="flex shrink-0 items-center h-10 md:h-14 rounded-[18px] py-2 px-3 md:py-4 md:px-6 gap-[10px] bg-custom-black">
                <span class="font-medium text-white hidden md:block">VIEW ALL</span>
                <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-5 md:size-6 shrink-0" alt="icon">
            </RouterLink>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 md:gap-6">
            <template v-for="(product, index) in products" :key="product.id">
                <ProductCard 
                    :item="product" 
                    v-if="!loading"
                    :class="{
                        'block': true,
                        'sm:hidden': index === 9,
                        'lg:hidden': index >= 8,
                        'xl:block': true
                    }"
                    class="transition-all duration-300"
                />
            </template>
        </div>
    </section>
</template>