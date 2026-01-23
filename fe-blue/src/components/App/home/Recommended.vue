<script setup>
import { onMounted, ref, computed } from 'vue';
import { useProductStore } from '@/stores/product';
import ProductCard from '@/components/card/ProductCard.vue';
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue';

const productStore = useProductStore();
const products = ref([]);
const loading = ref(true);

// Shuffle array for "random" recommendation simulation
const shuffleArray = (array) => {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
};

onMounted(async () => {
    // Fetch generic products for now. In real ML, this would pass user_id.
    try {
        const response = await productStore.searchProducts({ limit: 10 });
        if (response && Array.isArray(response)) {
            products.value = shuffleArray([...response]);
        }
    } catch (e) {
        console.error("Failed to load recommendations", e);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="flex items-center justify-between px-1 md:px-0">
            <h2 class="text-xl md:text-2xl font-bold text-custom-black">Recommended For You</h2>
            <RouterLink :to="{ name: 'app.all-products' }"
                class="text-sm font-semibold text-custom-blue hover:underline">
                See All
            </RouterLink>
        </div>

        <!-- Mobile: Horizontal Snap Scroll -->
        <div v-if="loading"
            class="flex md:grid md:grid-cols-5 gap-4 overflow-x-auto md:overflow-visible pb-4 md:pb-0 px-1 hide-scrollbar snap-x snap-mandatory">
            <div v-for="i in 5" :key="i" class="min-w-[160px] md:min-w-0 snap-start">
                <SkeletonProductCard />
            </div>
        </div>

        <div v-else
            class="flex md:grid md:grid-cols-5 gap-4 overflow-x-auto md:overflow-visible pb-4 md:pb-0 px-1 hide-scrollbar snap-x snap-mandatory">
            <div v-for="product in products" :key="product.id" class="min-w-[160px] md:min-w-0 snap-start h-full">
                <ProductCard :item="product" />
            </div>
        </div>
    </section>
</template>
