<script setup>
import ProductCard from '@/components/card/ProductCard.vue';
import { formatRupiah } from '@/helpers/format';
import { useProductStore } from '@/stores/product';
import { storeToRefs } from 'pinia';

import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useWishlistStore } from '@/stores/wishlist';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';

const route = useRoute()
const router = useRouter()

const product = ref({})
const activeImage = ref()

const productStore = useProductStore()
const { products, loading } = storeToRefs(productStore)
const { fetchProductBySlug, fetchProducts } = productStore


const authStore = useAuthStore()
const cart = useCartStore()
const wishlistStore = useWishlistStore()
const { hasProduct } = storeToRefs(wishlistStore)
const { toggleWishlist, fetchWishlist } = wishlistStore

const quantity = ref(1)

const isInWishlist = computed(() => {
    return hasProduct.value(product.value?.id)
})

const handleToggleWishlist = async () => {
    if (!authStore.token) {
        router.push({ name: 'auth.login' })
        return
    }
    await toggleWishlist(product.value.id)
}

const fetchProduct = async () => {
    const response = await fetchProductBySlug(route.params.slug)

    product.value = response

    product.value.product_images.sort((a, b) => {
        return (b.is_thumbnail === true) - (a.is_thumbnail === true)
    })

    activeImage.value = product.value?.product_images?.find(img => img.is_thumbnail)
}

function setActiveImage(image) {
    activeImage.value = image
}

const increase = () => {
    if (quantity.value < product.value.stock) {
        quantity.value++
    }
}

const decrease = () => {
    if (quantity.value > 1) {
        quantity.value--
    }
}

const addToCart = () => {
    if (!authStore.token) {
        router.push({ name: 'auth.login' })
        return
    }

    cart.addToCart({
        ...product.value,
        quantity: quantity.value
    })
}

onMounted(() => {
    fetchProduct()
    fetchProducts({
        limit: 4,
        random: true
    })
    if (authStore.user) {
        fetchWishlist()
    }
})

