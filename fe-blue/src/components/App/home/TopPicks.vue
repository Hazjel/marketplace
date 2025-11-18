<script setup>
import { useProductStore } from '@/stores/product';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';

const productStore = useProductStore();
const { products, loading } = storeToRefs(productStore);
const { fetchProducts } =  productStore ;

onMounted( () => {
    fetchProducts({
        limit: 8,
        random: true,
    });
});
</script>
<template>
    <section id="Top-Picks" class="flex flex-col gap-9">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-[32px]">Shop Quality Picks<br>from Top Sellers</h2>
                    <a href="#" class="flex items-center h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black">
                        <span class="font-medium text-white">VIEW ALL</span>
                        <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-6 shrink-0" alt="icon">
                    </a>
                </div>
                <div class="grid grid-cols-4 gap-6">
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden" v-for="(product, index) in products" :key="index">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img :src="product?.product_images?.find(image => image.is_thumbnail)?.image" 
                                class="size-full object-contain" alt="thumbnail">
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
                                        <p class="font-bold text-xl w-full truncate">SonicWhirl Wireless Headphone</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 3.500.500</p>
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
            </section>
</template>