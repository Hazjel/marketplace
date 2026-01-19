<script setup>
import { useProductCategoryStore } from '@/stores/productCategory';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import SectionHeader from '@/components/Molecule/SectionHeader.vue';

const productCategoryStore = useProductCategoryStore();
const { productCategories, loading } = storeToRefs(productCategoryStore);
const { fetchProductCategories } =  productCategoryStore ;

onMounted( () => {
    fetchProductCategories({
        limit: 6,
    });
});
</script>

<template>
    <section id="Categories" class="flex flex-col gap-6 md:gap-9 animate-fade-in-up delay-100">
        <SectionHeader 
            title="Explore High Quality" 
            subtitle="Products by Categories"
            :link="{name: 'app.all-categories'}"
        />
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-6">
            <template v-for="(category, index) in productCategories" :key="category.id">
                <RouterLink 
                    :to="{name: 'app.browse-category', params: {slug: category.slug}}" 
                    class="group card"
                    :class="{
                        'block': index < 4,              // Mobile: Show 4 (Indexes 0-3)
                        'hidden': index >= 4,            // Mobile: Hide 5th+ (Indexes 4+)
                        'sm:block': index < 6,           // Tablet (sm): Show up to 6 (Indexes 4,5 become visible)
                        'lg:hidden': index === 5         // Desktop (lg): Hide 6th item (Index 5) -> Total 5
                    }"
                >
                    <div class="flex flex-col rounded-[20px] ring-1 ring-custom-stroke py-6 md:py-8 px-4 md:px-6 items-center gap-4 md:gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300 h-full hover-scale">
                        <img :src="category.image" class="size-9" alt="icon">
                        <div class="flex flex-col items-center gap-1 text-center">
                            <p class="font-bold text-xs capitalize">{{ category.name }}</p>
                            <p class="font-medium text-custom-grey leading-none">{{ category.product_count }}</p>
                        </div>
                    </div>
                </RouterLink>
            </template>
        </div>
    </section>
</template>