<script setup>
import ProductCard from '@/components/card/ProductCard.vue';
import { useWishlistStore } from '@/stores/wishlist';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';

const wishlistStore = useWishlistStore();
const { items, loading } = storeToRefs(wishlistStore);
const { fetchWishlist } = wishlistStore;

onMounted(() => {
    fetchWishlist();
});
</script>

<template>
    <header class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background py-8 md:p-[52px]">
        <div class="flex flex-col w-full max-w-[1280px] px-4 md:px-[52px] gap-3 mx-auto">
            <div class="flex items-center gap-3">
                <RouterLink :to="{ name: 'app.home' }" class="font-medium text-lg text-custom-grey">
                    Homepage
                </RouterLink>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <span class="font-medium text-lg text-custom-blue">My Wishlist</span>
            </div>
            <h1 class="font-extrabold text-[32px]">My Wishlist</h1>
            <p class="font-medium text-custom-grey">Saved items you might want to buy later</p>
        </div>
    </header>

    <main class="flex flex-col w-full max-w-[1280px] px-4 md:px-[52px] mt-8 md:mt-[72px] mb-20 md:mb-[100px] mx-auto">
        <section class="flex flex-col gap-9">
            <div v-if="loading" class="flex items-center justify-center min-h-[200px]">
                <div class="size-10 border-4 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
            </div>

            <div v-else-if="items.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-6">
                <ProductCard v-for="product in items" :key="product.id" :item="product" />
            </div>

            <div v-else class="flex flex-col items-center justify-center min-h-[400px] gap-6">
                <div class="size-24 rounded-full bg-custom-background flex items-center justify-center">
                    <img src="@/assets/images/icons/heart-grey.svg" class="size-10 opacity-50" alt="empty">
                </div>
                <div class="text-center">
                    <p class="font-bold text-xl">Your wishlist is empty</p>
                    <p class="text-custom-grey">Find products you like and save them here.</p>
                </div>
                <RouterLink :to="{ name: 'app.home' }"
                    class="h-12 px-8 rounded-full bg-custom-blue text-white font-semibold flex items-center justify-center hover:shadow-lg hover:bg-custom-blue-dark transition-all">
                    Start Shopping
                </RouterLink>
            </div>
        </section>
    </main>
</template>
