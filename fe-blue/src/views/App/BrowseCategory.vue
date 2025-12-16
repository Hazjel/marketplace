<script setup>
import { useProductCategoryStore } from '@/stores/productCategory';
import { useProductStore } from '@/stores/product';
import { storeToRefs } from 'pinia';
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import ProductCard from '@/components/card/ProductCard.vue';

const route = useRoute()

const productCategory = ref({})

const productCategoryStore = useProductCategoryStore()
const { fetchProductCategoryBySlug } = productCategoryStore

const productStore = useProductStore()
const { products, loading: loadingProducts, meta } = storeToRefs(productStore)
const { fetchProductsPaginated, loadMoreProducts } = productStore

const fetchProductCategory = async () => {
    const response = await fetchProductCategoryBySlug(route.params.slug)

    productCategory.value = response
}

const handleLoadMore = async () => {
    await loadMoreProducts({
        product_category_id: productCategory.value.id,
        row_per_page: 8,
        page: meta.value.current_page + 1
    })
}

onMounted(async () => {
    await fetchProductCategory()

    fetchProductsPaginated({
        product_category_id: productCategory.value.id,
        row_per_page: 8,
    })
})
</script>

<template>
    <header class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background p-[52px]">
        <div class="flex flex-col w-full max-w-[1280px] px-[52px] gap-3 mx-auto">
            <div class="flex items-center gap-3">
                <RouterLink :to="{ name: 'app.home' }"
                    class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Homepage
                </RouterLink>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <RouterLink :to="{ name: 'app.browse-category', params: { slug: productCategory?.slug } }"
                    class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    {{ productCategory?.name }}
                </RouterLink>
            </div>
            <h1 class="font-extrabold text-[32px] capitalize">Explore based on Gadget Category</h1>
            <div class="flex items-center gap-4">
                <div class="group flex items-center gap-2">
                    <img src="@/assets/images/icons/box-grey.svg" class="flex size-5 shrink-0" alt="icon">
                    <span class="font-semibold text-custom-grey">{{ productCategory?.product_count }} Total
                        Products</span>
                </div>
                <div class="group flex items-center gap-2">
                    <img src="@/assets/images/icons/verify-star-grey.svg" class="flex size-5 shrink-0" alt="icon">
                    <span class="font-semibold text-custom-grey">Authenticity Guaranteed</span>
                </div>
            </div>
        </div>
    </header>
    <main class="flex flex-col gap-[100px] w-full max-w-[1280px] px-[52px] mt-[72px] mb-[100px] mx-auto">
        <section id="Popular" class="flex flex-col gap-9">
            <div class="flex items-center justify-between">
                <h2 class="font-extrabold text-[32px]">Sedang Popular 🔥 </h2>
            </div>
            <div class="grid grid-cols-4 gap-6">
                <ProductCard v-for="product in products" :key="product.id" :item="product" />
            </div>
            <!-- Popular section might also need its own data or just hide load more here if it's duping -->
            <!-- Use v-if="meta.current_page < meta.last_page" logic here too if this section shares the same list -->
        </section>
        <section id="Just-Released" class="flex flex-col gap-9">
            <div class="flex items-center justify-between">
                <h2 class="font-extrabold text-[32px] capitalize">Just Released in {{ productCategory?.name }} 🙌🏻
                </h2>
            </div>
            <div class="grid grid-cols-4 gap-6">
                <!-- Using same products list for now as per previous implementation -->
                <ProductCard v-for="product in products" :key="product.id" :item="product" />
            </div>

            <button v-if="meta.current_page < meta.last_page" @click="handleLoadMore"
                class="flex items-center w-fit h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black mx-auto">
                <span class="font-medium text-white">Load More</span>
                <img src="@/assets/images/icons/arrow-down-white.svg" class="flex size-6 shrink-0" alt="icon">
            </button>
        </section>
    </main>
</template>