watch(
    () => route.params.slug,
    () => {
        fetchProduct()
        fetchProducts({
            limit: 4,
            random: true
        })
    }
)
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
                <RouterLink :to="{ name: 'app.browse-category', params: { slug: product?.product_category?.slug } }"
                    class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    {{ product?.product_category?.name }}
                </RouterLink>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <a href="#" class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Product Details
                </a>
            </div>
        </div>
    </header>
    <main class="flex flex-col gap-[52px] w-full max-w-[1280px] px-[52px] mt-8 mb-[100px] mx-auto">
        <div class="flex gap-[72px] flex-1">
            <div class="flex flex-col w-full gap-[52px]">
                <div id="Gallery" class="flex flex-col gap-3">
                    <div id="Main-Thumbnail"
                        class="flex w-full h-[365px] bg-custom-background rounded-2xl items-center justify-center overflow-hidden">
                        <img :src="activeImage?.image" class="size-full object-contain" alt="thumbnail">
                    </div>
                    <div class="grid grid-cols-4 gap-3">
                        <button
                            class="thumbnail-selector flex items-center justify-center rounded-2xl bg-custom-background overflow-hidden h-[124px] border-2 border-custom-background hover:border-custom-blue transition-300 [&.active]:border-custom-blue"
                            v-for="image in product?.product_images"
                            :class="{ 'active': image.image === activeImage?.image }" @click="setActiveImage(image)">
                            <img :src="image.image" class="size-full object-contain" alt="thumbnail">
                        </button>
                    </div>
                </div>
                <div id="Store"
                    class="flex items-center justify-between rounded-3xl border border-custom-stroke p-5 gap-4">
                    <div class="flex items-center w-full gap-5">
                        <div class="flex items-center gap-[14px] w-full min-w-0">
                            <div class="flex size-[86px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                                <img :src="product?.store?.logo" class="size-full object-cover" alt="photo">
                            </div>
                            <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                                <div class="flex items-center gap-[6px] w-full overflow-hidden">
                                    <p class="font-bold text-lg leading-tight">
                                        {{ product?.store?.name }}
                                    </p>
                                    <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                </div>
                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/box-grey.svg" class="size-5" alt="icon">
                                    {{ product?.store?.product_count }} Total Products
                                </p>
                            </div>
                        </div>
                        <RouterLink v-if="product?.store?.username"
                            :to="{ name: 'app.store-detail', params: { username: product?.store?.username } }"
                            class="font-semibold text-lg text-custom-blue text-nowrap hover:underline">Visit Store
                        </RouterLink>
                    </div>
                </div>
                <div id="Descriptions" class="group flex flex-col">
                    <p class="font-bold text-lg">Product About</p>
                    <article id="Product-Descriptions"
                        class="mt-3 overflow-y-hidden h-fit max-h-[390px] group-has-[:checked]:max-h-fit"
                        v-html="product?.description">
                    </article>
                    <label>
                        <span
                            class="font-bold text-lg text-custom-blue after:content-['Read_More'] group-has-[:checked]:after:content-['See_Lees']"></span>
                        <input type="checkbox" class="hidden">
                    </label>
                </div>
                <div id="Promo-Card" class="flex gap-5 overflow-hidden">
                    <a href="#" class="overflow-hidden">
                        <img src="@/assets/images/thumbnails/promo-potrait-1.png" class="size-full object-contain"
                            alt="promo">
                    </a>
                    <a href="#" class="overflow-hidden">
                        <img src="@/assets/images/thumbnails/promo-potrait-2.png" class="size-full object-contain"
                            alt="promo">
                    </a>
                </div>
                <div id="Testimony" class="flex flex-col gap-6">
                    <p class="font-bold text-lg">Product About</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col w-full rounded-[20px] border border-custom-stroke p-5 gap-4">
                            <div class="flex items-center gap-[10px]">
                                <div class="flex size-16 rounded-full overflow-hidden bg-custom-background">
                                    <img src="@/assets/images/photos/photo-6.png" class="size-full object-cover"
                                        alt="photo">
                                </div>
                                <div class="flex flex-col items-center gap-[6px]">
                                    <p class="font-bold text-lg">Bryan Utami</p>
                                    <p class="font-medium text-custom-grey">3 Days Ago</p>
                                </div>
                            </div>
                            <p class="font-semibold">“The MacBook is perfect for work, and the AirPods sound crystal
                                clear. Plus, the store's service was amazing—fast delivery and great support!”</p>
                            <div class="flex items-center gap-0.5">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                            </div>
                        </div>
                        <div class="flex flex-col w-full rounded-[20px] border border-custom-stroke p-5 gap-4">
                            <div class="flex items-center gap-[10px]">
                                <div class="flex size-16 rounded-full overflow-hidden bg-custom-background">
                                    <img src="@/assets/images/photos/photo-4.png" class="size-full object-cover"
                                        alt="photo">
                                </div>
                                <div class="flex flex-col items-center gap-[6px]">
                                    <p class="font-bold text-lg">Kintan Saff</p>
                                    <p class="font-medium text-custom-grey">1 Days Ago</p>
                                </div>
                            </div>
                            <p class="font-semibold">“The MacBook is perfect for work, and the AirPods sound crystal
                                clear. Plus, the store's service was amazing—fast delivery and great support!”</p>
                            <div class="flex items-center gap-0.5">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="size-[22px] p-0.5" alt="star">
                            </div>
                        </div>
                    </div>
                    <div id="Pagination" class="flex items-center gap-6">
                        <button
                            class="flex items-center justify-center size-14 rounded-full border border-custom-stroke">
                            <img src="@/assets/images/icons/arrow-right-black.svg" class="size-6 rotate-180" alt="icon">
                        </button>
                        <div class="flex items-center gap-2">
                            <button class="w-[42px] h-1 rounded-full bg-custom-blue"></button>
                            <button class="w-[42px] h-1 rounded-full bg-custom-stroke"></button>
                            <button class="w-[42px] h-1 rounded-full bg-custom-stroke"></button>
                        </div>
                        <button
                            class="flex items-center justify-center size-14 rounded-full border border-custom-stroke">
                            <img src="@/assets/images/icons/arrow-right-black.svg" class="size-6" alt="icon">
                        </button>
                    </div>
                </div>
            </div>
            <div class="relative flex w-[504px] shrink-0">
                <div class="w-full">
                    <div class="sticky top-[200px] flex flex-col gap-6">
                        <div class="flex flex-col gap-3">
                            <h1 class="font-extrabold text-[32px]">{{ product?.name }}</h1>
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue text-lg">{{ product?.product_category?.name
                                    }}</span>
                                </div>
                                <p class="flex items-center gap-[6px]">
                                    <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 p-0.5 shrink-0"
                                        alt="star">
                                    <span class="font-bold text-lg">4.5</span>
                                    <span class="font-semibold text-lg text-custom-grey text-nowrap">({{
                                        product?.product_reviews?.length }})</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col rounded-xl border border-custom-stroke p-5 gap-4">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-lg text-custom-grey">Condition:</p>
                                <p class="font-bold text-lg">{{ product?.condition }}</p>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-lg text-custom-grey">Weight:</p>
                                <p class="font-bold text-lg">{{ product?.weight }} KG</p>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-lg text-custom-grey">Warranty:</p>
                                <p class="font-bold text-lg">24 Month</p>
                            </div>
                            <hr class="border-custom-stroke">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-lg text-custom-grey">Order Status:</p>
                                <p class="font-bold text-lg">Ready to Ship</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col gap-[6px]">
                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                    <img src="@/assets/images/icons/shopping-cart-grey.svg" class="size-5" alt="icon">
                                    Subtotal
                                </p>
                                <p class="font-bold text-2xl text-custom-blue leading-none">Rp {{
                                    formatRupiah(product?.price) }}</p>
                            </div>
                            <div
                                class="quantity-container flex items-center shrink-0 rounded-2xl border border-custom-stroke p-4">
                                <button type="button" class="subtract size-5 flex items-center justify-center"
                                    @click="decrease">
                                    <span class="text-[30px] font-light leading-none align-middle mb-1">-</span>
                                </button>
                                <div class="h-[18px] border border-custom-stroke ml-4"></div>
                                <input type="number" name="" value="1"
                                    class="amount appearance-none w-[70px] pl-5 text-center font-bold text-lg"
                                    v-model="quantity">
                                <div class="h-[18px] border border-custom-stroke mr-4"></div>
                                <button type="button" class="add size-5 flex items-center justify-center"
                                    @click="increase">
                                    <span class="text-[24px] font-light leading-none align-middle mb-1">+</span>
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-5">
                                <button @click.prevent="addToCart"
                                    class="flex items-center justify-center h-16 w-full rounded-2xl p-4 gap-2 bg-custom-blue">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg"
                                        class="flex size-6 shrink-0" alt="icon">
                                    <span class="font-bold text-white">Add to Cart</span>
                                </button>
                                <button @click="handleToggleWishlist"
                                    class="flex items-center justify-center h-16 w-full rounded-2xl p-4 gap-2 border border-custom-stroke transition-all"
                                    :class="{ 'bg-custom-red/10 border-custom-red': isInWishlist }">
                                    <img v-if="isInWishlist" src="@/assets/images/icons/heart-red.svg"
                                        class="flex size-6 shrink-0" alt="icon">
                                    <img v-else src="@/assets/images/icons/heart-grey.svg" class="flex size-6 shrink-0"
                                        alt="icon">
                                    <span class="font-bold"
                                        :class="isInWishlist ? 'text-custom-red' : 'text-custom-grey'">
                                        {{ isInWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' }}
                                    </span>
                                </button>
                            </div>
                            <p class="flex items-center gap-1 font-semibold text-custom-red text-lg leading-none">
                                <img src="@/assets/images/icons/bag-tick-red.svg" class="size-5" alt="icon">
                                {{ product?.total_sold }} Units Sold
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <section id="Top-Picks" class="flex flex-col gap-9">
            <div class="flex items-center justify-between">
                <h2 class="font-extrabold text-[32px]">Shop Quality Picks<br>from Top Sellers</h2>
                <a href="#" class="flex items-center h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black">
                    <span class="font-medium text-white">VIEW ALL</span>
                    <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-6 shrink-0" alt="icon">
                </a>
            </div>
            <div class="grid grid-cols-4 gap-6">
                <ProductCard v-for="product in products" :key="product.id" :item="product" v-if="!loading" />
            </div>
        </section>
    </main>
</template>