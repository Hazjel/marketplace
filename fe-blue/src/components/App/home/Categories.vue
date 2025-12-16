<script setup>
import { useProductCategoryStore } from '@/stores/productCategory';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';

const productCategoryStore = useProductCategoryStore();
const { productCategories, loading } = storeToRefs(productCategoryStore);
const { fetchProductCategories } =  productCategoryStore ;

onMounted( () => {
    fetchProductCategories({
        limit: 5,
    });
});
</script>

<template>
    <section id="Categories" class="flex flex-col gap-9">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-[32px]">Explore High Quality<br>Products by Categories</h2>
            <RouterLink :to="{name: 'app.all-categories'}" class="flex items-center h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black">
                <span class="font-medium text-white">VIEW ALL</span>
                <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-6 shrink-0" alt="icon">
            </RouterLink>
        </div>
        <div class="grid grid-cols-5 gap-6">
            <RouterLink :to="{name: 'app.browse-category', params: {slug: category.slug}}" class="group card" v-for="category in productCategories">
                <div class="flex flex-col rounded-[20px] ring-1 ring-custom-stroke py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300">
                    <img :src="category.image" class="size-9" alt="icon">
                    <div class="flex flex-col items-center gap-1">
                        <p class="font-bold text-xs capitalize">{{ category.name }}</p>
                        <p class="font-medium text-custom-grey leading-none">{{ category.product_count }}</p>
                    </div>
                </div>
            </RouterLink>
        </div>
    </section>
</template>