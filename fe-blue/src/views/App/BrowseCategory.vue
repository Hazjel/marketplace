<script setup>
import { useProductCategoryStore } from '@/stores/productCategory';
import { useProductStore } from '@/stores/product';
import { storeToRefs } from 'pinia';
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { random } from 'lodash';
import ProductCard from '@/components/card/ProductCard.vue';

const route = useRoute()

const productCategory = ref({})

const productCategoryStore = useProductCategoryStore()
const { loading } = storeToRefs(productCategoryStore)
const { fetchProductCategoryBySlug } = productCategoryStore

const productStore = useProductStore()
const { products, loading: loadingProducts } = storeToRefs(productStore)
const { fetchProducts } = productStore

const fetchProductCategory = async () => {
    const response = await fetchProductCategoryBySlug(route.params.slug)

    productCategory.value = response
}

onMounted(async () => {
    await fetchProductCategory()

    fetchProducts({
        product_category_id: productCategory.value.id,
        limit: 8, 
        random: true,
    })
})
</script>

<template>
    <header class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background p-[52px]">
        <div class="flex flex-col w-full max-w-[1280px] px-[52px] gap-3 mx-auto">
            <div class="flex items-center gap-3">
                <a href="main.html" class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Homepage
                </a>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <a href="#" class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    {{ productCategory?.name }}
                </a>
            </div>
            <h1 class="font-extrabold text-[32px] capitalize">Explore based on Gadget Category</h1>
            <div class="flex items-center gap-4">
                <div class="group flex items-center gap-2">
                    <img src="@/assets/images/icons/box-grey.svg" class="flex size-5 shrink-0" alt="icon">
                    <span class="font-semibold text-custom-grey">{{ productCategory?.product_count }} Total Products</span>
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
                <ProductCard v-for="product in products" :key="product.id" :item="product" v-if="!loading" />
            </div>
            <button class="flex items-center w-fit h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black mx-auto">
                <span class="font-medium text-white">Load More</span>
                <img src="@/assets/images/icons/arrow-down-white.svg" class="flex size-6 shrink-0" alt="icon">
            </button>
        </section>
        <section id="Just-Released" class="flex flex-col gap-9">
            <div class="flex items-center justify-between">
                <h2 class="font-extrabold text-[32px]">Just Released 🙌🏻 </h2>
            </div>
            <div class="grid grid-cols-4 gap-6">
                <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                    <a href="product-details.html">
                        <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                            <img src="@/assets/images/thumbnails/airpod.png" class="size-full object-contain" alt="thumbnail">
                        </div>
                    </a>
                    <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue">Gadget</span>
                                </div>
                                <p class="font-semibold text-custom-red">120 Sold</p>
                            </div>
                            <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                <a href="product-details.html">
                                    <p class="font-bold text-xl w-full truncate">Airpods Gen Z 2025</p>
                                </a>
                                <p class="font-bold text-xl text-custom-blue">Rp 18.500.5000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full">
                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                <div class="relative size-6">
                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                            </button>
                            <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                <div class="flex size-6 shrink-0 relative">
                                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                    <a href="product-details.html">
                        <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                            <img src="@/assets/images/thumbnails/macbook.png" class="size-full object-contain" alt="thumbnail">
                        </div>
                    </a>
                    <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue">Gadget</span>
                                </div>
                                <p class="font-semibold text-custom-red">120 Sold</p>
                            </div>
                            <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                <a href="product-details.html">
                                    <p class="font-bold text-xl w-full truncate">Macbook Pro M2</p>
                                </a>
                                <p class="font-bold text-xl text-custom-blue">Rp 18.500.5000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full">
                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                <div class="relative size-6">
                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                            </button>
                            <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                <div class="flex size-6 shrink-0 relative">
                                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                    <a href="product-details.html">
                        <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                            <img src="@/assets/images/thumbnails/macbook.png" class="size-full object-contain" alt="thumbnail">
                        </div>
                    </a>
                    <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue">Gadget</span>
                                </div>
                                <p class="font-semibold text-custom-red">120 Sold</p>
                            </div>
                            <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                <a href="product-details.html">
                                    <p class="font-bold text-xl w-full truncate">Macbook Pro M2</p>
                                </a>
                                <p class="font-bold text-xl text-custom-blue">Rp 18.500.5000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full">
                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                <div class="relative size-6">
                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                            </button>
                            <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                <div class="flex size-6 shrink-0 relative">
                                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                    <a href="product-details.html">
                        <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                            <img src="@/assets/images/thumbnails/macbook.png" class="size-full object-contain" alt="thumbnail">
                        </div>
                    </a>
                    <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue">Gadget</span>
                                </div>
                                <p class="font-semibold text-custom-red">120 Sold</p>
                            </div>
                            <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                <a href="product-details.html">
                                    <p class="font-bold text-xl w-full truncate">Macbook Pro M2</p>
                                </a>
                                <p class="font-bold text-xl text-custom-blue">Rp 18.500.5000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full">
                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                <div class="relative size-6">
                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                            </button>
                            <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                <div class="flex size-6 shrink-0 relative">
                                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                    <a href="product-details.html">
                        <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                            <img src="@/assets/images/thumbnails/macbook.png" class="size-full object-contain" alt="thumbnail">
                        </div>
                    </a>
                    <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue">Gadget</span>
                                </div>
                                <p class="font-semibold text-custom-red">120 Sold</p>
                            </div>
                            <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                <a href="product-details.html">
                                    <p class="font-bold text-xl w-full truncate">Macbook Pro M2</p>
                                </a>
                                <p class="font-bold text-xl text-custom-blue">Rp 18.500.5000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full">
                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                <div class="relative size-6">
                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                            </button>
                            <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                <div class="flex size-6 shrink-0 relative">
                                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                    <a href="product-details.html">
                        <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                            <img src="@/assets/images/thumbnails/macbook.png" class="size-full object-contain" alt="thumbnail">
                        </div>
                    </a>
                    <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue">Gadget</span>
                                </div>
                                <p class="font-semibold text-custom-red">120 Sold</p>
                            </div>
                            <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                <a href="product-details.html">
                                    <p class="font-bold text-xl w-full truncate">Macbook Pro M2</p>
                                </a>
                                <p class="font-bold text-xl text-custom-blue">Rp 18.500.5000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full">
                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                <div class="relative size-6">
                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                            </button>
                            <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                <div class="flex size-6 shrink-0 relative">
                                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                    <a href="product-details.html">
                        <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                            <img src="@/assets/images/thumbnails/macbook.png" class="size-full object-contain" alt="thumbnail">
                        </div>
                    </a>
                    <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue">Gadget</span>
                                </div>
                                <p class="font-semibold text-custom-red">120 Sold</p>
                            </div>
                            <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                <a href="product-details.html">
                                    <p class="font-bold text-xl w-full truncate">Macbook Pro M2</p>
                                </a>
                                <p class="font-bold text-xl text-custom-blue">Rp 18.500.5000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full">
                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                <div class="relative size-6">
                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                            </button>
                            <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                <div class="flex size-6 shrink-0 relative">
                                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                    <a href="product-details.html">
                        <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                            <img src="@/assets/images/thumbnails/macbook.png" class="size-full object-contain" alt="thumbnail">
                        </div>
                    </a>
                    <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                    <span class="font-bold text-custom-blue">Gadget</span>
                                </div>
                                <p class="font-semibold text-custom-red">120 Sold</p>
                            </div>
                            <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                <a href="product-details.html">
                                    <p class="font-bold text-xl w-full truncate">Macbook Pro M2</p>
                                </a>
                                <p class="font-bold text-xl text-custom-blue">Rp 18.500.5000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full">
                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                <div class="relative size-6">
                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                            </button>
                            <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                <div class="flex size-6 shrink-0 relative">
                                    <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                    <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                </div>
                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <button class="flex items-center w-fit h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black mx-auto">
                <span class="font-medium text-white">Load More</span>
                <img src="@/assets/images/icons/arrow-down-white.svg" class="flex size-6 shrink-0" alt="icon">
            </button>
        </section>
    </main>
</template>