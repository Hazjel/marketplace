<script setup>
import { useProductCategoryStore } from '@/stores/productCategory';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';

const productCategoryStore = useProductCategoryStore();
const { productCategories } = storeToRefs(productCategoryStore);
const { fetchProductCategories } = productCategoryStore;

onMounted(() => {
    fetchProductCategories({
        limit: 100, // Fetch more/all for the view all page
    });
});
</script>

<template>
    <div class="flex flex-col gap-8 w-full max-w-[1280px] px-4 md:px-[75px] mx-auto mt-8 md:mt-10 mb-20 md:mb-24">
        <div class="flex items-center justify-between">
            <h1 class="font-bold text-[32px] text-custom-black">All Categories</h1>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-6">
            <RouterLink :to="{ name: 'app.browse-category', params: { slug: category.slug } }" class="group card"
                v-for="category in productCategories" :key="category.id">
                <div
                    class="flex flex-col h-full rounded-[20px] ring-1 ring-custom-stroke py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300">
                    <img :src="category.image" class="size-9" alt="icon">
                    <div class="flex flex-col items-center gap-1">
                        <p class="font-bold text-xs capitalize text-center line-clamp-2">{{ category.name }}</p>
                        <p class="font-medium text-custom-grey leading-none">{{ category.product_count }} products</p>
                    </div>
                </div>
            </RouterLink>
        </div>
    </div>
</template>
