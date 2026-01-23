<script setup>
import { useProductCategoryStore } from '@/stores/productCategory';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import SectionHeader from '@/components/Molecule/SectionHeader.vue';

const productCategoryStore = useProductCategoryStore();
const { productCategories, loading } = storeToRefs(productCategoryStore);
const { fetchProductCategories } = productCategoryStore;

onMounted(() => {
    fetchProductCategories({
        limit: 6,
    });
});
</script>

<template>
    <section id="Categories" class="flex flex-col gap-6 md:gap-9 animate-fade-in-up delay-100">
        <SectionHeader title="Explore Categories" subtitle="Curated for You" :link="{ name: 'app.all-categories' }" />

        <!-- Mobile: Horizontal Scroll | Desktop: Grid -->
        <div
            class="flex md:grid md:grid-cols-5 lg:grid-cols-6 gap-3 md:gap-6 overflow-x-auto md:overflow-visible pb-4 md:pb-0 px-1 hide-scrollbar snap-x snap-mandatory">
            <template v-for="(category, index) in productCategories" :key="category.id">
                <RouterLink :to="{ name: 'app.browse-category', params: { slug: category.slug } }"
                    class="group flex-shrink-0 w-24 md:w-auto snap-start">
                    <div
                        class="flex flex-col rounded-2xl bg-white shadow-soft p-4 md:p-6 items-center gap-3 md:gap-4 hover:shadow-floating hover:-translate-y-1 transition-all duration-300 h-full border border-transparent hover:border-custom-blue/10">
                        <div
                            class="size-12 md:size-14 rounded-full bg-custom-icon-background flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300">
                            <img :src="category.image" class="size-6 md:size-8 object-contain" alt="icon">
                        </div>
                        <div class="flex flex-col items-center gap-1 text-center">
                            <p
                                class="font-bold text-xs md:text-sm capitalize text-custom-black group-hover:text-custom-blue transition-colors">
                                {{ category.name }}</p>
                            <span
                                class="text-[10px] bg-gray-100 px-2 py-0.5 rounded-full text-custom-grey group-hover:bg-blue-50 group-hover:text-custom-blue transition-colors">{{
                                category.product_count }} items</span>
                        </div>
                    </div>
                </RouterLink>
            </template>
        </div>
    </section>
</template>