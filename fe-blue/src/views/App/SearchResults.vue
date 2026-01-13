<script setup>
import { useProductStore } from '@/stores/product';
import { useStoreStore } from '@/stores/store';
import { storeToRefs } from 'pinia';
import { onMounted, watch, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router'; // Added useRouter
import ProductCard from '@/components/card/ProductCard.vue';
import StoreCard from '@/components/card/StoreCard.vue';
import FilterSidebar from '@/components/App/FilterSidebar.vue'; // Import

const route = useRoute();
const router = useRouter(); // Init router
const productStore = useProductStore();
const storeStore = useStoreStore();

const { products, loading: loadingProducts } = storeToRefs(productStore);
const { stores, loading: loadingStores } = storeToRefs(storeStore);

const { fetchProducts } = productStore;
const { fetchStores } = storeStore;

const searchQuery = ref('');
const currentFilters = ref({});

const performSearch = async () => {
    const query = route.query.q;
    searchQuery.value = query;

    // Parse filters from query
    const filters = {
        min_price: route.query.min_price,
        max_price: route.query.max_price,
        condition: route.query.condition, // might be array or string
        city: route.query.city,
        min_rating: route.query.min_rating,
        stock_status: route.query.stock_status,
        created_since: route.query.created_since,
        product_category_id: route.query.product_category_id
    };
    currentFilters.value = filters;

    if (query) {
        await Promise.all([
            fetchProducts({ search: query, limit: 12, ...filters }), // Pass filters
            fetchStores({ search: query, limit: 9 })
        ]);
    } else {
        products.value = [];
        stores.value = [];
    }
};

const handleFilterChange = (newFilters) => {
    router.push({
        query: {
            ...route.query,
            ...newFilters
        }
    });
};

onMounted(() => {
    performSearch();
});

watch(
    () => route.query,
    () => {
        performSearch();
    },
    { deep: true }
);
</script>

<template>
    <header class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background py-8 md:p-[52px]">
        <div class="flex flex-col w-full max-w-[1280px] px-4 md:px-[52px] gap-3 mx-auto">
            <div class="flex items-center gap-3">
                <RouterLink :to="{ name: 'app.home' }"
                    class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Homepage
                </RouterLink>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <span class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Search Results
                </span>
            </div>
            <h1 class="font-extrabold text-[32px] capitalize">Results for "{{ searchQuery }}"</h1>
        </div>
    </header>

    <main class="flex flex-col lg:flex-row gap-10 w-full max-w-[1280px] px-4 md:px-[52px] mt-8 md:mt-[50px] mb-20 md:mb-[100px] mx-auto">
        <!-- Sidebar -->
        <aside class="shrink-0 w-full lg:w-auto">
            <FilterSidebar :initialFilters="currentFilters" @filter-change="handleFilterChange" />
        </aside>

        <!-- Content -->
        <div class="flex flex-col gap-10 md:gap-[80px] w-full">
            <!-- Products Section -->
            <section v-if="loadingProducts || products.length > 0" class="flex flex-col gap-9">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-[32px]">Products Found</h2>
                </div>
                <div v-if="loadingProducts" class="flex justify-center py-10">
                    <div class="size-10 border-4 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
                </div>
                <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-6">
                    <ProductCard v-for="product in products" :key="product.id" :item="product" />
                </div>
            </section>

            <section v-else-if="!loadingProducts && products.length === 0 && !loadingStores && stores.length === 0"
                class="flex flex-col gap-9 items-center justify-center py-20 text-center">
                <div class="flex flex-col items-center gap-4">
                    <img src="@/assets/images/icons/box-search-grey.svg" class="size-20 opacity-50" alt="No results">
                    <h2 class="font-bold text-2xl text-custom-grey">No results found for "{{ searchQuery }}"</h2>
                    <p class="text-custom-grey">Try refreshing the page or check your spelling.</p>
                </div>
            </section>

            <!-- Stores Section -->
            <section v-if="loadingStores || stores.length > 0" class="flex flex-col gap-9">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-[32px]">Stores Found</h2>
                </div>
                <div v-if="loadingStores" class="flex justify-center py-10">
                    <div class="size-10 border-4 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-6">
                    <StoreCard v-for="store in stores" :key="store.id" :item="store" />
                </div>
            </section>
        </div>
    </main>
</template>
