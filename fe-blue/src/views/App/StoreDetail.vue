<script setup>
import ProductCard from '@/components/card/ProductCard.vue';
import { useProductStore } from '@/stores/product';
import { useStoreStore } from '@/stores/store';
import { storeToRefs } from 'pinia';
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute()

const store = ref({})

const storeStore = useStoreStore()
const { loading } = storeToRefs(storeStore)
const { fetchStoreByUsername } = storeStore

const productStore = useProductStore();
const { products, loading: loadingProducts } = storeToRefs(productStore);
const { fetchProducts } = productStore;

import { useAuthStore } from '@/stores/auth';
const authStore = useAuthStore();
const { user } = storeToRefs(authStore);

const fetchStore = async () => {
    const response = await fetchStoreByUsername(route.params.username)
    store.value = response
}

onMounted(async () => {
    await fetchStore()

    fetchProducts({
        limit: 12,
        store_id: store.value.id,
        random: true
    })
})

const router = useRouter() // Import useRouter before using
const handleFollow = () => {
    if (!authStore.token) {
        router.push({ name: 'auth.login' })
        return
    }
    // Logic for follow store (if implemented)
}

</script>

<template>
    <header class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background">
        <div class="flex flex-col w-full max-w-[1280px] py-6 px-[52px] gap-3 mx-auto">
            <div class="flex items-center gap-3">
                <RouterLink :to="{ name: 'app.home' }"
                    class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Homepage
                </RouterLink>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <RouterLink :to="{ name: 'app.all-stores' }"
                    class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Stores
                </RouterLink>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <a href="#" class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    {{ store?.name }}
                </a>
            </div>
        </div>
    </header>
    <main class="flex flex-col gap-[52px] w-full max-w-[1280px] px-[52px] mt-8 mb-[100px] mx-auto">
        <section class="flex gap-5 w-full">
            <div id="Store-Info" class="flex flex-col rounded-3xl border border-custom-stroke p-5 gap-5 w-full">
                <div class="flex items-center w-full gap-5">
                    <div class="flex items-center gap-[14px] w-full min-w-0">
                        <div class="flex size-[86px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                            <img :src="store?.logo" class="size-full object-cover" alt="photo">
                        </div>
                        <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                            <div class="flex items-center gap-[6px] w-full overflow-hidden">
                                <h1 class="font-bold text-lg leading-tight">
                                    {{ store?.name }}
                                </h1>
                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon"
                                    v-if="store?.is_verified">
                            </div>
                            <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                <img src="@/assets/images/icons/box-grey.svg" class="size-5" alt="icon">
                                {{ store?.product_count }} Total Products
                            </p>
                        </div>
                    </div>
                </div>
                <hr class="border-custom-stroke">
                <div class="grid grid-cols-2 gap-6">
                    <div class="flex flex-col gap-[6px]">
                        <p class="font-bold text-lg leading-tight">{{ store?.user?.name }}</p>
                        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                            <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon">
                            Seller Name
                        </p>
                    </div>
                    <div class="flex flex-col gap-[6px]">
                        <p class="font-bold text-lg leading-tight">{{ store?.city }}</p>
                        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                            <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon">
                            Store Location
                        </p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <RouterLink v-if="user"
                        :to="{ name: 'user.chat', params: { username: user.username }, query: { userId: store?.user?.id } }"
                        class="flex items-center justify-center h-16 w-fit rounded-2xl p-4 px-6 gap-2 bg-custom-blue/10">
                        <img src="@/assets/images/icons/messages-blue.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-bold text-custom-blue">Message</span>
                    </RouterLink>
                    <RouterLink v-else :to="{ name: 'auth.login' }"
                        class="flex items-center justify-center h-16 w-fit rounded-2xl p-4 px-6 gap-2 bg-custom-blue/10">
                        <img src="@/assets/images/icons/messages-blue.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-bold text-custom-blue">Message</span>
                    </RouterLink>
                </div>
            </div>
            <a href="#" class="flex w-[253px] shrink-0 overflow-hidden">
                <img src="@/assets/images/thumbnails/promo-potrait-1-small.png" class="size-full object-contain"
                    alt="promo">
            </a>
            <a href="#" class="flex w-[253px] shrink-0 overflow-hidden">
                <img src="@/assets/images/thumbnails/promo-potrait-2-small.png" class="size-full object-contain"
                    alt="promo">
            </a>
        </section>
        <section class="flex flex-col gap-8">
            <div id="Tab-Buttons" class="flex items-center gap-[38px] w-full pb-3 border-b border-custom-stroke">
                <button class="font-bold text-xl text-custom-grey [&.active]:text-custom-blue active">Products</button>
                <button class="font-bold text-xl text-custom-grey [&.active]:text-custom-blue">Testimony</button>
            </div>
            <div id="Tab-Contents">
                <section id="Products-Content" class="flex flex-col gap-6">
                    <div class="flex flex-col gap-6">
                        <h2 class="font-bold text-[32px]">Store Products</h2>
                        <div class="flex items-center gap-5">
                            <button class="group">
                                <div
                                    class="flex items-center h-14 rounded-2xl p-4 gap-2 border border-custom-stroke bg-custom-black">
                                    <span class="text-white leading-none">
                                        Just Released 🙌🏻
                                    </span>
                                </div>
                            </button>
                            <button class="group">
                                <div
                                    class="flex items-center h-14 rounded-2xl p-4 gap-2 border border-custom-stroke group-[&.active]:border-custom-blue transition-300">
                                    <img src="@/assets/images/icons/mobile-blue-transparent.svg"
                                        class="flex size-6 shrink-0" alt="icon">
                                    <span class="leading-none">
                                        Gadget
                                    </span>
                                </div>
                            </button>
                            <button class="group">
                                <div
                                    class="flex items-center h-14 rounded-2xl p-4 gap-2 border border-custom-stroke group-[&.active]:border-custom-blue transition-300">
                                    <img src="@/assets/images/icons/tree-blue-transparent.svg"
                                        class="flex size-6 shrink-0" alt="icon">
                                    <span class="leading-none">
                                        Furniture
                                    </span>
                                </div>
                            </button>
                            <button class="group">
                                <div
                                    class="flex items-center h-14 rounded-2xl p-4 gap-2 border border-custom-stroke group-[&.active]:border-custom-blue transition-300">
                                    <img src="@/assets/images/icons/lamp-blue-transparent.svg"
                                        class="flex size-6 shrink-0" alt="icon">
                                    <span class="leading-none">
                                        electronic
                                    </span>
                                </div>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-9">
                        <div class="grid grid-cols-4 gap-6">
                            <ProductCard v-for="product in products" :key="product.id" :item="product"
                                v-if="!loadingProducts" />
                        </div>
                        <button
                            class="flex items-center w-fit h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black mx-auto">
                            <span class="font-medium text-white">Load More</span>
                            <img src="@/assets/images/icons/arrow-down-white.svg" class="flex size-6 shrink-0"
                                alt="icon">
                        </button>
                    </div>
                </section>
            </div>
        </section>
    </main>
</template>